<?php

declare(strict_types=1);

namespace App\AI\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Optional screen context sent by the frontend to make the AI aware of the
 * user's current view. Injected into the dynamic system prompt by both
 * AssistantController and AssistantStreamController.
 */
class ScreenContextInput
{
    /**
     * Identifies which screen the user is currently viewing.
     *
     * Known values: dashboard · pos · sale_form · inventory · cash_register ·
     *               customers · products · purchases · reports
     */
    #[Assert\Type('string')]
    #[Assert\Length(max: 64)]
    public ?string $screen = null;

    /**
     * The entity type being viewed or edited (e.g. "sale", "product", "customer").
     */
    #[Assert\Type('string')]
    #[Assert\Length(max: 64)]
    public ?string $entityType = null;

    /**
     * The UUID or numeric ID of the entity currently active on screen.
     */
    #[Assert\Type('string')]
    #[Assert\Length(max: 128)]
    public ?string $entityId = null;
}
