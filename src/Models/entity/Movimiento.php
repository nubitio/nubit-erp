<?php

namespace App\Models\entity;

class Movimiento extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'movimiento';
    protected $primaryKey = 'movimiento_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
}