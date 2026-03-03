<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Security\Auth\JWTManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces eager instantiation of JWTManager at the first kernel.request event.
 *
 * JWTManager throws LogicException in its constructor when APP_SECRET is empty.
 * Without this listener the container is lazy — JWTManager is only constructed
 * on the first login attempt, meaning a deploy with an empty APP_SECRET silently
 * serves requests until someone tries to authenticate.
 *
 * By injecting JWTManager here at priority 1025 (higher than TenantListener at
 * 1024) we surface the misconfiguration immediately on the first request that
 * reaches the kernel, which in practice means at deploy/smoke-test time.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1025)]
final class AppSecretBootListener
{
    private bool $checked = false;

    public function __construct(
        private readonly JWTManager $jwtManager,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($this->checked || !$event->isMainRequest()) {
            return;
        }

        // Calling verify() with an empty string is a zero-cost no-op that reads
        // $this->jwtManager and ensures the container cannot defer JWTManager
        // construction beyond the first main request.
        //
        // The real side-effect is in JWTManager::__construct(), which throws
        // LogicException immediately when APP_SECRET is empty — surfacing the
        // misconfiguration at deploy / smoke-test time rather than at the first
        // login attempt.
        $this->jwtManager->verify('');
        $this->checked = true;
    }
}
