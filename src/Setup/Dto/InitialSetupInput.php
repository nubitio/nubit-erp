<?php

declare(strict_types=1);

namespace App\Setup\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class InitialSetupInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 4, max: 50)]
    public string $adminUsername;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 255)]
    public string $adminPassword;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{11}$/', message: 'El RUC debe tener 11 dígitos')]
    public string $companyRuc;

    #[Assert\NotBlank]
    public string $companyBusinessName;

    public ?string $companyTradeName = null;

    #[Assert\NotBlank]
    public string $companyFiscalAddress;

    public ?string $companyPhone = null;

    #[Assert\NotBlank]
    public string $branchName;

    public ?string $branchAddress = null;

    public ?string $branchPhone = null;
}
