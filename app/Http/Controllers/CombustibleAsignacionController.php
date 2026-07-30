<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CombustibleAsignacionController extends BaseController
{
    public function index(): JsonResponse
    {
        try {

            $items = DB::table('combustible_asignacion as ca')
                ->join('predio as p', 'p.id', '=', 'ca.predio_id')
                ->select(
                    'ca.id',
                    'p.nombre as predio',
                    DB::raw("TO_CHAR(ca.mes, 'YYYY-MM') as mes"),
                    'ca.monto_asignado',
                    'ca.monto_utilizado',
                    'ca.saldo',
                    'ca.created_at',
                    'ca.updated_at'
                )
                ->orderBy('ca.mes', 'desc')
                ->orderBy('p.nombre', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $items
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener asignaciones de combustible',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'predio' => 'required|integer',
                'mes' => 'required',
                'monto' => 'required|numeric|min:1',
                //'doeRespuestaB5' => 'required', VERIFICAR 09-06-2026
            ]);

            // convertir YYYY-MM -> YYYY-MM-01
            $mes = $validated['mes'] . '-01';

            $existe = DB::table('combustible_asignacion')
                ->where('predio_id', $validated['predio'])
                ->whereDate('mes', $mes)
                ->exists();

            if ($existe) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Ya existe una asignación para este predio en ese mes.',
                ], 422);
            }

            $id = DB::table('combustible_asignacion')
                ->insertGetId([
                    'predio_id' => $validated['predio'],
                    'mes' => $mes,
                    'monto_asignado' => $validated['monto'],
                    'monto_utilizado' => 0,
                    'saldo' => $validated['monto'],
                    //'doe_respuesta_b5' => $validated['doeRespuestaB5'], // NUEVO CAMPO
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'id' => $id,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function disponibles(): JsonResponse
    {
        $items = DB::table('combustible_asignacion as ca')
            ->join(
                'predio as p',
                'p.id',
                '=',
                'ca.predio_id'
            )
            ->select(
                'ca.id',
                DB::raw("CONCAT(p.nombre , ' | ', TRIM(TO_CHAR(ca.mes, 'TMMonth')),
                        ' ',
                        EXTRACT(YEAR FROM ca.mes)
                    ) as nombre
                "),
                'ca.saldo',
                'ca.monto_asignado',
                'ca.monto_utilizado'
            )
            ->where('ca.saldo', '>=', 0)
            ->orderBy('ca.mes', 'desc')
            ->get();

        return response()->json($items);
    }

    public function detalle($id)
    {
        $detalle = DB::table('ingreso_combustible as ic')
            ->select('ic.*')
            ->where('ic.asignacion_id', $id)
            ->orderBy('ic.id', 'desc')
            ->get();

        $detalle->transform(function ($item) {
            if ($item->comprobante) {
                $item->comprobante = route(
                    'combustible.archivo',
                    $item->id
                );
            }
            return $item;
        });
        return response()->json([
            'success' => true,
            'data' => $detalle
        ]);
    }

    public function verArchivo($id)
    {
        $detalle = DB::table('ingreso_combustible')
            ->where('id', $id)
            ->first();


        if (!$detalle) {
            abort(404, 'Registro no encontrado');
        }


        if (!$detalle->comprobante) {
            abort(404, 'Este registro no tiene archivo adjunto');
        }


        $path = $detalle->comprobante;


        if (!Storage::disk('public')->exists($path)) {

            return response()->json([
                'error' => 'Archivo no encontrado',
                'ruta' => $path
            ],404);
        }


        return response()->file(
            Storage::disk('public')->path($path),
            [
                'Content-Type' => Storage::disk('public')->mimeType($path),
                'Content-Disposition' => 'inline'
            ]
        );
    }

    public function patentes($id): JsonResponse
    {
        $asignacion = DB::table('combustible_asignacion')
            ->where('id', $id)
            ->first();

        if (!$asignacion) {
            return response()->json([]);
        }

        $patentes = DB::table('parque_vehicular as pv')
            ->join(
                'tipo_vehiculo as tv',
                'tv.id',
                '=',
                'pv.tipo_vehicular_id'
            )
            ->where('pv.predio', $asignacion->predio_id)
            ->whereNotNull('pv.ppu')
            ->select(
                'pv.orden',
                'pv.ppu',
                DB::raw("
                    CONCAT(
                        tv.nombre,
                        ' - ',
                        pv.ppu
                    ) as nombre
                ")
            )
            ->orderBy('tv.nombre')
            ->get();

        return response()->json($patentes);
    }

    public function eliminarDetalleCombustible($id)
    {
        DB::beginTransaction();
        try {

            $registro = DB::table('ingreso_combustible')
                ->where('id', $id)
                ->first();

            if (!$registro) {
                return response()->json([
                    'message' => 'Registro no encontrado.'
                ], 404);
            }

            // Auditoría antes de eliminar
            $this->auditar(
                modulo: 'Eliminar detalle combustible',
                accion: 'ELIMINAR',
                tabla: 'asignacion_combustible',
                registroId: $registro->id,
                descripcion: 'Se eliminó un detalle de ingreso de combustible.',
                antes: $registro
            );

            // Eliminamos el ingreso
            DB::table('ingreso_combustible')
                ->where('id', $id)
                ->delete();

            // Recalcular monto utilizado
            $montoUtilizado = DB::table('ingreso_combustible')
                ->where('asignacion_id', $registro->asignacion_id)
                ->sum('monto');

            // Obtener monto asignado
            $asignacion = DB::table('combustible_asignacion')
                ->where('id', $registro->asignacion_id)
                ->first();

            if ($asignacion) {

                DB::table('combustible_asignacion')
                    ->where('id', $registro->asignacion_id)
                    ->update([
                        'monto_utilizado' => $montoUtilizado,
                        'saldo' => $asignacion->monto_asignado - $montoUtilizado,
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Detalle eliminado correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function actualizarAsignacion(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $request->validate([
                'monto_asignado' => 'required|numeric|min:0',
            ]);

            $registroAnterior = DB::table('combustible_asignacion')
                ->where('id', $id)
                ->first();

            if (!$registroAnterior) {
                return response()->json([
                    'success' => false,
                    'message' => 'La asignación no existe.'
                ], 404);
            }
            // Calcular el nuevo saldo
            $saldo = $request->monto_asignado - $registroAnterior->monto_utilizado;

            DB::table('combustible_asignacion')
                ->where('id', $id)
                ->update([
                    'monto_asignado'  => $request->monto_asignado,
                    'saldo'           => $saldo,
                    'updated_at'      => now(),
                ]);
                
            $registroNuevo = DB::table('combustible_asignacion')
                ->where('id', $id)
                ->first();

            // Auditoría
            $this->auditar(
                modulo: 'Asignación de Combustible',
                accion: 'ACTUALIZAR',
                tabla: 'combustible_asignacion',
                registroId: $registroNuevo->id,
                descripcion: 'Se actualizó una asignación de combustible.',
                antes: $registroAnterior,
                despues: $registroNuevo
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asignación actualizada correctamente.'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la asignación.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}