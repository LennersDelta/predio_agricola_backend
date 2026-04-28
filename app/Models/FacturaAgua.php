<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacturaAgua extends Model
{
    use SoftDeletes;

    protected $table = 'factura_agua';

    protected $fillable = [
        'predio_id',
        'n_factura',
        'mes_consumo',
        'valor',
        'proveedor',
        'estado_id',
        'doe',
        'consumo',
        'user_id',
    ];

    public function predio()
    {
        return $this->belongsTo(Predio::class, 'predio_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estados::class, 'estado_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}