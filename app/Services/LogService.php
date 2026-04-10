<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LogService
{
    // ── IDs de módulos (tabla modulos) ────────────────────────────────────────
    const MODULO_PREDIO    = 1;
    const MODULO_USUARIOS  = 2;
    const MODULO_REPORTES  = 3;
    const MODULO_DOCUMENTOS = 4;

    // ── IDs de acciones (tabla accion) ────────────────────────────────────────
    const ACCION_CREAR     = 1;
    const ACCION_EDITAR    = 2;
    const ACCION_ELIMINAR  = 3;
    const ACCION_VER       = 4;
    const ACCION_VER_EDITAR = 9;
    const ACCION_DESCARGAR = 5;
    const ACCION_SUBIR     = 6;
    const ACCION_LOGIN     = 7;
    const ACCION_LOGOUT    = 8;

    /**
     * Configuración por módulo:
     * tabla  → nombre de la tabla
     * campos → campos a capturar (null = todos)
     */
    private static array $config = [
        self::MODULO_PREDIO => [
            'tabla'  => 'propiedades',
            'campos' => null, // captura todos los campos
        ],
        self::MODULO_USUARIOS => [
            'tabla'  => 'users',
            'campos' => ['id', 'name', 'email', 'rut', 'grado', 'area_id', 'created_at'],
        ],
    ];

    /**
     * Registra una acción en el log del sistema.
     *
     * - CREAR:    old_data = null,        new_data = registro recién creado (auto)
     * - VER:      old_data = null,        new_data = null
     * - ELIMINAR: old_data = datos antes (auto), new_data = null
     *
     * Para EDITAR usar registrarEdicion() que captura old y new correctamente.
     *
     * @param int      $accionId    ID de la acción (usar constantes ACCION_*)
     * @param int      $moduloId    ID del módulo (usar constantes MODULO_*)
     * @param int|null $idRegistro  ID del registro afectado
     */
    public static function registrar(
        int  $accionId,
        int  $moduloId,
        ?int $idRegistro = null
    ): void {
        try {
            $oldData = null;
            $newData = null;

            if ($idRegistro) {
                match ($accionId) {
                    // CREAR — new_data = estado completo recién insertado
                    self::ACCION_CREAR    => $newData = self::capturarDatos($moduloId, $idRegistro),
                    // ELIMINAR — old_data = estado completo antes de borrar
                    self::ACCION_ELIMINAR => $oldData = self::capturarDatos($moduloId, $idRegistro),
                    default               => null,
                };
            }

            self::insertar($accionId, $moduloId, $idRegistro, $oldData, $newData);

        } catch (\Exception $e) {
            \Log::warning('LogService error: ' . $e->getMessage());
        }
    }

    /**
     * Para EDITAR: capturar old_data ANTES del UPDATE en el controlador.
     * Luego llamar a registrarEdicion() DESPUÉS del UPDATE.
     *
     * Ejemplo de uso en controlador:
     *   $oldData = LogService::capturarAntes(LogService::MODULO_PREDIO, $id);
     *   // ... hacer el UPDATE ...
     *   LogService::registrarEdicion(LogService::MODULO_PREDIO, $id, $oldData);
     */
    public static function capturarAntes(int $moduloId, int $idRegistro): ?array
    {
        return self::capturarDatos($moduloId, $idRegistro);
    }

    /**
     * Registra una edición con old_data pre-capturado y new_data automático.
     * Llamar DESPUÉS de ejecutar el UPDATE.
     */
    public static function registrarEdicion(
        int    $moduloId,
        int    $idRegistro,
        ?array $oldData
    ): void {
        try {
            $newData = self::capturarDatos($moduloId, $idRegistro);

            self::insertar(self::ACCION_EDITAR, $moduloId, $idRegistro, $oldData, $newData);

        } catch (\Exception $e) {
            \Log::warning('LogService error: ' . $e->getMessage());
        }
    }

    /**
     * Inserta el registro en user_log.
     */
    private static function insertar(
        int    $accionId,
        int    $moduloId,
        ?int   $idRegistro,
        ?array $oldData,
        ?array $newData
    ): void {
        DB::table('user_log')->insert([
            'user_id'     => Auth::id(),
            'accion_id'   => $accionId,
            'modulo_id'   => $moduloId,
            'id_registro' => $idRegistro,
            'ip'          => Request::ip(),
            'old_data'    => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data'    => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'created_at'  => now(),
        ]);
    }

    /**
     * Obtiene los datos del registro desde la BD.
     * Si campos = null captura todos; si hay lista, filtra.
     */
    private static function capturarDatos(int $moduloId, int $idRegistro): ?array
    {
        if (!isset(self::$config[$moduloId])) return null;

        $cfg  = self::$config[$moduloId];
        $fila = DB::table($cfg['tabla'])->where('id', $idRegistro)->first();

        if (!$fila) return null;

        $datos = (array) $fila;

        // Filtrar por campos específicos si se definieron
        if (!empty($cfg['campos'])) {
            $datos = array_intersect_key($datos, array_flip($cfg['campos']));
        }

        // Excluir campos sensibles siempre
        unset($datos['password'], $datos['remember_token']);

        return $datos;
    }
}