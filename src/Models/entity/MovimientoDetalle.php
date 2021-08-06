<?php

class MovimientoDetalle extends CompositeModel
{
    protected $table = 'movimiento_detalle';
    protected $primaryKey = ['movimiento_id', 'producto_id'];
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
}