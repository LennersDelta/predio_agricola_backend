<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IngresoCombustibleController extends BaseController
{
    public function index(): JsonResponse
    {
        try {
            $data = DB::table('ingreso_combustible as ic')
                ->join(
                    'combustible_asignacion as ca',
                    'ca.id',
                    '=',
                    'ic.asignacion_id'
                )
                ->join(
                    'predio as p',
                    'p.id',
                    '=',
                    'ca.predio_id'
                )
                ->select(
                    'ic.id',
                    'p.nombre as predio',
                    'ic.numero_factura',
                    'ca.mes',
                    'ic.monto',
                    'ic.proveedor',
                    'ic.estado_factura',
                    'ic.doe_respuesta',
                    'ic.litros',
                    'ic.comprobante',
                    'ic.created_at',
                    'ic.patente',
                )
                ->orderByDesc('ic.id')
                ->get();
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'asignacion_id'           => 'required|integer',
                'nroFactura'              => 'required|string|max:100',
                'proveedor'               => 'required|string|max:255',
                'estadoFactura'           => 'required|string|max:50',
                'doeRespuestaB5'          => 'required|string|max:100',
                'cantidadConsumoLitros'   => 'required|numeric|min:1',
                'monto'                   => 'required|numeric|min:1',
                'comprobante'             => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'patente'                 => 'required|string|max:50',
            ], [
                'monto.min'                   => 'El monto ingresado debe ser mayor a 0.',
                'monto.required'              => 'Debe ingresar un monto.',
                'monto.numeric'               => 'El monto debe ser numérico.',
                'cantidadConsumoLitros.min'   => 'La cantidad de litros debe ser mayor a 0.',
            ]);

            /*
            |--------------------------------------------------------------------------
            | VALIDAR ASIGNACIÓN
            |--------------------------------------------------------------------------
            */
            $asignacion = DB::table('combustible_asignacion')
                ->where('id', $validated['asignacion_id'])
                ->lockForUpdate()
                ->first();

            if (!$asignacion) {
                throw new \Exception('Asignación no encontrada');
            }

            if ($validated['monto'] > $asignacion->saldo) {
                throw new \Exception('Saldo insuficiente');
            }

            /*
            |--------------------------------------------------------------------------
            | GENERAR NOMBRE ARCHIVO
            |--------------------------------------------------------------------------
            */

            $archivo = $request->file('comprobante');

            $patente = preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                strtoupper($validated['patente'])
            );

            $fecha = now()->format('Ymd');

            $ultimoId = (DB::table('ingreso_combustible')->max('id') ?? 0) + 1;

            $correlativo = str_pad(
                $ultimoId,
                5,
                '0',
                STR_PAD_LEFT
            );

            $extension = $archivo->getClientOriginalExtension();

            $nombreArchivo =
                $fecha . '_' .
                $patente . '_' .
                $correlativo . '.' .
                $extension;

            $path = $archivo->storeAs(
                'combustible',
                $nombreArchivo,
                'public'
            );

            /*
            |--------------------------------------------------------------------------
            | INSERTAR INGRESO
            |--------------------------------------------------------------------------
            */

            $id = DB::table('ingreso_combustible')->insertGetId([
                'asignacion_id'   => $validated['asignacion_id'],
                'numero_factura'  => $validated['nroFactura'],
                'proveedor'       => $validated['proveedor'],
                'estado_factura'  => $validated['estadoFactura'],
                'doe_respuesta'   => $validated['doeRespuestaB5'],
                'litros'          => $validated['cantidadConsumoLitros'],
                'monto'           => $validated['monto'],
                'comprobante'     => $path,
                'patente'         => $validated['patente'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | OBTENER REGISTRO PARA AUDITORÍA
            |--------------------------------------------------------------------------
            */

            $registro = DB::table('ingreso_combustible as ic')
                ->leftJoin('combustible_asignacion as ca', 'ic.asignacion_id', '=', 'ca.id')
                ->leftJoin('predio as p', 'ca.predio_id', '=', 'p.id')
                ->select(
                    'ic.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where('ic.id', $id)
                ->first();

            if (!$registro) {
                throw new \Exception('No fue posible recuperar el registro creado.');
            }

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */

            $this->auditar(
                modulo: 'Ingreso Combustible',
                accion: 'CREAR',
                tabla: 'ingreso_combustible',
                registroId: $registro->id,
                descripcion: 'Se registró un ingreso de combustible.',
                despues: $registro,
                predioId: $registro->predio_id,
                predioNombre: $registro->predio_nombre
            );

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR SALDO
            |--------------------------------------------------------------------------
            */

            DB::table('combustible_asignacion')
                ->where('id', $validated['asignacion_id'])
                ->update([
                    'monto_utilizado' =>
                        $asignacion->monto_utilizado + $validated['monto'],

                    'saldo' =>
                        $asignacion->saldo - $validated['monto'],

                    'updated_at' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ingreso registrado correctamente',
                'archivo' => $nombreArchivo,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function eliminarIngresoCombustible($id)
    {
        DB::beginTransaction();
        try {
            /*
            |--------------------------------------------------------------------------
            | OBTENER INGRESO
            |--------------------------------------------------------------------------
            */
            $registro = DB::table('ingreso_combustible')
                ->where('id', $id)
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | OBTENER ASIGNACIÓN
            |--------------------------------------------------------------------------
            */

            $asignacion = DB::table('combustible_asignacion')
                ->where('id', $registro->asignacion_id)
                ->lockForUpdate()
                ->first();
            /*
            |--------------------------------------------------------------------------
            | DEVOLVER SALDO
            |--------------------------------------------------------------------------
            */

            if ($asignacion) {
                DB::table('combustible_asignacion')
                    ->where('id', $registro->asignacion_id)
                    ->update([
                        // Restamos nuevamente lo utilizado
                        'monto_utilizado' => $asignacion->monto_utilizado - $registro->monto,
                        // Devolvemos el saldo
                        'saldo' => $asignacion->saldo + $registro->monto,
                        'updated_at' => now()
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */

            $this->auditar(
                modulo: 'Ingreso Combustible',
                accion: 'ELIMINAR',
                tabla: 'ingreso_combustible',
                registroId: $registro->id,
                descripcion: 'Se eliminó un ingreso de combustible.',
                antes: $registro
            );

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR REGISTRO
            |--------------------------------------------------------------------------
            */

            DB::table('ingreso_combustible')
                ->where('id', $id)
                ->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente.'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}