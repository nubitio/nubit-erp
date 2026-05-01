<?php

declare(strict_types=1);

namespace App\AI\Dto;

use Symfony\Component\Validator\Constraints as Assert;

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
