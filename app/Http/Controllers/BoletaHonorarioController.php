<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class BoletaHonorarioController extends Controller
{
    public function getListaBoletaHonorario(Request $request)
    {
        $query = DB::table('boleta_honorario as c')
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
                'doe_informa_ab5'  => 'required|string|max:255',
                'boleta'           => 'required|string|max:255',
                'observaciones'    => 'required|string',
            ]);

            // Validar que no exista una boleta para el mismo predio y mes
            $existe = DB::table('boleta_honorario')
                ->where('predio_id', $request->predio_id)
                ->where('mes', $request->mes)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una boleta de honorario para el predio seleccionado en el mes indicado.'
                ], 422);
            }

            DB::table('boleta_honorario')->insert([
                'predio_id'        => $request->predio_id,
                'mes'              => $request->mes,
                'item'             => $request->item,
                'total'            => $request->total,
                'fecha'            => $request->fecha,
                'doe_informa_ab5'  => $request->doe_informa_ab5,
                'boleta'           => $request->boleta,
                'observaciones'    => $request->observaciones,
                'uuid'             => Str::uuid(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Boleta de honorario registrada correctamente.'
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
                'message' => 'Error al guardar la boleta de honorario.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarBoletaHonorario($numeroOrden)
    {
        try {
            $deleted = DB::table('boleta_honorario')
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

            $registro = DB::table('boleta_honorario as b')
                ->leftJoin('predio as p', 'b.predio_id', '=', 'p.id')
                ->select(
                    'b.uuid',
                    'b.orden',
                    'b.predio_id',
                    'p.nombre as predio_nombre',
                    'b.mes',
                    'b.item',
                    'b.total',
                    'b.fecha',
                    'b.doe_informa_ab5',
                    'b.boleta',
                    'b.observaciones',
                    'b.created_at',
                    'b.updated_at'
                )
                ->where('b.uuid', $uuid)
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
                'message' => 'Error al obtener la boleta de honorario',
                'error' => $e->getMessage()
            ], 500);
        }
    }    

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'predio_id'        => ['required', 'integer'],
            'mes'              => ['required', 'string'],
            'item'             => ['required', 'string'],
            'total'            => ['required', 'numeric'],
            'fecha'            => ['required', 'date'],
            'doe_informa_ab5'  => ['required', 'string'],
            'boleta'           => ['required', 'string'],
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

            $query = DB::table('boleta_honorario');

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

            // Validar que no exista otra boleta para el mismo Predio + Mes
            $duplicado = DB::table('boleta_honorario')
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
                    'message' => 'Ya existe una boleta de honorario para el predio y mes seleccionados.'
                ], 422);
            }

            $updateQuery = DB::table('boleta_honorario');

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
                'boleta'          => $request->boleta,
                'observaciones'   => $request->observaciones,
                'updated_at'      => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Boleta de honorario actualizada correctamente.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar la boleta de honorario.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}