<?php

namespace App\Company\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Company\Domain\Company;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "empresa")]
class CompanyEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "empresa_id", type: "string")]
    private ?string $empresa_id;
    #[Column(name: "ruc", type: "string")]
    private ?string $ruc;
    #[Column(name: "razon_social", type: "string")]
    private ?string $razon_social;
    #[Column(name: "nombre_comercial", type: "string")]
    private ?string $nombre_comercial;
    #[Column(name: "ubigeo", type: "string")]
    private ?string $ubigeo;
    #[Column(name: "domicilio_fiscal", type: "string")]
    private ?string $domicilio_fiscal;
    #[Column(name: "telefono", type: "string")]
    private ?string $telefono;
    #[Column(name: "logo", type: "string")]
    private ?string $logo;
    #[Column(name: "certificado", type: "string")]
    private ?string $certificado;
    #[Column(name: "sunat_user", type: "string")]
    private ?string $sunat_user;
    #[Column(name: "sunat_password", type: "string")]
    private ?string $sunat_password;
    #[Column(name: "estado", type: "boolean")]
    private ?bool $estado;

    public function mapToDomainModel(): Company
    {
        return new Company(
            $this->empresa_id,
            $this->ruc,
            $this->razon_social,
            $this->nombre_comercial,
            $this->ubigeo,
            $this->domicilio_fiscal,
            $this->telefono,
            $this->logo,
            $this->certificado,
            $this->sunat_user,
            $this->sunat_password,
            $this->estado,

        );
    }

    public function mapFromDomainModel(Company $company): CompanyEntity
    {
        $this->empresa_id = $company->empresa_id;
        $this->ruc = $company->ruc;
        $this->razon_social = $company->razon_social;
        $this->nombre_comercial = $company->nombre_comercial;
        $this->ubigeo = $company->ubigeo;
        $this->domicilio_fiscal = $company->domicilio_fiscal;
        $this->telefono = $company->telefono;
        $this->logo = $company->logo;
        $this->certificado = $company->certificado;
        $this->sunat_user = $company->sunat_user;
        $this->sunat_password = $company->sunat_password;
        $this->estado = $company->estado;

        return $this;
    }
}