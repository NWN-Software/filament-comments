<?php

namespace Parallax\FilamentComments\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;

class CommentsAction extends Action
{
    public ?string $resource = null;

    public bool $sentMailWhenTagged = false;

    public ?string $mailSubjectForTaggedUsers = null;

    public static function getDefaultName(): ?string
    {
        return 'comments';
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

    public function sentMailWhenTagged(bool $sentMailWhenTagged): static
    {
        $this->sentMailWhenTagged = $sentMailWhenTagged;

        return $this;
    }

    public function setMailSubjectForTaggedUsers(string $mailSubjectForTaggedUsers): static
    {
        $this->mailSubjectForTaggedUsers = $mailSubjectForTaggedUsers;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hiddenLabel()
            ->icon(config('filament-comments.icons.action'))
            ->color('gray')
            ->badge($this->record?->filamentComments()->count())
            ->slideOver()
            ->modalContentFooter(fn (): View => view('filament-comments::component', ['resource' => $this->resource, 'sentMailWhenTagged' => $this->sentMailWhenTagged, 'mailSubjectForTaggedUsers' => $this->mailSubjectForTaggedUsers]))
            ->modalHeading(__('filament-comments::filament-comments.modal.heading'))
            ->modalWidth(MaxWidth::Medium)
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }
}
