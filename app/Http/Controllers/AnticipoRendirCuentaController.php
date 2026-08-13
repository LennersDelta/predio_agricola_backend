<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Exception;

class AnticipoRendirCuentaController extends BaseController
{
    public function getListaAnticipoRendirCuenta(Request $request)
    {
        try {

            $usuarioActual = auth()->user();

            if (!$usuarioActual) {
                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $query = DB::table('anticipo_rendir_cuenta as c')
                ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
                ->select(
                    'c.*',
                    'p.nombre as predio_nombre'
                );

            // Administrador y Super Administrador pueden ver todos los registros
            if (!$usuarioActual->hasAnyRole([
                'administrador',
                'super_administrador'
            ])) {

                if (!$usuarioActual->predio_id) {
                    return response()->json([
                        'message' => 'El usuario no tiene un predio asociado.'
                    ], 403);
                }

                // Los demás usuarios solo ven registros de su predio
                $query->where(
                    'c.predio_id',
                    $usuarioActual->predio_id
                );
            }

            $registros = $query
                ->orderBy('c.orden', 'desc')
                ->get();

            return response()->json($registros);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error al cargar los anticipos a rendir cuenta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function insert(Request $request)
    {
        try {

            $request->validate([
                'predio_id'         => 'required|integer|exists:predio,id',
                'numero_cuenta'     => 'required|string|max:100',
                'nombre_cuenta'     => 'required|string|max:255',
                'monto'             => 'required|numeric|min:0',
                'compra'            => 'required|string|max:255',
                'fecha'             => 'required|date',
                'doe_respuesta_b5'  => 'nullable|string|max:255',
                'observaciones'     => 'nullable|string',
            ]);


            $id = DB::table('anticipo_rendir_cuenta')->insertGetId(
                [
                    'predio_id'        => $request->predio_id,
                    'numero_cuenta'    => $request->numero_cuenta,
                    'nombre_cuenta'    => $request->nombre_cuenta,
                    'monto'            => $request->monto,
                    'compra'           => $request->compra,
                    'fecha'            => $request->fecha,
                    'doe_respuesta_b5' => $request->doe_respuesta_b5,
                    'observaciones'    => $request->observaciones,
                    'uuid'             => Str::uuid(),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
                'orden'
            );


            $registro = DB::table('anticipo_rendir_cuenta')
                ->where('orden', $id)
                ->first();


            if (!$registro) {
                throw new Exception('No fue posible recuperar el registro creado.');
            }


            $this->auditar(
                modulo: 'Anticipo Rendir Cuenta',
                accion: 'CREAR',
                tabla: 'anticipo_rendir_cuenta',
                registroId: $registro->orden,
                descripcion: 'Se creó un anticipo a rendir cuenta.',
                despues: $registro
            );


            return response()->json([
                'success' => true,
                'message' => 'Anticipo a rendir cuenta registrado correctamente.'
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
                'message' => 'Error al guardar el anticipo a rendir cuenta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarAnticipoRendirCuenta($numeroOrden)
    {
        try {

            // Obtener registro antes de eliminar
            $registro = DB::table('anticipo_rendir_cuenta')
                ->where('orden', $numeroOrden)
                ->first();


            if (!$registro) {

                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ], 404);

            }


            // Registrar auditoría antes de eliminar
            $this->auditar(
                modulo: 'Anticipo Rendir Cuenta',
                accion: 'ELIMINAR',
                tabla: 'anticipo_rendir_cuenta',
                registroId: $registro->orden,
                descripcion: 'Eliminó un anticipo a rendir cuenta.',
                antes: $registro
            );


            // Eliminar registro
            DB::table('anticipo_rendir_cuenta')
                ->where('orden', $numeroOrden)
                ->delete();


            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ], 200);


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

            $registro = DB::table('anticipo_rendir_cuenta as a')
                ->leftJoin('predio as p', 'a.predio_id', '=', 'p.id')
                ->select(
                    'a.uuid',
                    'a.orden',
                    'a.predio_id',
                    'p.nombre as predio_nombre',
                    'a.numero_cuenta',
                    'a.nombre_cuenta',
                    'a.monto',
                    'a.compra',
                    'a.fecha',
                    'a.doe_respuesta_b5',
                    'a.observaciones',
                    'a.created_at',
                    'a.updated_at'
                )
                ->where('a.uuid', $uuid)
                ->first();


            if (!$registro) {

                return response()->json([
                    'ok' => false,
                    'message' => 'Anticipo a rendir cuenta no encontrado.'
                ], 404);

            }

            return response()->json([
                'ok' => true,
                'data' => $registro
            ]);


        } catch (\Throwable $e) {

            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener el anticipo a rendir cuenta.',
                'error' => $e->getMessage()
            ], 500);

        }
    }

    /* UPDATE */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'predio_id'        => ['required', 'integer', 'exists:predio,id'],
            'numero_cuenta'    => ['required', 'string', 'max:100'],
            'nombre_cuenta'    => ['required', 'string', 'max:255'],
            'monto'            => ['required', 'numeric', 'min:0'],
            'compra'           => ['required', 'string', 'max:255'],
            'fecha'            => ['required', 'date'],
            'doe_respuesta_b5' => ['nullable', 'string', 'max:255'],
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

            $query = DB::table('anticipo_rendir_cuenta');

            if (is_numeric($id)) {
                $query->where('orden', $id);
            } else {
                $query->where('uuid', $id);
            }
            // Registro antes de modificar
            $registroAnterior = $query->first();

            if (!$registroAnterior) {
                return response()->json([
                    'message' => 'Anticipo a rendir cuenta no encontrado.'
                ], 404);
            }

            // Actualizar
            $updateQuery = DB::table('anticipo_rendir_cuenta');

            if (is_numeric($id)) {
                $updateQuery->where('orden', $id);
            } else {
                $updateQuery->where('uuid', $id);
            }

            $updateQuery->update([
                'predio_id'        => (int) $request->predio_id,
                'numero_cuenta'    => $request->numero_cuenta,
                'nombre_cuenta'    => $request->nombre_cuenta,
                'monto'            => $request->monto,
                'compra'           => $request->compra,
                'fecha'            => $request->fecha,
                'doe_respuesta_b5' => $request->doe_respuesta_b5,
                'observaciones'    => $request->observaciones,
                'updated_at'       => now(),
            ]);

            // Registro actualizado
            $registroActualizado = DB::table('anticipo_rendir_cuenta')
                ->where('orden', $registroAnterior->orden)
                ->first();

            // Auditoría
            $this->auditar(
                modulo: 'Anticipo Rendir Cuenta',
                accion: 'EDITAR',
                tabla: 'anticipo_rendir_cuenta',
                registroId: $registroAnterior->orden,
                //nombreRegistro: $registroActualizado->nombre_cuenta,
                descripcion: 'Actualizó un anticipo a rendir cuenta.',
                antes: $registroAnterior,
                despues: $registroActualizado
            );

            DB::commit();

            return response()->json([
                'message' => 'Anticipo a rendir cuenta actualizado correctamente.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar el anticipo a rendir cuenta.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}