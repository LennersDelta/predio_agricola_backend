<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CombustibleAsignacionController extends Controller
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
    
   /* public function disponibles(): JsonResponse
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
                'p.nombre as predio',
                'ca.mes',
                'ca.saldo',
                'ca.monto_asignado',
                'ca.monto_utilizado'
            )
            ->where('ca.saldo', '>', 0)
            ->orderBy('ca.mes', 'desc')
            ->get();
        return response()->json($items);
    }*/

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






}