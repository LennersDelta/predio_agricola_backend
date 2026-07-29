<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class RendicionMensualController extends BaseController
{
    public function getListaRendicionMensual(Request $request)
    {
        $query = DB::table('rendicion_mensual as c')
            ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
            ->select(
                'c.*',
                'p.nombre as predio_nombre'
            )
            ->orderBy('c.orden', 'desc');

        return response()->json($query->get());
    }

    public function insert(Request $request)
    {
        try {

            $request->validate([
                'predio_id'        => 'required|integer',
                'mes'              => 'required|string',
                'item'             => 'required|string|max:255',
                'total'            => 'required|numeric',
                'fecha'            => 'required|date',
                'doe_informa_ab5'  => 'required|string',
                'observaciones'    => 'required|string',
            ]);


            // Validar que no exista una rendición para el mismo predio y mes
            $existe = DB::table('rendicion_mensual')
                ->where('predio_id', $request->predio_id)
                ->where('mes', $request->mes)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una rendición mensual para el predio seleccionado en el mes indicado.'
                ], 422);
            }


            DB::beginTransaction();

            try {

                $id = DB::table('rendicion_mensual')->insertGetId([
                    'predio_id'        => $request->predio_id,
                    'mes'              => $request->mes,
                    'item'             => $request->item,
                    'total'            => $request->total,
                    'fecha'            => $request->fecha,
                    'doe_informa_ab5'  => $request->doe_informa_ab5,
                    'observaciones'    => $request->observaciones,
                    'uuid'             => Str::uuid(),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ], 'orden');

                // Recuperar registro creado con nombre del predio
                $registro = DB::table('rendicion_mensual as r')
                    ->leftJoin('predio as p', 'r.predio_id', '=', 'p.id')
                    ->select(
                        'r.*',
                        'p.nombre as predio_nombre'
                    )
                    ->where('r.orden', $id)
                    ->first();
                if (!$registro) {
                    throw new Exception('No fue posible recuperar el registro creado.');
                }

                // Auditoría
                $this->auditar(
                    modulo: 'Rendición Mensual',
                    accion: 'CREAR',
                    tabla: 'rendicion_mensual',
                    registroId: $registro->orden,
                    descripcion: 'Se creó una nueva rendición mensual.',
                    despues: $registro
                );
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Rendición mensual registrada correctamente.',
                    'id' => $id
                ], 201);
            } catch (\Exception $e) {

                DB::rollBack();

                throw $e;
            }


        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors()
            ], 422);


        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la rendición mensual.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarRendicionMensual($numeroOrden)
    {
        DB::beginTransaction();
        try {
            // Recuperar registro antes de eliminar
            $registro = DB::table('rendicion_mensual as r')
                ->leftJoin('predio as p', 'r.predio_id', '=', 'p.id')
                ->select(
                    'r.*',
                    'p.nombre as predio_nombre'
                )
                ->where('r.orden', $numeroOrden)
                ->first();
            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ], 404);
            }

            // Auditoría antes de eliminar
            $this->auditar(
                modulo: 'Rendición Mensual',
                accion: 'ELIMINAR',
                tabla: 'rendicion_mensual',
                registroId: $registro->orden,
                descripcion: 'Se eliminó una rendición mensual.',
                antes: $registro
            );
            // Eliminar registro
            $deleted = DB::table('rendicion_mensual')
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

            $registro = DB::table('rendicion_mensual as r')
                ->leftJoin('predio as p', 'r.predio_id', '=', 'p.id')
                ->select(
                    'r.uuid',
                    'r.orden',
                    'r.predio_id',
                    'p.nombre as predio_nombre',
                    'r.mes',
                    'r.item',
                    'r.total',
                    'r.fecha',
                    'r.doe_informa_ab5',
                    'r.observaciones',
                    'r.created_at',
                    'r.updated_at'
                )
                ->where('r.uuid', $uuid)
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
                'message' => 'Error al obtener el registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* UPDATE */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'predio_id'        => ['required', 'integer'],
            'mes'              => ['required', 'date'],
            'item'             => ['required', 'string'],
            'total'            => ['required', 'numeric'],
            'fecha'            => ['required', 'date'],
            'doe_informa_ab5'  => ['required', 'string'],
            'observaciones'    => ['nullable', 'string'],
            'uuid'             => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();

        try {

            // Obtener registro anterior con nombre del predio
            $query = DB::table('rendicion_mensual as r')
                ->leftJoin('predio as p', 'r.predio_id', '=', 'p.id')
                ->select(
                    'r.*',
                    'p.nombre as predio_nombre'
                );

            if (is_numeric($id)) {
                $query->where('r.orden', $id);
            } else {
                $query->where('r.uuid', $id);
            }

            $antes = $query->first();

            if (!$antes) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // Validar que no exista otra rendición con el mismo Predio + Mes
            $duplicado = DB::table('rendicion_mensual')
                ->where('predio_id', $request->predio_id)
                ->where('mes', $request->mes)
                ->when(is_numeric($id), function ($q) use ($id) {
                    $q->where('orden', '<>', $id);
                }, function ($q) use ($id) {
                    $q->where('uuid', '<>', $id);
                })
                ->exists();

            if ($duplicado) {
                return response()->json([
                    'message' => 'Ya existe una rendición mensual para el predio y mes seleccionados.'
                ], 422);
            }

            // Ejecutar actualización
            $updateQuery = DB::table('rendicion_mensual');

            if (is_numeric($id)) {
                $updateQuery->where('orden', $id);
            } else {
                $updateQuery->where('uuid', $id);
            }

            $updateQuery->update([
                'predio_id'       => (int) $request->predio_id,
                'mes'             => $request->mes,
                'item'            => $request->item,
                'total'           => $request->total,
                'fecha'           => $request->fecha,
                'doe_informa_ab5' => $request->doe_informa_ab5,
                'observaciones'   => $request->observaciones,
                'updated_at'      => now(),
            ]);

            // Recuperar registro actualizado
            $despues = DB::table('rendicion_mensual as r')
                ->leftJoin('predio as p', 'r.predio_id', '=', 'p.id')
                ->select(
                    'r.*',
                    'p.nombre as predio_nombre'
                )
                ->where('r.orden', $antes->orden)
                ->first();

            // Auditoría
            $this->auditar(
                modulo: 'Rendición Mensual',
                accion: 'ACTUALIZAR',
                tabla: 'rendicion_mensual',
                registroId: $antes->orden,
                descripcion: 'Se actualizó una rendición mensual.',
                antes: $antes,
                despues: $despues
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rendición mensual actualizada correctamente.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}