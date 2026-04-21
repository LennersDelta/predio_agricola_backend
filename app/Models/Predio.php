<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Predio extends Model
{
    protected $table = 'predio';

    protected $fillable = [
        'nombre',
        'fechacreacion',
        'estado',
    ];

    public $timestamps = false;
}