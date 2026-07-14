<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class Compra3UTMController extends Controller
{
    public function getListaCompra3UTM(Request $request)
    {
        $query = DB::table('compra_utm as c')
            ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
            ->leftJoin('estados as e', 'c.estado_id', '=', 'e.id')

            ->select(
                'c.*',
                'p.nombre as predio_nombre',
                'e.nombre as estado_nombre'
            )
            ->orderBy('c.orden', 'desc');
        return response()->json($query->get());
    }

    public function insert(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'predio_id'      => ['required', 'integer'],
            'proveedor'      => ['required', 'string', 'max:255'],
            'factura'        => ['required', 'string', 'max:100'],
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric'],
            'materia'        => ['required', 'string', 'max:255'],
            'estado_id'      => ['required', 'integer'],
            'doe_envio_ab5'  => ['required', 'string', 'max:255'],
            'observaciones'  => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            $id = DB::table('compra_utm')->insertGetId([
                'predio_id'      => $request->predio_id,
                'proveedor'      => $request->proveedor,
                'factura'        => $request->factura,
                'fecha'          => $request->fecha,
                'monto'          => $request->monto,
                'materia'        => $request->materia,
                'estado_id'      => $request->estado_id,
                'doe_envio_ab5'  => $request->doe_envio_ab5,
                'observaciones'  => $request->observaciones,
                'uuid'           => Str::uuid(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ], 'orden');

            DB::commit();

            return response()->json([
                'message' => 'Guardado correctamente',
                'id'      => $id
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al guardar',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarCompra3UTM($numeroOrden)
    {
        try {
            $deleted = DB::table('compra_utm')
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

            $registro = DB::table('compra_utm as c')
                ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
                ->leftJoin('estados as e', 'c.estado_id', '=', 'e.id')
                ->select(
                    'c.uuid',
                    'c.orden',
                    'c.predio_id',
                    'p.nombre as predio_nombre',
                    'c.proveedor',
                    'c.factura',
                    'c.fecha',
                    'c.monto',
                    'c.materia',
                    'c.estado_id',
                    'e.nombre as estado',
                    'c.doe_envio_ab5',
                    'c.observaciones',
                    'c.created_at',
                    'c.updated_at'
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
            'proveedor'        => ['required', 'string', 'max:255'],
            'factura'          => ['required', 'string', 'max:100'],
            'fecha'            => ['required', 'date'],
            'monto'            => ['required', 'numeric'],
            'materia'          => ['required', 'string', 'max:255'],
            'estado_id'        => ['required', 'integer'],
            'doe_envio_ab5'    => ['nullable', 'string', 'max:255'],
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

            $query = DB::table('compra_utm');

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

            $updateQuery = DB::table('compra_utm');
            if (is_numeric($id)) {
                $updateQuery->where('orden', $id);
            } else {
                $updateQuery->where('uuid', $id);
            }

            $updateQuery->update([
                'predio_id'        => (int) $request->predio_id,
                'proveedor'        => $request->proveedor,
                'factura'          => $request->factura,
                'fecha'            => $request->fecha,
                'monto'            => $request->monto,
                'materia'          => $request->materia,
                'estado_id'        => (int) $request->estado_id,
                'doe_envio_ab5'    => $request->doe_envio_ab5,
                'observaciones'    => $request->observaciones,
                'updated_at'       => now(),
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Compra 3 UTM actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}