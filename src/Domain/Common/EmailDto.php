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

    /**
     * @param string $from
     * @param string|null $fromName
     * @param string $to
     * @param array|null $cc
     * @param array|null $bcc
     * @param string $subject
     * @param array|null $attachments
     * @param string $body
     */
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

    /**
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * @param string $from
     */
    public function setFrom(string $from): void
    {
        $this->from = $from;
    }

    /**
     * @return string|null
     */
    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    /**
     * @param string|null $fromName
     */
    public function setFromName(?string $fromName): void
    {
        $this->fromName = $fromName;
    }

    /**
     * @return string
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * @param string $to
     */
    public function setTo(string $to): void
    {
        $this->to = $to;
    }

    /**
     * @return array|null
     */
    public function getCc(): ?array
    {
        return $this->cc;
    }

    /**
     * @param array|null $cc
     */
    public function setCc(?array $cc): void
    {
        $this->cc = $cc;
    }

    /**
     * @return array|null
     */
    public function getBcc(): ?array
    {
        return $this->bcc;
    }

    /**
     * @param array|null $bcc
     */
    public function setBcc(?array $bcc): void
    {
        $this->bcc = $bcc;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    /**
     * @return array|null
     */
    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    /**
     * @param array|null $attachments
     */
    public function setAttachments(?array $attachments): void
    {
        $this->attachments = $attachments;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param string $body
     */
    public function setBody(string $body): void
    {
        $this->body = $body;
    }
}