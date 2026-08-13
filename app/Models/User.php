<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

use App\Models\Grado;
use App\Models\Contratacion;
use App\Models\Predio;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;


    /**
     * ============================================================
     * CAMPOS ASIGNABLES
     * ============================================================
     */
    protected $fillable = [
        'rut',
        'name',
        'apellido_ap',
        'apellido_mat',
        'email',
        'grado_id',
        'tipo_contratacion',
        'telefono',
        'area_id',
        'predio_id',
        'password',
    ];


    /**
     * ============================================================
     * CAMPOS OCULTOS
     * ============================================================
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    /**
     * ============================================================
     * CASTS
     * ============================================================
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    /**
     * ============================================================
     * RUT FORMATEADO
     * ============================================================
     *
     * Ejemplo:
     *
     * 123456789
     *
     * Resultado:
     *
     * 12.345.678-9
     */
    public function getRutFormateadoAttribute(): string
    {
        if (!$this->rut) {
            return '';
        }

        $cuerpo = substr($this->rut, 0, -1);
        $dv = strtoupper(substr($this->rut, -1));

        $conPuntos = number_format(
            (int) $cuerpo,
            0,
            ',',
            '.'
        );

        return "{$conPuntos}-{$dv}";
    }


    /**
     * ============================================================
     * RELACIÓN CON GRADO
     * ============================================================
     */
    public function grado()
    {
        return $this->belongsTo(
            Grado::class,
            'grado_id'
        );
    }


    /**
     * ============================================================
     * RELACIÓN CON CONTRATACIÓN
     * ============================================================
     */
    public function contratacion()
    {
        return $this->belongsTo(
            Contratacion::class,
            'tipo_contratacion',
            'id'
        );
    }


    /**
     * ============================================================
     * RELACIÓN CON PREDIO
     * ============================================================
     *
     * Cada usuario restringido puede tener un predio asignado.
     */
    public function predio()
    {
        return $this->belongsTo(
            Predio::class,
            'predio_id',
            'id'
        );
    }


    /**
     * ============================================================
     * ROLES QUE ADMINISTRAN TODOS LOS PREDIOS
     * ============================================================
     *
     * super_administrador
     * administrador
     *
     * Estos usuarios pueden:
     *
     * - Ver todos los predios
     * - Crear en cualquier predio
     * - Editar cualquier predio
     * - Eliminar registros
     */
    public function puedeAdministrarTodosLosPredios(): bool
    {
        return $this->hasAnyRole([
            'super_administrador',
            'administrador',
        ]);
    }


    /**
     * ============================================================
     * ROLES RESTRINGIDOS A UN PREDIO
     * ============================================================
     *
     * supervisor
     * usuario_consulta
     *
     * Estos usuarios solamente pueden trabajar
     * con el predio asignado en predio_id.
     */
    public function tieneAccesoRestringidoPredio(): bool
    {
        return $this->hasAnyRole([
            'supervisor',
            'usuario_consulta',
        ]);
    }


    /**
     * ============================================================
     * PUEDE CONSULTAR
     * ============================================================
     *
     * Todos los usuarios autenticados pueden consultar.
     */
    public function puedeConsultar(): bool
    {
        return $this->exists;
    }


    /**
     * ============================================================
     * PUEDE ACCEDER A UN PREDIO
     * ============================================================
     *
     * super_administrador:
     *      cualquier predio
     *
     * administrador:
     *      cualquier predio
     *
     * supervisor:
     *      solamente su predio
     *
     * usuario_consulta:
     *      solamente su predio
     */
    public function puedeAccederPredio(?int $predioId): bool
    {
        /*
         * Usuario no persistido / no válido.
         */
        if (!$this->exists) {
            return false;
        }


        /*
         * Administradores:
         *
         * Pueden acceder a cualquier predio.
         */
        if ($this->puedeAdministrarTodosLosPredios()) {
            return true;
        }


        /*
         * Usuarios restringidos:
         *
         * Solo pueden acceder al predio
         * que tienen asignado.
         */
        if (
            $this->tieneAccesoRestringidoPredio() &&
            $this->predio_id &&
            $predioId
        ) {
            return (int) $this->predio_id === (int) $predioId;
        }


        /*
         * Si no tiene predio asignado,
         * no puede acceder.
         */
        return false;
    }


    /**
     * ============================================================
     * PREDIO PERMITIDO
     * ============================================================
     *
     * Administrador / Super Administrador:
     *
     *      null
     *
     * significa que pueden trabajar con todos.
     *
     * Supervisor / Usuario Consulta:
     *
     *      retorna su predio_id.
     */
    public function getPredioPermitido(): ?int
    {
        /*
         * Administradores:
         *
         * null = todos los predios.
         */
        if ($this->puedeAdministrarTodosLosPredios()) {
            return null;
        }


        /*
         * Usuarios restringidos:
         *
         * solamente su predio.
         */
        return $this->predio_id
            ? (int) $this->predio_id
            : null;
    }


    /**
     * ============================================================
     * PUEDE CREAR
     * ============================================================
     *
     * super_administrador  -> SI
     * administrador        -> SI
     * supervisor           -> SI
     * usuario_consulta     -> NO
     */
    public function puedeCrear(): bool
    {
        return $this->hasAnyRole([
            'super_administrador',
            'administrador',
            'supervisor',
        ]);
    }


    /**
     * ============================================================
     * PUEDE EDITAR
     * ============================================================
     *
     * super_administrador  -> SI
     * administrador        -> SI
     * supervisor           -> SI
     * usuario_consulta     -> NO
     */
    public function puedeEditar(): bool
    {
        return $this->hasAnyRole([
            'super_administrador',
            'administrador',
            'supervisor',
        ]);
    }


    /**
     * ============================================================
     * PUEDE ELIMINAR
     * ============================================================
     *
     * super_administrador  -> SI
     * administrador        -> SI
     * supervisor           -> NO
     * usuario_consulta     -> NO
     */
    public function puedeEliminar(): bool
    {
        return $this->hasAnyRole([
            'super_administrador',
            'administrador',
        ]);
    }


    /**
     * ============================================================
     * USUARIO DE CONSULTA
     * ============================================================
     *
     * Este rol puede:
     *
     * - Consultar
     *
     * Pero NO puede:
     *
     * - Crear
     * - Editar
     * - Eliminar
     */
    public function esUsuarioConsulta(): bool
    {
        return $this->hasRole('usuario_consulta');
    }


    /**
     * ============================================================
     * SUPERVISOR
     * ============================================================
     */
    public function esSupervisor(): bool
    {
        return $this->hasRole('supervisor');
    }


    /**
     * ============================================================
     * ADMINISTRADOR
     * ============================================================
     */
    public function esAdministrador(): bool
    {
        return $this->hasRole('administrador');
    }


    /**
     * ============================================================
     * SUPER ADMINISTRADOR
     * ============================================================
     */
    public function esSuperAdministrador(): bool
    {
        return $this->hasRole('super_administrador');
    }
}
