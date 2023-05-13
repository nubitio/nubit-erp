<?php

namespace App\Employee\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Employee\Domain\Employee;

#[ApiResource(
    shortName: 'Employee',
    provider: EmployeeProvider::class,
    processor: EmployeeProcessor::class,
)]
class EmployeeDto extends Employee
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
