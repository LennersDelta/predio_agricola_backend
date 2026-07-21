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

use App\Http\Controllers\Compra3UTMController;
use App\Http\Controllers\RendicionMensualController;

use App\Http\Controllers\BoletaHonorarioController;
use App\Http\Controllers\IngresosExtrasController;
use App\Http\Controllers\AnticipoRendirCuentaController;

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
    //Route::get('/documentos/{uuid}/ver', [PredioController::class, 'verDocumento']);
    //Route::get('/documentos/{uuid}/descargar', [PredioController::class, 'descargarDocumento']); 
   
    // COMPRA 3 UTM //
    Route::get('listaCompra3UTM', [Compra3UTMController::class, 'getListaCompra3UTM']);
    Route::post('compra3utm/insert', [Compra3UtmController::class, 'insert']);
    Route::delete('deleteCompra3UTM/{numeroOrden}', [Compra3UtmController::class, 'eliminarCompra3UTM']);    
    Route::get('/compra3utm/{uuid}', [Compra3UtmController::class,'show']);
    Route::post('/compra3utm/{uuid}', [Compra3UtmController::class, 'update']);

    // RENDICION MENSUAL //

    Route::get('listaRendicionMensual', [RendicionMensualController::class, 'getListaRendicionMensual']);
    Route::post('rendicionmensual/insert', [RendicionMensualController::class, 'insert']);
    Route::delete('deleteRendicionMensual/{numeroOrden}', [RendicionMensualController::class, 'eliminarRendicionMensual']);    
    Route::get('/rendicionmensual/{uuid}', [RendicionMensualController::class,'show']);
    Route::post('/rendicionmensual/{uuid}', [RendicionMensualController::class, 'update']);

    // BOLETA HONORARIO //
    Route::get('listaBoletaHonorario', [BoletaHonorarioController::class, 'getListaBoletaHonorario']);
    Route::post('boletahonorario/insert', [BoletaHonorarioController::class, 'insert']);
    Route::delete('deleteBoletaHonorario/{numeroOrden}', [BoletaHonorarioController::class, 'eliminarBoletaHonorario']);    
    Route::get('/boletahonorario/{uuid}', [BoletaHonorarioController::class,'show']);
    Route::post('/boletahonorario/{uuid}', [BoletaHonorarioController::class, 'update']);

    // INGRESOS EXTRAS //
    Route::get('listaIngresosExtras', [IngresosExtrasController::class, 'getListaIngresosExtras']);
    Route::post('ingresosextras/insert', [IngresosExtrasController::class, 'insert']);
    Route::delete('deleteIngresosExtras/{numeroOrden}', [IngresosExtrasController::class, 'eliminarIngresosExtras']);    
    Route::get('/ingresosextras/{uuid}', [IngresosExtrasController::class,'show']);
    Route::post('/ingresosextras/{uuid}', [IngresosExtrasController::class, 'update']);

    // ANTICIPO RENDIR CUENTAS //
    Route::get('listaAnticipoRendirCuenta', [AnticipoRendirCuentaController::class, 'getListaAnticipoRendirCuenta']);
    Route::post('anticiporendircuenta/insert', [AnticipoRendirCuentaController::class, 'insert']);
    Route::delete('deleteAnticipoRendirCuenta/{numeroOrden}', [AnticipoRendirCuentaController::class, 'eliminarAnticipoRendirCuenta']);    
    Route::get('/anticiporendircuenta/{uuid}', [AnticipoRendirCuentaController::class,'show']);
    Route::post('/anticiporendircuenta/{uuid}', [AnticipoRendirCuentaController::class, 'update']);

    });
    Route::get('/documentos/{uuid}/ver', [PredioController::class, 'verDocumento']);
    Route::get('/documentos/{uuid}/descargar', [PredioController::class, 'descargarDocumento']);







    Route::prefix('combustible')->group(function(){
        // ASIGNACIONES 
        Route::get( '/asignacion', [CombustibleAsignacionController::class, 'index'] ); 
        Route::post( '/asignacion', [CombustibleAsignacionController::class, 'store'] ); 
        Route::get( '/asignacion/disponibles', [CombustibleAsignacionController::class, 'disponibles'] ); 
        Route::get( '/asignacion/{id}/patentes', [CombustibleAsignacionController::class, 'patentes'] ); 
        // DETALLE
        Route::get('/asignacion/{id}/detalle',[CombustibleAsignacionController::class, 'detalle']);

        // ARCHIVOS
        Route::get( '/archivo/{id}',[CombustibleAsignacionController::class, 'verArchivo'])->name('combustible.archivo');

        // INGRESOS
        Route::get( '/', [IngresoCombustibleController::class, 'index'] ); 
        Route::post( '/ingreso', [IngresoCombustibleController::class, 'store'] );
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
