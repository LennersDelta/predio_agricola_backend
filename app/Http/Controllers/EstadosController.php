<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadosController extends Controller
{
    public function getEstados($tipo)
    {
        try {
            return response()->json(
                DB::table('estados')
                    ->where('tipo', $tipo)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre'])
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getListaPredio()
    {
        try {
            return response()->json(
                DB::table('predio')
                    ->where('estado', true) 
                    ->orderBy('nombre')
                    ->get(['id', 'nombre'])
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getListaTipoVehiculos()
    {
        try{
            return response()->json(
                DB::table('tipo_vehiculo')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
            );
        }catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getListaTipoGrado()
    {
        try{
            return response()->json(
                DB::table('grados')                
                ->orderBy('id')
                ->get(['id', 'descripcion'])
            );
        }catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getListaTipoContrato()
    {
        try{
            return response()->json(
                DB::table('tipo_contrato')                
                ->orderBy('id')
                ->get(['id', 'nombre'])
            );
        }catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

}