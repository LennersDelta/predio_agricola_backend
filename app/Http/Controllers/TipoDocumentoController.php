<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TipoDocumentoController extends Controller
{
    public function index()
    {
        $tipos = DB::table('tipo_documento')
                   ->orderBy('id')
                   ->get(['id', 'descripcion', 'label', 'icono']);

        return response()->json($tipos);
    }
}