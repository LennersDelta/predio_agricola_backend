<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\PredioAgricolaRaicesController;
use App\Http\Controllers\ComunaController;
use App\Http\Controllers\ConservadorController;
use App\Http\Controllers\EstadoPropiedadController;
use App\Http\Controllers\ProvinciaController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\TipoDocumentoController;
use App\Http\Controllers\TipoPropiedadController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\SubPropiedadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReporteRegionController;

// ── Autenticado (cualquier rol) ───────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $r) => new UserResource($r->user()));
    Route::get('dashboard',         [DashboardController::class,      'index']);
    Route::get('reportes/regiones',  [ReporteRegionController::class,  'index']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Selectores
    Route::get('tipo-propiedad',         [TipoPropiedadController::class,  'index']);
    Route::get('estado-propiedad',       [EstadoPropiedadController::class,'index']);
    Route::get('regiones',               [RegionController::class,         'index']);
    Route::get('provincias/{region_id}', [ProvinciaController::class,      'index']);
    Route::get('comunas/{provincia_id}', [ComunaController::class,         'index']);
    Route::get('tipo-documento',         [TipoDocumentoController::class,  'index']);
    Route::get('conservador',            [ConservadorController::class,    'index']);
    Route::get('administrador',          fn() => response()->json(DB::table('administrador')->orderBy('descripcion')->get()));
    Route::get('uso',                    fn() => response()->json(DB::table('uso')->orderBy('descripcion')->get()));


    // PREDIO //

    Route::get('predio/insumosproductos', [InsumosServiciosController::class, 'index']);
    Route::get('predio/parquevehicular', [ParqueVehicularController::class, 'index']); 
    Route::get('predio/recursoshumano', [RecursosHumanoController::class, 'index']);

    // Reportes
    // Route::get('reportes/regiones', [ReportesController::class, 'regiones']);
    // Route::get('reportes/comunas',  [ReportesController::class, 'comunas']);

});

// ── Solo administrador ────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {

    Route::apiResource('usuarios', UsuarioController::class);

});