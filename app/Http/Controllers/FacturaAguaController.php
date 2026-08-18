<?php

namespace App\Http\Controllers;

use App\Models\FacturaAgua;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FacturaAguaController extends BaseController
{
    public function index()
    {
        $facturas = FacturaAgua::with(['predio', 'estado'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($f) => [
                'id'          => $f->id,
                'predio_id'   => $f->predio_id,
                'predio'      => $f->predio?->nombre ?? '—',
                'n_factura'   => $f->n_factura,
                'mes_consumo' => $f->mes_consumo,
                'valor'       => $f->valor,
                'proveedor'   => $f->proveedor,
                'doe'         => $f->doe,
                'consumo'     => $f->consumo,
                'estado_id'   => $f->estado_id,
                'estado'      => $f->estado?->nombre ?? '—',
                'uuid'        => $f->uuid,
                'created_at'  => $f->created_at,
            ]);

        return response()->json($facturas);
    }

    public function insert(Request $request)
    {
        $data = $request->validate([
            'predio'               => ['required', 'integer', 'exists:predio,id'],
            'nroFactura'           => ['required', 'string', 'min:1', 'max:50'],
            'mesConsumo'           => ['required', 'date_format:Y-m'],
            'valorTotal'           => ['required', 'numeric', 'digits_between:1,10'],
            'proveedor'            => ['required', 'string', 'min:1', 'max:100'],
            'estadoFactura'        => ['required', 'integer', 'exists:estados,id'],
            'doeRespuestaB5'       => ['required', 'string', 'min:1', 'max:50'],
            'cantidadConsumoKilos' => ['required', 'string', 'min:1', 'max:10'],
        ], [
            'predio.required'               => 'Debe seleccionar un predio.',
            'predio.exists'                 => 'El predio seleccionado no existe.',
            'nroFactura.required'           => 'Debe ingresar número de factura.',
            'nroFactura.max'                => 'El número de factura no puede superar 50 caracteres.',
            'mesConsumo.required'           => 'Debe ingresar mes de consumo.',
            'mesConsumo.date_format'        => 'El formato del mes debe ser YYYY-MM.',
            'valorTotal.required'           => 'Debe ingresar valor total.',
            'valorTotal.numeric'            => 'El valor total debe ser numérico.',
            'valorTotal.digits_between'     => 'El valor total debe tener entre 1 y 10 dígitos.',
            'proveedor.required'            => 'Debe ingresar proveedor.',
            'proveedor.max'                 => 'El proveedor no puede superar 100 caracteres.',
            'estadoFactura.required'        => 'Debe seleccionar un estado.',
            'estadoFactura.exists'          => 'El estado seleccionado no existe.',
            'doeRespuestaB5.required'       => 'Debe ingresar N° de DOE.',
            'doeRespuestaB5.max'            => 'El DOE no puede superar 50 caracteres.',
            'cantidadConsumoKilos.required' => 'Debe ingresar cantidad de kilos.',
            'cantidadConsumoKilos.max'      => 'La cantidad de kilos no puede superar 10 caracteres.',
        ]);

        DB::beginTransaction();

        try {

            $factura = FacturaAgua::create([
                'predio_id'   => $data['predio'],
                'n_factura'   => $data['nroFactura'],
                'mes_consumo' => \Carbon\Carbon::createFromFormat('Y-m', $data['mesConsumo'])
                    ->startOfMonth()
                    ->toDateString(),
                'valor'       => $data['valorTotal'],
                'proveedor'   => $data['proveedor'],
                'estado_id'   => $data['estadoFactura'],
                'doe'         => $data['doeRespuestaB5'],
                'consumo'     => $data['cantidadConsumoKilos'],
                'user_id'     => Auth::id(),
            ]);

            $registro = DB::table('factura_agua as f')
                ->leftJoin('predio as p', 'f.predio_id', '=', 'p.id')
                ->select(
                    'f.*',
                    'p.id as predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where('f.id', $factura->id)
                ->first();

            if (!$registro) {
                throw new Exception('No fue posible recuperar el registro creado.');
            }

            $this->auditar(
                modulo: 'Factura Agua',
                accion: 'CREAR',
                tabla: 'factura_agua',
                registroId: $registro->id,
                descripcion: 'Se creó una factura de agua.',
                despues: $registro,
                predioId: $registro->predio_id,
                predioNombre: $registro->predio_nombre
            );

            DB::commit();

            return response()->json([
                'message' => 'Factura de agua ingresada correctamente.',
                'data'    => [],
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error al guardar la factura de agua.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {

            $registro = DB::table('factura_agua as f')
                ->leftJoin('predio as p', 'f.predio_id', '=', 'p.id')
                ->select(
                    'f.id',
                    'f.predio_id',
                    'p.nombre as predio_nombre'
                )
                ->where('f.id', $id)
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura de agua no encontrada.'
                ], 404);
            }

            $this->auditar(
                modulo: 'Factura Agua',
                accion: 'ELIMINAR',
                tabla: 'factura_agua',
                registroId: $registro->id,
                descripcion: 'Se eliminó una factura de agua.',
                antes: $registro,
                predioId: $registro->predio_id,
                predioNombre: $registro->predio_nombre
            );

            $factura = FacturaAgua::findOrFail($id);
            $factura->delete(); // Soft Delete

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factura de agua eliminada correctamente.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la factura de agua.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($uuid)
    {
        try {

            $factura = DB::table('factura_agua as f')
                ->leftJoin('predio as p', 'p.id', '=', 'f.predio_id')
                ->leftJoin('estados as e', 'e.id', '=', 'f.estado_id')
                ->where('f.uuid', $uuid)
                ->select(
                    'f.id',
                    'f.predio_id',
                    'p.nombre as predio_nombre',

                    'f.n_factura',
                    'f.mes_consumo',
                    'f.valor',
                    'f.proveedor',

                    'f.uuid',
                    'f.doe',
                    'f.consumo',

                    'f.estado_id',
                    'e.nombre as estado_nombre',

                    'f.user_id',
                    'f.created_at',
                    'f.updated_at'
                )
                ->first();

            if (!$factura) {
                return response()->json([
                    'message' => 'Factura de agua no encontrada.'
                ], 404);
            }

            return response()->json([
                'data' => $factura
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error al obtener la factura de agua.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function update(Request $request, $uuid)
    {
        $validator = Validator::make($request->all(), [
            'predio_id'  => ['required', 'integer', 'exists:predio,id'],
            'n_factura'  => ['required', 'string', 'max:50'],
            'mes_consumo' => ['required', 'date'],
            'valor'      => ['required', 'numeric'],
            'proveedor'  => ['nullable', 'string', 'max:100'],
            'doe'        => ['nullable', 'string', 'max:100'],
            'consumo'    => ['nullable', 'string', 'max:10'],
            'estado_id'  => ['required', 'integer', 'exists:estados,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();

        try {

            // Obtener registro anterior con nombre del predio y estado
            $antes = DB::table('factura_agua as f')
                ->leftJoin('predio as p', 'f.predio_id', '=', 'p.id')
                ->leftJoin('estados as e', 'f.estado_id', '=', 'e.id')
                ->select(
                    'f.*',
                    'p.nombre as predio_nombre',
                    'e.nombre as estado_nombre'
                )
                ->where('f.uuid', $uuid)
                ->first();

            if (!$antes) {
                return response()->json([
                    'message' => 'Factura de agua no encontrada.'
                ], 404);
            }

            // Actualizar factura
            DB::table('factura_agua')
                ->where('uuid', $uuid)
                ->update([
                    'predio_id'   => (int) $request->predio_id,
                    'n_factura'   => $request->n_factura,
                    'mes_consumo' => $request->mes_consumo,
                    'valor'       => $request->valor,
                    'proveedor'   => $request->proveedor,
                    'doe'         => $request->doe,
                    'consumo'     => $request->consumo,
                    'estado_id'   => (int) $request->estado_id,
                    'updated_at'  => now(),
                ]);

            // Recuperar registro actualizado
            $despues = DB::table('factura_agua as f')
                ->leftJoin('predio as p', 'f.predio_id', '=', 'p.id')
                ->leftJoin('estados as e', 'f.estado_id', '=', 'e.id')
                ->select(
                    'f.*',
                    'p.nombre as predio_nombre',
                    'e.nombre as estado_nombre'
                )
                ->where('f.id', $antes->id)
                ->first();

            // Auditoría
            $this->auditar(
                modulo: 'Factura Agua',
                accion: 'ACTUALIZAR',
                tabla: 'factura_agua',
                registroId: $antes->id,
                descripcion: 'Se actualizó una factura de agua.',
                antes: $antes,
                despues: $despues
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factura de agua actualizada correctamente.'
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la factura de agua.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }    
}
