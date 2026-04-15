<?php

namespace Parallax\FilamentComments\Livewire;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Schema;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Parallax\FilamentComments\Mail\UserTaggedOnCommentMail;
use Parallax\FilamentComments\Models\FilamentComment;

class CommentsComponent extends Component implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    public ?array $data = [];

    public ?Model $record;

    public string $resource;

    public bool $sendMailWhenTagged = false;

    public ?string $mailSubjectForTaggedUsers = null;

    public function mount($resource, $sendMailWhenTagged = false, $mailSubjectForTaggedUsers = null): void
    {
        $this->resource = $resource;
        $this->sendMailWhenTagged = $sendMailWhenTagged;
        $this->mailSubjectForTaggedUsers = $mailSubjectForTaggedUsers;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        if (config('filament-comments.editor') === 'rich') {
            $editor = RichEditor::make('comment')
                ->hiddenLabel()
                ->required()
                ->placeholder(__('filament-comments::filament-comments.comments.placeholder'))
                ->extraInputAttributes(['style' => 'min-height: 6rem'])
                ->toolbarButtons(config('filament-comments.toolbar_buttons'))
                ->mentions([
                    MentionProvider::make('@')
                        ->getSearchResultsUsing(fn (string $search): array => User::query()
                            ->where(fn ($subquery) => $subquery->where('firstname', 'ilike', "%{$search}%")->orWhere('lastname', 'ilike', "%{$search}%"))
                            ->orderBy('firstname')
                            ->limit(10)
                            ->get(['firstname', 'lastname', 'id'])
                            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
                            ->toArray())
                        ->getLabelsUsing(fn (array $ids): array => User::query()
                            ->whereIn('id', $ids)
                            ->get(['firstname', 'lastname', 'id'])
                            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
                            ->toArray())
                ]);
        } else {
            $editor = MarkdownEditor::make('comment')
                ->hiddenLabel()
                ->required()
                ->placeholder(__('filament-comments::filament-comments.comments.placeholder'))
                ->toolbarButtons(config('filament-comments.toolbar_buttons'));
        }

        return $schema
            ->components([
                $editor,
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $this->form->validate();

        $data = $this->form->getState();

        if (config('filament-comments.editor') === 'rich') {
            $users = $this->getUsersFromComment($data['comment']);
        }

        $url = $this->resource::getUrl('view', ['record' => $this->record->id]);
        $label = $this->resource::getLabel();
        $title = $this->record->{$this->resource::getRecordTitleAttribute()};
        $notificationText = __('filament-comments::filament-comments.tagged.body', ['label' => $label, 'title' => $title]);
        $comment = $this->record->filamentComments()->create([
            'subject_type' => $this->record->getMorphClass(),
            'comment' => $data['comment'],
            'user_id' => auth()->id(),
        ]);

        foreach ($users ?? [] as $userId) {
            if (auth()->user()?->id == $userId) {
                continue;
            }
            $model = User::find($userId);
            if (! $model) {
                continue;
            }
            Notification::make()
                ->title(__('filament-comments::filament-comments.tagged', locale: $model->locale))
                ->body($notificationText)
                ->actions([Action::make('view')
                    ->url($url)
                    ->label(__('filament-comments::filament-comments.view', locale: $model->locale)),
                ])
                ->info()
                ->sendToDatabase($model);
            if ($this->sendMailWhenTagged && $model->email) {
                Mail::to($model->email)
                    ->queue(new UserTaggedOnCommentMail($comment->comment, $this->mailSubjectForTaggedUsers, $url, $model->locale, auth()->user()->email));
            }
        }

        Notification::make()
            ->title(__('filament-comments::filament-comments.notifications.created'))
            ->success()
            ->send();

        $this->data = [];

        $this->form->fill($this->data);
    }

    public function delete(int $id): void
    {
        $comment = FilamentComment::find($id);

        if (! $comment) {
            return;
        }

        if (! auth()->user()->can('delete', $comment)) {
            return;
        }

        $comment->delete();

        Notification::make()
            ->title(__('filament-comments::filament-comments.notifications.deleted'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        $comments = $this->record->filamentComments()->with(['user'])->latest()->get();

        return view('filament-comments::comments', ['comments' => $comments]);
    }

    public function getUsersFromComment(string $comment): array
    {
        if ($comment === null || $comment === '' || $comment === []) {
            return [];
        }

        $editor = RichContentRenderer::make($comment)->getEditor();

        $ids = [];
        $editor->descendants(function (object &$node) use (&$ids): void {
            if ($node->type !== 'mention') {
                return;
            }

            $id = $node->attrs->id ?? null;
            if (blank($id) || ! ctype_digit((string) $id)) {
                return;
            }

            $ids[] = (int) $id;
        });

        return array_values(array_unique($ids));
    }
}
