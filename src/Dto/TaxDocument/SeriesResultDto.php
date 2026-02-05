<?php

namespace App\Dto\TaxDocument;

class SeriesResultDto
{
    public function __construct(
        public string $series,
        public string $associatedSeries
    ) {
    }
}
