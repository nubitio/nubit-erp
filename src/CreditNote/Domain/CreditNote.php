<?php

namespace App\CreditNote\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Sale\Domain\Sale;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class CreditNote
{
    public function __construct(
        #[SerializedName('nota_id')]
        public ?string $nota_id = null,
        #[SerializedName('sale')]
        public ?Sale $sale = null,
        #[SerializedName('raw_document_id')]
        public ?int $raw_document_id = null,
        #[SerializedName('fecha')]
        public ?\DateTimeInterface $fecha = null,
        #[SerializedName('tipo_id')]
        public ?string $tipo_id = null,
        #[SerializedName('codigo_motivo')]
        public ?string $codigo_motivo = null,
        #[SerializedName('descripcion_motivo')]
        public ?string $descripcion_motivo = null,
        #[SerializedName('serie')]
        public ?string $serie = null,
        #[SerializedName('correlativo')]
        public ?int $correlativo = null,
        #[SerializedName('doc_afectado')]
        public ?string $doc_afectado = null,
        #[SerializedName('digest')]
        public ?string $digest = null,
        #[SerializedName('envio_sunat')]
        public ?string $envio_sunat = null,
        #[SerializedName('mensaje_sunat')]
        public ?string $mensaje_sunat = null,
        #[SerializedName('total')]
        public ?float $total = null,
        #[SerializedName('created_by')]
        public ?int $created_by = null,
        #[SerializedName('updated_by')]
        public ?int $updated_by = null,
        #[SerializedName('created_at')]
        public ?\DateTimeInterface $created_at = null,
        #[SerializedName('updated_at')]
        public ?\DateTimeInterface $updated_at = null
    ) {
    }
}
