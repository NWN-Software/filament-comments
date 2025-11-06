<?php

namespace Parallax\FilamentComments\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserTaggedOnCommentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected string $mailContent,
        protected ?string $mailSubjectForTaggedUsers = null,
        protected ?string $url = null,
        protected ?string $language = null,
        protected string $replyToEmail
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubjectForTaggedUsers ?? __('filament-comments::filament-comments.tagged.body'),
            replyTo: new Address(
                address: $this->replyToEmail,
                name: $this->replyToEmail,
            ),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'filament-comments::mail.user-tagged-on-comment-mail',
            with: ['mailContent' => $this->mailContent, 'url' => $this->url, 'language' => $this->language, 'subject' => $this->mailSubjectForTaggedUsers ?? __('filament-comments::filament-comments.tagged.body')],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
