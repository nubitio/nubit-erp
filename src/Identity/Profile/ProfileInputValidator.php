<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Checks what a user typed into their profile; says nothing about who may change what. */
final readonly class ProfileInputValidator
{
    public const array DENSITIES = ['normal', 'compact'];
    public const array THEMES = ['light', 'dark', 'auto'];

    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    /**
     * Only the keys present are checked, so saving a theme does not demand the phone too.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, string> field => message
     */
    public function validateUpdate(array $input): array
    {
        $violations = [];
        if (array_key_exists('displayName', $input)) {
            $violations += $this->check('displayName', $input['displayName'], [new Assert\Length(max: 120)]);
        }
        if (array_key_exists('phone', $input)) {
            $violations += $this->check('phone', $input['phone'], [
                new Assert\Regex(
                    pattern: '/^(\+?[1-9]\d{1,2}[-.\s]?\d{6,14})?$/',
                    message: 'El teléfono ingresado no es válido.',
                ),
            ]);
        }
        if (array_key_exists('preferences', $input)) {
            $violations += $this->checkPreferences($input['preferences']);
        }

        return $violations;
    }

    /** @return array<string, string> field => message */
    public function validateEmail(string $email): array
    {
        return $this->check('email', $email, [
            new Assert\NotBlank(message: 'Ingresa un correo.'),
            new Assert\Email(message: 'El correo ingresado no es válido.'),
            new Assert\Length(max: 180),
        ]);
    }

    /** @return array<string, string> */
    private function checkPreferences(mixed $preferences): array
    {
        if (!is_array($preferences)) {
            return ['preferences' => 'Las preferencias no son válidas.'];
        }

        $violations = [];
        if (isset($preferences['density']) && !in_array($preferences['density'], self::DENSITIES, true)) {
            $violations['preferences.density'] = 'La densidad no es válida.';
        }
        if (isset($preferences['theme']) && !in_array($preferences['theme'], self::THEMES, true)) {
            $violations['preferences.theme'] = 'El tema no es válido.';
        }

        return $violations;
    }

    /**
     * @param list<Constraint> $constraints
     *
     * @return array<string, string>
     */
    private function check(string $field, mixed $value, array $constraints): array
    {
        if ($value !== null && !is_string($value)) {
            return [$field => 'El valor no es válido.'];
        }
        $errors = $this->validator->validate($value === '' ? null : $value, $constraints);

        foreach ($errors as $error) {
            return [$field => (string) $error->getMessage()];
        }

        return [];
    }
}
