<?php

namespace App\Sale\Domain\Voider;

interface VoiderInterface
{
    public function void(string $saleId, string $reason): void;
}