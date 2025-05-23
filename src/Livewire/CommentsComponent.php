<?php

namespace Parallax\FilamentComments\Livewire;

use App\Models\User;
use Awcodes\Scribble\ScribbleEditor;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Parallax\FilamentComments\Mail\UserTaggedOnCommentMail;
use Parallax\FilamentComments\Models\FilamentComment;

class CommentsComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public ?Model $record;

    public string $resource;

    public bool $sentMailWhenTagged = false;

    public ?string $mailSubjectForTaggedUsers = null;

    public function mount($resource, $sentMailWhenTagged = false, $mailSubjectForTaggedUsers = null): void
    {
        $this->resource = $resource;
        $this->sentMailWhenTagged = $sentMailWhenTagged;
        $this->mailSubjectForTaggedUsers = $mailSubjectForTaggedUsers;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        // if (! auth()->user()->can('create', config('filament-comments.comment_model'))) {
        //     return $form;
        // }

        if (config('filament-comments.editor') === 'markdown') {
            $editor = Forms\Components\MarkdownEditor::make('comment')
                ->hiddenLabel()
                ->required()
                ->placeholder(__('filament-comments::filament-comments.comments.placeholder'))
                ->toolbarButtons(config('filament-comments.toolbar_buttons'));
        } elseif (config('filament-comments.editor') === 'rich') {
            $editor = Forms\Components\RichEditor::make('comment')
                ->hiddenLabel()
                ->required()
                ->placeholder(__('filament-comments::filament-comments.comments.placeholder'))
                ->extraInputAttributes(['style' => 'min-height: 6rem'])
                ->toolbarButtons(config('filament-comments.toolbar_buttons'));
        } else {
            $editor = ScribbleEditor::make('comment')
                ->hiddenLabel()
                ->userTags(User::all()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                    ];
                })->toArray())
                ->required()
                ->placeholder(__('filament-comments::filament-comments.comments.placeholder'));
        }

        return $form
            ->schema([
                $editor,
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        // if (! auth()->user()->can('create', config('filament-comments.comment_model'))) {
        //     return;
        // }

        $this->form->validate();

        $data = $this->form->getState();

        $mappedTags = User::all()->mapWithKeys(function ($user) {
            $name = e($user->name);

            return [$user->id => "@{$name}"];
        })->toArray();

        $scribble = scribble($data['comment']);
        $users = [];

        $scribble->getEditor()->setContent($data['comment'])->descendants(function ($node) use (&$users) {
            if ($node->type === 'userTag') {
                $users[] = $node->attrs->id->id;
            }
        });

        $url = $this->resource::getUrl('view', ['record' => $this->record->id]);
        $label = $this->resource::getLabel();
        $title = $this->record->{$this->resource::getRecordTitleAttribute()};

        $notificationText = __('filament-comments::filament-comments.tagged.body', ['label' => $label, 'title' => $title]);

        $comment = $this->record->filamentComments()->create([
            'subject_type' => $this->record->getMorphClass(),
            'comment' => scribble($data['comment'])->userTagsMap($mappedTags)->toHtml(),
            'user_id' => auth()->id(),
        ]);

        foreach ($users as $user) {
            $model = User::find($user);

            if (auth()->user()?->id == $user) { // Skip giving notification or email for yourself
                continue;
            }

            if (! $model) {
                continue;
            }

            Notification::make()
                ->title(__('filament-comments::filament-comments.tagged'))
                ->body($notificationText)
                ->actions([\Filament\Notifications\Actions\Action::make('view')
                    ->url($url)
                    ->label(__('filament-actions::view.single.label')),
                ])
                ->info()
                ->sendToDatabase($model);

            if ($this->sentMailWhenTagged && $model->email) {
                Mail::to($model->email)
                    ->queue(new UserTaggedOnCommentMail($comment->comment, $this->mailSubjectForTaggedUsers, $url, $model->locale));
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
}
