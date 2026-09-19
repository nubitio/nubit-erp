<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Controller;

use App\Onboarding\Controller\OpeningReceivableImportController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * This endpoint creates customer debt and `customer_account_entries` rows
 * directly, bypassing the normal sale flow — it must not be reachable by a
 * plain authenticated user. This codebase has no HTTP-kernel/security-firewall
 * test harness (every other test is unit-level), so the attribute itself —
 * the thing Symfony's security layer actually enforces — is what's asserted.
 */
final class OpeningReceivableImportControllerTest extends TestCase
{
    public function testControllerRequiresRoleAdmin(): void
    {
        $attributes = new \ReflectionClass(OpeningReceivableImportController::class)->getAttributes(IsGranted::class);

        self::assertNotEmpty($attributes, 'Expected an #[IsGranted] attribute on the controller.');

        $isGranted = $attributes[0]->newInstance();

        self::assertSame('ROLE_ADMIN', $isGranted->attribute);
    }
}
