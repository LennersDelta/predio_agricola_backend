<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class InsumosServiciosController extends Controller
{
    public function getListaInsumosProductos(Request $request)
    {
        $query = DB::table('insumosproductos as ip')
            ->leftJoin('predio as p', 'ip.predio', '=', 'p.id')

            ->leftJoin('estados as tc', function($join) {
                $join->on('ip.tipo_compra', '=', 'tc.id')
                    ->where('tc.tipo', 'tipoCompra');
            })

            ->leftJoin('estados as eo', function($join) {
                $join->on('ip.estado_orden', '=', 'eo.id')
                    ->where('eo.tipo', 'estadoOC');
            })

            ->leftJoin('estados as ef', function($join) {
                $join->on('ip.estado_factura', '=', 'ef.id')
                    ->where('ef.tipo', 'estadoFactura');
            })

            ->select(
                'ip.*',
                'p.nombre as predio_nombre',
                'tc.nombre as tipo_compra_nombre',
                'eo.nombre as estado_orden_nombre',
                'ef.nombre as estado_factura_nombre'
            )

            ->orderBy('ip.orden', 'desc');

        return response()->json($query->get());
    }

    public function eliminarInsumosProductos($numeroOrden)
    {
        try {

            // Verificar si existe la orden
            $existe = DB::table('insumosproductos') // <-- CAMBIA ESTO por el nombre real de tu tabla
                ->where('orden', $numeroOrden)
                ->exists();

            if (!$existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe la orden ' . $numeroOrden
                ], 404);
            }

            // Eliminar todos los registros asociados a esa orden
            $deleted = DB::table('insumosproductos')
                ->where('orden', $numeroOrden)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registros eliminados correctamente',
                'orden' => $numeroOrden,
                'filas_eliminadas' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registros',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function insertar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'predio'                => ['required', 'integer'],
            'producto_servicio'     => ['required', 'string', 'max:255'],

            'empresa'    => ['required', 'string', 'max:255'],
            'fecha_cotizacion'      => ['required', 'date'],
            'valor_cotizacion'      => ['required', 'numeric', 'min:0'],

            'tipo_compra'           => ['required', 'integer'],
            'etapa'          => ['required', 'string', 'max:100'],

            'numero_orden'   => ['required', 'string', 'max:100'],
            'estado_orden'   => ['required', 'integer'],
            'fecha_orden'    => ['required', 'date'],
            'valor_total'     => ['required', 'numeric', 'min:0'],

            'numero_factura'        => ['required', 'string', 'max:100'],
            'fecha_factura'         => ['required', 'date'],
            'proveedor'             => ['required', 'string', 'max:255'],
            'estado_factura'        => ['required', 'integer'],

            'observaciones'         => ['nullable', 'string'],
            'doerespuesta'          => ['required', 'string', 'max:255'],
        ], [
            'predio.required' => 'El predio es obligatorio.',
            'producto_servicio.required' => 'Debe indicar producto o servicio.',
            'empresa.required' => 'La empresa es obligatoria.',
            'fecha_cotizacion.required' => 'La fecha de cotización es obligatoria.',
            'valor_cotizacion.required' => 'El valor de cotización es obligatorio.',
            'tipo_compra.required' => 'Debe seleccionar tipo de compra.',
            'etapa.required' => 'Debe indicar la etapa de compra.',
            'numero_orden.required' => 'El número de orden de compra es obligatorio.',
            'estado_orden.required' => 'Debe seleccionar estado de la orden.',
            'fecha_orden.required' => 'La fecha de orden de compra es obligatoria.',
            'valor_total.required' => 'El valor total de la orden es obligatorio.',
            'numero_factura.required' => 'El número de factura es obligatorio.',
            'fecha_factura.required' => 'La fecha de factura es obligatoria.',
            'proveedor.required' => 'El proveedor es obligatorio.',
            'estado_factura.required' => 'Debe seleccionar estado de la factura.',
            'doerespuesta' => 'Debe indicar el doe respuesta.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $id = DB::table('insumosproductos')->insertGetId([
                'predio'            => (int) $request->predio,
                'producto_servicio' => $request->producto_servicio,
                'empresa'           => $request->empresa_cotizacion,
                'fecha_cotizacion'  => $request->fecha_cotizacion,
                'valor_cotizacion'  => $request->valor_cotizacion,
                'tipo_compra'       => (int) $request->tipo_compra,
                'etapa'             => $request->etapa_compra,
                'numero_orden'      => $request->numero_orden_compra,
                'estado_orden'      => (int) $request->estado_orden_compra,
                'fecha_orden'       => $request->fecha_orden_compra,
                'valor_total'       => $request->valor_total_orden,
                'numero_factura'    => $request->numero_factura,
                'fecha_factura'     => $request->fecha_factura,
                'proveedor'         => $request->proveedor,
                'estado_factura'    => (int) $request->estado_factura,
                'observaciones'     => $request->observaciones ?? null,
                'doerespuesta'      => $request->doerespuesta, 
            ], 'orden'); 

            DB::commit();

            return response()->json([
                'message' => 'Registro guardado correctamente',
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

    public function show($orden)
    {
        try {

            $registro = DB::table('insumosproductos')
                ->where('orden', $orden)
                ->first();

            if (!$registro) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }
            // nombres BD → frontend
            $data = [
                'id'                    => $registro->orden,
                'orden'                 => $registro->orden,
                'predio'                => $registro->predio,
                'producto_servicio'     => $registro->producto_servicio,
                'empresa'    => $registro->empresa,
                'fecha_cotizacion'      => $registro->fecha_cotizacion,
                'valor_cotizacion'      => $registro->valor_cotizacion,

                'tipo_compra'           => $registro->tipo_compra,
                'etapa'          => $registro->etapa,

                'numero_orden'   => $registro->numero_orden,
                'estado_orden'   => $registro->estado_orden,
                'fecha_orden'    => $registro->fecha_orden,
                'valor_total'     => $registro->valor_total,
                'numero_factura'        => $registro->numero_factura,
                'fecha_factura'         => $registro->fecha_factura,
                'proveedor'             => $registro->proveedor,
                'estado_factura'        => $registro->estado_factura,
                'observaciones'         => $registro->observaciones,
                'doerespuesta'          => $registro->doerespuesta,   
            ];

            return response()->json([
                'data' => $data
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error al obtener el registro',
                'error'   => $e->getMessage()
            ], 500);
        }
    }











    public function update(Request $request, $orden)
    {
        $validator = Validator::make($request->all(), [
            'predio'                => ['required', 'integer'],
            'producto_servicio'     => ['required', 'string', 'max:255'],

            'empresa'    => ['required', 'string', 'max:255'],
            'fecha_cotizacion'      => ['required', 'date'],
            'valor_cotizacion'      => ['required', 'numeric', 'min:0'],

            'tipo_compra'           => ['required', 'integer'],
            'etapa'          => ['required', 'string', 'max:100'],

            'numero_orden'   => ['required', 'string', 'max:100'],
            'estado_orden'   => ['required', 'integer'],
            'fecha_orden'    => ['required', 'date'],
            'valor_total'     => ['required', 'numeric', 'min:0'],

            'numero_factura'        => ['required', 'string', 'max:100'],
            'fecha_factura'         => ['required', 'date'],
            'proveedor'             => ['required', 'string', 'max:255'],
            'estado_factura'        => ['required', 'integer'],

            'observacion'           => ['nullable', 'string'],
            'doerespuesta'          => ['required', 'string', 'max:255'],
        ], [
            'predio.required' => 'El predio es obligatorio.',
            'producto_servicio.required' => 'Debe indicar producto o servicio.',
            'empresa.required' => 'La empresa es obligatoria.',
            'fecha_cotizacion.required' => 'La fecha de cotización es obligatoria.',
            'valor_cotizacion.required' => 'El valor de cotización es obligatorio.',
            'tipo_compra.required' => 'Debe seleccionar tipo de compra.',
            'etapa.required' => 'Debe indicar la etapa de compra.',
            'numero_orden.required' => 'El número de orden de compra es obligatorio.',
            'estado_orden.required' => 'Debe seleccionar estado de la orden.',
            'fecha_orden.required' => 'La fecha de orden de compra es obligatoria.',
            'valor_total.required' => 'El valor total de la orden es obligatorio.',
            'numero_factura.required' => 'El número de factura es obligatorio.',
            'fecha_factura.required' => 'La fecha de factura es obligatoria.',
            'proveedor.required' => 'El proveedor es obligatorio.',
            'estado_factura.required' => 'Debe seleccionar estado de la factura.',
            'doerespuesta' => 'Debe indicar doe de repuesta',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            // 🔹 Verificar que exista
            $existe = DB::table('insumosproductos')
                ->where('orden', $orden)
                ->first();

            if (!$existe) {
                return response()->json([
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // 🔹 UPDATE
            DB::table('insumosproductos')
                ->where('orden', $orden)
                ->update([
                    'predio'            => (int) $request->predio,
                    'producto_servicio' => $request->producto_servicio,
                    'empresa'           => $request->empresa_cotizacion,
                    'fecha_cotizacion'  => $request->fecha_cotizacion,
                    'valor_cotizacion'  => $request->valor_cotizacion,
                    'tipo_compra'       => (int) $request->tipo_compra,
                    'etapa'             => $request->etapa_compra,
                    'numero_orden'      => $request->numero_orden_compra,
                    'estado_orden'      => (int) $request->estado_orden_compra,
                    'fecha_orden'       => $request->fecha_orden_compra,
                    'valor_total'       => $request->valor_total_orden,
                    'numero_factura'    => $request->numero_factura,
                    'fecha_factura'     => $request->fecha_factura,
                    'proveedor'         => $request->proveedor,
                    'estado_factura'    => (int) $request->estado_factura,
                    'observaciones'     => $request->observacion ?? null,
                    'doerespuesta'      => $request->doerespuesta,
                ]);

            DB::commit();

            return response()->json([
                'message' => 'Registro actualizado correctamente'
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



}