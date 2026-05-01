<?php

declare(strict_types=1);

namespace App\AI\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class AssistantMessageInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['user', 'assistant'])]
    public string $role;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $content;
}
