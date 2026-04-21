<?php

namespace App\Http\Controllers;

use App\Models\FacturaLuz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class FacturaLuzController extends Controller
{
    public function index()
    {
        $facturas = FacturaLuz::with(['predio', 'estado'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($f) => [
                'id'          => $f->id,
                'predio_id'   => $f->predio_id,
                'predio'      => $f->predio?->nombre ?? '—',   // columna nombre de tabla predio
                'n_factura'   => $f->n_factura,
                'mes_consumo' => $f->mes_consumo,
                'valor'       => $f->valor,
                'proveedor'   => $f->proveedor,
                'doe'         => $f->doe,
                'consumo'     => $f->consumo,
                'estado_id'   => $f->estado_id,
                'estado'      => $f->estado?->nombre ?? '—',   // columna nombre de tabla estados
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
            'mesConsumo'           => ['required', 'string', 'min:1', 'max:10'],
            'valorTotal'           => ['required', 'numeric', 'digits_between:1,10'],
            'proveedor'            => ['required', 'string', 'min:1', 'max:100'],
            'estadoFactura'        => ['required', 'integer', 'exists:estados,id'],
            'doeRespuestaB5'       => ['required', 'string', 'min:1', 'max:50'],
            'cantidadConsumoKilos' => ['required', 'string', 'min:1', 'max:10'],
        ], [
            'predio.required'                    => 'Debe seleccionar un predio.',
            'predio.integer'                     => 'El predio seleccionado no es válido.',
            'predio.exists'                      => 'El predio seleccionado no existe.',

            'nroFactura.required'                => 'Debe ingresar número de factura.',
            'nroFactura.string'                  => 'El número de factura debe ser texto.',
            'nroFactura.min'                     => 'El número de factura no puede estar vacío.',
            'nroFactura.max'                     => 'El número de factura no puede superar 50 caracteres.',

            'mesConsumo.required'                => 'Debe ingresar mes de consumo.',
            'mesConsumo.string'                  => 'El mes de consumo debe ser texto.',
            'mesConsumo.min'                     => 'El mes de consumo no puede estar vacío.',
            'mesConsumo.max'                     => 'El mes de consumo no puede superar 10 caracteres.',

            'valorTotal.required'                => 'Debe ingresar valor total.',
            'valorTotal.numeric'                 => 'El valor total debe ser numérico.',
            'valorTotal.digits_between'          => 'El valor total debe tener entre 1 y 10 dígitos.',

            'proveedor.required'                 => 'Debe ingresar proveedor.',
            'proveedor.string'                   => 'El proveedor debe ser texto.',
            'proveedor.min'                      => 'El proveedor no puede estar vacío.',
            'proveedor.max'                      => 'El proveedor no puede superar 100 caracteres.',

            'estadoFactura.required'             => 'Debe seleccionar un estado.',
            'estadoFactura.integer'              => 'El estado seleccionado no es válido.',
            'estadoFactura.exists'               => 'El estado seleccionado no existe.',

            'doeRespuestaB5.required'            => 'Debe ingresar N° de DOE.',
            'doeRespuestaB5.string'              => 'El DOE debe ser texto.',
            'doeRespuestaB5.min'                 => 'El DOE no puede estar vacío.',
            'doeRespuestaB5.max'                 => 'El DOE no puede superar 50 caracteres.',

            'cantidadConsumoKilos.required'      => 'Debe ingresar cantidad de kilos.',
            'cantidadConsumoKilos.string'        => 'La cantidad de kilos debe ser texto.',
            'cantidadConsumoKilos.min'           => 'La cantidad de kilos no puede estar vacía.',
            'cantidadConsumoKilos.max'           => 'La cantidad de kilos no puede superar 10 caracteres.',
        ]);

        FacturaLuz::create([
            'predio_id' => $data['predio'],
            'n_factura' => $data['nroFactura'],
            'mes_consumo' => \Carbon\Carbon::createFromFormat('Y-m', $data['mesConsumo'])->startOfMonth()->toDateString(),
            'valor'       => $data['valorTotal'],
            'proveedor'   => $data['proveedor'],
            'estado_id'   => $data['estadoFactura'],
            'doe'         => $data['doeRespuestaB5'],
            'consumo'     => $data['cantidadConsumoKilos'],
            'user_id'     => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Factura de luz ingresada correctamente.',
            'data'    => [],
        ], 201);
    }

    public function destroy($id)
    {
        $factura = FacturaLuz::findOrFail($id);
        $factura->delete(); // ← solo setea deleted_at, no borra la fila

        return response()->json([
            'message' => 'Factura eliminada correctamente.',
        ]);
    }
}
