<?php

namespace App\Config\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Config\Domain\Config;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "options")]
class ConfigEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "id", type: "integer")]
    public ?int $id;
    #[Column(name: "name", type: "string")]
    public ?string $name;
    #[Column(name: "value", type: "string")]
    public ?string $value;

    public function mapToDomainModel(): Config
    {
        return new Config(
            $this->id,
            $this->name,
            $this->value,

        );
    }

    public function mapFromDomainModel(Config $config): ConfigEntity
    {
        $this->id = $config->id;
        $this->name = $config->name;
        $this->value = $config->value;

        return $this;
    }
}