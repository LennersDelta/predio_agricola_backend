<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ReporteRegionController extends Controller
{
    public function index()
    {
        $inicioMes = now()->startOfMonth();

        // Totales por región
        $porRegion = DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('regiones as r',          'p.region_id',           '=', 'r.id')
            ->leftJoin('estado_propiedad as ep',  'p.estado_propiedad_id', '=', 'ep.id')
            ->select([
                'r.id as region_id',
                'r.descripcion as nombre',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN p.created_at >= '{$inicioMes}' THEN 1 ELSE 0 END) as nuevas"),
                DB::raw("SUM(CASE WHEN LOWER(ep.descripcion) LIKE '%activ%' THEN 1 ELSE 0 END) as activas"),
            ])
            ->whereNotNull('r.id')
            ->groupBy('r.id', 'r.descripcion')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'region_id' => $r->region_id,
                'nombre'    => $r->nombre,
                'total'     => (int) $r->total,
                'nuevas'    => (int) $r->nuevas,
                'activas'   => (int) $r->activas,
            ]);

        // Composición por estado para donut
        $porEstado = DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('estado_propiedad as ep', 'p.estado_propiedad_id', '=', 'ep.id')
            ->select('ep.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('ep.descripcion')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'estado' => $r->descripcion ?? 'Sin estado',
                'total'  => (int) $r->total,
            ]);

        $grandTotal   = $porRegion->sum('total');
        $grandNuevas  = $porRegion->sum('nuevas');
        $grandActivas = $porRegion->sum('activas');

        return response()->json([
            'porRegion'    => $porRegion,
            'porEstado'    => $porEstado,
            'grandTotal'   => $grandTotal,
            'grandNuevas'  => $grandNuevas,
            'grandActivas' => $grandActivas,
        ]);
    }

    // Detalle de una región al hacer clic
    public function detalle($regionId)
    {
        $inicioMes = now()->startOfMonth();

        $region = DB::table('regiones')->where('id', $regionId)->first();
        if (!$region) return response()->json(['message' => 'Región no encontrada'], 404);

        $propiedades = DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('tipo_propiedad as tp',  'p.tipo_propiedad_id',  '=', 'tp.id')
            ->leftJoin('estado_propiedad as ep', 'p.estado_propiedad_id','=', 'ep.id')
            ->leftJoin('comunas as c',           'p.comuna_id',          '=', 'c.id')
            ->where('p.region_id', $regionId)
            ->select([
                'p.id', 'p.uuid', 'p.carpeta', 'p.nombre_conjunto', 'p.rol_avaluo',
                'p.direccion', 'p.avaluo_fiscal_total', 'p.created_at',
                DB::raw("COALESCE(tp.descripcion,'—') as tipo"),
                DB::raw("COALESCE(ep.descripcion,'—') as estado"),
                DB::raw("COALESCE(c.descripcion,'—')  as comuna"),
            ])
            ->orderByDesc('p.created_at')
            ->get();

        $porEstado = $propiedades->groupBy('estado')->map(fn($g) => $g->count());
        $porTipo   = $propiedades->groupBy('tipo')->map(fn($g) => $g->count())->sortDesc();

        return response()->json([
            'region'       => $region->descripcion,
            'region_id'    => (int) $regionId,
            'total'        => $propiedades->count(),
            'nuevas'       => $propiedades->filter(fn($p) => $p->created_at >= $inicioMes)->count(),
            'avaluo_total' => $propiedades->sum('avaluo_fiscal_total'),
            'porEstado'    => $porEstado,
            'porTipo'      => $porTipo,
            'propiedades'  => $propiedades->values(),
        ]);
    }
}