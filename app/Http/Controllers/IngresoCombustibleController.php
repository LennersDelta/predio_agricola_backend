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

            $usuarioActual = auth()->user();

            // Validar usuario autenticado
            if (!$usuarioActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $query = DB::table('ingreso_combustible as ic')
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
                    'ca.predio_id',
                    'p.nombre as predio',
                    'ic.numero_factura',
                     DB::raw("TO_CHAR(ca.mes, 'YYYY-MM') as mes"),                    
                    'ic.monto',
                    'ic.proveedor',
                    'ic.estado_factura',
                    'ic.doe_respuesta',
                    'ic.litros',
                    'ic.comprobante',
                    'ic.created_at',
                    'ic.patente'
                );

            /*
            |--------------------------------------------------------------------------
            | ADMINISTRADOR / SUPER ADMINISTRADOR
            |--------------------------------------------------------------------------
            |
            | Ambos pueden visualizar los ingresos de todos los predios.
            |
            */

            if (
                !$usuarioActual->hasRole('administrador') &&
                !$usuarioActual->hasRole('super_administrador')
            ) {

                /*
                |--------------------------------------------------------------------------
                | USUARIO NORMAL
                |--------------------------------------------------------------------------
                */

                if (!$usuarioActual->predio_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario no tiene un predio asociado.'
                    ], 403);
                }

                // Solo ingresos correspondientes al predio del usuario
                $query->where(
                    'ca.predio_id',
                    $usuarioActual->predio_id
                );
            }

            $data = $query
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

            /* USUARIO AUTENTICADO */

            $usuarioActual = auth()->user();

            if (!$usuarioActual) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*  SOLO SUPERVISOR */

            if (!$usuarioActual->hasRole('supervisor')) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el supervisor puede registrar ingresos de combustible.'
                ], 403);
            }


            /* VALIDAR PREDIO DEL USUARIO */

            if (!$usuarioActual->predio_id) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El usuario no tiene un predio asociado.'
                ], 403);
            }


            /* VALIDACIÓN DEL FORMULARIO */

            $validated = $request->validate([

                'asignacion_id' =>              'required|integer',
                'nroFactura' =>                 'required|string|max:100',
                'proveedor' =>                  'required|string|max:255',
                'estadoFactura' =>              'required|string|max:50',
                'doeRespuestaB5' =>             'required|string|max:100',
                'cantidadConsumoLitros' =>      'required|numeric|min:0.01',
                'monto' =>                      'required|numeric|min:0.01',
                'comprobante' =>                'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'patente' =>                    'required|string|max:50',

            ], [
                'asignacion_id.required' =>           'Debe seleccionar una asignación.',
                'asignacion_id.integer' =>            'La asignación seleccionada no es válida.',
                'nroFactura.required' =>              'Debe ingresar un número de factura.',
                'proveedor.required' =>               'Debe ingresar el proveedor.',
                'estadoFactura.required' =>           'Debe seleccionar el estado de la factura.',
                'doeRespuestaB5.required' =>          'Debe ingresar el DOE de respuesta B.5.',
                'cantidadConsumoLitros.required' =>   'Debe ingresar la cantidad de litros.',
                'cantidadConsumoLitros.numeric' =>    'La cantidad de litros debe ser numérica.',
                'cantidadConsumoLitros.min' =>        'La cantidad de litros debe ser mayor a 0.',
                'monto.required' =>                   'Debe ingresar un monto.',
                'monto.numeric' =>                    'El monto debe ser numérico.',
                'monto.min' =>                        'El monto ingresado debe ser mayor a 0.',
                'comprobante.required' =>             'Debe adjuntar un comprobante.',
                'comprobante.mimes' =>                'El comprobante debe ser PDF, JPG, JPEG o PNG.',
                'patente.required' =>                 'Debe seleccionar una patente.',
            ]);


            /* OBTENER ASIGNACIÓN
            | lockForUpdate evita que dos operaciones simultáneas
            | consuman el mismo saldo.
            ====================================================== */

            $asignacion = DB::table('combustible_asignacion')
                ->where(
                    'id',
                    $validated['asignacion_id']
                )
                ->lockForUpdate()
                ->first();

            if (!$asignacion) {
                throw new \Exception(
                    'La asignación seleccionada no existe.'
                );
            }


            /* VALIDAR QUE LA ASIGNACIÓN PERTENEZCA AL PREDIO */

            if (
                (int) $asignacion->predio_id !==
                (int) $usuarioActual->predio_id
            ) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'La asignación seleccionada no pertenece al predio del usuario.'
                ], 403);
            }


            /*  VALIDAR SALDO */

            $monto = (float) $validated['monto'];
            $saldoActual = (float) $asignacion->saldo;
            if ($monto > $saldoActual) {

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El monto ingresado supera el saldo disponible.',
                    'saldo_disponible' => $saldoActual,
                    'monto_solicitado' => $monto
                ], 422);
            }


            /*  NORMALIZAR PPU */

            $ppu = strtoupper(
                trim($validated['patente'])
            );


            /* VALIDAR PPU CONTRA EL PREDIO */

            $patenteExiste = DB::table('parque_vehicular')
                ->where('ppu', $ppu)
                ->where(
                    'predio',
                    $asignacion->predio_id
                )
                ->exists();

            if (!$patenteExiste) {
                throw new \Exception(
                    'La PPU seleccionada no pertenece al predio de la asignación.'
                );
            }


            /* GENERAR NOMBRE ARCHIVO */

            $archivo = $request->file('comprobante');
            $fecha = now()->format('Ymd');
            $ultimoId =
                (DB::table('ingreso_combustible')->max('id') ?? 0)
                + 1;
            $correlativo = str_pad(
                $ultimoId,
                5,
                '0',
                STR_PAD_LEFT
            );
            $extension = $archivo->getClientOriginalExtension();
            $ppuArchivo = preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $ppu
            );

            $nombreArchivo =
                $fecha . '_' .
                $ppuArchivo . '_' .
                $correlativo . '.' .
                $extension;

            $path = $archivo->storeAs(
                'combustible',
                $nombreArchivo,
                'public'
            );


            /* INSERTAR INGRESO */

            $id = DB::table('ingreso_combustible')
                ->insertGetId([

                    'asignacion_id' =>       $asignacion->id,
                    'numero_factura' =>      $validated['nroFactura'],
                    'proveedor' =>           $validated['proveedor'],
                    'estado_factura' =>      $validated['estadoFactura'],
                    'doe_respuesta' =>       $validated['doeRespuestaB5'],
                    'litros' =>              $validated['cantidadConsumoLitros'],
                    'monto' =>               $monto,
                    'comprobante' =>         $path,
                    'patente' =>             $ppu,
                    'created_at' =>          now(),
                    'updated_at' =>          now(),
                ]);


            /* CALCULAR NUEVOS VALORES */

            $montoUtilizadoActual = (float) $asignacion->monto_utilizado;
            $nuevoMontoUtilizado = round($montoUtilizadoActual + $monto, 2);
            $nuevoSaldo = round($saldoActual - $monto,  2);

            /* ACTUALIZAR ASIGNACIÓN */

            DB::table('combustible_asignacion')
                ->where(
                    'id',
                    $asignacion->id
                )
                ->update([
                    'monto_utilizado' =>       $nuevoMontoUtilizado,
                    'saldo' =>                 $nuevoSaldo,
                    'updated_at' =>            now(),
                ]);


            /*  OBTENER REGISTRO PARA AUDITORÍA */

            $registro = DB::table(
                'ingreso_combustible as ic'
            )
                ->leftJoin(
                    'combustible_asignacion as ca',
                    'ic.asignacion_id',
                    '=',
                    'ca.id'
                )
                ->leftJoin(
                    'predio as p',
                    'ca.predio_id',
                    '=',
                    'p.id'
                )
                ->select(
                    'ic.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where(
                    'ic.id',
                    $id
                )
                ->first();


            if (!$registro) {

                throw new \Exception(
                    'No fue posible recuperar el registro creado.'
                );
            }

            /* AUDITORÍA */

            $this->auditar(
                modulo: 'Ingreso Combustible',
                accion: 'CREAR',
                tabla: 'ingreso_combustible',
                registroId: $registro->id,
                descripcion:'Se registró un ingreso de combustible.',
                despues: $registro,
                predioId: $registro->predio_id,
                predioNombre: $registro->predio_nombre
            );

            DB::commit();
            return response()->json([

                'success' => true,
                'message' => 'Ingreso registrado correctamente.',
                'archivo' =>  $nombreArchivo,
                'data' => [

                    'id' =>              $id,
                    'asignacion_id' =>   $asignacion->id,
                    'predio_id' =>       $asignacion->predio_id,
                    'monto' =>           $monto,
                    'monto_utilizado' => $nuevoMontoUtilizado,
                    'saldo' =>           $nuevoSaldo,
                    'patente' =>         $ppu,
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
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