<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class IngresosExtrasController extends Controller
{
    public function getListaIngresosExtras(Request $request)
    {
        $query = DB::table('ingresos_extras as c')
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
                'predio_id'       => 'required|integer',
                'item_venta'      => 'required|string|max:255',
                'dte_resolucion'  => 'required|string|max:255',
                'valor_total'     => 'required|numeric',
                'fecha'           => 'required|date',
                'estado_pago'     => 'required|integer|in:0,1',
                'doe_informa_ab5' => 'required|string|max:255',
                'observaciones'   => 'nullable|string',
            ]);

            DB::table('ingresos_extras')->insert([
                'predio_id'       => $request->predio_id,
                'item_venta'      => $request->item_venta,
                'dte_resolucion'  => $request->dte_resolucion,
                'valor_total'     => $request->valor_total,
                'fecha'           => $request->fecha,
                'estado_pago'     => $request->estado_pago,
                'doe_informa_ab5' => $request->doe_informa_ab5,
                'observaciones'   => $request->observaciones,
                'uuid'            => Str::uuid(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ingreso extra registrado correctamente.'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el ingreso extra.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarIngresosExtras($numeroOrden)
    {
        try {
            $deleted = DB::table('ingresos_extras')
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

            $registro = DB::table('ingresos_extras as i')
                ->leftJoin('predio as p', 'i.predio_id', '=', 'p.id')
                ->select(
                    'i.uuid',
                    'i.orden',
                    'i.predio_id',
                    'p.nombre as predio_nombre',
                    'i.item_venta',
                    'i.dte_resolucion',
                    'i.valor_total',
                    'i.fecha',
                    'i.estado_pago',
                    'i.doe_informa_ab5',
                    'i.observaciones',
                    'i.created_at',
                    'i.updated_at'
                )
                ->where('i.uuid', $uuid)
                ->first();

            if (!$registro) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Ingreso extra no encontrado.'
                ], 404);
            }

            return response()->json([
                'ok' => true,
                'data' => $registro
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener el ingreso extra.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* UPDATE */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'predio_id'       => ['required', 'integer'],
            'item_venta'      => ['required', 'string', 'max:255'],
            'dte_resolucion'  => ['required', 'string', 'max:255'],
            'valor_total'     => ['required', 'numeric'],
            'fecha'           => ['required', 'date'],
            'estado_pago'     => ['required', 'integer', 'in:0,1'],
            'doe_informa_ab5' => ['required', 'string', 'max:255'],
            'observaciones'   => ['nullable', 'string'],
            'uuid'            => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();

        try {

            $query = DB::table('ingresos_extras');

            if (is_numeric($id)) {
                $query->where('orden', $id);
            } else {
                $query->where('uuid', $id);
            }

            $existe = $query->first();

            if (!$existe) {
                return response()->json([
                    'message' => 'Ingreso extra no encontrado.'
                ], 404);
            }

            $updateQuery = DB::table('ingresos_extras');

            if (is_numeric($id)) {
                $updateQuery->where('orden', $id);
            } else {
                $updateQuery->where('uuid', $id);
            }

            $updateQuery->update([
                'predio_id'       => (int) $request->predio_id,
                'item_venta'      => $request->item_venta,
                'dte_resolucion'  => $request->dte_resolucion,
                'valor_total'     => $request->valor_total,
                'fecha'           => $request->fecha,
                'estado_pago'     => $request->estado_pago,
                'doe_informa_ab5' => $request->doe_informa_ab5,
                'observaciones'   => $request->observaciones,
                'updated_at'      => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Ingreso extra actualizado correctamente.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar el ingreso extra.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}