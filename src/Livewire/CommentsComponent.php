<?php

namespace Parallax\FilamentComments\Livewire;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Parallax\FilamentComments\Forms\RichTextEditor;
use Parallax\FilamentComments\Mail\UserTaggedOnCommentMail;
use Parallax\FilamentComments\Models\FilamentComment;

class CommentsComponent extends Component implements HasForms
{
    use InteractsWithForms;

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
            $editor = RichTextEditor::make('comment')
                ->hiddenLabel()
                ->required()
                ->placeholder(__('filament-comments::filament-comments.comments.placeholder'))
                ->extraInputAttributes(['style' => 'min-height: 6rem'])
                ->toolbarButtons(config('filament-comments.toolbar_buttons'))
                ->mergeTags(User::all()
                    ->mapWithKeys(fn ($user) => [$user->id => "@{$user->name}"])
                    ->toArray()
                );
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

        $renderedComment = $data['comment'];

        if (config('filament-comments.editor') === 'rich') {
            $processedData = $this->processRichComment($renderedComment);
            $renderedComment = $processedData['comment'];
            $users = $processedData['users'];
        }

        $url = $this->resource::getUrl('view', ['record' => $this->record->id]);
        $label = $this->resource::getLabel();
        $title = $this->record->{$this->resource::getRecordTitleAttribute()};
        $notificationText = __('filament-comments::filament-comments.tagged.body', ['label' => $label, 'title' => $title]);
        $comment = $this->record->filamentComments()->create([
            'subject_type' => $this->record->getMorphClass(),
            'comment' => $renderedComment,
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

    public function parseVariables(?string $message, array $variables, bool $stripTags = false): string
    {
        foreach ($variables as $key => $value) {
            $stringValue = $value instanceof Htmlable ? $value->toHtml() : (string) $value;

            $boldValue = '<strong>' . $stringValue . '</strong>';

            $message = str_replace('{{'.$key.'}}', $boldValue, $message);
            $message = preg_replace(
                '/<span\b(?=[^>]*\bdata-type=["\']mergeTag["\'])(?=[^>]*\bdata-id=["\']'.preg_quote($key, '/').'["\'])[^>]*>.*?<\/span>/s',
                $boldValue,
                $message,
            );
        }
        if ($stripTags) {
            $message = strip_tags($message);
            $message = preg_replace('/(&nbsp;|\s)+/u', ' ', $message);
        }
        return $message;
    }

    public function processRichComment(string $comment): array
    {
        $mappedTags = User::all()->mapWithKeys(function ($user) {
            $name = e($user->name); // accessor combines firstname + lastname
            return [$user->id => "@{$name}"];
        })->toArray();

        $labelToUserIds = [];

        foreach ($mappedTags as $id => $label) {
            $labelToUserIds[$label] ??= [];
            $labelToUserIds[$label][] = $id;
        }

        $users = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $comment);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-type="mergeTag"]');
        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            $label = $node->getAttribute('data-id');
            if (! $label) {
                continue;
            }
            if (! isset($labelToUserIds[$label])) {
                continue;
            }

            foreach ($labelToUserIds[$label] as $userId) {
                $users[] = $userId;
            }
        }
        $users = array_values(array_unique($users));
        $variables = [];
        foreach ($mappedTags as $id => $label) {
            $variables[$label] = $label;
        }

        $renderedComment = $this->parseVariables($comment, $variables);

        return [
            'comment' => $renderedComment,
            'users' => $users,
        ];
    }
}
