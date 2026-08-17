<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class RecursosHumanoController extends BaseController
{
    public function getListaRecursosHumanos(Request $request)
    {
        try {

            $usuarioActual = auth()->user();

            // Validar usuario autenticado
            if (!$usuarioActual) {
                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $query = DB::table('recursos_humanos as rh')
                ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
                ->leftJoin('grados as g', 'rh.grado_id', '=', 'g.id')
                ->leftJoin('tipo_contrato as tc', 'rh.tipo_contrato_id', '=', 'tc.id')
                ->select(
                    'rh.orden',
                    'p.nombre as predio',
                    'rh.nombres_apellidos',
                    'rh.rut',
                    'tc.nombre as tipo_contrato',
                    'g.descripcion as grado',
                    'rh.cargo_contratado',
                    'rh.area_funciones as area',
                    'rh.funcion_actual',
                    'rh.fecha_inicio_contrato',
                    'rh.anios_servicio',
                    'rh.ultima_calificacion',
                    'rh.capacitado_prevencion_riesgo',
                    'rh.uuid'
                );

            // Administrador y Super Administrador
            // pueden ver todos los registros
            if (
                !$usuarioActual->hasRole('administrador') &&
                !$usuarioActual->hasRole('super_administrador')
            ) {

                // Validar que el usuario tenga predio asociado
                if (!$usuarioActual->predio_id) {
                    return response()->json([
                        'message' => 'El usuario no tiene un predio asociado.'
                    ], 403);
                }

                // Usuarios normales solo ven
                // los recursos humanos de su predio
                $query->where(
                    'rh.predio_id',
                    $usuarioActual->predio_id
                );
            }

            $recursosHumanos = $query
                ->orderBy('rh.orden', 'desc')
                ->get();

            return response()->json($recursosHumanos);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error al cargar los recursos humanos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function insertar(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'predio' => ['required','integer'],
            'nombresApellidos' => ['required','string','max:150'],
            'rut' => ['required','string','max:12'],
            'tipoContrato' => ['required','integer'],
            'grado' => ['required','integer'],
            'cargoContratado' => ['required','string','max:150'],
            'area' => ['required','string','max:150'],
            'funcionActual' => ['required','string','max:150'],
            'fechaInicioContrato' => ['required','date'],
            'aniosServicio' => ['required','integer'],
            'ultimaCalificacion' => ['required','string','max:50'],
            'capacitadoPrevencionRiesgo' => ['required','string'],
        ]);

        if($validator->fails()){
            return response()->json([
                'message'=>$validator->errors()->first(),
                'errors'=>$validator->errors()
            ],422);
        }

        DB::beginTransaction();

        try {

            $id = DB::table('recursos_humanos')->insertGetId([
                'predio_id' => $request->predio,
                'grado_id' => $request->grado,
                'nombres_apellidos' => $request->nombresApellidos,
                'rut' => $request->rut,
                'tipo_contrato_id' => $request->tipoContrato,
                'cargo_contratado' => $request->cargoContratado,
                'area_funciones' => $request->area,
                'funcion_actual' => $request->funcionActual,
                'fecha_inicio_contrato' => $request->fechaInicioContrato,
                'anios_servicio' => $request->aniosServicio,
                'ultima_calificacion' => $request->ultimaCalificacion,
                'capacitado_prevencion_riesgo' =>  $request->capacitadoPrevencionRiesgo === 'si',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'orden');

            /*
            |--------------------------------------------------------------------------
            | RECUPERAR REGISTRO CREADO PARA AUDITORÍA
            |--------------------------------------------------------------------------
            */
            $registro = DB::table('recursos_humanos as rh')
                ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
                ->select(
                    'rh.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where('rh.orden', $id)
                ->first();


            if (!$registro) {
                throw new Exception('No fue posible recuperar el registro creado.');
            }


            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */
            $this->auditar(
                modulo: 'Recursos Humanos',
                accion: 'CREAR',
                tabla: 'recursos_humanos',
                registroId: $registro->orden,
                descripcion: 'Se creó un registro de recurso humano.',
                despues: $registro
            );

            DB::commit();

            return response()->json([
                'message'=>'Guardado correctamente',
                'id'=>$id
            ],201);

        } catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'message'=>'Error al guardar',
                'error'=>$e->getMessage()
            ],500);
        }
    }

    public function eliminarRecursosHumanos($numeroOrden)
    {
        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | OBTENER REGISTRO ANTES DE ELIMINAR
            |--------------------------------------------------------------------------
            */
            $registro = DB::table('recursos_humanos as rh')
                ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
                ->select(
                    'rh.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where('rh.orden', $numeroOrden)
                ->first();

            if (!$registro) {

                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ], 404);
            }
            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA ANTES DE ELIMINAR
            |--------------------------------------------------------------------------
            */
            $this->auditar(
                modulo: 'Recursos Humanos',
                accion: 'ELIMINAR',
                tabla: 'recursos_humanos',
                registroId: $registro->orden,
                descripcion: 'Se eliminó un registro de recursos humanos.',
                antes: $registro
            );
            /*
            |--------------------------------------------------------------------------
            | ELIMINAR REGISTRO
            |--------------------------------------------------------------------------
            */
            $deleted = DB::table('recursos_humanos')
                ->where('orden', $numeroOrden)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente',
                'orden' => $numeroOrden,
                'filas_eliminadas' => $deleted
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($uuid)
    {
        try {
            $registro = DB::table('recursos_humanos as rh')
                ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
                ->leftJoin('grados as g', 'rh.grado_id', '=', 'g.id')
                ->leftJoin('tipo_contrato as tc', 'rh.tipo_contrato_id', '=', 'tc.id')
                ->select(
                    'rh.uuid',
                    'rh.orden',

                    'rh.predio_id',
                    'p.nombre as predio_nombre',

                    'rh.grado_id',
                    'g.descripcion as grado_nombre',

                    'rh.tipo_contrato_id',
                    'tc.nombre as tipo_contrato_nombre',

                    'rh.nombres_apellidos',
                    'rh.rut',

                    'rh.cargo_contratado',
                    'rh.area_funciones',
                    'rh.funcion_actual',

                    'rh.fecha_inicio_contrato',
                    'rh.anios_servicio',

                    'rh.ultima_calificacion',
                    'rh.capacitado_prevencion_riesgo'
                )
                ->where('rh.uuid', $uuid)
                ->first();

            if (!$registro) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            return response()->json([
                'ok' => true,
                'data' => $registro
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener registro',
                'error' => $e->getMessage()
            ], 500);

        }
    }

    /* UPDATE */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'predio_id' => ['required', 'integer'],

            'grado_id' => ['required', 'integer'],
            'nombres_apellidos' => ['required', 'string', 'max:150'],
            'rut' => ['required', 'string', 'max:12'],

            'cargo_contratado' => ['required', 'string', 'max:150'],
            'area_funciones' => ['required', 'string', 'max:150'],
            'funcion_actual' => ['required', 'string', 'max:150'],

            'fecha_inicio_contrato' => ['nullable', 'date'],
            'anios_servicio' => ['nullable', 'integer'],

            'ultima_calificacion' => ['nullable', 'string', 'max:100'],
            'capacitado_prevencion_riesgo' => ['nullable', 'boolean'],

            'tipo_contrato_id' => ['required', 'integer'],

            'uuid' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | REGISTRO ANTES DE ACTUALIZAR
            |--------------------------------------------------------------------------
            */
            $query = DB::table('recursos_humanos as rh')
                ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
                ->select(
                    'rh.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                );

            if (is_numeric($id)) {
                $query->where('rh.orden', $id);
            } else {
                $query->where('rh.uuid', $id);
            }

            $registroAnterior = $query->first();

            if (!$registroAnterior) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR REGISTRO
            |--------------------------------------------------------------------------
            */
            $updateQuery = DB::table('recursos_humanos');

            if (is_numeric($id)) {
                $updateQuery->where('orden', $id);
            } else {
                $updateQuery->where('uuid', $id);
            }

            $updateQuery->update([

                'predio_id' => (int) $request->predio_id,

                'grado_id' => (int) $request->grado_id,
                'nombres_apellidos' => $request->nombres_apellidos,
                'rut' => $request->rut,

                'cargo_contratado' => $request->cargo_contratado,
                'area_funciones' => $request->area_funciones,
                'funcion_actual' => $request->funcion_actual,

                'fecha_inicio_contrato' => $request->fecha_inicio_contrato,
                'anios_servicio' => $request->anios_servicio,

                'ultima_calificacion' => $request->ultima_calificacion,
                'capacitado_prevencion_riesgo' => (bool) $request->capacitado_prevencion_riesgo,

                'tipo_contrato_id' => (int) $request->tipo_contrato_id,

                'updated_at' => now()

            ]);

            /*
            |--------------------------------------------------------------------------
            | REGISTRO DESPUÉS DE ACTUALIZAR
            |--------------------------------------------------------------------------
            */
            $registroDespues = DB::table('recursos_humanos as rh')
                ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
                ->select(
                    'rh.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where('rh.orden', $registroAnterior->orden)
                ->first();

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */
            $this->auditar(
                modulo: 'Recursos Humanos',
                accion: 'ACTUALIZAR',
                tabla: 'recursos_humanos',
                registroId: $registroAnterior->orden,
                descripcion: 'Se actualizó un registro de recursos humanos.',
                antes: $registroAnterior,
                despues: $registroDespues
            );

            DB::commit();

            return response()->json([
                'message' => 'Registro de recursos humanos actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}