<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CombustibleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR ASIGNACIONES
    |--------------------------------------------------------------------------
    */

    public function asignaciones(): JsonResponse
    {
        $items = DB::table('combustible_asignacion as ca')
            ->join(
                'predio as p',
                'p.id',
                '=',
                'ca.predio_id'
            )
            ->select(
                'ca.id',
                'ca.mes',
                'ca.saldo',
                'p.nombre as predio_nombre'
            )
            ->where('ca.saldo', '>', 0)
            ->orderByDesc('ca.id')
            ->get();

        return response()->json($items);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {

            $user = auth()->user();

            /*
            |--------------------------------------------------------------------------
            | ADMINISTRADOR
            |--------------------------------------------------------------------------
            */

            if ($user->is_admin) {

                $validated = $request->validate([
                    'predio'      => 'required|integer',
                    'mesConsumo'  => 'required|date',
                    'valorTotal'  => 'required|numeric|min:1',
                ]);

                $existe = DB::table(
                    'combustible_asignacion'
                )
                    ->where(
                        'predio_id',
                        $validated['predio']
                    )
                    ->where(
                        'mes',
                        $validated['mesConsumo']
                    )
                    ->exists();

                if ($existe) {
                    throw new \Exception(
                        'Ya existe una asignación para este predio y mes.'
                    );
                }

                $monto = (float)
                    $validated['valorTotal'];

                $id = DB::table(
                    'combustible_asignacion'
                )
                    ->insertGetId([
                        'predio_id' =>
                            $validated['predio'],

                        'mes' =>
                            $validated['mesConsumo'],

                        'monto_asignado' =>
                            $monto,

                        'monto_utilizado' =>
                            0,

                        'saldo' =>
                            $monto,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' =>
                        'Asignación creada correctamente.',
                    'id' => $id,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | USUARIO NORMAL
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([
                'asignacionId' =>
                    'required|integer',

                'orden' =>
                    'required|string|max:100',

                'nroFactura' =>
                    'required|string|max:100',

                'proveedor' =>
                    'required|string|max:255',

                'estadoFactura' =>
                    'required|string|max:50',

                'doeRespuestaB5' =>
                    'required|string|max:100',

                'cantidadConsumoLitros' =>
                    'required|numeric|min:1',

                'monto' =>
                    'required|numeric|min:1',

                'comprobante' =>
                    'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            /*
            |--------------------------------------------------------------------------
            | BUSCAR ASIGNACION
            |--------------------------------------------------------------------------
            */

            $asignacion = DB::table(
                'combustible_asignacion'
            )
                ->where(
                    'id',
                    $validated['asignacionId']
                )
                ->lockForUpdate()
                ->first();

            if (!$asignacion) {
                throw new \Exception(
                    'Asignación no encontrada.'
                );
            }

            $monto = (float)
                $validated['monto'];

            /*
            |--------------------------------------------------------------------------
            | VALIDAR SALDO
            |--------------------------------------------------------------------------
            */

            if (
                $monto >
                $asignacion->saldo
            ) {
                throw new \Exception(
                    'El monto excede el saldo disponible.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SUBIR ARCHIVO
            |--------------------------------------------------------------------------
            */

            $path = $request
                ->file('comprobante')
                ->store(
                    'combustible/comprobantes',
                    'public'
                );

            /*
            |--------------------------------------------------------------------------
            | INSERT INGRESO
            |--------------------------------------------------------------------------
            */

            DB::table(
                'ingreso_combustible'
            )
                ->insert([
                    'asignacion_id' =>  $validated['asignacionId'],
                    'orden' =>          $validated['orden'],
                    'numero_factura' => $validated['nroFactura'],
                    'proveedor' =>      $validated['proveedor'],
                    'estado_factura' => $validated['estadoFactura'],
                    'doe_respuesta'  => $validated['doeRespuestaB5'],
                    'litros' =>         $validated['cantidadConsumoLitros'],
                    'monto' =>          $monto,
                    'comprobante' =>    $path,
                    'created_at' =>     now(),
                    'updated_at' =>     now(),
                ]);

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR ASIGNACION
            |--------------------------------------------------------------------------
            */

            DB::table(
                'combustible_asignacion'
            )
                ->where(
                    'id',
                    $validated['asignacionId']
                )
                ->update([
                    'monto_utilizado' =>
                        $asignacion->monto_utilizado + $monto,

                    'saldo' =>
                        $asignacion->saldo - $monto,

                    'updated_at' =>
                        now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' =>
                    'Ingreso registrado correctamente.',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    $e->getMessage(),
            ], 500);
        }
    }
}