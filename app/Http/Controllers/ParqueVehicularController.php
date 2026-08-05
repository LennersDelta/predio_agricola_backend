<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class ParqueVehicularController extends BaseController
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

            /*
            |--------------------------------------------------------------------------
            | VALIDACIONES
            |--------------------------------------------------------------------------
            */
          $request->validate([
                'predio'              => 'required|integer',
                'tipo_vehicular'      => 'required|integer',
                'ppu'                 => 'required|string|max:20',
                'sigla_institucional' => 'required|string|max:255',
                'marca'               => 'required|string|max:255',
                'modelo'              => 'required|string|max:255',
                'anio'                => 'required|integer',
                'fecha_adquisicion'   => 'required|date',
                'fondo_adquisicion'   => 'required|string|max:255',

                'permiso.0.archivo' => [
                    'nullable',
                    'file',
                    'mimes:pdf,doc,docx',
                    'max:2048', // 2 MB
                ],

                'seguro.0.archivo' => [
                    'nullable',
                    'file',
                    'mimes:pdf,doc,docx',
                    'max:2048', // 2 MB
                ],
            ], [

                'permiso.0.archivo.file'  => 'El permiso no corresponde a un archivo válido.',
                'permiso.0.archivo.mimes' => 'El permiso debe ser PDF, DOC o DOCX.',
                'permiso.0.archivo.max'   => 'El permiso no puede superar los 2 MB.',

                'seguro.0.archivo.file'   => 'El seguro no corresponde a un archivo válido.',
                'seguro.0.archivo.mimes'  => 'El seguro debe ser PDF, DOC o DOCX.',
                'seguro.0.archivo.max'    => 'El seguro no puede superar los 2 MB.',
            ]);

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
            | GENERAR UUID
            |--------------------------------------------------------------------------
            */
            $uuid = (string) Str::uuid();

            /*
            |--------------------------------------------------------------------------
            | INSERT VEHÍCULO
            |--------------------------------------------------------------------------
            */
            $vehiculoId = DB::table('parque_vehicular')->insertGetId([

                'uuid' => $uuid,
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

                'condicion' => $request->condicion,

                'created_at' => now(),
                'updated_at' => now(),

            ], 'orden');

            /*
            |--------------------------------------------------------------------------
            | RECUPERAR REGISTRO PARA AUDITORÍA
            |--------------------------------------------------------------------------
            */
            $registro = DB::table('parque_vehicular as v')
                        ->leftJoin('predio as p', 'v.predio', '=', 'p.id')
                        ->select(
                            'v.*',
                            'p.nombre as predio_nombre'
                        )
                        ->where('v.orden', $vehiculoId)
                        ->first();
            if (!$registro) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No existe la orden ' . $numeroOrden
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */
            $this->auditar(
                modulo: 'Parque Vehicular',
                accion: 'CREAR',
                tabla: 'parque_vehicular',
                registroId: $registro->orden,
                descripcion: 'Se creó un vehículo en el parque vehicular.',
                despues: $registro
            );

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Guardado correctamente',
                'orden' => $vehiculoId,
                'uuid' => $uuid
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarParqueVehicular($numeroOrden)
    {
        DB::beginTransaction();

        try {
            // Obtener el registro antes de eliminar
            $registro = DB::table('parque_vehicular as v')
                        ->leftJoin('predio as p', 'v.predio', '=', 'p.id')
                        ->select(
                            'v.*',
                            'p.nombre as predio_nombre'
                        )
                        ->where('v.orden', $numeroOrden)
                        ->first();

            if (!$registro) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No existe la orden ' . $numeroOrden
                ], 404);
            }

            // Auditoría
            $this->auditar(
                modulo: 'Parque Vehicular',
                accion: 'ELIMINAR',
                tabla: 'parque_vehicular',
                registroId: $registro->orden,
                descripcion: 'Se eliminó un vehículo del parque vehicular.',
                antes: $registro
            );

            // Eliminar registro
            $deleted = DB::table('parque_vehicular')
                ->where('orden', $numeroOrden)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente.',
                'orden' => $numeroOrden,
                'filas_eliminadas' => $deleted
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registros.',
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
                    'pv.seguro_obligatorio_img',
                    'pv.condicion',
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
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */
            $request->validate([
                'predio' => 'required|integer',
                'tipo_vehicular' => 'required|integer',
                'ppu' => 'required|string|max:50',
                'sigla_institucional' => 'nullable|string|max:255',
                'marca' => 'nullable|string|max:100',
                'modelo' => 'nullable|string|max:100',
                'anio' => 'nullable|string|max:10',
                'fecha_adquisicion' => 'nullable|date',
                'fondo_adquisicion' => 'nullable|string|max:255',
                'vencimiento_permiso' => 'nullable|date',
                'vencimiento_seguro' => 'nullable|date',
                'ultima_mantencion' => 'nullable|date',
                'condicion' => 'nullable|string|max:100',

                'permiso.*.archivo' => [
                    'nullable',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                ],

                'seguro.*.archivo' => [
                    'nullable',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | BUSCAR VEHÍCULO ANTERIOR
            |--------------------------------------------------------------------------
            */
            $registroAnterior = DB::table('parque_vehicular as v')
                ->leftJoin('predio as p', 'v.predio', '=', 'p.id')
                ->select(
                    'v.*',
                    'p.nombre as predio_nombre'
                );

            if (is_numeric($uuid)) {
                $registroAnterior->where('v.orden', $uuid);
            } else {
                $registroAnterior->where('v.uuid', $uuid);
            }

            $registroAnterior = $registroAnterior->first();

            if (!$registroAnterior) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | ARCHIVOS ACTUALES
            |--------------------------------------------------------------------------
            */
            $nuevoPermiso = $registroAnterior->permiso_circulacion_img;
            $nuevoSeguro  = $registroAnterior->seguro_obligatorio_img;

            $archivoPermisoAnterior = null;
            $archivoSeguroAnterior = null;

            /*
            |--------------------------------------------------------------------------
            | NUEVO PERMISO CIRCULACIÓN
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('permiso.0.archivo')) {

                $archivo = $request->file('permiso.0.archivo');

                $nombreArchivo = 'permiso_' .
                    strtoupper($request->ppu) .
                    '_' .
                    time() .
                    '.' .
                    $archivo->getClientOriginalExtension();

                $nuevoPermiso = $archivo->storeAs(
                    'parquevehicular/permisos',
                    $nombreArchivo,
                    'public'
                );

                $archivoPermisoAnterior = $registroAnterior->permiso_circulacion_img;
            }

            /*
            |--------------------------------------------------------------------------
            | NUEVO SEGURO OBLIGATORIO
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('seguro.0.archivo')) {

                $archivo = $request->file('seguro.0.archivo');

                $nombreArchivo = 'seguro_' .
                    strtoupper($request->ppu) .
                    '_' .
                    time() .
                    '.' .
                    $archivo->getClientOriginalExtension();

                $nuevoSeguro = $archivo->storeAs(
                    'parquevehicular/seguros',
                    $nombreArchivo,
                    'public'
                );

                $archivoSeguroAnterior = $registroAnterior->seguro_obligatorio_img;
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR VEHÍCULO
            |--------------------------------------------------------------------------
            */
            $vehiculo = DB::table('parque_vehicular');

            if (is_numeric($uuid)) {
                $vehiculo->where('orden', $uuid);
            } else {
                $vehiculo->where('uuid', $uuid);
            }

            $vehiculo->update([
                'predio' => (int) $request->predio,
                'tipo_vehicular_id' => (int) $request->tipo_vehicular,
                'ppu' => strtoupper($request->ppu),
                'sigla_institucional' => $request->sigla_institucional,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'anio' => $request->anio,
                'fecha_adquisicion' => $request->fecha_adquisicion,
                'fondo_adquisicion' => $request->fondo_adquisicion,
                'vencimiento_permiso_circulacion' => $request->vencimiento_permiso,
                'vencimiento_seguro_obligatorio' => $request->vencimiento_seguro,
                'ultima_mantencion' => $request->ultima_mantencion,
                'permiso_circulacion_img' => $nuevoPermiso,
                'seguro_obligatorio_img' => $nuevoSeguro,
                'condicion' => $request->condicion,
                'updated_at' => now()
            ]);

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR ARCHIVOS ANTIGUOS
            |--------------------------------------------------------------------------
            */
            if (
                $archivoPermisoAnterior &&
                $archivoPermisoAnterior != $nuevoPermiso &&
                Storage::disk('public')->exists($archivoPermisoAnterior)
            ) {
                Storage::disk('public')->delete($archivoPermisoAnterior);
            }

            if (
                $archivoSeguroAnterior &&
                $archivoSeguroAnterior != $nuevoSeguro &&
                Storage::disk('public')->exists($archivoSeguroAnterior)
            ) {
                Storage::disk('public')->delete($archivoSeguroAnterior);
            }

            /*
            |--------------------------------------------------------------------------
            | REGISTRO DESPUÉS
            |--------------------------------------------------------------------------
            */
            $registroDespues = DB::table('parque_vehicular as v')
                ->leftJoin('predio as p', 'v.predio', '=', 'p.id')
                ->select(
                    'v.*',
                    'p.nombre as predio_nombre'
                )
                ->where('v.orden', $registroAnterior->orden)
                ->first();

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */
            $this->auditar(
                modulo: 'Parque Vehicular',
                accion: 'ACTUALIZAR',
                tabla: 'parque_vehicular',
                registroId: $registroAnterior->orden,
                descripcion: 'Se actualizó información del vehículo.',
                antes: $registroAnterior,
                despues: $registroDespues
            );

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Vehículo actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}