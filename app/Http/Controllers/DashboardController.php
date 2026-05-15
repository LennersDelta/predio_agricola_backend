<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        try {

            $totalPredio = DB::table('predio')
                ->count();

            // Total vehículos
            $totalVehiculos = DB::table('parque_vehicular')
                ->count();

            // Vehículos por predio
            $porPredio = DB::table('parque_vehicular as pv')
                ->join('predio as p', 'p.id', '=', 'pv.predio')
                ->select(
                    'p.id',
                    'p.nombre',
                    DB::raw('COUNT(pv.orden) as total')
                )
                ->groupBy('p.id', 'p.nombre')
                ->orderByDesc('total')
                ->get();

            // Vehículos por tipo
            $porTipo = DB::table('parque_vehicular as pv')
                ->join('tipo_vehiculo as tv', 'tv.id', '=', 'pv.tipo_vehicular_id')
                ->select(
                    'tv.id',
                    'tv.nombre',
                    DB::raw('COUNT(pv.orden) as total')
                )
                ->groupBy('tv.id', 'tv.nombre')
                ->orderByDesc('total')
                ->get();

            // Últimos vehículos registrados (opcional)
            $ultimosVehiculos = DB::table('parque_vehicular as pv')
                ->join('predio as p', 'p.id', '=', 'pv.predio')
                ->join('tipo_vehiculo as tv', 'tv.id', '=', 'pv.tipo_vehicular_id')
                ->select(
                    'pv.orden',
                    'pv.ppu',
                    'pv.marca',
                    'pv.modelo',
                    'pv.anio',
                    'p.nombre as predio',
                    'tv.nombre as tipo',
                    'pv.created_at'
                )
                ->orderByDesc('pv.created_at')
                ->limit(5)
                ->get();


                // RRHH //

            $totalFuncionarios = DB::table('recursos_humanos')
                ->count();

            $funcionariosPorPredio = DB::table('recursos_humanos as rh')
                ->join('predio as p', 'p.id', '=', 'rh.predio_id')
                ->select(
                    'p.id',
                    'p.nombre',
                    DB::raw('COUNT(rh.orden) as total')
                )
                ->groupBy('p.id', 'p.nombre')
                ->orderByDesc('total')
                ->get();

            $insumosproductos = DB::table('insumosproductos as ip')
                ->join('predio as p', 'p.id', '=', 'ip.predio')
                ->select(
                    'ip.predio',
                    'p.nombre',

                    DB::raw("
                        COALESCE(
                            CAST(SUM(ip.valor_total) AS BIGINT),
                        0) as total_por_predio
                    "),

                    DB::raw("
                        COALESCE(
                            CAST(SUM(ip.valor_cotizacion) AS BIGINT),
                        0) as total_cotizacion_por_predio
                    ")
                )
                ->groupBy('ip.predio', 'p.nombre')
                ->orderBy('p.nombre')
                ->get();

            return response()->json([
                'totalVehiculos' => $totalVehiculos,
                'porPredio'      => $porPredio,
                'porTipo'        => $porTipo,
                'ultimosVehiculos' => $ultimosVehiculos,
                'totalPredio' => $totalPredio,

                // RRHH
                'totalFuncionarios' => $totalFuncionarios,
                'funcionariosPorPredio' => $funcionariosPorPredio,

                // INSUMOS Y PRODUCTOS
               'insumosproductos' => $insumosproductos,
               
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error al cargar dashboard',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function vehiculosPorPredio($id)
    {
        $vehiculos = DB::table('parque_vehicular as pv')
            ->join('tipo_vehiculo as tv', 'tv.id', '=', 'pv.tipo_vehicular_id')
            ->select(
                'pv.orden',
                'pv.ppu',
                'pv.marca',
                'pv.modelo',
                'pv.anio',
                'tv.nombre as tipo',
                'pv.fecha_adquisicion',
                'pv.vencimiento_permiso_circulacion',
                'pv.vencimiento_seguro_obligatorio'
            )
            ->where('pv.predio', $id)
            ->orderBy('pv.marca')
            ->get();

        return response()->json($vehiculos);
    }

    public function recursosHumanosPorPredio($id)
    {
        try {

            $personal = DB::table('recursos_humanos as rh')

                ->leftJoin(
                    'grados as g',
                    'g.id',
                    '=',
                    'rh.grado_id'
                )

                ->leftJoin(
                    'tipo_contrato as tc',
                    'tc.id',
                    '=',
                    'rh.tipo_contrato_id'
                )

                ->leftJoin(
                    'predio as p',
                    'p.id',
                    '=',
                    'rh.predio_id'
                )

                ->select(
                    'rh.orden',
                    'rh.nombres_apellidos',
                    'rh.rut',
                    'rh.cargo_contratado',
                    'rh.area_funciones',
                    'rh.funcion_actual',
                    'rh.fecha_inicio_contrato',
                    'rh.anios_servicio',
                    'rh.ultima_calificacion',
                    'rh.capacitado_prevencion_riesgo',

                    DB::raw("COALESCE(g.descripcion, '-') as grado"),
                    DB::raw("COALESCE(tc.nombre, '-') as tipo_contrato"),
                    DB::raw("COALESCE(p.nombre, '-') as predio")
                )

                ->where('rh.predio_id', $id)

                ->orderBy('rh.nombres_apellidos', 'asc')

                ->get();

            return response()->json($personal);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error RRHH',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /*public function insumosProductos(Request $request)
    {
        $mes = $request->query('mes');
        $anio = $request->query('anio');

        $data = DB::table('predio as p')
            ->leftJoin('insumos_productos as ip', function ($join) use ($mes, $anio) {
                $join->on('ip.predio', '=', 'p.id')
                    ->whereMonth('ip.fecha', $mes)
                    ->whereYear('ip.fecha', $anio);
            })
            ->select(
                'p.id as predio',
                'p.nombre',
                DB::raw('COALESCE(SUM(ip.valor_total),0) as total_por_predio')
            )
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('total_por_predio')
            ->get();

        return response()->json($data);
    }*/
}