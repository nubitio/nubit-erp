<?php

declare(strict_types=1);

namespace App\Pharmacy\State;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Write shape for `POST /api/pharmacy/prescriptions`.
 */
final class PrescriptionInput
{
    #[Assert\NotBlank]
    public string $patientName = '';

    public ?string $patientDocument = null;

    #[Assert\NotBlank]
    public string $prescriberName = '';

    public ?string $prescriberRegistration = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $issuedOn = '';

    /** @var list<int> */
    #[Assert\Count(min: 1)]
    public array $productIds = [];

    public ?string $saleId = null;

    public ?string $notes = null;
}
