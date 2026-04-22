<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReporteRegionController;

use App\Http\Controllers\EstadosController;
use App\Http\Controllers\InsumosServiciosController;
use App\Http\Controllers\ParqueVehicularController;

// ── Autenticado (cualquier rol) ───────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $r) => new UserResource($r->user()));
    Route::get('dashboard',         [DashboardController::class,      'index']);
    Route::get('reportes/regiones',  [ReporteRegionController::class,  'index']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Selectores

    Route::get('tipo-documento',         [TipoDocumentoController::class,  'index']);
    Route::get('administrador',          fn() => response()->json(DB::table('administrador')->orderBy('descripcion')->get()));
    Route::get('uso',                    fn() => response()->json(DB::table('uso')->orderBy('descripcion')->get()));


    // PREDIO //

    Route::get('listaInsumosProductos', [InsumosServiciosController::class, 'getListaInsumosProductos']); // LISTO TODO LOS PREDIO INCLUIDO LOS FILTROS NECESARIOS
    Route::delete('deleteInsumosProductos/{numeroOrden}', [InsumosServiciosController::class, 'eliminarInsumosProductos']);// ELIMINO DE LA LISTA EL INSUMO Y PRODUCTOS PASANDO EL CODIGO ORDEN 
    Route::post('insumosproducto/insert', [InsumosServiciosController::class, 'insertar']); //INSERT DE INSUMOS Y PRODUCTOS
    Route::get('insumosproducto/{orden}', [InsumosServiciosController::class, 'show']);
    Route::put('insumosproducto/update/{orden}', [InsumosServiciosController::class, 'update']); //UPDATE DE INSUMOS Y PRODUCTOS
    

    // PARQUE VEHICULAR //
    Route::get('listaParqueVehicular', [ParqueVehicularController::class, 'getListaParqueVehicular']); // LISTO TODO LOS PREDIO INCLUIDO LOS FILTROS NECESARIOS
    Route::post('parquevehicular/insert', [ParqueVehicularController::class, 'insertar']); //INSERT DE INSUMOS Y PRODUCTOS


 
   // COMBOX  SELECTOR //
    Route::get('estados/{tipo}', [EstadosController::class, 'getEstados']); // TIPO COMPRA - ESTADO O.C - ESTADO FACTURA
    Route::get('listaPredio', [EstadosController::class, 'getListaPredio']); // LISTA TODOS LOS PREDIOS QUE ESTA EN ESTADO ACTIVO.
    Route::get('listaTipoVehiculos', [EstadosController::class, 'getListaTipoVehiculos']); // LISTO TODOS LOS VEHICULOS ACTIVO


    // Reportes
    // Route::get('reportes/regiones', [ReportesController::class, 'regiones']);
    // Route::get('reportes/comunas',  [ReportesController::class, 'comunas']);

});



   

// ── Solo administrador ────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {

    Route::apiResource('usuarios', UsuarioController::class);
   

});