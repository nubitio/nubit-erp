<?php

namespace App\Domain\Note;

/**
 * Class NoteDetail.
 */
class NoteDetail implements \JsonSerializable
{
    /**
     * @var string
     */
    private $notaId;

    /**
     * @var string
     */
    private $productoId;

    /**
     * @var float
     */
    private $cantidad;

    /**
     * @var float
     */
    private $precioUnitario;

    /**
     * @var float
     */
    private $precioTotal;

    public function jsonSerialize(): mixed
    {
        return [
            'nota_id' => $this->getNotaId(),
            'producto_id' => $this->getProductoId(),
            'cantidad' => $this->getCantidad(),
            'precio_unitario' => $this->getPrecioUnitario(),
            'precio_total' => $this->getPrecioTotal(),
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
     * @return NoteDetail
     */
    public function setNotaId($notaId)
    {
        $this->notaId = $notaId;

        return $this;
    }

    /**
     * @return string
     */
    public function getProductoId()
    {
        return $this->productoId;
    }

    /**
     * @param string $productoId
     *
     * @return NoteDetail
     */
    public function setProductoId($productoId)
    {
        $this->productoId = $productoId;

        return $this;
    }

    /**
     * @return float
     */
    public function getCantidad()
    {
        return $this->cantidad;
    }

    /**
     * @param float $cantidad
     *
     * @return NoteDetail
     */
    public function setCantidad($cantidad)
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    /**
     * @return float
     */
    public function getPrecioUnitario()
    {
        return $this->precioUnitario;
    }

    /**
     * @param float $precioUnitario
     *
     * @return NoteDetail
     */
    public function setPrecioUnitario($precioUnitario)
    {
        $this->precioUnitario = $precioUnitario;

        return $this;
    }

    /**
     * @return float
     */
    public function getPrecioTotal()
    {
        return $this->precioTotal;
    }

    /**
     * @param float $precioTotal
     *
     * @return NoteDetail
     */
    public function setPrecioTotal($precioTotal)
    {
        $this->precioTotal = $precioTotal;

        return $this;
    }
}
