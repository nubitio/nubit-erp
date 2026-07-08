<?php

declare(strict_types=1);

namespace App\Efact\Domain;

use Nubit\Platform\Exception\ValidationException;

/**
 * Central place to enforce "disabled records can't be wired into new
 * transactions, but stay readable in historical ones". Call from a state
 * processor's create path only — never on update, so editing an existing
 * sale/purchase/stock entry doesn't break just because one of its references
 * was disabled afterwards.
 */
final class ActiveReferenceGuard
{
    /**
     * @throws ValidationException when $reference is set but disabled
     */
    public function assertActiveOnCreate(?ActivatableInterface $reference, string $field, string $label): void
    {
        if ($reference !== null && !$reference->getIsActive()) {
            throw ValidationException::withErrors([
                $field => [sprintf('%s está deshabilitado(a); no se puede usar en nuevos registros.', $label)],
            ]);
        }
    }
}
