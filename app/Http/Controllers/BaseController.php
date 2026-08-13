<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BaseController extends Controller
{
    /**
     * ============================================================
     * AUDITORÍA
     * ============================================================
     */
    protected function auditar(
        string $modulo,
        string $accion,
        ?string $tabla = null,
        $registroId = null,
        ?string $descripcion = null,
        $antes = null,
        $despues = null,
        ?int $predioId = null,
        ?string $predioNombre = null
    ): void {
        try {

            $usuario = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | DATOS DEL USUARIO
            |--------------------------------------------------------------------------
            */

            $nombreCompleto = null;

            if ($usuario) {
                $nombreCompleto = trim(
                    ($usuario->name ?? '') . ' ' .
                    ($usuario->apellido_ap ?? '') . ' ' .
                    ($usuario->apellido_mat ?? '')
                );
            }

            /*
            |--------------------------------------------------------------------------
            | OBTENER PREDIO DESDE LOS DATOS AUDITADOS
            |--------------------------------------------------------------------------
            |
            | Si no se recibe predio_id explícitamente, se intenta obtener
            | desde los datos anteriores o posteriores.
            |
            */

            if (!$predioId) {

                foreach ([$despues, $antes] as $dato) {

                    if (!$dato) {
                        continue;
                    }

                    if (is_object($dato)) {
                        $dato = (array) $dato;
                    }

                    if (is_array($dato) && isset($dato['predio_id'])) {
                        $predioId = (int) $dato['predio_id'];
                        break;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | OBTENER NOMBRE DEL PREDIO
            |--------------------------------------------------------------------------
            */

            if (!$predioNombre && $predioId) {

                $predioNombre = DB::table('predio')
                    ->where('id', $predioId)
                    ->value('nombre');
            }

            /*
            |--------------------------------------------------------------------------
            | REGISTRAR AUDITORÍA
            |--------------------------------------------------------------------------
            */

            DB::table('auditoria')->insert([

                'usuario_id' => $usuario?->id,

                'rut' => $usuario?->rut,

                'nombre_usuario' => $nombreCompleto,

                'email' => $usuario?->email,

                'predio_id' => $predioId,

                'predio_nombre' => $predioNombre,

                'modulo' => $modulo,

                'accion' => strtoupper($accion),

                'tabla' => $tabla,

                'registro_id' => $registroId,

                'descripcion' => $descripcion,

                'datos_antes' => $antes !== null
                    ? json_encode(
                        $antes,
                        JSON_UNESCAPED_UNICODE
                    )
                    : null,

                'datos_despues' => $despues !== null
                    ? json_encode(
                        $despues,
                        JSON_UNESCAPED_UNICODE
                    )
                    : null,

                'metodo_http' => request()->method(),

                'url' => request()->fullUrl(),

                'ruta' =>
                    request()->route()?->getName()
                    ?? request()->path(),

                'ip' => request()->ip(),

                'navegador' => request()->userAgent(),

                'fecha' => now(),
            ]);

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | LA AUDITORÍA NO DEBE BOTAR LA OPERACIÓN PRINCIPAL
            |--------------------------------------------------------------------------
            */

            report($e);
        }
    }


    /**
     * ============================================================
     * VERIFICAR SI EL USUARIO ES ADMINISTRADOR GLOBAL
     * ============================================================
     *
     * super_administrador:
     *     Acceso total.
     *
     * administrador:
     *     Acceso total.
     *
     * supervisor:
     *     Acceso restringido a su predio.
     *
     * usuario_consulta:
     *     Acceso restringido a su predio.
     */
    protected function esAdministradorGlobal(): bool
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return false;
        }

        return
            $usuario->hasRole('super_administrador') ||
            $usuario->hasRole('administrador');
    }


    /**
     * ============================================================
     * VERIFICAR ACCESO A PREDIO
     * ============================================================
     *
     * SUPER ADMINISTRADOR:
     *     Puede acceder a cualquier predio.
     *
     * ADMINISTRADOR:
     *     Puede acceder a cualquier predio.
     *
     * SUPERVISOR:
     *     Solo puede acceder a su predio.
     *
     * USUARIO CONSULTA:
     *     Solo puede acceder a su predio.
     */
    protected function puedeAccederPredio(
        int|string|null $predioId
    ): bool {

        $usuario = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USUARIO NO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        if (!$usuario) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | ACCESO GLOBAL
        |--------------------------------------------------------------------------
        |
        | SUPER ADMINISTRADOR Y ADMINISTRADOR
        | NO tienen restricción de predio.
        |
        */

        if ($this->esAdministradorGlobal()) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIO RESTRINGIDO
        |--------------------------------------------------------------------------
        |
        | Supervisor y usuario_consulta.
        |
        */

        if (!$usuario->predio_id || !$predioId) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR QUE SEA SU PREDIO
        |--------------------------------------------------------------------------
        */

        return (int) $usuario->predio_id === (int) $predioId;
    }


    /**
     * ============================================================
     * VALIDAR ACCESO A PREDIO
     * ============================================================
     *
     * Si el usuario no tiene autorización devuelve HTTP 403.
     */
    protected function validarAccesoPredio(
        int|string|null $predioId
    ): void {

        if (!$this->puedeAccederPredio($predioId)) {

            abort(response()->json([
                'message' =>
                    'No tiene permisos para acceder a la información de este predio.',
            ], 403));
        }
    }


    /**
     * ============================================================
     * VALIDAR PREDIO SOLICITADO
     * ============================================================
     *
     * Se utiliza principalmente en POST y PUT.
     *
     * SUPER ADMINISTRADOR:
     *     Puede seleccionar cualquier predio.
     *
     * ADMINISTRADOR:
     *     Puede seleccionar cualquier predio.
     *
     * SUPERVISOR:
     *     Solo puede seleccionar su predio.
     *
     * USUARIO CONSULTA:
     *     Solo puede seleccionar su predio.
     */
    protected function validarPredioSolicitado(
        int|string|null $predioId
    ): void {

        $usuario = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USUARIO NO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        if (!$usuario) {

            abort(response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401));
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR QUE EXISTA PREDIO
        |--------------------------------------------------------------------------
        */

        if (!$predioId) {

            abort(response()->json([
                'message' => 'Debe seleccionar un predio.',

                'errors' => [
                    'predio_id' => [
                        'El predio es obligatorio.'
                    ]
                ]
            ], 422));
        }

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMINISTRADOR / ADMINISTRADOR
        |--------------------------------------------------------------------------
        |
        | Ambos pueden seleccionar cualquier predio.
        |
        */

        if ($this->esAdministradorGlobal()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIO RESTRINGIDO SIN PREDIO
        |--------------------------------------------------------------------------
        */

        if (!$usuario->predio_id) {

            abort(response()->json([
                'message' =>
                    'El usuario no tiene un predio asociado.',
            ], 403));
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR QUE EL PREDIO SEA EL DEL USUARIO
        |--------------------------------------------------------------------------
        */

        if ((int) $usuario->predio_id !== (int) $predioId) {

            abort(response()->json([

                'message' =>
                    'No tiene permisos para utilizar este predio.',

                'errors' => [

                    'predio_id' => [

                        'No puede seleccionar un predio diferente al que tiene asignado.'

                    ]

                ]

            ], 403));
        }
    }


    /**
     * ============================================================
     * VALIDAR ROL SOLICITADO
     * ============================================================
     *
     * Controla qué roles puede asignar cada tipo de usuario.
     *
     * ROLES DEL SISTEMA:
     *
     * super_administrador
     * administrador
     * supervisor
     * usuario_consulta
     *
     * REGLAS:
     *
     * super_administrador:
     *     Puede asignar cualquier rol.
     *
     * administrador:
     *     Puede asignar:
     *     - supervisor
     *     - usuario_consulta
     *
     *     No puede asignar:
     *     - super_administrador
     *     - administrador
     *
     * supervisor:
     *     No puede asignar roles.
     *
     * usuario_consulta:
     *     No puede asignar roles.
     */
    protected function validarRolSolicitado(
        ?string $rol
    ): void {

        $usuario = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USUARIO NO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        if (!$usuario) {

            abort(response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401));
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR QUE EXISTA ROL
        |--------------------------------------------------------------------------
        */

        if (!$rol) {

            abort(response()->json([

                'message' =>
                    'Debe asignar un rol al usuario.',

                'errors' => [

                    'role' => [

                        'El rol es obligatorio.'

                    ]

                ]

            ], 422));
        }

        /*
        |--------------------------------------------------------------------------
        | ROLES VÁLIDOS DEL SISTEMA
        |--------------------------------------------------------------------------
        */

        $rolesValidos = [

            'super_administrador',

            'administrador',

            'supervisor',

            'usuario_consulta',

        ];

        /*
        |--------------------------------------------------------------------------
        | VALIDAR QUE EL ROL EXISTA
        |--------------------------------------------------------------------------
        */

        if (!in_array($rol, $rolesValidos, true)) {

            abort(response()->json([

                'message' =>
                    'El rol solicitado no existe.',

                'errors' => [

                    'role' => [

                        'El rol seleccionado no es válido.'

                    ]

                ]

            ], 422));
        }

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMINISTRADOR
        |--------------------------------------------------------------------------
        |
        | Puede asignar cualquier rol.
        |
        */

        if ($usuario->hasRole('super_administrador')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMINISTRADOR
        |--------------------------------------------------------------------------
        |
        | Puede asignar supervisor y usuario_consulta.
        |
        */

        if ($usuario->hasRole('administrador')) {

            $rolesPermitidos = [

                'supervisor',

                'usuario_consulta',

            ];

            if (!in_array($rol, $rolesPermitidos, true)) {

                abort(response()->json([

                    'message' =>
                        'No tiene permisos para asignar este rol.',

                    'errors' => [

                        'role' => [

                            'Un administrador no puede asignar el rol seleccionado.'

                        ]

                    ]

                ], 403));
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SUPERVISOR
        |--------------------------------------------------------------------------
        */

        if ($usuario->hasRole('supervisor')) {

            abort(response()->json([

                'message' =>
                    'No tiene permisos para asignar roles.',

                'errors' => [

                    'role' => [

                        'El supervisor no tiene permisos para asignar roles.'

                    ]

                ]

            ], 403));
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIO CONSULTA
        |--------------------------------------------------------------------------
        */

        if ($usuario->hasRole('usuario_consulta')) {

            abort(response()->json([

                'message' =>
                    'No tiene permisos para asignar roles.',

                'errors' => [

                    'role' => [

                        'El usuario de consulta no tiene permisos para asignar roles.'

                    ]

                ]

            ], 403));
        }

        /*
        |--------------------------------------------------------------------------
        | CUALQUIER OTRO CASO
        |--------------------------------------------------------------------------
        */

        abort(response()->json([

            'message' =>
                'No tiene permisos para asignar roles.',

            'errors' => [

                'role' => [

                    'No tiene permisos para asignar el rol seleccionado.'

                ]

            ]

        ], 403));
    }


    /**
     * ============================================================
     * VALIDAR ASIGNACIÓN DE PERMISOS
     * ============================================================
     *
     * SUPER ADMINISTRADOR:
     *     Puede asignar cualquier permiso.
     *
     * ADMINISTRADOR:
     *     Puede asignar permisos.
     *
     * SUPERVISOR:
     *     NO puede asignar permisos.
     *
     * USUARIO CONSULTA:
     *     NO puede asignar permisos.
     */
    protected function validarAsignacionPermisos(): void
    {

        $usuario = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USUARIO NO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        if (!$usuario) {

            abort(response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401));
        }

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMINISTRADOR
        |--------------------------------------------------------------------------
        */

        if ($usuario->hasRole('super_administrador')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMINISTRADOR
        |--------------------------------------------------------------------------
        */

        if ($usuario->hasRole('administrador')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SUPERVISOR
        |--------------------------------------------------------------------------
        */

        if ($usuario->hasRole('supervisor')) {

            abort(response()->json([

                'message' =>
                    'No tiene permisos para asignar permisos.',

                'errors' => [

                    'permissions' => [

                        'El supervisor no tiene permisos para asignar permisos.'

                    ]

                ]

            ], 403));
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIO CONSULTA
        |--------------------------------------------------------------------------
        */

        if ($usuario->hasRole('usuario_consulta')) {

            abort(response()->json([

                'message' =>
                    'No tiene permisos para asignar permisos.',

                'errors' => [

                    'permissions' => [

                        'El usuario de consulta no tiene permisos para asignar permisos.'

                    ]

                ]

            ], 403));
        }

        /*
        |--------------------------------------------------------------------------
        | CUALQUIER OTRO CASO
        |--------------------------------------------------------------------------
        */

        abort(response()->json([

            'message' =>
                'No tiene permisos para asignar permisos.',

            'errors' => [

                'permissions' => [

                    'No tiene permisos para asignar permisos.'

                ]

            ]

        ], 403));
    }


    /**
     * ============================================================
     * OBTENER PREDIO DEL USUARIO
     * ============================================================
     *
     * IMPORTANTE:
     *
     * super_administrador:
     *     null = todos los predios
     *
     * administrador:
     *     null = todos los predios
     *
     * supervisor:
     *     ID de su predio
     *
     * usuario_consulta:
     *     ID de su predio
     *
     * Esto permite utilizar esta función directamente en los
     * métodos index/listados de los diferentes controladores.
     */
    protected function predioUsuario(): ?int
    {

        $usuario = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USUARIO NO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        if (!$usuario) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMINISTRADOR / ADMINISTRADOR
        |--------------------------------------------------------------------------
        |
        | NULL significa:
        | NO APLICAR FILTRO POR PREDIO.
        |
        */

        if ($this->esAdministradorGlobal()) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | SUPERVISOR / USUARIO CONSULTA
        |--------------------------------------------------------------------------
        */

        return $usuario->predio_id
            ? (int) $usuario->predio_id
            : null;
    }


    /**
     * ============================================================
     * APLICAR FILTRO DE PREDIO A UNA CONSULTA
     * ============================================================
     *
     * Esta función evita repetir en todos los controladores:
     *
     * $predioId = $this->predioUsuario();
     *
     * if ($predioId !== null) {
     *     $query->where('predio_id', $predioId);
     * }
     *
     * SUPER ADMINISTRADOR / ADMINISTRADOR:
     *     No aplica filtro.
     *
     * SUPERVISOR / USUARIO CONSULTA:
     *     Filtra por su predio.
     *
     * USO:
     *
     * $query = DB::table('mi_tabla');
     * $this->aplicarFiltroPredio($query);
     */
    protected function aplicarFiltroPredio($query)
    {
        $predioId = $this->predioUsuario();

        if ($predioId !== null) {
            $query->where('predio_id', $predioId);
        }

        return $query;
    }
}


