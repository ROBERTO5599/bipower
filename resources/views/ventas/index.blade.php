@extends('employees.layouts.main')

@section('title', 'Ventas, Descuentos y Medios de Pago')

@section('styles')
    <style type="text/css">
        .cursor-pointer {
            cursor: pointer;
        }

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

        .bg-light-success {
            background-color: rgba(25, 135, 84, 0.1);
        }

        .bg-light-danger {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .bg-light-info {
            background-color: rgba(13, 202, 240, 0.1);
        }

        .bg-light-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }

        .bg-light-primary {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .bg-light-secondary {
            background-color: rgba(108, 117, 125, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        /* Spinner */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .metric-tooltip {
            position: relative;
            display: inline-block;
            border-bottom: 1px dotted #6c757d;
            cursor: help;
        }
    </style>
@endsection

@section('content')

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <h5 class="text-muted fw-bold">Analizando ventas y pagos...</h5>
    </div>

    <div class="container-fluid p-4" id="dashboard-content" style="display: none;">
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="title fw-bold text-dark">Análisis de Ventas, Descuentos y Medios de Pago</h4>
                <p class="text-muted">Desempeño de ventas de piso, rentabilidad y métodos de cobro</p>
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
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}"
                            class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha Hasta</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}"
                            class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-funnel-fill me-2"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPIs Principales -->
        <div class="row mb-4 justify-content-center">
            <!-- Ventas Totales -->
            <div class="col-12 col-xl-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                <span class="metric-tooltip" id="tooltip-ventas-totales" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                    Ventas Totales
                                </span>
                            </h6>
                            <div class="icon-shape bg-light-success text-success">
                                <i class="bi bi-cart-check"></i>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ventas-totales">$ 0.00</h2>
                        <span class="text-muted small">Oro, Varios, Autos</span>
                    </div>
                </div>
            </div>

            <!-- Contratos de ventas y apartados liquidados -->
            <div class="col-12 col-xl-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                <span class="metric-tooltip" id="tooltip-total-tickets" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                    Contratos de ventas, apartados y creditos liquidados
                                </span>
                            </h6>
                            <div class="icon-shape bg-light-info text-info">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-tickets">0 Contratos</h2>
                        <span class="text-muted small" id="kpi-ticket-promedio">Monto prestamo: $ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Utilidad Bruta de Ventas -->
            <div class="col-12 col-xl-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                <span class="metric-tooltip" id="tooltip-utilidad-bruta" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                    Utilidad Venta
                                </span>
                            </h6>
                            <div class="icon-shape bg-light-primary text-primary">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-utilidad-bruta">$ 0.00</h2>
                        <span class="text-muted small" id="kpi-margen-venta">0.0% Margen de Venta</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desglose de Ventas Totales (Ventas Directas, Apartados, Créditos) -->
        <div class="row mb-4 justify-content-center">
            <!-- Ventas Directas -->
            <div class="col-12 col-md-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-primary border-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">
                                <span class="metric-tooltip" id="tooltip-ventas-directas" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                    Ventas Directas
                                </span>
                            </h6>
                            <div class="icon-shape bg-light-primary text-primary">
                                <i class="bi bi-bag-check"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0" id="kpi-ventas-directas">$ 0.00</h3>
                        <div class="d-flex justify-content-between align-items-center mt-1 mb-2">
                            <span class="text-muted small fw-semibold" id="kpi-ventas-directas-contratos">0 Contratos</span>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Oro:</span>
                                <span class="fw-bold text-dark" id="kpi-ventas-directas-oro">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Plata:</span>
                                <span class="fw-bold text-dark" id="kpi-ventas-directas-plata">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Varios:</span>
                                <span class="fw-bold text-dark" id="kpi-ventas-directas-varios">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-0" style="font-size: 0.75rem;">
                                <span class="text-muted">Autos:</span>
                                <span class="fw-bold text-dark" id="kpi-ventas-directas-autos">$ 0.00 (0)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Apartados Liquidados -->
            <div class="col-12 col-md-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-warning border-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">
                                <span class="metric-tooltip" id="tooltip-apartados-liquidados" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                    Apartados Liquidados
                                </span>
                            </h6>
                            <div class="icon-shape bg-light-warning text-warning">
                                <i class="bi bi-bookmark-check"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0" id="kpi-apartados-liquidados">$ 0.00</h3>
                        <div class="d-flex justify-content-between align-items-center mt-1 mb-2">
                            <span class="text-muted small fw-semibold" id="kpi-apartados-liquidados-contratos">0 Contratos</span>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Oro:</span>
                                <span class="fw-bold text-dark" id="kpi-apartados-liquidados-oro">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Plata:</span>
                                <span class="fw-bold text-dark" id="kpi-apartados-liquidados-plata">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Varios:</span>
                                <span class="fw-bold text-dark" id="kpi-apartados-liquidados-varios">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-0" style="font-size: 0.75rem;">
                                <span class="text-muted">Autos:</span>
                                <span class="fw-bold text-dark" id="kpi-apartados-liquidados-autos">$ 0.00 (0)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Créditos Liquidados -->
            <div class="col-12 col-md-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-info border-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">
                                <span class="metric-tooltip" id="tooltip-creditos-liquidados" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                    Créditos Liquidados
                                </span>
                            </h6>
                            <div class="icon-shape bg-light-info text-info">
                                <i class="bi bi-credit-card-2-front"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-0" id="kpi-creditos-liquidados">$ 0.00</h3>
                        <div class="d-flex justify-content-between align-items-center mt-1 mb-2">
                            <span class="text-muted small fw-semibold" id="kpi-creditos-liquidados-contratos">0 Contratos</span>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Oro:</span>
                                <span class="fw-bold text-dark" id="kpi-creditos-liquidados-oro">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Plata:</span>
                                <span class="fw-bold text-dark" id="kpi-creditos-liquidados-plata">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Varios:</span>
                                <span class="fw-bold text-dark" id="kpi-creditos-liquidados-varios">$ 0.00 (0)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-0" style="font-size: 0.75rem;">
                                <span class="text-muted">Autos:</span>
                                <span class="fw-bold text-dark" id="kpi-creditos-liquidados-autos">$ 0.00 (0)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs Secundarios: Descuentos y Tarjetas -->
        <div class="row mb-4">
            <!-- Descuentos -->
            <div class="col-12 col-xl-6 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-shape bg-light-warning text-warning me-3">
                                <i class="bi bi-tags-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                    <span class="metric-tooltip" id="tooltip-descuento-total" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                        Total en Descuentos
                                    </span>
                                </h6>
                                <h3 class="fw-bold text-dark mb-0" id="kpi-descuento-total">$ 0.00</h3>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <small class="text-muted d-block">Número de contratos con descuento</small>
                                <span class="fw-bold" id="kpi-tickets-descuento">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagos con Efectivo / Tarjeta -->
            <div class="col-12 col-xl-6 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-shape bg-light-secondary text-secondary me-3">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                    <span class="metric-tooltip" id="tooltip-pagos-metodos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">
                                        Efectivo / Tarjeta
                                    </span>
                                </h6>
                                <h3 class="fw-bold text-dark mb-0">
                                    <span id="kpi-pagos-efectivo" class="text-success">$ 0.00</span>
                                    <span class="text-muted fs-5 mx-1">|</span>
                                    <span id="kpi-pagos-tarjeta" class="text-primary">$ 0.00</span>
                                </h3>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <small class="text-muted d-block">% Ventas en Efectivo</small>
                                <span class="fw-bold text-success" id="kpi-porcentaje-efectivo">0%</span>
                                <small class="text-muted d-block mt-2">Contratos Efectivo</small>
                                <span class="fw-bold" id="kpi-contratos-efectivo">0</span>
                            </div>
                            <div class="col-6 border-start">
                                <small class="text-muted d-block">% Ventas con Tarjeta</small>
                                <span class="fw-bold text-primary" id="kpi-porcentaje-tarjeta">0%</span>
                                <small class="text-muted d-block mt-2">Contratos Tarjeta</small>
                                <span class="fw-bold" id="kpi-contratos-tarjeta">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <!-- Ventas por Familia (Apilada Ventas vs Utilidad) -->
            <div class="col-md-8 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Ventas vs Utilidad por Tipo de Prenda</h5>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="ventasFamiliaChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Métodos de pago -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Métodos de Pago</h5>
                    </div>
                    <div class="card-body p-4 d-flex justify-content-center align-items-center">
                        <canvas id="metodosPagoChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablas Top 10 Artículos Dividido por Metal, Varios y Autos (Más Vendidos) -->
        <div class="row mb-4">
            <!-- 1. Top 10 Metal -->
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-3 px-3 d-flex align-items-center">
                        <div class="icon-shape bg-light-warning text-warning me-2" style="width:34px; height:34px; font-size:1rem;">
                            <i class="bi bi-gem"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-dark">Top 10 Metal <span class="text-muted fw-normal small">(Más Vendidos)</span></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Artículo</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Vendidos</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">Venta Total</th>
                                    </tr>
                                </thead>
                                <tbody id="top-articulos-metal">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3 small">Cargando datos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Top 10 Varios -->
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-3 px-3 d-flex align-items-center">
                        <div class="icon-shape bg-light-primary text-primary me-2" style="width:34px; height:34px; font-size:1rem;">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-dark">Top 10 Varios <span class="text-muted fw-normal small">(Más Vendidos)</span></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Artículo</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Vendidos</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">Venta Total</th>
                                    </tr>
                                </thead>
                                <tbody id="top-articulos-varios">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3 small">Cargando datos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Top 10 Autos -->
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-3 px-3 d-flex align-items-center">
                        <div class="icon-shape bg-light-success text-success me-2" style="width:34px; height:34px; font-size:1rem;">
                            <i class="bi bi-car-front-fill"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-dark">Top 10 Autos <span class="text-muted fw-normal small">(Más Vendidos)</span></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Artículo</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Vendidos</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">Venta Total</th>
                                    </tr>
                                </thead>
                                <tbody id="top-articulos-autos">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3 small">Cargando datos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Top 10 Artículos (Mayor Margen %) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-0 pt-3 px-3 d-flex align-items-center">
                        <div class="icon-shape bg-light-info text-info me-2" style="width:34px; height:34px; font-size:1rem;">
                            <i class="bi bi-percent"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-dark">Top 10 Artículos <span class="text-muted fw-normal small">(Mayor Margen %)</span></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Artículo</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Utilidad Neta</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">% Margen</th>
                                    </tr>
                                </thead>
                                <tbody id="top-articulos-margen">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3 small">Cargando datos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Marcas -->
    <div class="modal fade" id="modalMarcas" tabindex="-1" aria-labelledby="modalMarcasLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-3">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold" id="modalMarcasLabel">
                        <i class="bi bi-tag-fill me-2"></i> Top 10 Marcas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3 text-muted" id="modal-subtitle" style="font-size: 0.9rem;">
                        Mostrando las marcas más vendidas para este artículo.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-uppercase text-muted small fw-bold">Marca</th>
                                    <th class="text-uppercase text-muted small fw-bold text-center">Operaciones</th>
                                    <th class="text-uppercase text-muted small fw-bold text-end">Monto Total</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-marcas-top">
                                <!-- Filas dinámicas -->
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
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            let ventasFamiliaChart = null;
            let metodosPagoChart = null;

            const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
            const numberFormatter = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            const percentFormatter = new Intl.NumberFormat('es-MX', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 });

            const overlay = document.getElementById('loading-overlay');
            const dashboard = document.getElementById('dashboard-content');
            const form = document.getElementById('filter-form');

            loadData();

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                loadData();
            });

            function loadData() {
                overlay.style.display = 'flex';
                dashboard.style.opacity = '0.5';

                const formData = new FormData(form);
                const urlParams = new URLSearchParams(formData).toString();

                fetch(`{{ route('ventas.data') }}?${urlParams}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        updateDashboard(data);
                    })
                    .catch(error => {
                        console.error("Error:", error);
                    })
                    .finally(() => {
                        overlay.style.display = 'none';
                        dashboard.style.display = 'block';
                        dashboard.style.opacity = '1';
                    });
            }

            function updateElementText(id, text) {
                const el = document.getElementById(id);
                if (el) el.innerText = text;
            }

            function updateTooltip(id, tooltipHtml) {
                const el = document.getElementById(id);
                if (el && typeof bootstrap !== 'undefined') {
                    const existingTooltip = bootstrap.Tooltip.getInstance(el);
                    if (existingTooltip) existingTooltip.dispose();
                    el.setAttribute('data-bs-original-title', tooltipHtml);
                    el.setAttribute('title', tooltipHtml);
                    new bootstrap.Tooltip(el, { html: true, placement: 'top' });
                }
            }
 
            function updateDashboard(data) {
                // KPIs Principales
                updateElementText('kpi-ventas-totales', formatter.format(data.ventasTotales || 0));
                updateElementText('kpi-ticket-promedio', `Monto prestamo: ${formatter.format(data.montoPrestamo || 0)}`);
                updateElementText('kpi-total-tickets', `${numberFormatter.format(data.totalTickets || 0)} Contratos`);
 
                updateElementText('kpi-utilidad-bruta', formatter.format(data.utilidadBruta || 0));
                updateElementText('kpi-margen-venta', `${(data.margenVentaPorcentaje || 0).toFixed(1)}% Margen de Venta`);

                // Desgloses de ventas en KPIs individuales
                const dV = data.desgloseVentas || {};
                updateElementText('kpi-ventas-directas', formatter.format(dV.venta ? dV.venta.monto : 0));
                updateElementText('kpi-ventas-directas-contratos', `${numberFormatter.format(dV.venta ? dV.venta.cantidad : 0)} Contratos`);
                const vtaDet = (dV.venta && dV.venta.detalles) || {};
                updateElementText('kpi-ventas-directas-oro', `${formatter.format(vtaDet.Oro ? vtaDet.Oro.monto : 0)} (${numberFormatter.format(vtaDet.Oro ? vtaDet.Oro.cantidad : 0)})`);
                updateElementText('kpi-ventas-directas-plata', `${formatter.format(vtaDet.Plata ? vtaDet.Plata.monto : 0)} (${numberFormatter.format(vtaDet.Plata ? vtaDet.Plata.cantidad : 0)})`);
                updateElementText('kpi-ventas-directas-varios', `${formatter.format(vtaDet.Varios ? vtaDet.Varios.monto : 0)} (${numberFormatter.format(vtaDet.Varios ? vtaDet.Varios.cantidad : 0)})`);
                updateElementText('kpi-ventas-directas-autos', `${formatter.format(vtaDet.Autos ? vtaDet.Autos.monto : 0)} (${numberFormatter.format(vtaDet.Autos ? vtaDet.Autos.cantidad : 0)})`);

                updateElementText('kpi-apartados-liquidados', formatter.format(dV.apartado ? dV.apartado.monto : 0));
                updateElementText('kpi-apartados-liquidados-contratos', `${numberFormatter.format(dV.apartado ? dV.apartado.cantidad : 0)} Contratos`);
                const aptDet = (dV.apartado && dV.apartado.detalles) || {};
                updateElementText('kpi-apartados-liquidados-oro', `${formatter.format(aptDet.Oro ? aptDet.Oro.monto : 0)} (${numberFormatter.format(aptDet.Oro ? aptDet.Oro.cantidad : 0)})`);
                updateElementText('kpi-apartados-liquidados-plata', `${formatter.format(aptDet.Plata ? aptDet.Plata.monto : 0)} (${numberFormatter.format(aptDet.Plata ? aptDet.Plata.cantidad : 0)})`);
                updateElementText('kpi-apartados-liquidados-varios', `${formatter.format(aptDet.Varios ? aptDet.Varios.monto : 0)} (${numberFormatter.format(aptDet.Varios ? aptDet.Varios.cantidad : 0)})`);
                updateElementText('kpi-apartados-liquidados-autos', `${formatter.format(aptDet.Autos ? aptDet.Autos.monto : 0)} (${numberFormatter.format(aptDet.Autos ? aptDet.Autos.cantidad : 0)})`);

                updateElementText('kpi-creditos-liquidados', formatter.format(dV.credito ? dV.credito.monto : 0));
                updateElementText('kpi-creditos-liquidados-contratos', `${numberFormatter.format(dV.credito ? dV.credito.cantidad : 0)} Contratos`);
                const credDet = (dV.credito && dV.credito.detalles) || {};
                updateElementText('kpi-creditos-liquidados-oro', `${formatter.format(credDet.Oro ? credDet.Oro.monto : 0)} (${numberFormatter.format(credDet.Oro ? credDet.Oro.cantidad : 0)})`);
                updateElementText('kpi-creditos-liquidados-plata', `${formatter.format(credDet.Plata ? credDet.Plata.monto : 0)} (${numberFormatter.format(credDet.Plata ? credDet.Plata.cantidad : 0)})`);
                updateElementText('kpi-creditos-liquidados-varios', `${formatter.format(credDet.Varios ? credDet.Varios.monto : 0)} (${numberFormatter.format(credDet.Varios ? credDet.Varios.cantidad : 0)})`);
                updateElementText('kpi-creditos-liquidados-autos', `${formatter.format(credDet.Autos ? credDet.Autos.monto : 0)} (${numberFormatter.format(credDet.Autos ? credDet.Autos.cantidad : 0)})`);
 
                // Descuentos y Tarjetas
                updateElementText('kpi-descuento-total', formatter.format(data.montoDescuentoTotal || 0));
                updateElementText('kpi-tickets-descuento', numberFormatter.format(data.ticketsConDescuento || 0));
 
                updateElementText('kpi-pagos-efectivo', formatter.format(data.pagosEfectivo || 0));
                updateElementText('kpi-pagos-tarjeta', formatter.format(data.pagosTarjeta || 0));
                updateElementText('kpi-porcentaje-efectivo', `${(data.pagosEfectivoPorcentaje || 0).toFixed(1)}%`);
                updateElementText('kpi-porcentaje-tarjeta', `${(data.pagosTarjetaPorcentaje || 0).toFixed(1)}%`);
                
                updateElementText('kpi-contratos-efectivo', numberFormatter.format(data.contratosEfectivo || 0));
                updateElementText('kpi-contratos-tarjeta', numberFormatter.format(data.contratosTarjeta || 0));

                // Desgloses dinámicos para Tooltips
                const dF = data.desgloseFamilias || {};

                // 1. Ventas Totales Tooltip
                const vtaTotalHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 250px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose de Ventas:</strong>
                        <span class="text-muted small fw-bold">POR TIPO DE TRANSACCIÓN</span>
                        <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${formatter.format(dV.venta ? dV.venta.monto : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados Liquidados:</span> <span class="fw-bold">${formatter.format(dV.apartado ? dV.apartado.monto : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Créditos Liquidados:</span> <span class="fw-bold">${formatter.format(dV.credito ? dV.credito.monto : 0)}</span></div>
                        <hr class="my-1">
                        <span class="text-muted small fw-bold">POR FAMILIA DE PRENDA</span>
                        <div class="d-flex justify-content-between"><span>Oro:</span> <span class="fw-bold">${formatter.format(dF.Oro ? dF.Oro.monto : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Varios:</span> <span class="fw-bold">${formatter.format(dF.Varios ? dF.Varios.monto : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Autos:</span> <span class="fw-bold">${formatter.format(dF.Autos ? dF.Autos.monto : 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>VENTAS TOTALES</span> <span class="text-success">${formatter.format(data.ventasTotales || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-ventas-totales', vtaTotalHtml);

                // Tooltips para los nuevos KPIs individuales
                const vtaDir = dV.venta || {};
                const vtaDirHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 230px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose Ventas Directas:</strong>
                        <div class="d-flex justify-content-between"><span>Monto Total:</span> <span class="fw-bold">${formatter.format(vtaDir.monto || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Cantidad:</span> <span class="fw-bold">${numberFormatter.format(vtaDir.cantidad || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Utilidad:</span> <span class="fw-bold text-success">${formatter.format(vtaDir.utilidad || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Descuento:</span> <span class="fw-bold text-danger">${formatter.format(vtaDir.descuento || 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between"><span>Cobro Efectivo:</span> <span class="fw-bold text-success">${formatter.format(vtaDir.efectivo || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Cobro Tarjeta:</span> <span class="fw-bold text-primary">${formatter.format(vtaDir.tarjeta || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-ventas-directas', vtaDirHtml);

                const aptLiq = dV.apartado || {};
                const aptLiqHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 230px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose Apartados Liquidados:</strong>
                        <div class="d-flex justify-content-between"><span>Monto Total:</span> <span class="fw-bold">${formatter.format(aptLiq.monto || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Cantidad:</span> <span class="fw-bold">${numberFormatter.format(aptLiq.cantidad || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Utilidad:</span> <span class="fw-bold text-success">${formatter.format(aptLiq.utilidad || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Descuento:</span> <span class="fw-bold text-danger">${formatter.format(aptLiq.descuento || 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between"><span>Cobro Efectivo:</span> <span class="fw-bold text-success">${formatter.format(aptLiq.efectivo || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Cobro Tarjeta:</span> <span class="fw-bold text-primary">${formatter.format(aptLiq.tarjeta || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-apartados-liquidados', aptLiqHtml);

                const credLiq = dV.credito || {};
                const credLiqHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 230px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose Créditos Liquidados:</strong>
                        <div class="d-flex justify-content-between"><span>Monto Total:</span> <span class="fw-bold">${formatter.format(credLiq.monto || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Cantidad:</span> <span class="fw-bold">${numberFormatter.format(credLiq.cantidad || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Utilidad:</span> <span class="fw-bold text-success">${formatter.format(credLiq.utilidad || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Descuento:</span> <span class="fw-bold text-danger">${formatter.format(credLiq.descuento || 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between"><span>Cobro Efectivo:</span> <span class="fw-bold text-success">${formatter.format(credLiq.efectivo || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Cobro Tarjeta:</span> <span class="fw-bold text-primary">${formatter.format(credLiq.tarjeta || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-creditos-liquidados', credLiqHtml);

                // 2. Contratos Tooltip
                const contHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 250px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose de Contratos:</strong>
                        <span class="text-muted small fw-bold">POR TIPO DE TRANSACCIÓN</span>
                        <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${numberFormatter.format(dV.venta ? dV.venta.cantidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados Liquidados:</span> <span class="fw-bold">${numberFormatter.format(dV.apartado ? dV.apartado.cantidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Créditos Liquidados:</span> <span class="fw-bold">${numberFormatter.format(dV.credito ? dV.credito.cantidad : 0)}</span></div>
                        <hr class="my-1">
                        <span class="text-muted small fw-bold">POR FAMILIA DE PRENDA</span>
                        <div class="d-flex justify-content-between"><span>Oro:</span> <span class="fw-bold">${numberFormatter.format(dF.Oro ? dF.Oro.cantidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Varios:</span> <span class="fw-bold">${numberFormatter.format(dF.Varios ? dF.Varios.cantidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Autos:</span> <span class="fw-bold">${numberFormatter.format(dF.Autos ? dF.Autos.cantidad : 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>TOTAL CONTRATOS</span> <span class="text-info">${numberFormatter.format(data.totalTickets || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-total-tickets', contHtml);

                // 3. Utilidad Venta Tooltip
                const utilHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 250px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose de Utilidad:</strong>
                        <span class="text-muted small fw-bold">POR TIPO DE TRANSACCIÓN</span>
                        <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${formatter.format(dV.venta ? dV.venta.utilidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados Liquidados:</span> <span class="fw-bold">${formatter.format(dV.apartado ? dV.apartado.utilidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Créditos Liquidados:</span> <span class="fw-bold">${formatter.format(dV.credito ? dV.credito.utilidad : 0)}</span></div>
                        <hr class="my-1">
                        <span class="text-muted small fw-bold">POR FAMILIA DE PRENDA</span>
                        <div class="d-flex justify-content-between"><span>Oro:</span> <span class="fw-bold">${formatter.format(dF.Oro ? dF.Oro.utilidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Varios:</span> <span class="fw-bold">${formatter.format(dF.Varios ? dF.Varios.utilidad : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Autos:</span> <span class="fw-bold">${formatter.format(dF.Autos ? dF.Autos.utilidad : 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>UTILIDAD TOTAL</span> <span class="text-primary">${formatter.format(data.utilidadBruta || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-utilidad-bruta', utilHtml);

                // 4. Total en Descuentos Tooltip
                const descHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 250px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose de Descuentos:</strong>
                        <span class="text-muted small fw-bold">POR TIPO DE TRANSACCIÓN</span>
                        <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${formatter.format(dV.venta ? dV.venta.descuento : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados Liquidados:</span> <span class="fw-bold">${formatter.format(dV.apartado ? dV.apartado.descuento : 0)}</span></div>
                        <hr class="my-1">
                        <span class="text-muted small fw-bold">POR FAMILIA DE PRENDA</span>
                        <div class="d-flex justify-content-between"><span>Oro:</span> <span class="fw-bold">${formatter.format(dF.Oro ? dF.Oro.descuento : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Varios:</span> <span class="fw-bold">${formatter.format(dF.Varios ? dF.Varios.descuento : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Autos:</span> <span class="fw-bold">${formatter.format(dF.Autos ? dF.Autos.descuento : 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>DESCUENTOS TOTALES</span> <span class="text-warning">${formatter.format(data.montoDescuentoTotal || 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-descuento-total', descHtml);

                // 5. Efectivo / Tarjeta Tooltip
                const pagosHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 250px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose por Medio de Pago:</strong>
                        <span class="text-success small fw-bold">EFECTIVO POR ORIGEN</span>
                        <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${formatter.format(dV.venta ? dV.venta.efectivo : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados:</span> <span class="fw-bold">${formatter.format(dV.apartado ? dV.apartado.efectivo : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Créditos:</span> <span class="fw-bold">${formatter.format(dV.credito ? dV.credito.efectivo : 0)}</span></div>
                        <hr class="my-1">
                        <span class="text-primary small fw-bold">TARJETA POR ORIGEN</span>
                        <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${formatter.format(dV.venta ? dV.venta.tarjeta : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados:</span> <span class="fw-bold">${formatter.format(dV.apartado ? dV.apartado.tarjeta : 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Créditos:</span> <span class="fw-bold">${formatter.format(dV.credito ? dV.credito.tarjeta : 0)}</span></div>
                    </div>
                `;
                updateTooltip('tooltip-pagos-metodos', pagosHtml);

                // Tablas 3 Top 10 Artículos (Más Vendidos por Categoría: Metal, Varios, Autos)
                renderTopTable('top-articulos-metal', data.topArticulosMetal);
                renderTopTable('top-articulos-varios', data.topArticulosVarios);
                renderTopTable('top-articulos-autos', data.topArticulosAutos);
                
                // Tabla Top 10 Mayor Margen %
                renderTopMargenTable('top-articulos-margen', data.topArticulosMargen);

                // Gráficos
                updateBarChart(data.chartVentasFamilia);
                updateDoughnutChart(data.chartMetodosPago);
            }

            function renderTopMargenTable(containerId, itemsArray) {
                const tbody = document.getElementById(containerId);
                if (!tbody) return;
                tbody.innerHTML = '';
                if (itemsArray && itemsArray.length > 0) {
                    itemsArray.forEach(item => {
                        const tr = document.createElement('tr');
                        const codPrenda = item.cod_prenda;
                        if (codPrenda) {
                            tr.className = 'cursor-pointer';
                            tr.title = 'Haz clic para ver marcas';
                            tr.innerHTML = `
                                <td class="ps-3 py-2 fw-bold text-dark small">
                                    <i class="bi bi-info-circle text-primary me-1"></i> ${escapeHtml(item.nombre)}
                                </td>
                                <td class="py-2 text-end text-success small fw-semibold">${formatter.format(item.utilidad)}</td>
                                <td class="pe-3 py-2 text-end fw-bold text-dark small">${(item.margen_prc || 0).toFixed(1)}%</td>
                            `;
                            tr.addEventListener('click', function() {
                                mostrarMarcas(codPrenda, item.nombre);
                            });
                        } else {
                            tr.innerHTML = `
                                <td class="ps-3 py-2 fw-bold text-dark small">${escapeHtml(item.nombre)}</td>
                                <td class="py-2 text-end text-success small fw-semibold">${formatter.format(item.utilidad)}</td>
                                <td class="pe-3 py-2 text-end fw-bold text-dark small">${(item.margen_prc || 0).toFixed(1)}%</td>
                            `;
                        }
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4 small">Sin datos registrados</td></tr>';
                }
            }

            function renderTopTable(containerId, itemsArray) {
                const tbody = document.getElementById(containerId);
                if (!tbody) return;
                tbody.innerHTML = '';
                if (itemsArray && itemsArray.length > 0) {
                    itemsArray.forEach(item => {
                        const tr = document.createElement('tr');
                        const codPrenda = item.cod_prenda;
                        if (codPrenda) {
                            tr.className = 'cursor-pointer';
                            tr.title = 'Haz clic para ver marcas';
                            tr.innerHTML = `
                                <td class="ps-3 py-2 fw-bold text-dark small">
                                    <i class="bi bi-info-circle text-primary me-1"></i> ${escapeHtml(item.nombre)}
                                </td>
                                <td class="py-2 text-end small">${numberFormatter.format(item.cantidad)}</td>
                                <td class="pe-3 py-2 text-end fw-bold text-primary small">${formatter.format(item.ventas)}</td>
                            `;
                            tr.addEventListener('click', function() {
                                mostrarMarcas(codPrenda, item.nombre);
                            });
                        } else {
                            tr.innerHTML = `
                                <td class="ps-3 py-2 fw-bold text-dark small">${escapeHtml(item.nombre)}</td>
                                <td class="py-2 text-end small">${numberFormatter.format(item.cantidad)}</td>
                                <td class="pe-3 py-2 text-end fw-bold text-primary small">${formatter.format(item.ventas)}</td>
                            `;
                        }
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4 small">Sin datos registrados</td></tr>';
                }
            }

            function updateBarChart(chartData) {
                const ctx = document.getElementById('ventasFamiliaChart');
                if (!ctx) return;

                if (ventasFamiliaChart) ventasFamiliaChart.destroy();

                if (!chartData) return;

                ventasFamiliaChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: [
                            {
                                label: 'Ventas Brutas',
                                data: chartData.ventas,
                                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                                borderRadius: 4
                            },
                            {
                                label: 'Utilidad Generada',
                                data: chartData.utilidades,
                                backgroundColor: 'rgba(25, 135, 84, 0.7)',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: value => formatter.format(value) }
                            }
                        }
                    }
                });
            }

            function updateDoughnutChart(chartData) {
                const ctx = document.getElementById('metodosPagoChart');
                if (!ctx) return;

                if (metodosPagoChart) metodosPagoChart.destroy();
                if (!chartData) return;

                metodosPagoChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            data: chartData.data,
                            backgroundColor: [
                                '#198754', // Efectivo
                                '#0d6efd', // Tarjeta
                                '#fd7e14'  // Transferencia
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return ' ' + context.label + ': ' + formatter.format(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/&/g, '&amp;')
                          .replace(/</g, '&lt;')
                          .replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;')
                          .replace(/'/g, '&#039;');
            }

            function mostrarMarcas(codPrenda, prendaNombre) {
                if (!codPrenda) return;
                const modalElement = document.getElementById('modalMarcas');
                const tbody = document.getElementById('tbody-marcas-top');
                const subtitle = document.getElementById('modal-subtitle');
                const label = document.getElementById('modalMarcasLabel');
                
                if (!modalElement || !tbody) return;

                label.innerHTML = `<i class="bi bi-tag-fill me-2"></i> Top 10 Marcas: ${escapeHtml(prendaNombre)}`;
                subtitle.innerText = `Mostrando las marcas con más operaciones de venta en el período.`;
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Cargando...</td></tr>';
                
                // Mostrar modal
                const modal = new bootstrap.Modal(modalElement);
                modal.show();

                const sucursalId = document.getElementById('sucursal_id').value;
                const fechaInicio = document.getElementById('fecha_inicio').value;
                const fechaFin = document.getElementById('fecha_fin').value;

                const params = new URLSearchParams({
                    cod_prenda: codPrenda,
                    sucursal_id: sucursalId,
                    fecha_inicio: fechaInicio,
                    fecha_fin: fechaFin
                }).toString();

                fetch(`{{ route('ventas.top-marcas') }}?${params}`)
                    .then(r => r.json())
                    .then(data => {
                        tbody.innerHTML = '';
                        if (!data || data.length === 0) {
                            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-4">No se encontraron marcas registradas para este artículo.</td></tr>`;
                            return;
                        }
                        data.forEach((item, index) => {
                            const tr = document.createElement('tr');
                            let badgeClass = 'bg-secondary';
                            if (index === 0) badgeClass = 'bg-warning text-dark';
                            else if (index === 1) badgeClass = 'bg-light text-dark border';
                            else if (index === 2) badgeClass = 'bg-danger text-white';
                            
                            tr.innerHTML = `
                                <td>
                                    <span class="badge ${badgeClass} rounded-pill me-2" style="width: 24px; display: inline-block; text-align: center;">${index + 1}</span>
                                    <span class="fw-semibold text-dark">${escapeHtml(item.marca || 'Desconocido')}</span>
                                </td>
                                <td class="text-center fw-bold">${numberFormatter.format(item.total)}</td>
                                <td class="text-end text-primary fw-bold">${formatter.format(item.monto)}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    })
                    .catch(err => {
                        console.error("Error cargando marcas:", err);
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">Error al cargar marcas</td></tr>`;
                    });
            }
        });
    </script>
@endsection