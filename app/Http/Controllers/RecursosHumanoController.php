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
                'rh.capacitado_prevencion_riesgo'
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
}