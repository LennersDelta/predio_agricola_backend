<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TipoPropiedadController extends Controller
{
    public function index()
    {
        $tipos = DB::table('tipo_propiedad')
                   ->orderBy('descripcion')
                   ->get(['id', 'descripcion']);

        return response()->json($tipos);
    }
}