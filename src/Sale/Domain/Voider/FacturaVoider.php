<?php

namespace App\Sale\Domain\Voider;

class FacturaVoider implements VoiderInterface
{
    private VoiderInterface $voider;

    public function __construct(VoiderInterface $voider)
    {
        $this->voider = $voider;
    }

    public function void(string $saleId, string $reason): void
    {
        // TODO: Implement void() method.
    }
}