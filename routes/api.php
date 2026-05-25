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
use App\Http\Controllers\ParqueVehicularController;
use App\Http\Controllers\TipoDocumentoController;
use App\Http\Controllers\PredioController;
use App\Http\Controllers\RecursosHumanoController;
use App\Http\Controllers\ContratosEfectuadosController;

use App\Http\Controllers\ConfiguracionController;

use App\Http\Controllers\CombustibleAsignacionController;
use App\Http\Controllers\IngresoCombustibleController;


// ── Autenticado (cualquier rol) ───────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $r) => new UserResource($r->user()));
    Route::get('dashboard',         [DashboardController::class,      'index']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    //Route::post('/login',  [AuthenticatedSessionController::class, 'index']);
    //Route::get('tipo-documento',         [TipoDocumentoController::class,  'index']);


    // DASHBOARD //
    Route::get('dashboard',         [DashboardController::class,      'index']);
    Route::get('/dashboard/predio/{id}', [DashboardController::class, 'vehiculosPorPredio']);
    Route::get('/dashboard/recursoshumanos/{id}', [DashboardController::class, 'recursosHumanosPorPredio']);
    Route::get('/dashboard/insumosproductos', [DashboardController::class, 'insumosProductos']);

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

    // PARQUE VEHICULAR //
    Route::get('listaParqueVehicular', [ParqueVehicularController::class, 'getListaParqueVehicular']); // LISTO TODO LOS PREDIO INCLUIDO LOS FILTROS NECESARIOS
    Route::post('parquevehicular/insert', [ParqueVehicularController::class, 'insertar']); //INSERT DE INSUMOS Y PRODUCTOS
    Route::delete('deleteParqueVehicular/{numeroOrden}', [ParqueVehicularController::class, 'eliminarParqueVehicular']); 
    Route::get('/parquevehicular/{uuid}', [ParqueVehicularController::class,'show']);
    Route::post('/parquevehicular/{uuid}', [ParqueVehicularController::class, 'update']);

    // RECURSOS HUMANOS //
    Route::get('listaRecursosHumanos', [RecursosHumanoController::class, 'getListaRecursosHumanos']); // LISTO TODOS EL PERSONAL QUE ESTA REGISTRADO COMO TRABAJADOR
    Route::post('recursoshumanos/insert', [RecursosHumanoController::class, 'insertar']); //INSERT DE RECURSOS HUMANOS
    Route::delete('deleteRecursosHumanos/{numeroOrden}', [RecursosHumanoController::class, 'eliminarRecursosHumanos']); 
    Route::get('/recursoshumanos/{uuid}', [RecursosHumanoController::class,'show']);
    Route::post('/recursoshumanos/{uuid}', [RecursosHumanoController::class, 'update']);


    // CONTRATOS EFECTUADOS //
    Route::get('listaContratosEfectuados', [ContratosEfectuadosController::class, 'getListaContratos']);// LISTO TODOS LOS CONTRATOS
    Route::post('contratos/insert', [ContratosEfectuadosController::class, 'insertar']); //INSERT CONTRATOS
    Route::delete('deleteContratos/{numeroOrden}', [ContratosEfectuadosController::class, 'eliminarContratos']); 
    Route::get('/contratos/{uuid}', [ContratosEfectuadosController::class,'show']);
    Route::post('/contratos/{uuid}', [ContratosEfectuadosController::class, 'update']);



    // COMBOX  SELECTOR //
    Route::get('estados/{tipo}', [EstadosController::class, 'getEstados']); // TIPO COMPRA - ESTADO O.C - ESTADO FACTURA
    Route::get('listaPredio', [EstadosController::class, 'getListaPredio']); // LISTA TODOS LOS PREDIOS QUE ESTA EN ESTADO ACTIVO.
    Route::get('listaTipoVehiculos', [EstadosController::class, 'getListaTipoVehiculos']); // LISTO TODOS LOS VEHICULOS ACTIVO
    Route::get('listaTipoGrado', [EstadosController::class,'getListaTipoGrado']); // LISTO TODOS LOS GRADOS Y TAMBIEN INCLUYO UNO ADICIONAL QUE ("NO APLICA") 
    Route::get('listaTipoContrato', [EstadosController::class,'getListaTipoContrato']); // LISTO TODOS LOS TIPOS CONTRATOS
    Route::get('listaTipoRol', [EstadosController::class,'getListaTipoRol']);

    // DOCUMENTOS - PREDIO //
    Route::delete('/documentos/{uuid}', [PredioController::class, 'eliminarDocumento']);
    Route::get('/documentos/{uuid}/ver', [PredioController::class, 'verDocumento']);
    Route::get('/documentos/{uuid}/descargar', [PredioController::class, 'descargarDocumento']);

    // Reportes
    // Route::get('reportes/regiones', [ReportesController::class, 'regiones']);
    // Route::get('reportes/comunas',  [ReportesController::class, 'comunas']);
 
    // COMBUSTIBLE
    
    //Route::get('/combustible/asignaciones',[CombustibleController::class, 'asignaciones']);
    //Route::post('/combustible', [CombustibleController::class, 'store']);
    


    });

Route::prefix('combustible')->group(function(){
    Route::post('/asignacion', [CombustibleAsignacionController::class, 'store']);
    Route::get('/asignacion/disponibles', [CombustibleAsignacionController::class, 'disponibles']);
    Route::post('/ingreso', [IngresoCombustibleController::class, 'store']);
});



Route::prefix('configuracion')->group(function () {
    Route::get('/{tabla}', [ConfiguracionController::class, 'index']);
    Route::post('/{tabla}', [ConfiguracionController::class, 'store']);
    Route::put('/{tabla}/{id}', [ConfiguracionController::class, 'update']);
    Route::delete('/{tabla}/{id}', [ConfiguracionController::class, 'destroy']);
    Route::patch('/{tabla}/{id}/reactivar', [ConfiguracionController::class, 'reactivar']);
    Route::get('/estados/tipos', [ConfiguracionController::class, 'tiposEstado']);
});


// ── Solo administrador ────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {

    Route::apiResource('usuarios', UsuarioController::class);
});
