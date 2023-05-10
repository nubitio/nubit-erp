<?php

namespace App\Department\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Department\Domain\Department;

#[ApiResource(
    shortName: 'Department',
    provider: DepartmentProvider::class,
    processor: DepartmentProcessor::class,
)]
class DepartmentDto extends Department
{
    #[ApiProperty(identifier: true)]
    public ?int $id;

}