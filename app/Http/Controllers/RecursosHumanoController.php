<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogService;

class RecursosHumanoController extends Controller
{
    public function getListaRecursosHumanos(Request $request)
    {
         
        $query = DB::table('recursos_humanos as rh')
            ->leftJoin('predio as p', 'rh.predio_id', '=', 'p.id')
            ->leftJoin('grados as g', 'rh.grado_id', '=', 'g.id')

            ->select(
                'rh.orden',                
                'p.nombre as predio',
                'rh.nombres_apellidos',
                'rh.rut',
                'rh.tipo_contrato',
                'g.descripcion as grado',
                'rh.cargo_contratado',
                'rh.area_funciones as area',
                'rh.funcion_actual',
                'rh.fecha_inicio_contrato',
                'rh.anios_servicio',
                'rh.ultima_calificacion',
                'rh.capacitado_prevencion_riesgo'
            )

            ->orderBy('rh.orden', 'desc');

        return response()->json($query->get());
    }

}