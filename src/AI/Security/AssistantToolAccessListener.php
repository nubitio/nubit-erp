<?php

declare(strict_types=1);

namespace App\AI\Security;

use App\Identity\Security\PermissionCheckerInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Stops a tool the user has no permission for before it runs. The toolbox turns the exception into
 * a failed tool call the model can explain, instead of data the user should not see.
 */
#[AsEventListener]
final readonly class AssistantToolAccessListener
{
    public function __construct(
        private PermissionCheckerInterface $permissions,
    ) {}

    public function __invoke(ToolCallArgumentsResolved $event): void
    {
        $tool = $event->getMetadata()->getName();

        if (!AssistantToolAccess::knows($tool)) {
            throw new AccessDeniedException(sprintf('The assistant tool "%s" has no access rule.', $tool));
        }

        $permission = AssistantToolAccess::permissionFor($tool);
        if ($permission !== null && !$this->permissions->hasPermission($permission)) {
            throw new AccessDeniedException(sprintf('The user may not use the assistant tool "%s".', $tool));
        }
    }
}
