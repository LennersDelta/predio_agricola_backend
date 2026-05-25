<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IngresoCombustibleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {

            $validated = $request->validate([
                'asignacion_id' => 'required|integer',

                'orden' => 'required|string|max:100',
                'nroFactura' =>  'required|string|max:100',
                'proveedor'  =>  'required|string|max:255',
                'estadoFactura' =>'required|string|max:50',
                'doeRespuestaB5' =>'required|string|max:100',
                'cantidadConsumoLitros' =>'required|numeric|min:1',
                'monto' => 'required|numeric|min:1',
                'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $asignacion =
                DB::table('combustible_asignacion')
                ->where(
                    'id',
                    $validated['asignacion_id']
                )
                ->lockForUpdate()
                ->first();

            if (!$asignacion) {
                throw new \Exception(
                    'Asignación no encontrada'
                );
            }

            if (
                $validated['monto'] >
                $asignacion->saldo
            ) {

                throw new \Exception(
                    'Saldo insuficiente'
                );
            }

            $path = $request
                ->file('comprobante')
                ->store(
                    'combustible',
                    'public'
                );

            DB::table('ingreso_combustible')
                ->insert([
                    'asignacion_id' =>
                        $validated['asignacion_id'],

                    'orden' =>
                        $validated['orden'],

                    'numero_factura' =>
                        $validated['nroFactura'],

                    'proveedor' =>
                        $validated['proveedor'],

                    'estado_factura' =>
                        $validated['estadoFactura'],

                    'doe_respuesta' =>
                        $validated['doeRespuestaB5'],

                    'litros' =>
                        $validated['cantidadConsumoLitros'],

                    'monto' =>
                        $validated['monto'],

                    'comprobante' =>
                        $path,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('combustible_asignacion')
                ->where(
                    'id',
                    $validated['asignacion_id']
                )
                ->update([
                    'monto_utilizado' =>
                        $asignacion->monto_utilizado +
                        $validated['monto'],

                    'saldo' =>
                        $asignacion->saldo -
                        $validated['monto'],

                    'updated_at' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' =>
                    'Ingreso registrado correctamente',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}