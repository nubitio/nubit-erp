<?php

declare(strict_types=1);

namespace App\AI\Dto;

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
     * @var AttachmentInput[]
     */
    #[Assert\Type('array')]
    #[Assert\Count(max: 10)]
    #[Assert\Valid]
    public array $attachments = [];

    /**
     * Optional screen context: which screen the user is on and what entity is active.
     * When provided, the AI receives richer context to give more relevant answers.
     */
    #[Assert\Valid]
    public ?ScreenContextInput $screenContext = null;
}
