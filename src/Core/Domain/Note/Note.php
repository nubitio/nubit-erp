<?php

namespace App\Core\Domain\Note;

/**
 * Class Note.
 */
class Note implements \JsonSerializable
{
    /**
     * @var string
     */
    private $notaId;

    /**
     * @var string
     */
    private $movimientoId;

    /**
     * @var string
     */
    private $fecha;

    /**
     * @var string
     */
    private $tipoId;

    /**
     * @var string
     */
    private $codigoMotivo;

    /**
     * @var string
     */
    private $descripcionMotivo;

    /**
     * @var string
     */
    private $serie;

    /**
     * @var int
     */
    private $correlativo;

    /**
     * @var string
     */
    private $digest;

    /**
     * @var int
     */
    private $envioSunat;

    /**
     * @var string
     */
    private $mensajeSunat;

    /**
     * @var int
     */
    private $createdBy;

    /**
     * @var int
     */
    private $updatedBy;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * @var NoteDetail[]
     */
    private $details;

    public function jsonSerialize(): mixed
    {
        return [
            'nota_id' => $this->getNotaId(),
            'movimiento_id' => $this->getMovimientoId(),
            'fecha' => $this->getFecha(),
            'tipo_id' => $this->getTipoId(),
            'codigo_motivo' => $this->getCodigoMotivo(),
            'descripcion_motivo' => $this->getDescripcionMotivo(),
            'serie' => $this->getSerie(),
            'correlativo' => $this->getCorrelativo(),
            'digest' => $this->getDigest(),
            'envio_sunat' => $this->getEnvioSunat(),
            'mensaje_sunat' => $this->getMensajeSunat(),
            'created_by' => $this->getCreatedBy(),
            'updated_by' => $this->getUpdatedBy(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'details' => $this->getDetails(),
        ];
    }

    /**
     * @return string
     */
    public function getNotaId()
    {
        return $this->notaId;
    }

    /**
     * @param string $notaId
     *
     * @return Note
     */
    public function setNotaId($notaId)
    {
        $this->notaId = $notaId;

        return $this;
    }

    /**
     * @return string
     */
    public function getMovimientoId()
    {
        return $this->movimientoId;
    }

    /**
     * @param string $movimientoId
     *
     * @return Note
     */
    public function setMovimientoId($movimientoId)
    {
        $this->movimientoId = $movimientoId;

        return $this;
    }

    /**
     * @return string
     */
    public function getFecha()
    {
        return $this->fecha;
    }

    /**
     * @param string $fecha
     *
     * @return Note
     */
    public function setFecha($fecha)
    {
        $this->fecha = $fecha;

        return $this;
    }

    /**
     * @return string
     */
    public function getTipoId()
    {
        return $this->tipoId;
    }

    /**
     * @param string $tipoId
     *
     * @return Note
     */
    public function setTipoId($tipoId)
    {
        $this->tipoId = $tipoId;

        return $this;
    }

    /**
     * @return string
     */
    public function getCodigoMotivo()
    {
        return $this->codigoMotivo;
    }

    /**
     * @param string $codigoMotivo
     *
     * @return Note
     */
    public function setCodigoMotivo($codigoMotivo)
    {
        $this->codigoMotivo = $codigoMotivo;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescripcionMotivo()
    {
        return $this->descripcionMotivo;
    }

    /**
     * @param string $descripcionMotivo
     *
     * @return Note
     */
    public function setDescripcionMotivo($descripcionMotivo)
    {
        $this->descripcionMotivo = $descripcionMotivo;

        return $this;
    }

    /**
     * @return string
     */
    public function getSerie()
    {
        return $this->serie;
    }

    /**
     * @param string $serie
     *
     * @return Note
     */
    public function setSerie($serie)
    {
        $this->serie = $serie;

        return $this;
    }

    /**
     * @return int
     */
    public function getCorrelativo()
    {
        return $this->correlativo;
    }

    /**
     * @param int $correlativo
     *
     * @return Note
     */
    public function setCorrelativo($correlativo)
    {
        $this->correlativo = $correlativo;

        return $this;
    }

    /**
     * @return string
     */
    public function getDigest()
    {
        return $this->digest;
    }

    /**
     * @param string $digest
     *
     * @return Note
     */
    public function setDigest($digest)
    {
        $this->digest = $digest;

        return $this;
    }

    /**
     * @return int
     */
    public function getEnvioSunat()
    {
        return $this->envioSunat;
    }

    /**
     * @param int $envioSunat
     *
     * @return Note
     */
    public function setEnvioSunat($envioSunat)
    {
        $this->envioSunat = $envioSunat;

        return $this;
    }

    /**
     * @return string
     */
    public function getMensajeSunat()
    {
        return $this->mensajeSunat;
    }

    /**
     * @param string $mensajeSunat
     *
     * @return Note
     */
    public function setMensajeSunat($mensajeSunat)
    {
        $this->mensajeSunat = $mensajeSunat;

        return $this;
    }

    /**
     * @return int
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @param int $createdBy
     *
     * @return Note
     */
    public function setCreatedBy($createdBy)
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return int
     */
    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    /**
     * @param int $updatedBy
     *
     * @return Note
     */
    public function setUpdatedBy($updatedBy)
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param string $createdAt
     *
     * @return Note
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param string $updatedAt
     *
     * @return Note
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return NoteDetail[]
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * @param NoteDetail[] $details
     *
     * @return Note
     */
    public function setDetails($details)
    {
        $this->details = $details;

        return $this;
    }
}
