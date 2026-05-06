<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContratosEfectuadosController extends Controller
{
    public function getListaContratos(Request $request)
    {
        $query = DB::table('contratos as c')
            ->leftJoin('predio as p', 'c.predio_id', '=', 'p.id')
            ->leftJoin('estados as e', 'c.renta_id', '=', 'e.id')

            ->select(
                'c.*',
                'p.nombre as predio_nombre',
                'e.nombre as renta_nombre'
            )

            ->orderBy('c.orden', 'desc');

        return response()->json($query->get());
    }

}