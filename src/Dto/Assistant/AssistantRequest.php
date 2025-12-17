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
}
