<?php

namespace App\VoucherSeries\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\VoucherSeries\Domain\VoucherSeries;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'local_comprobante')]
class VoucherSeriesEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id', type: 'integer')]
    public ?int $id;
    #[Column(name: 'local_id', type: 'string')]
    public ?string $local_id;
    #[Column(name: 'tipo_comprobante_id', type: 'string')]
    public ?string $tipo_comprobante_id;
    #[Column(name: 'serie', type: 'string')]
    public ?string $serie;
    #[Column(name: 'serie_asociada', type: 'string')]
    public ?string $serie_asociada;
    #[Column(name: 'active', type: 'boolean')]
    public ?bool $active;

    public function mapToDomainModel(): VoucherSeries
    {
        return new VoucherSeries(
            $this->id,
            $this->local_id,
            $this->tipo_comprobante_id,
            $this->serie,
            $this->serie_asociada,
            $this->active,
        );
    }

    public function mapFromDomainModel(VoucherSeries $voucherSeries): VoucherSeriesEntity
    {
        $this->id = $voucherSeries->id;
        $this->local_id = $voucherSeries->local_id;
        $this->tipo_comprobante_id = $voucherSeries->tipo_comprobante_id;
        $this->serie = $voucherSeries->serie;
        $this->serie_asociada = $voucherSeries->serie_asociada;
        $this->active = $voucherSeries->active;

        return $this;
    }
}
