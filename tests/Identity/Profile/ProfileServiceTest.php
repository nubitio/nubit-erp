<?php

declare(strict_types=1);

namespace App\Tests\Identity\Profile;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use App\Identity\Profile\ProfileInputValidator;
use App\Identity\Profile\ProfileService;
use App\Identity\Profile\ProfileView;
use App\Identity\Profile\ProfileViolations;
use App\Identity\Repository\UserRepository;
use App\Identity\Security\ReauthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
final class ProfileServiceTest extends TestCase
{
    private int $flushes = 0;
    private SpyActivity $activity;
    private ?User $existingEmailOwner = null;
    private bool $passwordIsValid = true;

    public function testUpdateAppliesOnlyTheKeysSent(): void
    {
        $user = ProfileUsers::user();
        $user->setDisplayName('Ana')->setPhone('+51 987654321');
        $service = $this->service();

        $service->update($user, ['preferences' => ['theme' => 'dark']]);

        self::assertSame('Ana', $user->getDisplayName());
        self::assertSame('+51 987654321', $user->getPhone());
        self::assertSame(['density' => null, 'theme' => 'dark'], ProfileView::preferences($user));
    }

    public function testPreferencesAreMergedNotReplaced(): void
    {
        $user = ProfileUsers::user();
        $service = $this->service();

        $service->update($user, ['preferences' => ['density' => 'compact']]);
        $service->update($user, ['preferences' => ['theme' => 'light']]);

        self::assertSame(['density' => 'compact', 'theme' => 'light'], ProfileView::preferences($user));
    }

    public function testBlankValuesClearTheField(): void
    {
        $user = ProfileUsers::user();
        $user->setDisplayName('Ana')->setPhone('+51 987654321');

        $this->service()->update($user, ['displayName' => '  ', 'phone' => '']);

        self::assertNull($user->getDisplayName());
        self::assertNull($user->getPhone());
    }

    public function testInvalidValuesAreReportedPerFieldAndNothingIsSaved(): void
    {
        $user = ProfileUsers::user();
        $user->setDisplayName('Ana');

        try {
            $this->service()->update($user, [
                'displayName' => str_repeat('x', 121),
                'phone' => 'abc',
                'preferences' => ['density' => 'huge', 'theme' => 'neon'],
            ]);
            self::fail('Expected violations');
        } catch (ProfileViolations $violations) {
            self::assertEqualsCanonicalizing(
                ['displayName', 'phone', 'preferences.density', 'preferences.theme'],
                array_keys($violations->violations),
            );
        }

        self::assertSame('Ana', $user->getDisplayName());
        self::assertSame(0, $this->flushes);
    }

    public function testNonStringInputIsRejectedInsteadOfBreaking(): void
    {
        $this->expectException(ProfileViolations::class);

        $this->service()->update(ProfileUsers::user(), ['displayName' => ['nested']]);
    }

    public function testViewDefaultsPreferencesAndHidesTheStoragePath(): void
    {
        $user = ProfileUsers::user();
        $user->setAvatar('secret-path.png');

        $view = ProfileView::of($user);

        self::assertSame(['density' => null, 'theme' => null], $view['preferences']);
        self::assertStringStartsWith('/api/profile/avatar?v=', (string) $view['avatarUrl']);
        self::assertStringNotContainsString('secret-path', (string) json_encode($view));
    }

    public function testEmailChangeNeedsTheCurrentPassword(): void
    {
        $user = ProfileUsers::user();
        $user->setEmail('old@example.com');
        $this->passwordIsValid = false;

        try {
            $this->service()->changeEmail($user, 'new@example.com', 'wrong');
            self::fail('Expected violations');
        } catch (ProfileViolations $violations) {
            self::assertSame(['currentPassword'], array_keys($violations->violations));
        }

        self::assertSame('old@example.com', $user->getEmail());
        self::assertSame([], $this->activity->types);
    }

    public function testEmailChangeIsRecordedInTheActivityLog(): void
    {
        $user = ProfileUsers::user();
        $user->setEmail('old@example.com');

        $this->service()->changeEmail($user, ' New@Example.com ', 'right');

        self::assertSame('New@Example.com', $user->getEmail());
        self::assertSame([ActivityType::EMAIL_CHANGED], $this->activity->types);
    }

    public function testEmailAlreadyUsedByAnotherUserIsRejected(): void
    {
        $this->existingEmailOwner = ProfileUsers::user(id: 99);

        try {
            $this->service()->changeEmail(ProfileUsers::user(), 'taken@example.com', 'right');
            self::fail('Expected violations');
        } catch (ProfileViolations $violations) {
            self::assertSame(['email'], array_keys($violations->violations));
        }
    }

    public function testKeepingTheSameEmailIsANoOp(): void
    {
        $user = ProfileUsers::user();
        $user->setEmail('same@example.com');
        $this->existingEmailOwner = $user;

        $this->service()->changeEmail($user, 'same@example.com', 'right');

        self::assertSame(0, $this->flushes);
        self::assertSame([], $this->activity->types);
    }

    public function testInvalidEmailIsRejectedBeforeAskingForTheCredentials(): void
    {
        $this->passwordIsValid = false;

        try {
            $this->service()->changeEmail(ProfileUsers::user(), 'not-an-email', 'whatever');
            self::fail('Expected violations');
        } catch (ProfileViolations $violations) {
            self::assertSame(['email'], array_keys($violations->violations));
        }
    }

    private function service(): ProfileService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('flush')
            ->willReturnCallback(function (): void {
                ++$this->flushes;
            });

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturnCallback(fn(): bool => $this->passwordIsValid);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturnCallback(fn(): ?User => $this->existingEmailOwner);

        return new ProfileService(
            $entityManager,
            new ProfileInputValidator(Validation::createValidator()),
            new ReauthenticationService($hasher),
            $users,
            $this->activity,
        );
    }

    protected function setUp(): void
    {
        $this->activity = new SpyActivity();
    }
}
