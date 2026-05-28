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
                'nroFactura' => 'required|string|max:100',
                'proveedor' => 'required|string|max:255',
                'estadoFactura' => 'required|string|max:50',
                'doeRespuestaB5' => 'required|string|max:100',
                'cantidadConsumoLitros' => 'required|numeric|min:1',
                'monto' => 'required|numeric|min:1',
                'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'patente' => 'required|string|max:50',
            ], [
                'monto.min' => 'El monto ingresado debe ser mayor a 0.',
                'monto.required' => 'Debe ingresar un monto.',
                'monto.numeric' => 'El monto debe ser numérico.',

                'cantidadConsumoLitros.min' => 'La cantidad de litros debe ser mayor a 0.',
            ]);

            $asignacion = DB::table('combustible_asignacion')
                ->where(
                    'id',
                    $validated['asignacion_id']
                )
                ->lockForUpdate()
                ->first();
            if (!$asignacion) {
                throw new \Exception('Asignación no encontrada');
            }
            if (
                $validated['monto'] >
                $asignacion->saldo
            ){
                throw new \Exception('Saldo insuficiente');
            }

            // GENERAR NOMBRE ARCHIVO          
            $archivo = $request->file('comprobante');
            // Limpiar patente
            $patente = preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                strtoupper($validated['patente'])
            );
            // Fecha actual
            $fecha = now()->format('Ymd');
            // Obtener último ID
            $ultimoId = DB::table('ingreso_combustible')
                ->max('id') + 1;
            // Correlativo
            $correlativo = str_pad(
                $ultimoId,
                5,
                '0',
                STR_PAD_LEFT
            );
            // Extensión archivo
            $extension = $archivo->getClientOriginalExtension();
            // Nombre final
            $nombreArchivo =
                $fecha . '_' .
                $patente . '_' .
                $correlativo . '.' .
                $extension;
            // Guardar archivo
            $path = $archivo->storeAs(
                'combustible',
                $nombreArchivo,
                'public'
            );

            // =========================
            // INSERTAR REGISTRO
            // =========================

            DB::table('ingreso_combustible')
                ->insert([
                    'asignacion_id' => $validated['asignacion_id'],
                    /*'orden' => $validated['orden'],*/
                    'numero_factura' => $validated['nroFactura'],
                    'proveedor' => $validated['proveedor'],
                    'estado_factura' => $validated['estadoFactura'],
                    'doe_respuesta' => $validated['doeRespuestaB5'],
                    'litros' => $validated['cantidadConsumoLitros'],
                    'monto' => $validated['monto'],
                    'comprobante' => $path,
                    'patente' => $validated['patente'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            // =========================
            // ACTUALIZAR SALDO
            // =========================

            DB::table('combustible_asignacion')
                ->where('id', $validated['asignacion_id'])
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


}