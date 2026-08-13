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

    /*public function getListaPredio()
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
    }*/
    public function getListaPredio()
    {
        try {

            $usuarioActual = auth()->user();
            $query = DB::table('predio')
                ->where('estado', true);

            /*
            * ADMINISTRADOR
            * Puede ver todos los predios activos.
            */
            if ($usuarioActual->hasRole('administrador')) {
                $query->orderBy('nombre');

            } else {
                /*
                * USUARIO NORMAL
                * Solamente puede recibir su propio predio.
                */
                $query->where('id', $usuarioActual->predio_id)
                    ->orderBy('nombre');
            }

            return response()->json(
                $query->get([
                    'id',
                    'nombre'
                ])
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al cargar los predios.',
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

    public function getListaTipoRol()
    {
        try{
            return response()->json(
                DB::table('roles')                
                ->orderBy('id')
                ->get(['id', 'name'])
            );
        }catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

}