<?php

namespace App\Billing\Dto;

class SeriesResultDto
{
    public function __construct(
        public string $series,
        public ?string $associatedSeries,
    ) {}
}
