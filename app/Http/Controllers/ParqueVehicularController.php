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

            $permisoPath = null;
            $seguroPath  = null;

            /*
            |--------------------------------------------------------------------------
            | PERMISO
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('permiso.0.archivo')) {

                $permisoFile = $request->file('permiso.0.archivo');

                $extension = $permisoFile->getClientOriginalExtension();

                $nombreArchivo = 'permiso_' .
                    $request->ppu . '_' .
                    $request->anio . '.' .
                    $extension;

                $permisoPath = $permisoFile->storeAs(
                    'parquevehicular/permisos',
                    $nombreArchivo,
                    'public'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | SEGURO
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('seguro.0.archivo')) {

                $seguroFile = $request->file('seguro.0.archivo');
                $extension = $seguroFile->getClientOriginalExtension();
                $nombreArchivo = 'seguro_' .
                    $request->ppu . '_' .
                    $request->anio . '.' .
                    $extension;

                $seguroPath = $seguroFile->storeAs(
                    'parquevehicular/seguros',
                    $nombreArchivo,
                    'public'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT VEHÍCULO
            |--------------------------------------------------------------------------
            */
            $vehiculoId = DB::table('parque_vehicular')->insertGetId([
                'uuid' => Str::uuid(),

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
            ],500);
        }
    }

    public function eliminarParqueVehicular($numeroOrden)
    {
        try {

            // Verificar si existe la orden
            $existe = DB::table('parque_vehicular') 
                ->where('orden', $numeroOrden)
                ->exists();

            if (!$existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe la orden ' . $numeroOrden
                ], 404);
            }

            // Eliminar todos los registros asociados a esa orden
            $deleted = DB::table('parque_vehicular')
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
    
    public function show($uuid)
    {
        try {

            $registro = DB::table('parque_vehicular as pv')
                ->leftJoin('predio as p', 'pv.predio', '=', 'p.id')
                ->leftJoin('tipo_vehiculo as tv', 'pv.tipo_vehicular_id', '=', 'tv.id')
                ->select(
                    'pv.uuid',
                    'pv.orden',
                    'pv.predio',
                    'p.nombre as predio_nombre',
                    'pv.tipo_vehicular_id',
                    'tv.nombre as tipo_vehicular_nombre',
                    'pv.ppu',
                    'pv.sigla_institucional',
                    'pv.marca',
                    'pv.modelo',
                    'pv.anio',
                    'pv.fecha_adquisicion',
                    'pv.fondo_adquisicion',
                    'pv.vencimiento_permiso_circulacion',
                    'pv.vencimiento_seguro_obligatorio',
                    'pv.ultima_mantencion',
                    'pv.permiso_circulacion_img',
                    'pv.seguro_obligatorio_img'
                )
                ->where('pv.uuid', $uuid)
                ->first();

            if (!$registro) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Registro no encontrado'
                ],404);
            }

            $registro->permiso_circulacion_url =
                $registro->permiso_circulacion_img
                ? Storage::disk('public')->url($registro->permiso_circulacion_img)
                : null;

            $registro->seguro_obligatorio_url =
                $registro->seguro_obligatorio_img
                ? Storage::disk('public')->url($registro->seguro_obligatorio_img)
                : null;

            return response()->json([
                'ok' => true,
                'data' => $registro
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ],500);

        }
    }



    public function update(Request $request, $uuid)
    {
        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | BUSCAR VEHÍCULO
            |--------------------------------------------------------------------------
            */
            $vehiculo = DB::table('parque_vehicular')
                ->where('uuid', $uuid)
                ->first();

            if (!$vehiculo) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Vehículo no encontrado'
                ], 404);
            }

            // Mantener archivos actuales por defecto
            $permisoPath = $vehiculo->permiso_circulacion_img;
            $seguroPath  = $vehiculo->seguro_obligatorio_img;

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR PERMISO (si viene nuevo archivo)
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('permiso.0.archivo')) {

                // borrar archivo antiguo
                if ($permisoPath && Storage::disk('public')->exists($permisoPath)) {
                    Storage::disk('public')->delete($permisoPath);
                }

                $archivo = $request->file('permiso.0.archivo');

                $extension = $archivo->getClientOriginalExtension();

                $nombreArchivo = 'permiso_' .
                    $request->ppu . '_' .
                    $request->anio . '.' .
                    $extension;

                $permisoPath = $archivo->storeAs(
                    'parquevehicular/permisos',
                    $nombreArchivo,
                    'public'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR SEGURO (si viene nuevo archivo)
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('seguro.0.archivo')) {

                if ($seguroPath && Storage::disk('public')->exists($seguroPath)) {
                    Storage::disk('public')->delete($seguroPath);
                }

                $archivo = $request->file('seguro.0.archivo');

                $extension = $archivo->getClientOriginalExtension();

                $nombreArchivo = 'seguro_' .
                    $request->ppu . '_' .
                    $request->anio . '.' .
                    $extension;

                $seguroPath = $archivo->storeAs(
                    'parquevehicular/seguros',
                    $nombreArchivo,
                    'public'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE VEHÍCULO
            |--------------------------------------------------------------------------
            */
            DB::table('parque_vehicular')
                ->where('uuid', $uuid)
                ->update([
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

                    'permiso_circulacion_img' => $permisoPath,
                    'seguro_obligatorio_img' => $seguroPath,
                ]);


            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ],500);
        }
    }
}