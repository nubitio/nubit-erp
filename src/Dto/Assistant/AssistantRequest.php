<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

use Symfony\Component\Validator\Constraints as Assert;

class AssistantRequest
{
    /**
     * @var AssistantMessageInput[]
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 20)]
    #[Assert\Valid]
    public array $messages = [];

    /**
     * Optional file attachments to include with the last user message.
     * Each item must provide `name` and `mimeType`, plus at least one of `mediaId` or `s3Url`.
     * Invalid or missing items are silently skipped.
     *
     * @var AttachmentInput[]
     */
    #[Assert\Type('array')]
    #[Assert\Count(max: 10)]
    #[Assert\Valid]
    public array $attachments = [];
}
