<?php

namespace Parallax\FilamentComments\Actions;

use Filament\Support\Enums\Width;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Parallax\FilamentComments\Models\FilamentComment;

class CommentsAction extends Action
{
    public ?string $resource = null;

    public bool $sendMailWhenTagged = false;

    public ?string $mailSubjectForTaggedUsers = null;

    public static function getDefaultName(): ?string
    {
        return 'comments';
    }

    public function sendMailWhenTagged(bool $sendMailWhenTagged = true): static
    {
        $this->sendMailWhenTagged = $sendMailWhenTagged;

        return $this;
    }

    public function setMailSubjectForTaggedUsers(string $mailSubjectForTaggedUsers): static
    {
        $this->mailSubjectForTaggedUsers = $mailSubjectForTaggedUsers;

        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hiddenLabel()
            ->icon(config('filament-comments.icons.action'))
            ->color('gray')
            ->badge(fn ($record) => $record?->filamentComments()->count())
            ->slideOver()
            ->modalContentFooter(fn (): View => view('filament-comments::component', ['resource' => $this->resource, 'sendMailWhenTagged' => $this->sendMailWhenTagged, 'mailSubjectForTaggedUsers' => $this->mailSubjectForTaggedUsers]))
            ->modalHeading(__('filament-comments::filament-comments.modal.heading'))
            ->modalWidth(Width::Medium)
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }
}
