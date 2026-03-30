<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $ahora      = now();
        $inicioMes  = $ahora->copy()->startOfMonth();
        $hace12Meses = $ahora->copy()->subMonths(11)->startOfMonth();

        // ── Total propiedades ─────────────────────────────────────────────────
        $total = DB::table('propiedades')->where('estado_propiedad_id', '!=', 2)->count();

        // ── Ingresadas este mes ───────────────────────────────────────────────
        $ingresadasMes = DB::table('propiedades')
            ->where('estado_propiedad_id', '!=', 2)
            ->where('created_at', '>=', $inicioMes)
            ->count();

        // ── Por estado ───────────────────────────────────────────────────────
        $porEstado = DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('estado_propiedad as ep', 'p.estado_propiedad_id', '=', 'ep.id')
            ->select('ep.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('ep.descripcion')
            ->get()
            ->mapWithKeys(fn($r) => [$r->descripcion ?? 'Sin estado' => (int) $r->total]);

        // ── Usuarios activos (con sesión en los últimos 30 días) ──────────────
        $usuariosActivos = DB::table('users')
            ->where('updated_at', '>=', $ahora->copy()->subDays(30))
            ->count();

        // ── Tendencia últimos 12 meses ────────────────────────────────────────
        $tendencia = DB::table('propiedades')
            ->where('estado_propiedad_id', '!=', 2)
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as mes"),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', $hace12Meses)
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->mapWithKeys(fn($r) => [$r->mes => (int) $r->total]);

        // Rellenar los 12 meses (incluso los vacíos con 0)
        $tendenciaCompleta = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = $ahora->copy()->subMonths($i)->format('Y-m');
            $tendenciaCompleta[] = $tendencia[$key] ?? 0;
        }

        // ── Por región ────────────────────────────────────────────────────────
        $porRegion = DB::table('propiedades as p')
            ->where('p.estado_propiedad_id', '!=', 2)
            ->leftJoin('regiones as r', 'p.region_id', '=', 'r.id')
            ->select(
                'r.descripcion as nombre',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN p.created_at >= '{$inicioMes}' THEN 1 ELSE 0 END) as nuevas")
            )
            ->whereNotNull('r.id')
            ->groupBy('r.id', 'r.descripcion')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'nombre' => $r->nombre,
                'total'  => (int) $r->total,
                'nuevas' => (int) $r->nuevas,
            ]);

        return response()->json([
            'totalViviendas'   => $total,
            'viviendasMes'     => $ingresadasMes,
            'usuariosActivos'  => $usuariosActivos,
            'variacionMes'     => $total > 0
                ? round(($ingresadasMes / $total) * 100, 1)
                : 0,
            'porEstado'        => $porEstado,
            'tendencia'        => $tendenciaCompleta,
            'porRegion'        => $porRegion,
        ]);
    }
}