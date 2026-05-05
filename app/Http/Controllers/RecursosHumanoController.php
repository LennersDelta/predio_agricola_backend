<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class RecursosHumanoController extends Controller
{
    public function getListaRecursosHumanos(Request $request)
    {
         
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
            )

            ->orderBy('rh.orden', 'desc');
        return response()->json($query->get());
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
                'capacitado_prevencion_riesgo' => $request->capacitadoPrevencionRiesgo === 'si',
            ], 'orden');

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
        try {
            $deleted = DB::table('recursos_humanos')
                ->where('orden', $numeroOrden)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Registro eliminado correctamente'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ], 404);
            }

        } catch (\Exception $e) {
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

            /*if (!$uuid || $uuid === 'undefined') {
                return response()->json([
                    'ok' => false,
                    'message' => 'UUID inválido'
                ], 400);
            }*/

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

            $query = DB::table('recursos_humanos');

            if (is_numeric($id)) {
                $query->where('orden', $id);
            } else {
                $query->where('uuid', $id);
            }

            $existe = $query->first();

            if (!$existe) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }

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
            ]);

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