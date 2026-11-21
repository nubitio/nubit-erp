<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Security\ReauthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use SensitiveParameter;

/**
 * What a signed-in user may change about themselves: name, phone, photo, preferences, and the e-mail
 * (which also resets passwords, so it needs the current password). Role, username and permissions are
 * not here on purpose: those stay behind `users.edit`.
 */
final readonly class ProfileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProfileInputValidator $input,
        private ReauthenticationService $reauthentication,
        private UserRepository $users,
        private ActivityRecorderInterface $activity,
    ) {}

    /**
     * Applies only the keys present, so a client saving its theme does not blank the phone.
     *
     * @param array<string, mixed> $input
     *
     * @throws ProfileViolations
     */
    public function update(User $user, array $input): void
    {
        $violations = $this->input->validateUpdate($input);
        if ($violations !== []) {
            throw new ProfileViolations($violations);
        }
        $preferences = $input['preferences'] ?? null;

        if (array_key_exists('displayName', $input)) {
            $user->setDisplayName(is_string($input['displayName']) ? $input['displayName'] : null);
        }
        if (array_key_exists('phone', $input)) {
            $user->setPhone(is_string($input['phone']) ? $input['phone'] : null);
        }
        if (is_array($preferences)) {
            $merged = $user->getPreferences();
            foreach (['density', 'theme'] as $key) {
                if (array_key_exists($key, $preferences)) {
                    $merged[$key] = $preferences[$key];
                }
            }
            $user->setPreferences($merged);
        }
        $this->entityManager->flush();
    }

    /**
     * @throws ProfileViolations
     */
    public function changeEmail(User $user, string $email, #[SensitiveParameter] string $currentPassword): void
    {
        $email = trim($email);
        $violations = $this->input->validateEmail($email);
        if ($violations !== []) {
            throw new ProfileViolations($violations);
        }

        // Without this, a stolen session could point "forgot password" at an address of its own.
        if (!$this->reauthentication->isValid($user, $currentPassword)) {
            throw ProfileViolations::of('currentPassword', 'La contraseña actual no es correcta.');
        }

        $taken = $this->users->findOneBy(['email' => $email, 'deletedAt' => null]);
        if ($taken instanceof User && $taken->getId() !== $user->getId()) {
            throw ProfileViolations::of('email', 'Ya existe un usuario con este correo.');
        }

        $previous = $user->getEmail();
        if ($previous === $email) {
            return;
        }
        $user->setEmail($email);
        $this->entityManager->flush();

        $this->activity->record(
            ActivityType::EMAIL_CHANGED,
            sprintf('%s cambió su correo', $user->getUserIdentifier()),
            sprintf('%s → %s', $previous ?? '(sin correo)', $email),
            $user->getUserIdentifier(),
            $user,
        );
    }
}
