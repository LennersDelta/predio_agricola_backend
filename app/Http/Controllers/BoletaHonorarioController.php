<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class BoletaHonorarioController extends BaseController
{
    public function getListaBoletaHonorario(Request $request)
    {
        try {

            $usuarioActual = auth()->user();

            // Validar usuario autenticado
            if (!$usuarioActual) {
                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $query = DB::table('boleta_honorario as c')
                ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
                ->select(
                    'c.*',
                    'p.nombre as predio_nombre'
                );

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
                // los registros de su predio
                $query->where(
                    'c.predio_id',
                    $usuarioActual->predio_id
                );
            }

            $boletaHonorario = $query
                ->orderBy('c.orden', 'desc')
                ->get();

            return response()->json($boletaHonorario);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error al cargar las boletas de honorarios.',
                'error' => $e->getMessage()
            ], 500);
        }
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
                'observaciones'    => 'nullable|string',
            ]);


            // ============================================================
            // VALIDACIÓN DE DUPLICADOS
            // ============================================================

            /*
            * NO SE VALIDA DUPLICADO POR PREDIO + MES
            *
            * Un mismo predio puede tener múltiples boletas de honorarios
            * durante el mismo mes.
            *
            * Ejemplo:
            *
            * Predio 2 - Enero
            *   - Boleta 001
            *   - Boleta 002
            *   - Boleta 003
            *
            * Por lo tanto, NO se debe utilizar:
            *
            * where('predio_id', $request->predio_id)
            * where('mes', $request->mes)
            *
            * para impedir nuevos registros.
            */


            DB::beginTransaction();

            try {

                // ========================================================
                // INSERTAR REGISTRO
                // ========================================================

                $id = DB::table('boleta_honorario')->insertGetId([
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
                ], 'orden');


                // ========================================================
                // RECUPERAR REGISTRO CREADO
                // ========================================================

                $registro = DB::table('boleta_honorario as b')
                    ->leftJoin('predio as p', 'b.predio_id', '=', 'p.id')
                    ->select(
                        'b.*',
                        'p.nombre as predio_nombre'
                    )
                    ->where('b.orden', $id)
                    ->first();

                if (!$registro) {
                    throw new Exception(
                        'No fue posible recuperar el registro creado.'
                    );
                }


                // ========================================================
                // AUDITORÍA
                // ========================================================

                $this->auditar(
                    modulo: 'Boleta Honorario',
                    accion: 'CREAR',
                    tabla: 'boleta_honorario',
                    registroId: $registro->orden,
                    descripcion: 'Se creó una boleta de honorario.',
                    despues: $registro
                );


                // ========================================================
                // COMMIT
                // ========================================================

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Boleta de honorario registrada correctamente.',
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
                'message' => 'Error al guardar la boleta de honorario.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarBoletaHonorario($numeroOrden)
    {
        DB::beginTransaction();
        try {
            // Obtener registro antes de eliminar con nombre del predio
            $registro = DB::table('boleta_honorario as b')
                ->leftJoin('predio as p', 'b.predio_id', '=', 'p.id')
                ->select(
                    'b.*',
                    'p.nombre as predio_nombre'
                )
                ->where('b.orden', $numeroOrden)
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ], 404);
            }

            // Registrar auditoría antes de eliminar
            $this->auditar(
                modulo: 'Boleta Honorario',
                accion: 'ELIMINAR',
                tabla: 'boleta_honorario',
                registroId: $registro->orden,
                descripcion: 'Se eliminó una boleta de honorario.',
                antes: $registro
            );

            // Eliminar registro
            $deleted = DB::table('boleta_honorario')
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

            // ============================================================
            // OBTENER REGISTRO ANTERIOR CON NOMBRE DEL PREDIO
            // ============================================================

            $query = DB::table('boleta_honorario as b')
                ->leftJoin('predio as p', 'b.predio_id', '=', 'p.id')
                ->select(
                    'b.*',
                    'p.nombre as predio_nombre'
                );

            if (is_numeric($id)) {
                $query->where('b.orden', $id);
            } else {
                $query->where('b.uuid', $id);
            }

            $registroAnterior = $query->first();

            if (!$registroAnterior) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }


            // ============================================================
            // VALIDACIÓN DE DUPLICADOS
            // ============================================================

            /*
            * NO SE VALIDA DUPLICADO POR PREDIO + MES
            *
            * Un mismo predio puede tener múltiples boletas de honorarios
            * durante el mismo mes.
            *
            * Ejemplo:
            *
            * Predio 2 - Enero
            *   - Boleta 001
            *   - Boleta 002
            *   - Boleta 003
            *
            * Por lo tanto, NO corresponde utilizar:
            *
            * where('predio_id', $request->predio_id)
            * where('mes', $request->mes)
            *
            * para determinar si una boleta está duplicada.
            */


            // ============================================================
            // ACTUALIZAR REGISTRO
            // ============================================================

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


            // ============================================================
            // OBTENER REGISTRO DESPUÉS DE ACTUALIZAR
            // ============================================================

            $registroDespues = DB::table('boleta_honorario as b')
                ->leftJoin('predio as p', 'b.predio_id', '=', 'p.id')
                ->select(
                    'b.*',
                    'p.nombre as predio_nombre'
                )
                ->where('b.orden', $registroAnterior->orden)
                ->first();


            // ============================================================
            // AUDITORÍA
            // ============================================================

            $this->auditar(
                modulo: 'Boleta Honorario',
                accion: 'ACTUALIZAR',
                tabla: 'boleta_honorario',
                registroId: $registroAnterior->orden,
                descripcion: 'Se actualizó una boleta de honorario.',
                antes: $registroAnterior,
                despues: $registroDespues
            );


            // ============================================================
            // COMMIT
            // ============================================================

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Boleta de honorario actualizada correctamente.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la boleta de honorario.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}