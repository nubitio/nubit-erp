<?php

namespace App\Company\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Company
{
    public function __construct(
        #[SerializedName('empresa_id')]
        public ?string $empresa_id,
        #[SerializedName('ruc')]
        public ?string $ruc,
        #[SerializedName('razon_social')]
        public ?string $razon_social,
        #[SerializedName('nombre_comercial')]
        public ?string $nombre_comercial,
        #[SerializedName('ubigeo')]
        public ?string $ubigeo,
        #[SerializedName('domicilio_fiscal')]
        public ?string $domicilio_fiscal,
        #[SerializedName('telefono')]
        public ?string $telefono,
        #[SerializedName('logo')]
        public ?string $logo,
        #[SerializedName('certificado')]
        public ?string $certificado,
        #[SerializedName('sunat_user')]
        public ?string $sunat_user,
        #[SerializedName('sunat_password')]
        public ?string $sunat_password,
        #[SerializedName('estado')]
        public ?bool $estado,
    ) {
    }
}
