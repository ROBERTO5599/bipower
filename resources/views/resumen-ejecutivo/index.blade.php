@extends('employees.layouts.main')

@section('title', 'Resumen Ejecutivo')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
        .icon-shape {
            width: 4rem;
            height: 4rem;
            display: flex;
            align-items: center;
            
            justify-content: center;
            font-size: 1.5rem;
            border-radius: 50%;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.1); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.1); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.1); }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.1); }
        .bg-light-secondary { background-color: rgba(108, 117, 125, 0.1); }

        .table-responsive { overflow-x: auto; }

        /* Spinner */
        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }
        
        /* Tooltips personalizados */
        .metric-tooltip {
            position: relative;
            display: inline-block;
            border-bottom: 1px dotted #6c757d;
            cursor: help;
        }
        
        /* Progress bar */
        .progress-sm {
            height: 0.5rem;
        }
        
        /* Semáforos para cartera */
        .badge-vigente {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .badge-vencida {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .cartera-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .cartera-card.vigente {
            border-left-color: #28a745;
        }
        .cartera-card.vencida {
            border-left-color: #dc3545;
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold">Calculando métricas globales...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Flujo de Efectivo Global</h4>
            <p class="text-muted">Vista rápida del desempeño global de Valora Más</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tarjetas KPI Principales (Financieros) -->
    <div class="row mb-4 justify-content-center">
        <!-- Ingresos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-ingresos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de ingresos...">Ingresos Totales</span>
                        </h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ingresos">$ 0.00</h2>
                    <span class="text-muted small">Total de ingresos del período</span>
                </div>
            </div>
        </div>

        <!-- Gastos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-egresos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de egresos...">Egresos Totales</span>
                        </h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-gastos">$ 0.00</h2>
                    <span class="text-muted small">Total egresos registrados</span>
                </div>
            </div>
        </div>

        <!-- Utilidad Neta (Flujo Operativo) -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" title="Ingresos totales menos gastos operativos">Flujo Operativo</span>
                        </h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold mb-0" id="kpi-utilidad">$ 0.00</h2>
                    <span class="badge mt-2" id="kpi-margen">0% Margen</span>
                    <span class="text-muted small">Ingresos totales - Gastos operativos</span>
                </div>
            </div>
        </div>

    </div>

    <!-- NUEVA FILA: Utilidad Bruta y Operativa -->
    <div class="row mb-4">
        <!-- Utilidad Bruta -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-utilidad-bruta" title="Utilidad de Ventas + Intereses + Utilidad de Crédito Liquidado + Ventas de Certificados">Utilidad Bruta</span>
                        </h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-utilidad-bruta">$ 0.00</h2>
                    <div class="d-flex align-items-center mt-2">
                        <span class="badge bg-primary me-2" id="kpi-margen-bruto">0%</span>
                        <small class="text-muted">Margen Bruto</small>
                    </div>
                        <span class="text-muted small">Intereses + Utilidad de venta + Utilidad de credito + Certificados</span>
                    <small class="text-muted d-block mt-1"></small>
                </div>
            </div>
        </div>

        <!-- Utilidad Operativa -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" title="Utilidad Bruta - Gastos Operativos (EBIT)">Utilidad Operativa <small class="text-lowercase">(consolidado)</small></span>
                        </h6>
                        <div class="icon-shape bg-light-warning text-warning">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold mb-0" id="kpi-utilidad-operativa">$ 0.00</h2>
                    <span class="text-muted small">Utilidad Bruta − Gastos Operativos</span>
                </div>
            </div>
        </div>

        <!-- Flujo Operación (Salida) -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-flujo" title="Total de salidas operativas">Flujo Operación</span>
                        </h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-box-arrow-right"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-flujo-operacion">$ 0.00</h2>
                    <span class="text-muted small">Considerada salida</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Costo de Ventas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 bg-light rounded-3">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <small class="text-muted fw-bold">Costo de Ventas:</small>
                            <h5 class="mb-0 fw-bold" id="kpi-costo-ventas">$ 0.00</h5>
                        </div>
                        <div class="col-md-9">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" role="progressbar" id="progress-costo-ventas" style="width: 0%"></div>
                            </div>
                            <small class="text-muted" id="progress-costo-ventas-label">0% del costo sobre ingresos totales</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cartera de Empeño -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="row h-100">
                <div class="col-12 mb-3">
                    <h5 class="fw-bold text-dark">Monto de Cartera de Empeño</h5>
                </div>
                <!-- Cartera Vigente -->
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3 cartera-card vigente">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Cartera Vigente</h6>
                                <span class="badge badge-vigente"><i class="bi bi-check-circle me-1"></i> Vigente</span>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="cartera-vigente">$ 0.00</h2>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Monto correspondiente a préstamos al corriente</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Cartera Vencida -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3 cartera-card vencida">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Cartera Vencida</h6>
                                <span class="badge badge-vencida"><i class="bi bi-exclamation-triangle me-1"></i> Vencida</span>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="cartera-vencida">$ 0.00</h2>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-semibold text-danger" id="tasa-mora">Tasa de mora: 0.0%</span>                               
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 d-flex flex-column justify-content-end">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-3">Composición de Cartera</h6>
                    <div style="height: 150px; width: 100%;">
                        <canvas id="carteraChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <span class="text-muted small">Total Cartera:</span>
                        <h4 class="fw-bold mb-0 text-dark" id="cartera-total">$ 0.00</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Indicadores Financieros (Balance y Flujo) -->
    <div class="row mb-4">
        <!-- Balance General -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Resumen Balance General</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Activo Total:</span>
                        <span class="fw-bold text-dark" id="kpi-activo-total">$ 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Pasivo Total:</span>
                        <span class="fw-bold text-dark" id="kpi-pasivo-total">$ 0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted fw-bold">Capital Total:</span>
                        <span class="fw-bold text-primary display-6 fs-4" id="kpi-capital-total">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flujo de Efectivo -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Flujo de Efectivo</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Efectivo Inicial:</span>
                        <span class="fw-bold text-dark" id="kpi-efectivo-inicial">$ 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Flujo Neto:</span>
                        <span class="fw-bold text-success" id="kpi-flujo-neto">$ 0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted fw-bold">Efectivo Final:</span>
                        <span class="fw-bold text-primary display-6 fs-4" id="kpi-efectivo-final">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y Desglose -->
    <div class="row mb-4">
        <!-- Gráfico de Comparativa Financiera -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Comparativa Financiera Global</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="btn-chart-financial">Principal</button>
                        <button type="button" class="btn btn-outline-primary" id="btn-chart-utilities">Utilidades</button>
                        <button type="button" class="btn btn-outline-primary" id="btn-chart-timeline">Tendencia</button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <canvas id="financialChart" height="300" style="display: block;"></canvas>
                    <canvas id="utilitiesChart" height="300" style="display: none;"></canvas>
                    <canvas id="timelineChart" height="300" style="display: none;"></canvas>
                </div>
            </div>
        </div>

        <!-- KPI Cards Secundarios -->
        <div class="col-md-4">
            <!-- Empeños Nuevos -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-primary text-primary me-3">
                            <i class="bi bi-tags-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">
                                <span class="metric-tooltip" title="Nuevos préstamos otorgados en el período">Empeños Nuevos</span>
                            </h6>
                            <h4 class="fw-bold text-dark mb-0" id="kpi-empeno">$ 0.00</h4>
                            <small class="text-muted" id="kpi-empeno-contratos">0 Contratos</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventario en Piso -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-warning text-warning me-3">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">
                                <span class="metric-tooltip" title="Inventario disponible para venta (prendas en piso)">Inventario (Piso)</span>
                            </h6>
                            <h4 class="fw-bold text-dark mb-0" id="kpi-inventario">$ 0.00</h4>
                            <small class="text-muted d-block mt-1">
                                Oro: <span id="kpi-inventario-oro" class="fw-semibold">$ 0.00</span> | Varios: <span id="kpi-inventario-varios" class="fw-semibold">$ 0.00</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ventas Totales -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-success text-success me-3">
                            <i class="bi bi-cart-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">
                                <span class="metric-tooltip" title="Ventas de contado + ventas de apartados">Ventas Totales</span>
                            </h6>
                            <h4 class="fw-bold text-dark mb-0" id="kpi-ventas">$ 0.00</h4>
                            <span class="text-muted small">Oro, Varios</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Apartados Liquidados -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-warning text-warning me-3">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">
                                <span class="metric-tooltip" title="Apartados que fueron liquidados completamente">Apartados Liquidados</span>
                            </h6>
                            <h4 class="fw-bold text-dark mb-0" id="kpi-apartados-liquidados">$ 0.00</h4>
                            <small class="text-muted" id="kpi-apartados-contratos">0 Contratos</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Composición Inventario -->
            <div class="card shadow-sm border-0 rounded-3">
                 <div class="card-body">
                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-3">Composición Inventario</h6>
                    <canvas id="inventoryChart" height="200"></canvas>
                 </div>
            </div>
        </div>
    </div>

    <!-- Tabla Semáforo por Sucursal -->
    <div class="row" id="branch-table-container" style="display: none;">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Desempeño por Sucursal</h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#branchPerformanceChart" aria-expanded="false">
                        <i class="bi bi-bar-chart-line"></i> Ver Gráfico
                    </button>
                </div>
                <div class="card-body p-0">
                    <!-- Collapse para gráfico comparativo -->
                    <div class="collapse p-4" id="branchPerformanceChart">
                         <canvas id="branchesChart" height="100"></canvas>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ingresos</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Gastos</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Utilidad Neta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Margen %</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center" style="width: 120px;">Avance Meta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Estatus</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Inv. Total</th>
                                </tr>
                            </thead>
                            <tbody id="branch-table-body">
                                <!-- Filas generadas por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        // Chart instances
        let finChart = null;
        let utilsChart = null;
        let timelineChart = null;
        let invChart = null;
        let branchChart = null;
        let carteraChart = null;
        let carteraDetalleChart = null;

        // Formatter para moneda
        const formatter = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Formatter para números enteros
        const numberFormatter = new Intl.NumberFormat('es-MX', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });

        // Formatter para porcentajes
        const percentFormatter = new Intl.NumberFormat('es-MX', {
            style: 'percent',
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        });

        // Elements
        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const branchTableContainer = document.getElementById('branch-table-container');
        
        // Botones de cambio de gráfico
        const btnFinancial = document.getElementById('btn-chart-financial');
        const btnUtilities = document.getElementById('btn-chart-utilities');
        const btnTimeline = document.getElementById('btn-chart-timeline');
        const financialChart = document.getElementById('financialChart');
        const utilitiesChart = document.getElementById('utilitiesChart');
        const timelineChartCanvas = document.getElementById('timelineChart');

        // Inicializar tooltips de Bootstrap si existen
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Eventos para cambiar gráfico
        if (btnFinancial && btnUtilities && btnTimeline) {
            btnFinancial.addEventListener('click', function() {
                btnFinancial.classList.add('active');
                btnUtilities.classList.remove('active');
                btnTimeline.classList.remove('active');
                financialChart.style.display = 'block';
                utilitiesChart.style.display = 'none';
                timelineChartCanvas.style.display = 'none';
            });
            
            btnUtilities.addEventListener('click', function() {
                btnUtilities.classList.add('active');
                btnFinancial.classList.remove('active');
                btnTimeline.classList.remove('active');
                financialChart.style.display = 'none';
                utilitiesChart.style.display = 'block';
                timelineChartCanvas.style.display = 'none';
            });

            btnTimeline.addEventListener('click', function() {
                btnTimeline.classList.add('active');
                btnFinancial.classList.remove('active');
                btnUtilities.classList.remove('active');
                financialChart.style.display = 'none';
                utilitiesChart.style.display = 'none';
                timelineChartCanvas.style.display = 'block';
            });
        }

        // Load data initially
        loadData();

        // Handle form submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        /**
         * Carga los datos del servidor
         */
        function loadData() {
            // Show loader
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            const formData = new FormData(form);
            const urlParams = new URLSearchParams(formData).toString();

            fetch(`{{ route('resumen-ejecutivo.data') }}?${urlParams}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    updateDashboard(data);
                })
                .catch(error => {
                    console.error("Error cargando datos:", error);
                    showNotification('error', 'Ocurrió un error al cargar la información. Por favor, intenta de nuevo.');
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        /**
         * Actualiza el dashboard con los datos recibidos
         */
        function updateDashboard(data) {
            try {
                // ===== 1. Validar datos requeridos =====
                if (!data || typeof data !== 'object') {
                    throw new Error('Datos inválidos recibidos del servidor');
                }

                // ===== 2. Obtener valores de las métricas =====
                const utilidadBruta = data.utilidadBruta || 0;
                const margenBruto = data.margenBrutoPorcentaje || 0;
                const utilidadOperativa = data.utilidadOperativa || 0;
                const utilidadNeta = data.utilidadNetaConsolidada || 0;
                const margenNeto = data.margenNetoConsolidado || 0;
                const costoVentas = data.costoVentas || 0;
                const gastosOperativos = data.gastosOperativos || 0;
                
                // ===== 3. Datos de cartera =====
                const carteraVigente = data.carteraVigente || 0;
                const carteraVencida = data.carteraVencida || 0;
                const carteraTotal = data.carteraTotal || 0;
                const tasaMora = data.tasaMora || 0;
                
                // Rangos de cartera
                const carteraPorDias = data.carteraPorDias || {
                    '0_30': 0, '31_60': 0, '61_90': 0, '91_mas': 0
                };
                
                // Detalle por tipo
                const vigenteDetalle = data.carteraVigenteDetalle || { alhajas: 0, autos: 0, varios: 0 };
                const vencidaDetalle = data.carteraVencidaDetalle || { alhajas: 0, autos: 0, varios: 0 };

                // ===== 4. Actualizar KPI Cards Principales =====
                updateElementText('kpi-ingresos', formatter.format(data.totalIngresos || 0));
                updateElementText('kpi-gastos', formatter.format(data.totalEgresos || 0));
                
                // Calcular y asignar Flujo de Operación
                const fundicion = data.fundicion || 0;
                const flujoOperativo = (data.totalIngresos || 0) - (data.totalEgresos || 0); // Este es el valor de la tarjeta Flujo Operativo
                const flujoOperacionSalida = flujoOperativo + fundicion; 
                updateElementText('kpi-flujo-operacion', formatter.format(flujoOperacionSalida));

                // Construir tooltip de Flujo Operación
                const tooltipFlujoEl = document.getElementById('tooltip-flujo');
                if (tooltipFlujoEl) {
                    let tooltipHtmlFlujo = `
                        <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 220px;">
                            <strong class="d-block mb-1 border-bottom pb-1">Desglose de Flujo Operación:</strong>
                            <div class="d-flex justify-content-between"><span>Flujo Operativo:</span> <span class="fw-bold">${formatter.format(flujoOperativo)}</span></div>
                            <div class="d-flex justify-content-between text-danger"><span>Fundición:</span> <span class="fw-bold">${formatter.format(fundicion)}</span></div>
                        </div>
                    `;
                    const existingTooltipFlujo = bootstrap.Tooltip.getInstance(tooltipFlujoEl);
                    if (existingTooltipFlujo) { existingTooltipFlujo.dispose(); }
                    
                    tooltipFlujoEl.setAttribute('data-bs-original-title', tooltipHtmlFlujo);
                    tooltipFlujoEl.setAttribute('title', tooltipHtmlFlujo);
                    new bootstrap.Tooltip(tooltipFlujoEl, { html: true, placement: 'top' });
                }

                updateElementText('kpi-empeno', formatter.format(data.empenosData?.prestamo || 0));
                updateElementText('kpi-empeno-contratos', `${numberFormatter.format(data.empenosData?.contratos || 0)} Contratos`);

                // Construir tooltip de Ingresos
                const detalle = data.detalleIngresos;
                const tooltipEl = document.getElementById('tooltip-ingresos');
                if(detalle && tooltipEl) {
                    let tooltipHtml = `
                        <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 220px;">
                            <strong class="d-block mb-1 border-bottom pb-1">Desglose de Ingresos:</strong>
                            <div class="d-flex justify-content-between"><span>Ventas Contado:</span> <span class="fw-bold">${formatter.format(detalle.ventas || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Apartados Liquidados:</span> <span class="fw-bold">${formatter.format(detalle.apartados_liquidados || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Abono a Apartados:</span> <span class="fw-bold">${formatter.format(detalle.abono_apartado || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Intereses:</span> <span class="fw-bold">${formatter.format(detalle.intereses || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Desempeños:</span> <span class="fw-bold">${formatter.format(detalle.desempenos || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Abono a Capital:</span> <span class="fw-bold">${formatter.format(detalle.abono_capital || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Enganche Crédito:</span> <span class="fw-bold">${formatter.format(detalle.enganche_credito || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Abonos de Crédito:</span> <span class="fw-bold">${formatter.format(detalle.abono_credito || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Liquidación Crédito:</span> <span class="fw-bold">${formatter.format(detalle.liquidacion_credito || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Certificados de Confianza:</span> <span class="fw-bold">${formatter.format(detalle.certificado_confianza || 0)}</span></div>
                        </div>
                    `;
                    // Limpiar tooltip viejo si existe
                    const existingTooltip = bootstrap.Tooltip.getInstance(tooltipEl);
                    if (existingTooltip) { existingTooltip.dispose(); }
                    
                    tooltipEl.setAttribute('data-bs-original-title', tooltipHtml);
                    tooltipEl.setAttribute('title', tooltipHtml);
                    new bootstrap.Tooltip(tooltipEl, { html: true, placement: 'top' });
                }

                // Construir tooltip de Egresos
                const tooltipEgresosEl = document.getElementById('tooltip-egresos');
                if(tooltipEgresosEl) {
                    let tooltipHtmlEgresos = `
                        <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 200px;">
                            <strong class="d-block mb-1 border-bottom pb-1">Desglose de Egresos:</strong>
                            <div class="d-flex justify-content-between"><span>Gastos Operativos:</span> <span class="fw-bold">${formatter.format(gastosOperativos)}</span></div>
                            <div class="d-flex justify-content-between"><span>Empeños (Préstamos):</span> <span class="fw-bold">${formatter.format(data.empenosData?.prestamo || 0)}</span></div>
                        </div>
                    `;
                    // Limpiar tooltip viejo si existe
                    const existingTooltipEgresos = bootstrap.Tooltip.getInstance(tooltipEgresosEl);
                    if (existingTooltipEgresos) { existingTooltipEgresos.dispose(); }
                    
                    tooltipEgresosEl.setAttribute('data-bs-original-title', tooltipHtmlEgresos);
                    tooltipEgresosEl.setAttribute('title', tooltipHtmlEgresos);
                    new bootstrap.Tooltip(tooltipEgresosEl, { html: true, placement: 'top' });
                }

                // Utilidad Neta (simple)
                const utilidadCalculada = (data.totalIngresos || 0) - (data.totalEgresos || 0);
                const utilEl = document.getElementById('kpi-utilidad');
                if (utilEl) {
                    utilEl.innerText = formatter.format(utilidadCalculada);
                    utilEl.className = `display-6 fw-bold mb-0 ${utilidadCalculada >= 0 ? 'text-dark' : 'text-danger'}`;
                }

                // Margen (simple)
                const margenEl = document.getElementById('kpi-margen');
                if (margenEl) {
                    const margenVal = data.totalIngresos > 0 
                        ? ((utilidadCalculada / data.totalIngresos) * 100).toFixed(1) 
                        : 0;
                    margenEl.innerText = `${margenVal}% Margen`;
                    margenEl.className = `badge mt-2 ${utilidadCalculada >= 0 ? 'bg-success' : 'bg-danger'}`;
                }

                // ===== 5. Actualizar tarjetas de utilidades =====
                updateElementText('kpi-utilidad-bruta', formatter.format(utilidadBruta));

                // Construir tooltip de Utilidad Bruta
                const tooltipUtilidadBrutaEl = document.getElementById('tooltip-utilidad-bruta');
                if (tooltipUtilidadBrutaEl && detalle) {
                    let tooltipHtmlUtilidadBruta = `
                        <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 220px;">
                            <strong class="d-block mb-1 border-bottom pb-1">Desglose de Utilidad Bruta:</strong>
                            <div class="d-flex justify-content-between"><span>Utilidad de Venta:</span> <span class="fw-bold">${formatter.format(detalle.utilidad_venta || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Intereses:</span> <span class="fw-bold">${formatter.format(detalle.intereses || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Utilidad de Créditos:</span> <span class="fw-bold">${formatter.format(detalle.utilidad_creditos || 0)}</span></div>
                            <div class="d-flex justify-content-between"><span>Certificados de Confianza:</span> <span class="fw-bold">${formatter.format(detalle.certificado_confianza || 0)}</span></div>
                        </div>
                    `;
                    const existingTooltipUB = bootstrap.Tooltip.getInstance(tooltipUtilidadBrutaEl);
                    if (existingTooltipUB) { existingTooltipUB.dispose(); }
                    
                    tooltipUtilidadBrutaEl.setAttribute('data-bs-original-title', tooltipHtmlUtilidadBruta);
                    tooltipUtilidadBrutaEl.setAttribute('title', tooltipHtmlUtilidadBruta);
                    new bootstrap.Tooltip(tooltipUtilidadBrutaEl, { html: true, placement: 'top' });
                }

                updateElementText('kpi-margen-bruto', `${margenBruto.toFixed(1)}%`);
                updateElementText('kpi-utilidad-operativa', formatter.format(utilidadOperativa));
                updateElementText('kpi-utilidad-neta-consolidada', formatter.format(utilidadNeta));
                updateElementText('kpi-margen-neto-consolidado', `${margenNeto.toFixed(1)}%`);

                // Costo de Ventas
                updateElementText('kpi-costo-ventas', formatter.format(costoVentas));
                const progressBar = document.getElementById('progress-costo-ventas');
                const progressLabel = document.getElementById('progress-costo-ventas-label');
                if (progressBar && data.totalIngresos > 0) {
                    const porcentajeCosto = (costoVentas / data.totalIngresos) * 100;
                    progressBar.style.width = `${Math.min(porcentajeCosto, 100)}%`;
                    if (progressLabel) {
                        progressLabel.innerText = `${porcentajeCosto.toFixed(1)}% del costo sobre ingresos totales`;
                    }
                }

                // ===== 6. Actualizar CARTERA DE EMPEÑO =====
                updateElementText('cartera-vigente', formatter.format(carteraVigente));
                updateElementText('cartera-vencida', formatter.format(carteraVencida));
                updateElementText('cartera-total', formatter.format(carteraTotal));
                updateElementText('contratos-vigentes', numberFormatter.format(data.contratosVigentes || 0));
                updateElementText('contratos-vencidos', numberFormatter.format(data.contratosVencidos || 0));
                updateElementText('tasa-mora', `Tasa de mora: ${tasaMora.toFixed(1)}%`);
                
                // Rangos por días
                updateElementText('cartera-0-30', formatter.format(carteraPorDias['0_30']));
                updateElementText('cartera-31-60', formatter.format(carteraPorDias['31_60']));
                updateElementText('cartera-61-90', formatter.format(carteraPorDias['61_90']));
                updateElementText('cartera-91-mas', formatter.format(carteraPorDias['91_mas']));
                
                // Detalle por tipo de prenda
                updateElementText('cartera-alhajas-vigente', formatter.format(vigenteDetalle.alhajas));
                updateElementText('cartera-alhajas-vencida', formatter.format(vencidaDetalle.alhajas));
                const alhajasTotal = vigenteDetalle.alhajas + vencidaDetalle.alhajas;
                const alhajasMora = alhajasTotal > 0 ? (vencidaDetalle.alhajas / alhajasTotal) * 100 : 0;
                updateElementText('cartera-alhajas-total', formatter.format(alhajasTotal));
                updateElementText('cartera-alhajas-mora', `${alhajasMora.toFixed(1)}%`);
                
                updateElementText('cartera-autos-vigente', formatter.format(vigenteDetalle.autos));
                updateElementText('cartera-autos-vencida', formatter.format(vencidaDetalle.autos));
                const autosTotal = vigenteDetalle.autos + vencidaDetalle.autos;
                const autosMora = autosTotal > 0 ? (vencidaDetalle.autos / autosTotal) * 100 : 0;
                updateElementText('cartera-autos-total', formatter.format(autosTotal));
                updateElementText('cartera-autos-mora', `${autosMora.toFixed(1)}%`);
                
                updateElementText('cartera-varios-vigente', formatter.format(vigenteDetalle.varios));
                updateElementText('cartera-varios-vencida', formatter.format(vencidaDetalle.varios));
                const variosTotal = vigenteDetalle.varios + vencidaDetalle.varios;
                const variosMora = variosTotal > 0 ? (vencidaDetalle.varios / variosTotal) * 100 : 0;
                updateElementText('cartera-varios-total', formatter.format(variosTotal));
                updateElementText('cartera-varios-mora', `${variosMora.toFixed(1)}%`);

                // ===== 7. Actualizar KPI Cards Secundarios =====
                updateElementText('kpi-inventario', formatter.format(data.inventarioPisoVentaTotal || 0));
                updateElementText('kpi-inventario-oro', formatter.format(data.inventarioOro || 0));
                updateElementText('kpi-inventario-varios', formatter.format(data.inventarioVarios || 0));
                
                const ventasTotales = data.ventasTotales || 0;
                const transaccionesVentas = data.transaccionesVentas || 0;
                updateElementText('kpi-ventas', formatter.format(ventasTotales));
                updateElementText('kpi-ventas-oro', formatter.format(data.ventasOro || 0));
                updateElementText('kpi-ventas-varios', formatter.format(data.ventasVarios || 0));
                updateElementText('kpi-ventas-remate', formatter.format(data.ventasRemate || 0));

                const apartadosLiquidados = data.detalleIngresos?.apartados_liquidados || 0;
                const contratosApartados = data.contratosApartados || 0;
                updateElementText('kpi-apartados-liquidados', formatter.format(apartadosLiquidados));
                updateElementText('kpi-apartados-contratos', `${numberFormatter.format(contratosApartados)} Contratos`);

                // Indicadores Financieros
                if (data.balanceGeneral) {
                    updateElementText('kpi-activo-total', formatter.format(data.balanceGeneral.activo_total || 0));
                    updateElementText('kpi-pasivo-total', formatter.format(data.balanceGeneral.pasivo_total || 0));
                    updateElementText('kpi-capital-total', formatter.format(data.balanceGeneral.capital_total || 0));
                    updateElementText('kpi-efectivo-inicial', formatter.format(data.balanceGeneral.efectivo_inicial || 0));
                    updateElementText('kpi-flujo-neto', formatter.format(data.balanceGeneral.flujo_neto || 0));
                    updateElementText('kpi-efectivo-final', formatter.format(data.balanceGeneral.efectivo_final || 0));
                    
                    const flujoEl = document.getElementById('kpi-flujo-neto');
                    if (flujoEl) {
                        flujoEl.className = data.balanceGeneral.flujo_neto >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
                    }
                }

                // ===== 8. Actualizar Gráficos =====
                if (data.chartFinanciero) {
                    updateFinancialChart({
                        labels: ['Ingresos', 'Gastos Operativos', 'Utilidad Neta'],
                        data: [
                            data.totalIngresos || 0, 
                            gastosOperativos, 
                            utilidadNeta
                        ]
                    });
                }

                if (data.chartUtilidades) {
                    updateUtilitiesChart(data.chartUtilidades);
                }

                if (data.chartInventario) {
                    updateInventoryChart(data.chartInventario);
                }

                // Timeline chart
                if (data.chartTimeline) {
                    updateTimelineChart(data.chartTimeline);
                } else {
                    // Placeholder si no viene del backend
                    updateTimelineChart({
                        labels: ['Mes Seleccionado'],
                        ingresos: [data.totalIngresos || 0],
                        utilidades: [data.utilidadNetaConsolidada || 0],
                        flujo: [data.balanceGeneral ? data.balanceGeneral.flujo_neto : 0]
                    });
                }

                // Gráficos de cartera
                updateCarteraChart(carteraVigente, carteraVencida);
                updateCarteraDetalleChart(carteraPorDias);

                // ===== 9. Actualizar Tabla de Sucursales =====
                const isSingleBranch = document.getElementById('sucursal_id').value !== "";
                if (!isSingleBranch && data.branchKPIs && Object.keys(data.branchKPIs).length > 0) {
                    branchTableContainer.style.display = 'flex';
                    updateBranchTable(data.branchKPIs);
                    if (data.chartSucursales) {
                        updateBranchChart(data.chartSucursales);
                    }
                } else {
                    branchTableContainer.style.display = 'none';
                }


                console.log('Dashboard actualizado correctamente');

            } catch (error) {
                console.error('Error actualizando dashboard:', error);
                showNotification('error', 'Error al procesar los datos del dashboard');
            }
        }

        /**
         * Actualiza el gráfico de cartera (vigente vs vencida)
         */
        function updateCarteraChart(vigente, vencida) {
            const ctx = document.getElementById('carteraChart');
            if (!ctx) return;

            if (carteraChart) carteraChart.destroy();

            carteraChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Vigente', 'Vencida'],
                    datasets: [{
                        data: [vigente, vencida],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = formatter.format(context.raw);
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        /**
         * Actualiza el gráfico de detalle de cartera por días
         */
        function updateCarteraDetalleChart(carteraPorDias) {
            const ctx = document.getElementById('carteraDetalleChart');
            if (!ctx) return;

            if (carteraDetalleChart) carteraDetalleChart.destroy();

            carteraDetalleChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['0-30 días', '31-60 días', '61-90 días', '90+ días'],
                    datasets: [{
                        label: 'Monto',
                        data: [
                            carteraPorDias['0_30'] || 0,
                            carteraPorDias['31_60'] || 0,
                            carteraPorDias['61_90'] || 0,
                            carteraPorDias['91_mas'] || 0
                        ],
                        backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545'],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatter.format(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatter.format(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Actualiza el gráfico financiero
         */
        function updateFinancialChart(chartData) {
            const ctx = document.getElementById('financialChart');
            if (!ctx) return;

            if (finChart) finChart.destroy();

            finChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto (MXN)',
                        data: chartData.data,
                        backgroundColor: [
                            'rgba(25, 135, 84, 0.7)',
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(13, 202, 240, 0.7)'
                        ],
                        borderColor: [
                            'rgba(25, 135, 84, 1)',
                            'rgba(220, 53, 69, 1)',
                            'rgba(13, 202, 240, 1)'
                        ],
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatter.format(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { borderDash: [2, 4] },
                            ticks: {
                                callback: function(value) {
                                    return formatter.format(value);
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        /**
         * Actualiza el gráfico de utilidades
         */
        function updateUtilitiesChart(chartData) {
            const ctx = document.getElementById('utilitiesChart');
            if (!ctx) return;

            if (utilsChart) utilsChart.destroy();

            utilsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || ['Utilidad Bruta', 'Utilidad Operativa', 'Utilidad Neta'],
                    datasets: [{
                        label: 'Utilidades',
                        data: chartData.data || [0, 0, 0],
                        backgroundColor: 'rgba(13, 202, 240, 0.1)',
                        borderColor: 'rgba(13, 202, 240, 1)',
                        borderWidth: 3,
                        pointBackgroundColor: ['#0d6efd', '#ffc107', '#198754'],
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatter.format(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { borderDash: [2, 4] },
                            ticks: {
                                callback: function(value) {
                                    return formatter.format(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Actualiza el gráfico de inventario
         */
        function updateInventoryChart(chartData) {
            const ctx = document.getElementById('inventoryChart');
            if (!ctx) return;

            if (invChart) invChart.destroy();

            const hasData = chartData.data && chartData.data.some(value => value > 0);
            
            if (!hasData) {
                const parent = ctx.parentNode;
                parent.innerHTML = '<div class="text-center text-muted py-4">Sin datos de inventario</div>';
                return;
            }

            invChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels || ['Oro', 'Plata', 'Varios', 'Autos'],
                    datasets: [{
                        data: chartData.data || [0, 0, 0, 0],
                        backgroundColor: ['#FFD700', '#C0C0C0', '#fd7e14', '#0dcaf0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'right', labels: { boxWidth: 12 } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = formatter.format(context.raw);
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }

        /**
         * Actualiza la tabla de sucursales
         */
        function updateBranchTable(branchKPIs) {
            const tbody = document.getElementById('branch-table-body');
            if (!tbody) return;

            tbody.innerHTML = '';

            for (const [nombre, kpi] of Object.entries(branchKPIs)) {
                const utilidadNeta = (kpi.ingresos || 0) - (kpi.gastos || 0);
                const utilClass = utilidadNeta >= 0 ? 'text-success' : 'text-danger';
                const margen = kpi.margen_bruto_pct || 0;
                
                let badgeClass = 'bg-secondary';
                if (margen > 30) badgeClass = 'bg-success';
                else if (margen > 15) badgeClass = 'bg-warning text-dark';
                else if (margen > 0) badgeClass = 'bg-danger';
                else badgeClass = 'bg-dark';

                const cumplimiento = kpi.cumplimiento || 0;
                const semaforo = kpi.semaforo || 'rojo';
                let semaforoColor = '';
                if (semaforo === 'verde') { semaforoColor = 'text-success'; }
                else if (semaforo === 'amarillo') { semaforoColor = 'text-warning'; }
                else { semaforoColor = 'text-danger'; }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-4 fw-semibold text-dark">${escapeHtml(nombre)}</td>
                    <td class="text-end">${formatter.format(kpi.ingresos || 0)}</td>
                    <td class="text-end">${formatter.format(kpi.gastos || 0)}</td>
                    <td class="text-end fw-bold ${utilClass}">${formatter.format(utilidadNeta)}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass} rounded-pill px-3 py-2">${margen.toFixed(1)}%</span>
                    </td>
                    <td class="text-center">
                        <div class="progress progress-sm mt-1 mb-1" style="height: 6px;">
                            <div class="progress-bar ${semaforoColor.replace('text-', 'bg-')}" role="progressbar" style="width: ${Math.min(cumplimiento, 100)}%"></div>
                        </div>
                        <small class="text-muted fw-bold">${cumplimiento.toFixed(1)}%</small>
                    </td>
                    <td class="text-center">
                        <i class="bi bi-circle-fill ${semaforoColor} fs-6" title="${semaforo}"></i>
                    </td>
                    <td class="pe-4 text-end">${formatter.format(kpi.inventario_total || 0)}</td>
                `;
                tbody.appendChild(tr);
            }
        }


        /**
         * Actualiza el gráfico de tendencia (timeline)
         */
        function updateTimelineChart(chartData) {
            const ctx = document.getElementById('timelineChart');
            if (!ctx) return;

            if (timelineChart) timelineChart.destroy();

            timelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: chartData.ingresos || [],
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidades || [],
                            borderColor: '#0dcaf0',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Flujo Neto',
                            data: chartData.flujo || [],
                            borderColor: '#ffc107',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${formatter.format(context.raw)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatter.format(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Actualiza el gráfico de sucursales
         */
        function updateBranchChart(chartData) {
            const ctx = document.getElementById('branchesChart');
            if (!ctx) return;

            if (branchChart) branchChart.destroy();

            if (!chartData.labels || chartData.labels.length === 0) {
                const parent = ctx.parentNode;
                parent.innerHTML = '<div class="text-center text-muted py-4">Sin datos de sucursales</div>';
                return;
            }

            branchChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidades || [],
                            backgroundColor: 'rgba(13, 202, 240, 0.6)',
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Margen %',
                            data: chartData.margenes || [],
                            backgroundColor: 'rgba(25, 135, 84, 0.6)',
                            borderRadius: 4,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Avance Meta %',
                            data: chartData.cumplimientos || [],
                            backgroundColor: 'rgba(255, 193, 7, 0.6)',
                            borderRadius: 4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Utilidad Neta') {
                                        return `${context.dataset.label}: ${formatter.format(context.raw)}`;
                                    }
                                    return `${context.dataset.label}: ${context.raw}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true, 
                            grid: { color: '#f0f0f0' },
                            ticks: {
                                callback: function(value) {
                                    return formatter.format(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        /**
         * Actualiza el texto de un elemento de forma segura
         */
        function updateElementText(elementId, text) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerText = text;
            }
        }

        /**
         * Muestra una notificación al usuario
         */
        function showNotification(type, message) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                console.log(`${type}: ${message}`);
            } else {
                alert(message);
            }
        }

        /**
         * Escapa HTML para prevenir XSS
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

    });
</script>
@endsection