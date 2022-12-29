<?php

namespace App\Domain\Common;

use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class EmailDto
{
    private string $from;
    private ?string $fromName;
    private string $to;
    private ?array $cc;
    private ?array $bcc;
    private string $subject;
    private ?array $attachments;
    private string $body;

    public function __construct(string $from, string $to, string $subject, string $body, ?string $fromName = null, ?array $cc = null, ?array $bcc = null, ?array $attachments = null)
    {
        $this->from = $from;
        $this->fromName = $fromName;
        $this->to = $to;
        $this->cc = $cc;
        $this->bcc = $bcc;
        $this->subject = $subject;
        $this->attachments = $attachments;
        $this->body = $body;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function setFrom(string $from): void
    {
        $this->from = $from;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): void
    {
        $this->fromName = $fromName;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function setTo(string $to): void
    {
        $this->to = $to;
    }

    public function getCc(): ?array
    {
        return $this->cc;
    }

    public function setCc(?array $cc): void
    {
        $this->cc = $cc;
    }

    public function getBcc(): ?array
    {
        return $this->bcc;
    }

    public function setBcc(?array $bcc): void
    {
        $this->bcc = $bcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }
}
