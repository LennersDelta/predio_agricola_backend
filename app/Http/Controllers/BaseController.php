<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class BaseController extends Controller
{
    protected function auditar(
        string $modulo,
        string $accion,
        ?string $tabla = null,
        $registroId = null,
        ?string $descripcion = null,
        $antes = null,
        $despues = null,
        ?int $predioId = null,
        ?string $predioNombre = null,

    ): void {
        try {
            $usuario = Auth::user();
            $nombreCompleto = null;
            if ($usuario) {
                $nombreCompleto = trim(
                    ($usuario->name ?? '') . ' ' .
                    ($usuario->apellido_ap ?? '') . ' ' .
                    ($usuario->apellido_mat ?? '')
                );
            }
            if (!$predioId) {
                $datosBuscar = [
                    $despues,
                    $antes
                ];
                foreach ([$despues, $antes] as $dato) {
                    if (!$dato) {
                        continue;
                    }
                    if (is_object($dato)) {
                        $dato = (array)$dato;
                    }
                    if (isset($dato['predio_id'])) {
                        $predioId = $dato['predio_id'];
                        break;
                    }
                }
            }
            if (!$predioNombre && $predioId) {
                $predioNombre = DB::table('predio')
                    ->where('id', $predioId)
                    ->value('nombre');
            }


            DB::table('auditoria')->insert([
                'usuario_id'      => $usuario?->id,
                'rut'             => $usuario?->rut,
                'nombre_usuario'  => $nombreCompleto,
                'email'           => $usuario?->email,
                'predio_id'       => $predioId,
                'predio_nombre'   => $predioNombre,
                'modulo'          => $modulo,
                'accion'          => strtoupper($accion),
                'tabla'           => $tabla,
                'registro_id'     => $registroId,
                'descripcion'     => $descripcion,
                'datos_antes'     => $antes ? json_encode($antes) : null,
                'datos_despues'   => $despues ? json_encode($despues) : null,
                'metodo_http'     => request()->method(),
                'url'             => request()->fullUrl(),
                'ruta'            => request()->route()?->getName()
                                    ?? request()->path(),
                'ip'              => request()->ip(),
                'navegador'       => request()->userAgent(),
                'fecha'           => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}