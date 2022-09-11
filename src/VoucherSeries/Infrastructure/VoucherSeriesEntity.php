<?php

namespace App\VoucherSeries\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\VoucherSeries\Domain\VoucherSeries;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "local_comprobante")]
class VoucherSeriesEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "local_id", type: "string")]
    public ?string $local_id;
    #[Column(name: "tipo_comprobante_id", type: "string")]
    public ?string $tipo_comprobante_id;
    #[Column(name: "serie", type: "string")]
    public ?string $serie;
    #[Column(name: "serie_asociada", type: "string")]
    public ?string $serie_asociada;

    public function mapToDomainModel(): VoucherSeries
    {
        return new VoucherSeries(
            $this->local_id,
            $this->tipo_comprobante_id,
            $this->serie,
            $this->serie_asociada,

        );
    }

    public function mapFromDomainModel(VoucherSeries $voucherseries): VoucherSeriesEntity
    {
        $this->local_id = $voucherseries->local_id;
        $this->tipo_comprobante_id = $voucherseries->tipo_comprobante_id;
        $this->serie = $voucherseries->serie;
        $this->serie_asociada = $voucherseries->serie_asociada;

        return $this;
    }
}