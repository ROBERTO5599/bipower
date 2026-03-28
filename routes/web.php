<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResumenEjecutivoController;
use App\Http\Controllers\OperacionesCarteraController;
use App\Http\Controllers\VentasController;
use App\Http\Controllers\InventarioPisoController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\CreditosController;
use App\Http\Controllers\CertificadosController;
use App\Http\Controllers\GastosFinanzasController;
use App\Http\Controllers\BancosController;
use App\Http\Controllers\ColaboradoresController;
use App\Http\Controllers\MetasForecastController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create']);
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::get('/home', [HomeController::class, 'index'])->middleware(['auth'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/resumen-ejecutivo', [ResumenEjecutivoController::class, 'index'])->name('resumen-ejecutivo.index');
    Route::get('/resumen-ejecutivo/data', [ResumenEjecutivoController::class, 'data'])->name('resumen-ejecutivo.data');

    Route::get('/operaciones-cartera', [OperacionesCarteraController::class, 'index'])->name('operaciones-cartera.index');
    Route::get('/operaciones-cartera/data', [OperacionesCarteraController::class, 'data'])->name('operaciones-cartera.data');

    Route::get('/ventas', [VentasController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/data', [VentasController::class, 'data'])->name('ventas.data');

    Route::get('/inventario-piso', [InventarioPisoController::class, 'index'])->name('inventario-piso.index');
    Route::get('/inventario-piso/data', [InventarioPisoController::class, 'data'])->name('inventario-piso.data');

    Route::get('/clientes', [ClientesController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/data', [ClientesController::class, 'data'])->name('clientes.data');

    Route::get('/creditos', [CreditosController::class, 'index'])->name('creditos.index');
    Route::get('/creditos/data', [CreditosController::class, 'data'])->name('creditos.data');

    Route::get('/certificados', [CertificadosController::class, 'index'])->name('certificados.index');
    Route::get('/certificados/data', [CertificadosController::class, 'data'])->name('certificados.data');

    Route::get('/gastos-finanzas', [GastosFinanzasController::class, 'index'])->name('gastos-finanzas.index');
    Route::get('/gastos-finanzas/data', [GastosFinanzasController::class, 'data'])->name('gastos-finanzas.data');

    Route::get('/bancos', [BancosController::class, 'index'])->name('bancos.index');
    Route::get('/bancos/data', [BancosController::class, 'data'])->name('bancos.data');

    Route::get('/colaboradores', [ColaboradoresController::class, 'index'])->name('colaboradores.index');
    Route::get('/colaboradores/data', [ColaboradoresController::class, 'data'])->name('colaboradores.data');

    Route::get('/metas-forecast', [MetasForecastController::class, 'index'])->name('metas-forecast.index');
    Route::get('/metas-forecast/data', [MetasForecastController::class, 'data'])->name('metas-forecast.data');
});

require __DIR__.'/auth.php';