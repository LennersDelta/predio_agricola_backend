<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReporteRegionController;

use App\Http\Controllers\EstadosController;
use App\Http\Controllers\FacturaAguaController;
use App\Http\Controllers\FacturaLuzController;
use App\Http\Controllers\InsumosServiciosController;
// ── Autenticado (cualquier rol) ───────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $r) => new UserResource($r->user()));
    Route::get('dashboard',         [DashboardController::class,      'index']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // PREDIO //

    Route::get('listaInsumosProductos', [InsumosServiciosController::class, 'getListaInsumosProductos']); // LISTO TODO LOS PREDIO INCLUIDO LOS FILTROS NECESARIOS
    Route::delete('deleteInsumosProductos/{numeroOrden}', [InsumosServiciosController::class, 'eliminarInsumosProductos']); // ELIMINO DE LA LISTA EL INSUMO Y PRODUCTOS PASANDO EL CODIGO ORDEN 
    Route::post('insumosproducto/insert', [InsumosServiciosController::class, 'insertar']); //INSERT DE INSUMOS Y PRODUCTOS
    Route::get('insumosproducto/{orden}', [InsumosServiciosController::class, 'show']);
    Route::put('insumosproducto/update/{orden}', [InsumosServiciosController::class, 'update']); //UPDATE DE INSUMOS Y PRODUCTOS
    // FACTURA LUZ
    Route::get('/factura/luz',         [FacturaLuzController::class, 'index']);
    Route::post('/factura/luz',        [FacturaLuzController::class, 'insert']);
    Route::delete('/factura/luz/{id}', [FacturaLuzController::class, 'destroy']);

    // FACTURA AGUA
    Route::get('/factura/agua',         [FacturaAguaController::class, 'index']);
    Route::post('/factura/agua',        [FacturaAguaController::class, 'insert']);
    Route::delete('/factura/agua/{id}', [FacturaAguaController::class, 'destroy']);




    /*Route::get('predio/parquevehicular', [ParqueVehicularController::class, 'index']); 
    Route::get('predio/recursoshumano', [RecursosHumanoController::class, 'index']);*/


    // COMBOX  SELECTOR //
    Route::get('estados/{tipo}', [EstadosController::class, 'getEstados']); // TIPO COMPRA - ESTADO O.C - ESTADO FACTURA
    Route::get('listaPredio', [EstadosController::class, 'getListaPredio']); // LISTA TODOS LOS PREDIOS QUE ESTA EN ESTADO ACTIVO.


    // Reportes
    // Route::get('reportes/regiones', [ReportesController::class, 'regiones']);
    // Route::get('reportes/comunas',  [ReportesController::class, 'comunas']);

});





// ── Solo administrador ────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {

    Route::apiResource('usuarios', UsuarioController::class);
});
