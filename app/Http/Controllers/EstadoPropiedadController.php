<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadoPropiedadController extends Controller
{
    public function index()
    {
        $tipos = DB::table('estado_propiedad')
                   ->orderBy('descripcion')
                   ->get(['id', 'descripcion']);

        return response()->json($tipos);
    }
}