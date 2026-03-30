<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ConservadorController extends Controller
{
    public function index()
    {
        $tipos = DB::table('conservador')
                   ->orderBy('descripcion')
                   ->get(['id', 'descripcion']);

        return response()->json($tipos);
    }
}