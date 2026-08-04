<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contratacion extends Model
{
    protected $table = 'tipo_contrato';

    protected $fillable = [
        'nombre'
    ];

    public $timestamps = false;
}