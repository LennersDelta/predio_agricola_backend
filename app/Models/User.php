<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'rut',       // ← campo de login
        'email',     // opcional
        'grado',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /**
     * RUT formateado con puntos y guión para mostrar en vistas.
     * Ej: "123456789" → "12.345.678-9"
     */
    public function getRutFormateadoAttribute(): string
    {
        if (! $this->rut) return '';
        $cuerpo    = substr($this->rut, 0, -1);
        $dv        = strtoupper(substr($this->rut, -1));
        $conPuntos = number_format((int) $cuerpo, 0, ',', '.');
        return "{$conPuntos}-{$dv}";
    }
}