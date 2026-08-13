<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Predio extends Model
{
    protected $table = 'predio';

    protected $fillable = [
        'nombre',
        'fechacreacion',
        'estado',
    ];
    
    public $timestamps = false;

    public function usuarios()
    {
        return $this->hasMany(User::class, 'predio_id', 'id');
    }
}