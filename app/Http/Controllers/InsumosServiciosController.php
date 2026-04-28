<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
                'ip.*', // incluye uuid automáticamente
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

            $existe = DB::table('insumosproductos')
                ->where('orden', $numeroOrden)
                ->exists();

            if (!$existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe la orden '.$numeroOrden
                ],404);
            }

            $deleted = DB::table('insumosproductos')
                ->where('orden', $numeroOrden)
                ->delete();

            return response()->json([
                'success'=>true,
                'message'=>'Registros eliminados correctamente',
                'orden'=>$numeroOrden,
                'filas_eliminadas'=>$deleted
            ]);

        } catch(\Exception $e){

            return response()->json([
                'success'=>false,
                'message'=>'Error al eliminar registros',
                'error'=>$e->getMessage()
            ],500);

        }
    }


    public function insertar(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'predio' => ['required','integer'],

            'producto_servicio' => ['required','string','max:255'],
            'empresa' => ['required','string','max:255'],

            'fecha_cotizacion' => ['required','date'],
            'valor_cotizacion' => ['required','numeric','min:0'],

            'tipo_compra' => ['required','integer'],
            'etapa' => ['required','string','max:100'],

            'numero_orden' => ['required','string','max:100'],
            'estado_orden' => ['required','integer'],

            'fecha_orden' => ['required','date'],
            'valor_total' => ['required','numeric','min:0'],

            'numero_factura' => ['required','string','max:100'],
            'fecha_factura' => ['required','date'],

            'proveedor' => ['required','string','max:255'],
            'estado_factura' => ['required','integer'],

            'observaciones' => ['nullable','string'],

            'doerespuesta' => ['required','string','max:255'],
        ]);

        if($validator->fails()){
            return response()->json([
                'message'=>$validator->errors()->first(),
                'errors'=>$validator->errors()
            ],422);
        }

        DB::beginTransaction();

        try {

            $uuid = (string) Str::uuid();

            $id = DB::table('insumosproductos')->insertGetId([
                'uuid'              => $uuid,

                'predio'            => (int)$request->predio,
                'producto_servicio' => $request->producto_servicio,

                'empresa'           => $request->empresa,
                'fecha_cotizacion'  => $request->fecha_cotizacion,
                'valor_cotizacion'  => $request->valor_cotizacion,

                'tipo_compra'       => (int)$request->tipo_compra,
                'etapa'             => $request->etapa,

                'numero_orden'      => $request->numero_orden,
                'estado_orden'      => (int)$request->estado_orden,
                'fecha_orden'       => $request->fecha_orden,
                'valor_total'       => $request->valor_total,

                'numero_factura'    => $request->numero_factura,
                'fecha_factura'     => $request->fecha_factura,
                'proveedor'         => $request->proveedor,
                'estado_factura'    => (int)$request->estado_factura,

                'observaciones'     => $request->observaciones ?? null,
                'doerespuesta'      => $request->doerespuesta

            ],'orden');

            DB::commit();

            return response()->json([
                'message'=>'Registro guardado correctamente',
                'id'=>$id,
                'uuid'=>$uuid
            ],201);

        } catch(\Exception $e){

            DB::rollBack();

            return response()->json([
                'message'=>'Error al guardar',
                'error'=>$e->getMessage()
            ],500);

        }
    }


    // COMPATIBLE CON ORDEN O UUID
    public function show($id)
    {
        try {

            $query = DB::table('insumosproductos');

            if(is_numeric($id)){
                $registro = $query->where('orden',$id)->first();
            } else {
                $registro = $query->where('uuid',$id)->first();
            }

            if(!$registro){
                return response()->json([
                    'message'=>'Registro no encontrado'
                ],404);
            }

            return response()->json([
                'data'=>[
                    'id'=>$registro->orden,
                    'uuid'=>$registro->uuid,
                    'orden'=>$registro->orden,
                    'predio'=>$registro->predio,
                    'producto_servicio'=>$registro->producto_servicio,
                    'empresa'=>$registro->empresa,
                    'fecha_cotizacion'=>$registro->fecha_cotizacion,
                    'valor_cotizacion'=>$registro->valor_cotizacion,

                    'tipo_compra'=>$registro->tipo_compra,
                    'etapa'=>$registro->etapa,

                    'numero_orden'=>$registro->numero_orden,
                    'estado_orden'=>$registro->estado_orden,
                    'fecha_orden'=>$registro->fecha_orden,
                    'valor_total'=>$registro->valor_total,

                    'numero_factura'=>$registro->numero_factura,
                    'fecha_factura'=>$registro->fecha_factura,
                    'proveedor'=>$registro->proveedor,
                    'estado_factura'=>$registro->estado_factura,

                    'observaciones'=>$registro->observaciones,
                    'doerespuesta'=>$registro->doerespuesta
                ]
            ],200);

        } catch(\Exception $e){

            return response()->json([
                'message'=>'Error al obtener registro',
                'error'=>$e->getMessage()
            ],500);

        }
    }



    // UPDATE POR UUID O ORDEN
    public function update(Request $request,$id)
    {
        $validator = Validator::make($request->all(),[
            'predio' => ['required','integer'],

            'producto_servicio' => ['required','string','max:255'],
            'empresa' => ['required','string','max:255'],

            'fecha_cotizacion' => ['required','date'],
            'valor_cotizacion' => ['required','numeric','min:0'],

            'tipo_compra' => ['required','integer'],
            'etapa' => ['required','string','max:100'],

            'numero_orden' => ['required','string','max:100'],
            'estado_orden' => ['required','integer'],

            'fecha_orden' => ['required','date'],
            'valor_total' => ['required','numeric','min:0'],

            'numero_factura' => ['required','string','max:100'],
            'fecha_factura' => ['required','date'],

            'proveedor' => ['required','string','max:255'],
            'estado_factura' => ['required','integer'],

            'observaciones' => ['nullable','string'],

            'doerespuesta' => ['required','string','max:255'],
        ]);

        if($validator->fails()){
            return response()->json([
                'message'=>$validator->errors()->first()
            ],422);
        }

        DB::beginTransaction();

        try {

            $query = DB::table('insumosproductos');

            if(is_numeric($id)){
                $query->where('orden',$id);
            } else {
                $query->where('uuid',$id);
            }

            $existe = $query->first();

            if(!$existe){
                return response()->json([
                    'message'=>'Registro no encontrado'
                ],404);
            }

            $updateQuery = DB::table('insumosproductos');

            if(is_numeric($id)){
                $updateQuery->where('orden',$id);
            } else {
                $updateQuery->where('uuid',$id);
            }

            $updateQuery->update([

                'predio'=>(int)$request->predio,
                'producto_servicio'=>$request->producto_servicio,

                'empresa'=>$request->empresa,
                'fecha_cotizacion'=>$request->fecha_cotizacion,
                'valor_cotizacion'=>$request->valor_cotizacion,

                'tipo_compra'=>(int)$request->tipo_compra,
                'etapa'=>$request->etapa,

                'numero_orden'=>$request->numero_orden,
                'estado_orden'=>(int)$request->estado_orden,
                'fecha_orden'=>$request->fecha_orden,
                'valor_total'=>$request->valor_total,

                'numero_factura'=>$request->numero_factura,
                'fecha_factura'=>$request->fecha_factura,
                'proveedor'=>$request->proveedor,
                'estado_factura'=>(int)$request->estado_factura,

                'observaciones'=>$request->observaciones ?? null,
                'doerespuesta'=>$request->doerespuesta

            ]);

            DB::commit();

            return response()->json([
                'message'=>'Registro actualizado correctamente'
            ]);

        } catch(\Exception $e){

            DB::rollBack();

            return response()->json([
                'message'=>'Error al actualizar',
                'error'=>$e->getMessage()
            ],500);

        }
    }
}