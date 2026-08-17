<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class IngresosExtrasController extends BaseController
{

    public function getListaIngresosExtras(Request $request)
    {
        try {

            $usuarioActual = auth()->user();

            // Validar usuario autenticado
            if (!$usuarioActual) {
                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $query = DB::table('ingresos_extras as c')
                ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
                ->select(
                    'c.*',
                    'p.nombre as predio_nombre'
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

                // Usuario normal: solo registros de su predio
                $query->where(
                    'c.predio_id',
                    $usuarioActual->predio_id
                );
            }

            $ingresosExtras = $query
                ->orderBy('c.orden', 'desc')
                ->get();

            return response()->json($ingresosExtras);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al cargar los ingresos extras.',
                'error' => $e->getMessage()
            ], 500);
        }
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

            DB::beginTransaction();
            try {
                $id = DB::table('ingresos_extras')->insertGetId([

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

                ], 'orden');

                // Recuperar registro creado con nombre del predio
                $registro = DB::table('ingresos_extras as i')
                    ->leftJoin('predio as p', 'i.predio_id', '=', 'p.id')
                    ->select(
                        'i.*',
                        'p.nombre as predio_nombre'
                    )
                    ->where('i.orden', $id)
                    ->first();

                if (!$registro) {
                    throw new Exception('No fue posible recuperar el registro creado.');
                }

                // Auditoría
                $this->auditar(
                    modulo: 'Ingresos Extras',
                    accion: 'CREAR',
                    tabla: 'ingresos_extras',
                    registroId: $registro->orden,
                    descripcion: 'Se creó un ingreso extra.',
                    despues: $registro
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso extra registrado correctamente.',
                    'id'      => $id
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

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
        DB::beginTransaction();

        try {
            // Recuperar registro antes de eliminar con nombre del predio
            $registro = DB::table('ingresos_extras as i')
                ->leftJoin('predio as p', 'i.predio_id', '=', 'p.id')
                ->select(
                    'i.*',
                    'p.nombre as predio_nombre'
                )
                ->where('i.orden', $numeroOrden)
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ], 404);
            }

            // Auditoría antes de eliminar
            $this->auditar(
                modulo: 'Ingresos Extras',
                accion: 'ELIMINAR',
                tabla: 'ingresos_extras',
                registroId: $registro->orden,
                descripcion: 'Se eliminó un ingreso extra.',
                antes: $registro
            );

            // Eliminar registro
            $deleted = DB::table('ingresos_extras')
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
            // Obtener registro anterior con nombre del predio
            $query = DB::table('ingresos_extras as i')
                ->leftJoin('predio as p', 'i.predio_id', '=', 'p.id')
                ->select(
                    'i.*',
                    'p.nombre as predio_nombre'
                );

            if (is_numeric($id)) {
                $query->where('i.orden', $id);
            } else {
                $query->where('i.uuid', $id);
            }

            $antes = $query->first();

            if (!$antes) {
                return response()->json([
                    'message' => 'Ingreso extra no encontrado.'
                ], 404);
            }

            // Actualizar registro
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

            // Obtener registro actualizado
            $despues = DB::table('ingresos_extras as i')
                ->leftJoin('predio as p', 'i.predio_id', '=', 'p.id')
                ->select(
                    'i.*',
                    'p.nombre as predio_nombre'
                )
                ->where('i.orden', $antes->orden)
                ->first();

            // Auditoría
            $this->auditar(
                modulo: 'Ingresos Extras',
                accion: 'ACTUALIZAR',
                tabla: 'ingresos_extras',
                registroId: $antes->orden,
                descripcion: 'Se actualizó un ingreso extra.',
                antes: $antes,
                despues: $despues
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ingreso extra actualizado correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el ingreso extra.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}