<?php

declare(strict_types=1);

namespace App\Tests\Efact\Validation;

use App\Efact\Validation\Validator;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidatorTest extends TestCase
{
    private function makeValidator(int $violationCount = 0, string $path = 'field', string $message = 'Error'): Validator
    {
        $violations = [];
        for ($i = 0; $i < $violationCount; $i++) {
            $violations[] = new ConstraintViolation($message, $message, [], null, $path, null);
        }

        $list = new ConstraintViolationList($violations);

        $inner = $this->createStub(ValidatorInterface::class);
        $inner->method('validate')->willReturn($list);

        return new Validator($inner);
    }

    // ── validate ──────────────────────────────────────────────────────────────

    public function testValidatePassesWhenNoViolations(): void
    {
        $validator = $this->makeValidator(0);

        // Should not throw
        $validator->validate('value');
        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsValidationExceptionOnViolation(): void
    {
        $validator = $this->makeValidator(1, 'email', 'Invalid email');

        $this->expectException(ValidationException::class);
        $validator->validate('bad-email');
    }

    public function testValidateExceptionContainsFieldErrors(): void
    {
        $validator = $this->makeValidator(1, 'name', 'Must not be blank');

        try {
            $validator->validate('');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertArrayHasKey('name', $errors);
            self::assertContains('Must not be blank', $errors['name']);
        }
    }

    // ── isValid ───────────────────────────────────────────────────────────────

    public function testIsValidReturnsTrueWhenNoViolations(): void
    {
        self::assertTrue($this->makeValidator(0)->isValid('value'));
    }

    public function testIsValidReturnsFalseWhenViolationsExist(): void
    {
        self::assertFalse($this->makeValidator(1)->isValid('value'));
    }

    // ── getViolations ─────────────────────────────────────────────────────────

    public function testGetViolationsReturnsEmptyArrayWhenValid(): void
    {
        self::assertSame([], $this->makeValidator(0)->getViolations('value'));
    }

    public function testGetViolationsReturnsErrorMap(): void
    {
        $validator = $this->makeValidator(1, 'username', 'Too short');
        $violations = $validator->getViolations('x');

        self::assertArrayHasKey('username', $violations);
        self::assertContains('Too short', $violations['username']);
    }

    public function testGetViolationsGroupsMultipleViolationsPerField(): void
    {
        // Two violations on same path
        $list = new ConstraintViolationList([
            new ConstraintViolation('Error 1', 'E1', [], null, 'field', null),
            new ConstraintViolation('Error 2', 'E2', [], null, 'field', null),
        ]);

        $inner = $this->createStub(ValidatorInterface::class);
        $inner->method('validate')->willReturn($list);

        $validator = new Validator($inner);
        $violations = $validator->getViolations('x');

        self::assertCount(2, $violations['field']);
    }
}
