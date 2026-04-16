<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class InsumosServiciosController extends Controller
{
    public function getListaInsumosProductos(Request $request)
    {
        $query = DB::table('insumosproductos as ip')
            ->leftJoin('predio as p', 'ip.predio', '=', 'p.id')

            ->leftJoin('estados as tc', function($join) {
                $join->on('ip.tipo_compra', '=', 'tc.id')
                    ->where('tc.tipo', 'tipoCompra');
            })

            ->leftJoin('estados as eo', function($join) {
                $join->on('ip.estado_orden', '=', 'eo.id')
                    ->where('eo.tipo', 'estadoOC');
            })

            ->leftJoin('estados as ef', function($join) {
                $join->on('ip.estado_factura', '=', 'ef.id')
                    ->where('ef.tipo', 'estadoFactura');
            })

            ->select(
                'ip.*',
                'p.nombre as predio_nombre',
                'tc.nombre as tipo_compra_nombre',
                'eo.nombre as estado_orden_nombre',
                'ef.nombre as estado_factura_nombre'
            )

            ->orderBy('ip.orden', 'desc');

        return response()->json($query->get());
    }

    public function eliminarInsumosProductos($numeroOrden)
    {
        try {

            // Verificar si existe la orden
            $existe = DB::table('insumosproductos') // <-- CAMBIA ESTO por el nombre real de tu tabla
                ->where('orden', $numeroOrden)
                ->exists();

            if (!$existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe la orden ' . $numeroOrden
                ], 404);
            }

            // Eliminar todos los registros asociados a esa orden
            $deleted = DB::table('insumosproductos')
                ->where('orden', $numeroOrden)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registros eliminados correctamente',
                'orden' => $numeroOrden,
                'filas_eliminadas' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registros',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}