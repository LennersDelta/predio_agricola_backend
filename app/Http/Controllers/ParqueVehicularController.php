<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class ParqueVehicularController extends Controller
{
    public function getListaParqueVehicular(Request $request)
    {
        $query = DB::table('parque_vehicular as pv')
            ->leftJoin('predio as p', 'pv.predio', '=', 'p.id')
            ->leftJoin('tipo_vehiculo as tv', 'pv.tipo_vehicular_id', '=', 'tv.id')

            ->select(
                'pv.*',
                'p.nombre as predio_nombre',
                'tv.nombre as tipo_vehiculo_nombre'
            )

            ->orderBy('pv.orden', 'desc');

        return response()->json($query->get());
    }

    public function insertar(Request $request)
    {
        DB::beginTransaction();

        try {

            // 1. ARCHIVOS (solo 1 por tipo)
            $permisoPath = null;
            $seguroPath  = null;

            // PERMISO (primer archivo)
            if ($request->has('permiso') && isset($request->permiso[0]['archivo'])) {

                $file = $request->file('permiso.0.archivo');

                if ($file) {
                    $permisoPath = $file->store('parquevehicular/permisos', 'public');
                }
            }

            // SEGURO (primer archivo)
            if ($request->has('seguro') && isset($request->seguro[0]['archivo'])) {

                $file = $request->file('seguro.0.archivo');

                if ($file) {
                    $seguroPath = $file->store('parquevehicular/seguros', 'public');
                }
            }

            // 2. INSERT VEHÍCULO
            $vehiculoId = DB::table('parque_vehicular')->insertGetId([
                'predio' => (int) $request->predio,
                'tipo_vehicular_id' => (int) $request->tipo_vehicular,
                'ppu' => $request->ppu,
                'sigla_institucional' => $request->sigla_institucional,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'anio' => $request->anio,
                'fecha_adquisicion' => $request->fecha_adquisicion,
                'fondo_adquisicion' => $request->fondo_adquisicion,
                'vencimiento_permiso_circulacion' => $request->vencimiento_permiso,
                'vencimiento_seguro_obligatorio' => $request->vencimiento_seguro,
                'ultima_mantencion' => $request->ultima_mantencion,

                // AQUÍ SE GUARDA LA RUTA
                'permiso_circulacion_img' => $permisoPath,
                'seguro_obligatorio_img' => $seguroPath,
            ], 'orden');

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Guardado correctamente',
                'orden' => $vehiculoId
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}