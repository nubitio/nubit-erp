<?php

declare(strict_types=1);

namespace App\Tests\Efact\Domain;

use App\Efact\Domain\ActivatableInterface;
use App\Efact\Domain\ActiveReferenceGuard;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ActiveReferenceGuardTest extends TestCase
{
    public function testAllowsNullReference(): void
    {
        $guard = new ActiveReferenceGuard();

        $guard->assertActiveOnCreate(null, 'customer', 'El cliente');

        $this->addToAssertionCount(1);
    }

    public function testAllowsActiveReference(): void
    {
        $guard = new ActiveReferenceGuard();
        $reference = $this->createMock(ActivatableInterface::class);
        $reference->method('getIsActive')->willReturn(true);

        $guard->assertActiveOnCreate($reference, 'customer', 'El cliente');

        $this->addToAssertionCount(1);
    }

    public function testRejectsInactiveReference(): void
    {
        $guard = new ActiveReferenceGuard();
        $reference = $this->createMock(ActivatableInterface::class);
        $reference->method('getIsActive')->willReturn(false);

        $this->expectException(ValidationException::class);

        $guard->assertActiveOnCreate($reference, 'customer', 'El cliente');
    }
}
