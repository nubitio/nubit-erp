<?php

namespace App\Company\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Media\Domain\Media;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Company
{
    public function __construct(
        #[SerializedName('empresa_id')]
        public ?string $empresa_id = null,
        #[SerializedName('ruc')]
        public ?string $ruc = null,
        #[SerializedName('razon_social')]
        public ?string $razon_social = null,
        #[SerializedName('nombre_comercial')]
        public ?string $nombre_comercial = null,
        #[SerializedName('ubigeo')]
        public ?string $ubigeo = null,
        #[SerializedName('domicilio_fiscal')]
        public ?string $domicilio_fiscal = null,
        #[SerializedName('telefono')]
        public ?string $telefono = null,
        #[SerializedName('logo')]
        public ?Media $logo = null,
        #[SerializedName('certificado')]
        public ?string $certificado = null,
        #[SerializedName('sunat_user')]
        public ?string $sunat_user = null,
        #[SerializedName('sunat_password')]
        public ?string $sunat_password = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }
}
