<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a single file attachment sent with an AI assistant request.
 *
 * `name` and `mimeType` are required. At least one of `mediaId` or `s3Url`
 * should be provided to identify the attachment source.
 * Invalid attachments are silently skipped by the controller.
 */
class AttachmentInput
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $mimeType = '';

    #[Assert\Type('string')]
    public ?string $mediaId = null;

    #[Assert\Type('string')]
    #[Assert\Url]
    public ?string $s3Url = null;
}
