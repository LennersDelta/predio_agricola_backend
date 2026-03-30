<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    public function index()
    {
        $tipos = DB::table('regiones')
                   ->orderBy('descripcion')
                   ->get(['id', 'descripcion']);

        return response()->json($tipos);
    }
}