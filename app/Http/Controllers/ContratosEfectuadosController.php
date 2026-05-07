<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContratosEfectuadosController extends Controller
{
    public function getListaContratos(Request $request)
    {
        $query = DB::table('contratos as c')
            ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
            ->leftJoin('estados as e', 'c.renta_id', '=', 'e.id')

            ->select(
                'c.*',
                'p.nombre as predio_nombre',
                'e.nombre as renta_nombre'
            )

            ->orderBy('c.orden', 'desc');

        return response()->json($query->get());
    }    
    
    public function insertar(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'predio_id' => ['required', 'integer'],
            'contrato' => ['required', 'string', 'max:100'],
            'fecha' => ['required', 'date'],
            'empresa_persona' => ['required', 'string', 'max:150'],
            'rut' => ['required', 'string', 'max:20'],
            'valor_renta' => ['required', 'numeric'],
            'renta_id' => ['required', 'integer'],
            'fecha_vencimiento' => ['required', 'date'],
            'vigencia_contrato' => ['required', 'string', 'max:100'],
            'doe_respuesta_b5' => ['required', 'string', 'max:200'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $id = DB::table('contratos')->insertGetId([
                'predio_id' => $request->predio_id,
                'contrato' => $request->contrato,
                'fecha' => $request->fecha,
                'empresa_persona' => $request->empresa_persona,
                'rut' => $request->rut,
                'valor_renta' => $request->valor_renta,
                'renta_id' => $request->renta_id,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'vigencia_contrato' => $request->vigencia_contrato,
                'doe_respuesta_b5' => $request->doe_respuesta_b5,
                'observaciones' => $request->observaciones,
                'fecha_creacion' => now(),
            ], 'orden');

            DB::commit();
            return response()->json([
                'message' => 'Guardado correctamente',
                'id' => $id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarContratos($numeroOrden)
    {
        try {
            $deleted = DB::table('contratos')
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
            $registro = DB::table('contratos as c')
                ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
                ->leftJoin('estados as e', 'c.renta_id', '=', 'e.id')
                ->select(

                    'c.uuid',
                    'c.orden',
                    'c.predio_id',
                    'p.nombre as predio_nombre',
                    'c.contrato',
                    'c.fecha',
                    'c.empresa_persona',
                    'c.rut',
                    'c.valor_renta',
                    'c.renta_id',
                    'e.nombre as renta_nombre',
                    'c.fecha_vencimiento',
                    'c.vigencia_contrato',
                    'c.doe_respuesta_b5',
                    'c.observaciones',
                    'c.fecha_creacion',
                    'c.fecha_modificacion'
                )
                ->where('c.uuid', $uuid)
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


}