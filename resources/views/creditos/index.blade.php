@extends('employees.layouts.main')

@section('title', 'Créditos y Cartera')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease;
        }
        .icon-shape {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            border-radius: 50%;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.12); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.12); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.12); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.15); }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.12); }
        .bg-light-secondary { background-color: rgba(108, 117, 125, 0.12); }

        .table-responsive { overflow-x: auto; }

        /* Estilo de Pestañas Principales */
        .creditos-nav-pills {
            background: #ffffff;
            border-radius: 16px;
            padding: 6px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .creditos-nav-pills .nav-link {
            border-radius: 12px;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 24px;
            transition: all 0.25s ease;
            border: none;
        }
        .creditos-nav-pills .nav-link:hover {
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.05);
        }
        .creditos-nav-pills .nav-link.active {
            background: #0d6efd;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
        }

        /* Tooltip de desglose */
        .metric-tooltip {
            position: relative;
            display: inline-block;
            border-bottom: 1px dotted #6c757d;
            cursor: help;
        }

        /* KPI Card */
        .kpi-card {
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
        }

        /* Spinner Loading */
        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(3px);
        }
        .spinner-border { width: 3rem; height: 3rem; }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold" id="loading-text">Cargando información de Créditos y Cartera...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <!-- Encabezado Principal -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h3 class="title fw-bold text-dark mb-1">
                <i class="bi bi-credit-card-2-front-fill text-primary me-2"></i>Gestión de Créditos y Cartera
            </h3>
            <p class="text-muted mb-0">Control unificado de colocación, cobranza, morosidad e inventario de artículos financiados</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-light text-secondary border px-3 py-2 fs-7" id="active-range-badge">
                <i class="bi bi-calendar3 me-1"></i> Rango Activo
            </span>
        </div>
    </div>

    <!-- Filtros Inteligentes Unificados -->
    <div class="card shadow-sm border-0 mb-4 rounded-4 bg-white">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select border-2 rounded-3">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small text-uppercase">Rango Rápido</label>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-secondary quick-range-btn active" data-range="este-mes">Este Mes</button>
                        <button type="button" class="btn btn-outline-secondary quick-range-btn" data-range="mes-anterior">Mes Anterior</button>
                        <button type="button" class="btn btn-outline-secondary quick-range-btn" data-range="este-anio">Este Año</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-muted small text-uppercase">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ substr($fechaInicio, 0, 10) }}" class="form-control border-2 rounded-3">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-muted small text-uppercase">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control border-2 rounded-3">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3" title="Aplicar Filtros">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pestañas de Navegación Unificadas -->
    <ul class="nav creditos-nav-pills nav-fill mb-4" id="creditosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-cartera-btn" data-bs-toggle="pill" data-bs-target="#tab-cartera-pane" type="button" role="tab" aria-controls="tab-cartera-pane" aria-selected="true">
                <i class="bi bi-wallet2 me-2"></i>1. Cartera y Cobranza (Créditos)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-inventario-btn" data-bs-toggle="pill" data-bs-target="#tab-inventario-pane" type="button" role="tab" aria-controls="tab-inventario-pane" aria-selected="false">
                <i class="bi bi-box-seam-fill me-2"></i>2. Inventario de Artículos en Crédito
            </button>
        </li>
    </ul>

    <!-- Contenido de las Pestañas -->
    <div class="tab-content" id="creditosTabsContent">

        <!-- ============================================================= -->
        <!-- PESTAÑA 1: CARTERA Y COBRANZA                                 -->
        <!-- ============================================================= -->
        <div class="tab-pane fade show active" id="tab-cartera-pane" role="tabpanel" aria-labelledby="tab-cartera-btn">
            <!-- KPIs Principales Cartera -->
            <div class="row mb-4 justify-content-center">
                <!-- Saldo Cartera -->
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Saldo Cartera Créditos</h6>
                                <div class="icon-shape bg-light-primary text-primary">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-saldo-cartera">$ 0.00</h2>
                            <span class="text-muted small">Al término del periodo</span>
                        </div>
                    </div>
                </div>

                <!-- Colocación -->
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Colocación en el Periodo</h6>
                                <div class="icon-shape bg-light-info text-info">
                                    <i class="bi bi-rocket-takeoff-fill"></i>
                                </div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-colocacion-monto">$ 0.00</h2>
                            <span class="text-muted small">
                                <span class="fw-bold text-dark" id="kpi-colocacion-cantidad">0</span> créditos nuevos
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Morosidad -->
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Índice de Morosidad</h6>
                                <div class="icon-shape bg-light-danger text-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                            </div>
                            <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-indice-morosidad">0.0%</h2>
                            <div class="mt-2 text-muted small">
                                Saldo Vencido: <span class="fw-bold text-danger" id="kpi-saldo-vencido">$ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPIs Secundarios Cartera -->
            <div class="row mb-4">
                <!-- Recuperación vs Otorgamiento -->
                <div class="col-12 col-xl-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-shape bg-light-success text-success me-3">
                                    <i class="bi bi-piggy-bank-fill"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Recuperación vs Otorgamiento</h6>
                                    <h3 class="fw-bold text-dark mb-0"><span id="kpi-recuperacion-pct" class="text-success">0.0%</span></h3>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Capital Cobrado</small>
                                    <span class="fw-bold text-success" id="kpi-capital-cobrado">$ 0.00</span>
                                </div>
                                <div class="col-6 border-start">
                                    <small class="text-muted d-block">Capital Otorgado</small>
                                    <span class="fw-bold text-dark" id="kpi-capital-otorgado">$ 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Intereses / Rendimiento -->
                <div class="col-12 col-xl-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-shape bg-light-warning text-warning me-3">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Rentabilidad (Intereses)</h6>
                                    <h3 class="fw-bold text-dark mb-0" id="kpi-intereses-generados">$ 0.00</h3>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Intereses Cobrados</small>
                                    <span class="fw-bold text-success" id="kpi-intereses-cobrados">$ 0.00</span>
                                </div>
                                <div class="col-6 border-start">
                                    <small class="text-muted d-block">Tasa Efectiva de Rend.</small>
                                    <span class="fw-bold text-primary" id="kpi-tasa-efectiva">0.0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos Cartera -->
            <div class="row mb-4">
                <div class="col-md-8 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Evolución de Saldo y Colocación</h5>
                        </div>
                        <div class="card-body p-4 d-flex justify-content-center align-items-center">
                            <canvas id="saldoColocacionChart" height="260"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Distribución por Mora (Días de Atraso)</h5>
                        </div>
                        <div class="card-body p-4 d-flex justify-content-center align-items-center">
                            <canvas id="morosidadChart" height="260"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Detalle de Créditos -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">Detalle de Créditos y Estatus</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Cliente</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Monto Original</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Saldo Actual</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Intereses Gen.</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Estatus</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-creditos-body">
                                        <tr><td colspan="6" class="text-center text-muted py-4">Cargando datos...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- PESTAÑA 2: INVENTARIO DE ARTÍCULOS EN CRÉDITO                 -->
        <!-- ============================================================= -->
        <div class="tab-pane fade" id="tab-inventario-pane" role="tabpanel" aria-labelledby="tab-inventario-btn">
            <!-- KPIs Principales Inventario -->
            <div class="row mb-4">
                <!-- Ingresos Totales -->
                <div class="col-12 col-md-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-success border-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                    <span class="metric-tooltip" id="tooltip-ingresos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de ingresos...">
                                        Ingresos en Crédito
                                    </span>
                                </h6>
                                <div class="icon-shape bg-light-success text-success"><i class="bi bi-arrow-down-left-circle-fill"></i></div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ingresos">$ 0.00</h2>
                            <span class="text-muted small">Inv. Inicial + Enganche de Crédito</span>
                        </div>
                    </div>
                </div>

                <!-- Egresos Totales -->
                <div class="col-12 col-md-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-danger border-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                    <span class="metric-tooltip" id="tooltip-egresos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de egresos...">
                                        Egresos en Crédito
                                    </span>
                                </h6>
                                <div class="icon-shape bg-light-danger text-danger"><i class="bi bi-arrow-up-right-circle-fill"></i></div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-egresos">$ 0.00</h2>
                            <span class="text-muted small">Liquidación + Devolución</span>
                        </div>
                    </div>
                </div>

                <!-- Saldo Neto / Total Inventario en Créditos -->
                <div class="col-12 col-md-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-warning border-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                                    <span class="metric-tooltip" id="tooltip-inventario" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de inventario en créditos...">
                                        Total Inventario en Crédito
                                    </span>
                                </h6>
                                <div class="icon-shape bg-light-warning text-warning"><i class="bi bi-box-seam-fill"></i></div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-inventario-piso">$ 0.00</h2>
                            <span class="text-muted small">Ingresos - Egresos (Saldo Activo)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPIs Secundarios Inventario -->
            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 kpi-card h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Artículos Financiados</h6>
                                <div class="icon-shape bg-light-info text-info">
                                    <i class="bi bi-upc-scan fs-3"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-dark mb-0" id="kpi-total-articulos" style="font-size: 2rem;">0</h2>
                            <span class="text-muted small">Prendas con saldo activo</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 kpi-card h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Antigüedad Promedio</h6>
                                <div class="icon-shape bg-light-warning text-warning">
                                    <i class="bi bi-hourglass-split fs-3"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-dark mb-0" id="kpi-antiguedad-promedio" style="font-size: 2rem;">0 días</h2>
                            <div class="mt-2 text-muted small">
                                >30d: <span id="kpi-porcentaje-30">0%</span> | >60d: <span id="kpi-porcentaje-60">0%</span> | >90d: <span id="kpi-porcentaje-90">0%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 kpi-card h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Rotación de Cobro</h6>
                                <div class="icon-shape bg-light-success text-success">
                                    <i class="bi bi-arrow-repeat fs-3"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-dark mb-0" id="kpi-rotacion" style="font-size: 2rem;">0.00x</h2>
                            <span class="text-muted small">Cobros / Cartera Promedio</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos Inventario -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Distribución por Antigüedad del Crédito</h5>
                        </div>
                        <div class="card-body p-4">
                            <canvas id="distribucionAntiguedadChart" height="260"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Créditos y Antigüedad por Sucursal</h5>
                        </div>
                        <div class="card-body p-4">
                            <canvas id="valorSucursalChart" height="260"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Ranking Artículos Añejos -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">Ranking: Saldos de Crédito Más Antiguos</h5>
                            <span class="badge bg-light text-secondary border">Haz clic en un artículo para ver marcas</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3">Artículo Financiado</th>
                                            <th class="py-3">Familia</th>
                                            <th class="py-3">Sucursal</th>
                                            <th class="py-3 text-end">Saldo Deudor</th>
                                            <th class="pe-4 py-3 text-center">Días Activo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-articulos-body">
                                        <tr><td colspan="5" class="text-center text-muted py-4">Cargando datos...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Top 10 Marcas -->
<div class="modal fade" id="modalMarcas" tabindex="-1" aria-labelledby="modalMarcasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="modalMarcasLabel">
                    <i class="bi bi-tag-fill me-2"></i> Top 10 Marcas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 text-muted" id="modal-subtitle" style="font-size: 0.9rem;">
                    Mostrando las marcas más financiadas para este artículo.
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-uppercase text-muted small fw-bold">Marca</th>
                                <th class="text-uppercase text-muted small fw-bold text-center">Créditos</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Saldo Deudor</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-marcas-top">
                            <tr><td colspan="3" class="text-center py-4">Cargando marcas...</td></tr>
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

        // Formatters
        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2 });
        const numberFormatter = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

        // DOM elements
        const overlay = document.getElementById('loading-overlay');
        const loadingText = document.getElementById('loading-text');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const inputInicio = document.getElementById('fecha_inicio');
        const inputFin = document.getElementById('fecha_fin');
        const sucursalSelect = document.getElementById('sucursal_id');
        const activeRangeBadge = document.getElementById('active-range-badge');

        // Charts instances
        let saldoColocacionChart = null;
        let morosidadChart = null;
        let distribucionAntiguedadChart = null;
        let valorSucursalChart = null;

        // Flags para controlar carga
        let carteraLoaded = false;
        let inventarioLoaded = false;

        // Check if URL has tab=inventario
        const urlParamsInitial = new URLSearchParams(window.location.search);
        if (urlParamsInitial.get('tab') === 'inventario') {
            const inventarioTabBtn = document.getElementById('tab-inventario-btn');
            if (inventarioTabBtn) {
                const tabInstance = new bootstrap.Tab(inventarioTabBtn);
                tabInstance.show();
            }
        }

        // Quick range buttons
        document.querySelectorAll('.quick-range-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const range = this.dataset.range;
                const now = new Date();

                if (range === 'este-mes') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                    inputInicio.value = formatDateString(firstDay);
                    inputFin.value = formatDateString(now);
                } else if (range === 'mes-anterior') {
                    const firstDayPrev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const lastDayPrev = new Date(now.getFullYear(), now.getMonth(), 0);
                    inputInicio.value = formatDateString(firstDayPrev);
                    inputFin.value = formatDateString(lastDayPrev);
                } else if (range === 'este-anio') {
                    const firstDayYear = new Date(now.getFullYear(), 0, 1);
                    inputInicio.value = formatDateString(firstDayYear);
                    inputFin.value = formatDateString(now);
                }

                loadAllData();
            });
        });

        function formatDateString(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
            loadAllData();
        });

        // Tab change events: resize charts when tab is revealed
        document.querySelectorAll('#creditosTabs button[data-bs-toggle="pill"]').forEach(tabBtn => {
            tabBtn.addEventListener('shown.bs.tab', function(e) {
                if (e.target.id === 'tab-cartera-btn') {
                    if (saldoColocacionChart) saldoColocacionChart.resize();
                    if (morosidadChart) morosidadChart.resize();
                    if (!carteraLoaded) loadCarteraData();
                } else if (e.target.id === 'tab-inventario-btn') {
                    if (distribucionAntiguedadChart) distribucionAntiguedadChart.resize();
                    if (valorSucursalChart) valorSucursalChart.resize();
                    if (!inventarioLoaded) loadInventarioData();
                }
            });
        });

        // Initial load
        loadAllData();

        function loadAllData() {
            carteraLoaded = false;
            inventarioLoaded = false;
            
            overlay.style.display = 'flex';
            loadingText.innerText = 'Cargando información de Créditos y Cartera...';
            dashboard.style.opacity = '0.3';

            const sucursalText = sucursalSelect.options[sucursalSelect.selectedIndex].text;
            activeRangeBadge.innerHTML = `<i class="bi bi-calendar3 me-1"></i> Rango: ${inputInicio.value} al ${inputFin.value} | ${sucursalText}`;

            // Load both datasets concurrently
            Promise.allSettled([
                fetchCartera(),
                fetchInventario()
            ]).finally(() => {
                overlay.style.display = 'none';
                dashboard.style.display = 'block';
                dashboard.style.opacity = '1';
            });
        }

        function getQueryParams() {
            const formData = new FormData(form);
            return new URLSearchParams(formData).toString();
        }

        // ---------------- CARTERA FETCH & RENDER ----------------
        function fetchCartera() {
            const params = getQueryParams();
            return fetch(`{{ route('creditos.data') }}?${params}`)
                .then(res => {
                    if (!res.ok) throw new Error('Error al cargar datos de cartera');
                    return res.json();
                })
                .then(data => {
                    updateCarteraUI(data);
                    carteraLoaded = true;
                })
                .catch(err => {
                    console.error("Error en Cartera:", err);
                });
        }

        function loadCarteraData() {
            overlay.style.display = 'flex';
            loadingText.innerText = 'Actualizando Cartera y Cobranza...';
            fetchCartera().finally(() => {
                overlay.style.display = 'none';
            });
        }

        function updateElementText(id, text) {
            const el = document.getElementById(id);
            if (el) el.innerText = text;
        }

        function updateCarteraUI(data) {
            // KPIs Principales
            updateElementText('kpi-saldo-cartera', formatter.format(data.saldoCartera || 0));
            updateElementText('kpi-colocacion-monto', formatter.format(data.creditosNuevosMonto || 0));
            updateElementText('kpi-colocacion-cantidad', numberFormatter.format(data.creditosNuevosCantidad || 0));
            
            updateElementText('kpi-indice-morosidad', `${(data.indiceMorosidad || 0).toFixed(2)}%`);
            updateElementText('kpi-saldo-vencido', formatter.format(data.saldoVencido || 0));
            
            updateElementText('kpi-recuperacion-pct', `${(data.recuperacionPorcentaje || 0).toFixed(1)}%`);
            updateElementText('kpi-capital-cobrado', formatter.format(data.capitalCobrado || 0));
            updateElementText('kpi-capital-otorgado', formatter.format(data.capitalOtorgado || 0));

            updateElementText('kpi-intereses-generados', formatter.format(data.interesesGenerados || 0));
            updateElementText('kpi-intereses-cobrados', formatter.format(data.interesesCobrados || 0));
            updateElementText('kpi-tasa-efectiva', `${(data.tasaEfectivaRendimiento || 0).toFixed(2)}%`);

            // Tabla Detalle de Créditos
            const tbody = document.getElementById('top-creditos-body');
            if (data.topCreditos && data.topCreditos.length > 0) {
                let html = '';
                data.topCreditos.forEach(c => {
                    let badgeClass = c.estatus == 1 ? 'bg-success' : (c.estatus == 2 ? 'bg-warning text-dark' : 'bg-secondary');
                    html += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${escapeHtml(c.cliente || 'Desconocido')}</td>
                            <td class="py-3 text-muted">${escapeHtml(c.sucursal || '')}</td>
                            <td class="py-3 text-end">${formatter.format(c.monto_original || 0)}</td>
                            <td class="py-3 text-end text-danger fw-bold">${formatter.format(c.saldo_actual || 0)}</td>
                            <td class="py-3 text-end text-success">${formatter.format(c.intereses || 0)}</td>
                            <td class="pe-4 py-3 text-end"><span class="badge ${badgeClass} rounded-pill px-3 py-2">${c.estatus == 1 ? 'Al Corriente' : (c.estatus == 2 ? 'Con Mora' : 'Estatus ' + c.estatus)}</span></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay créditos registrados para este periodo</td></tr>';
            }

            // Gráficos Cartera
            renderMorosidadChart(data.chartCarteraMora);
            renderSaldoColocacionChart(data.chartSaldoColocacion);
        }

        function renderMorosidadChart(chartData) {
            const ctx = document.getElementById('morosidadChart');
            if (!ctx) return;
            if (morosidadChart) morosidadChart.destroy();
            if (!chartData || !chartData.data) return;

            morosidadChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels || ['0-30 días', '31-60 días', '61-90 días', '90+ días'],
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#198754', '#ffc107', '#fd7e14', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${formatter.format(ctx.raw)}`
                            }
                        }
                    }
                }
            });
        }

        function renderSaldoColocacionChart(chartData) {
            const ctx = document.getElementById('saldoColocacionChart');
            if (!ctx) return;
            if (saldoColocacionChart) saldoColocacionChart.destroy();
            if (!chartData || !chartData.labels) return;

            saldoColocacionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'line',
                            label: 'Saldo Cartera',
                            data: chartData.saldo || [],
                            borderColor: '#0d6efd',
                            backgroundColor: '#0d6efd',
                            tension: 0.1,
                            fill: false
                        },
                        {
                            type: 'bar',
                            label: 'Colocación',
                            data: chartData.colocacion || [],
                            backgroundColor: 'rgba(25, 135, 84, 0.75)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            ticks: { callback: v => formatter.format(v) }
                        }
                    }
                }
            });
        }

        // ---------------- INVENTARIO FETCH & RENDER ----------------
        function fetchInventario() {
            const params = getQueryParams();
            return fetch(`{{ route('inventario-credito.data') }}?${params}`)
                .then(res => {
                    if (!res.ok) throw new Error('Error al cargar datos de inventario');
                    return res.json();
                })
                .then(data => {
                    updateInventarioUI(data);
                    inventarioLoaded = true;
                })
                .catch(err => {
                    console.error("Error en Inventario:", err);
                });
        }

        function loadInventarioData() {
            overlay.style.display = 'flex';
            loadingText.innerText = 'Actualizando Inventario de Artículos...';
            fetchInventario().finally(() => {
                overlay.style.display = 'none';
            });
        }

        function updateInventarioUI(data) {
            // Ingresos
            const ingresos = data.ingresosTotales || 0;
            updateElementText('kpi-ingresos', formatter.format(ingresos));
            setCustomTooltip('tooltip-ingresos', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Desglose de Ingresos:</strong>
                    <div class="d-flex justify-content-between"><span>Inventario Inicial Crédito:</span> <span class="fw-bold text-success">${formatter.format(data.inventarioInicial || 0)}</span></div>
                    <div class="d-flex justify-content-between"><span>Enganche de Crédito (+):</span> <span class="fw-bold">${formatter.format(data.enganche || 0)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>INGRESOS TOTALES</span> <span class="text-success">${formatter.format(ingresos)}</span></div>
                </div>
            `);

            // Egresos
            const egresos = data.egresosTotales || 0;
            updateElementText('kpi-egresos', formatter.format(egresos));
            setCustomTooltip('tooltip-egresos', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Desglose de Egresos:</strong>
                    <div class="d-flex justify-content-between"><span>Liquidación de Crédito (-):</span> <span class="fw-bold text-danger">${formatter.format(data.liquidacion || 0)}</span></div>
                    <div class="d-flex justify-content-between"><span>Devolución de Crédito (-):</span> <span class="fw-bold">${formatter.format(data.devolucion || 0)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>EGRESOS TOTALES</span> <span class="text-danger">${formatter.format(egresos)}</span></div>
                </div>
            `);

            // Total Inventario en Créditos
            const saldoPorCobrar = data.saldoPorCobrar || 0;
            updateElementText('kpi-total-inventario-piso', formatter.format(saldoPorCobrar));
            setCustomTooltip('tooltip-inventario', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 280px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Flujo Neto en Créditos:</strong>
                    <div class="d-flex justify-content-between"><span>Ingresos Totales (+):</span> <span class="fw-bold text-success">${formatter.format(ingresos)}</span></div>
                    <div class="d-flex justify-content-between"><span>Egresos Totales (-):</span> <span class="fw-bold text-danger">${formatter.format(egresos)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>NETO CRÉDITOS</span> <span class="text-primary">${formatter.format(saldoPorCobrar)}</span></div>
                </div>
            `);

            // KPIs Secundarios
            updateElementText('kpi-total-articulos', numberFormatter.format(data.totalArticulosN || 0));
            updateElementText('kpi-antiguedad-promedio', `${numberFormatter.format(data.antiguedadPromedioDias || 0)} días`);
            updateElementText('kpi-porcentaje-30', `${(data.porcentajeMas30 || 0).toFixed(1)}%`);
            updateElementText('kpi-porcentaje-60', `${(data.porcentajeMas60 || 0).toFixed(1)}%`);
            updateElementText('kpi-porcentaje-90', `${(data.porcentajeMas90 || 0).toFixed(1)}%`);
            updateElementText('kpi-rotacion', `${(data.rotacionInventario || 0).toFixed(2)}x`);

            // Tabla de Artículos Añejos
            const tbody = document.getElementById('top-articulos-body');
            tbody.innerHTML = '';
            if (data.topArticulosAnejos && data.topArticulosAnejos.length > 0) {
                data.topArticulosAnejos.forEach(item => {
                    const tr = document.createElement('tr');
                    const codPrenda = item.cod_prenda;
                    let badgeClass = item.dias > 90 ? 'bg-danger' : (item.dias > 60 ? 'bg-warning text-dark' : 'bg-secondary');

                    if (codPrenda) {
                        tr.className = 'cursor-pointer';
                        tr.title = 'Haz clic para ver marcas más financiadas';
                        tr.innerHTML = `
                            <td class="ps-4 py-3 fw-bold text-dark">
                                <i class="bi bi-info-circle text-primary me-1"></i> ${escapeHtml(item.articulo || item.id)}
                            </td>
                            <td class="py-3 text-muted">${escapeHtml(item.familia || '')}</td>
                            <td class="py-3">${escapeHtml(item.sucursal || '')}</td>
                            <td class="py-3 text-end fw-bold text-success">${formatter.format(item.valor || 0)}</td>
                            <td class="pe-4 py-3 text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3 py-2">${item.dias || 0} días</span>
                            </td>
                        `;
                        tr.addEventListener('click', function() {
                            mostrarMarcas(codPrenda, item.articulo || item.id);
                        });
                    } else {
                        tr.innerHTML = `
                            <td class="ps-4 py-3 fw-bold text-dark">${escapeHtml(item.articulo || item.id)}</td>
                            <td class="py-3 text-muted">${escapeHtml(item.familia || '')}</td>
                            <td class="py-3">${escapeHtml(item.sucursal || '')}</td>
                            <td class="py-3 text-end fw-bold text-success">${formatter.format(item.valor || 0)}</td>
                            <td class="pe-4 py-3 text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3 py-2">${item.dias || 0} días</span>
                            </td>
                        `;
                    }
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay artículos añejos registrados</td></tr>';
            }

            // Gráficos Inventario
            renderDistribucionAntiguedadChart(data.chartDistribucionAntiguedad);
            renderValorSucursalChart(data.chartValorAntiguedadSucursal);
        }

        function setCustomTooltip(id, html) {
            const el = document.getElementById(id);
            if (el && typeof bootstrap !== 'undefined') {
                const existing = bootstrap.Tooltip.getInstance(el);
                if (existing) existing.dispose();
                el.setAttribute('data-bs-original-title', html);
                el.setAttribute('title', html);
                new bootstrap.Tooltip(el, { html: true, placement: 'top' });
            }
        }

        function renderDistribucionAntiguedadChart(chartData) {
            const ctx = document.getElementById('distribucionAntiguedadChart');
            if (!ctx) return;
            if (distribucionAntiguedadChart) distribucionAntiguedadChart.destroy();
            if (!chartData || !chartData.labels) return;

            distribucionAntiguedadChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        { label: 'Créditos Activos', data: chartData.data_varios || [0,0,0,0], backgroundColor: '#0dcaf0', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'bottom' },
                        tooltip: { 
                            callbacks: {
                                label: ctx => `${ctx.dataset.label}: ${numberFormatter.format(ctx.raw)} prendas`
                            }
                        }
                    },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Cantidad de Prendas' } } }
                }
            });
        }

        function renderValorSucursalChart(chartData) {
            const ctx = document.getElementById('valorSucursalChart');
            if (!ctx) return;
            if (valorSucursalChart) valorSucursalChart.destroy();
            if (!chartData || !chartData.labels) return;

            valorSucursalChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        { type: 'line', label: 'Antigüedad Prom. (días)', data: chartData.antiguedad || [], borderColor: '#dc3545', backgroundColor: 'transparent', borderWidth: 3, yAxisID: 'y1', tension: 0.1, fill: false, pointRadius: 4, pointBackgroundColor: '#dc3545' },
                        { type: 'bar', label: 'Saldo por Cobrar', data: chartData.valores || [], backgroundColor: '#0d6efd', borderRadius: 4, yAxisID: 'y' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { 
                            ticks: { callback: v => formatter.format(v) },
                            title: { display: true, text: 'Saldo por Cobrar ($)' }
                        },
                        y1: { 
                            position: 'right', 
                            grid: { drawOnChartArea: false },
                            ticks: { callback: v => v + ' d' },
                            title: { display: true, text: 'Antigüedad Promedio (días)' }
                        }
                    }
                }
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function mostrarMarcas(codPrenda, prendaNombre) {
            if (!codPrenda) return;
            const modalElement = document.getElementById('modalMarcas');
            const tbodyMarcas = document.getElementById('tbody-marcas-top');
            const subtitle = document.getElementById('modal-subtitle');
            const label = document.getElementById('modalMarcasLabel');
            
            if (!modalElement || !tbodyMarcas) return;

            label.innerHTML = `<i class="bi bi-tag-fill me-2"></i> Top 10 Marcas: ${escapeHtml(prendaNombre)}`;
            subtitle.innerText = `Mostrando las marcas más financiadas para este artículo.`;
            tbodyMarcas.innerHTML = '<tr><td colspan="3" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Cargando marcas...</td></tr>';
            
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            const sucursalId = sucursalSelect.value;
            const params = new URLSearchParams({
                cod_prenda: codPrenda,
                sucursal_id: sucursalId
            }).toString();

            fetch(`{{ route('inventario-credito.top-marcas') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    tbodyMarcas.innerHTML = '';
                    if (!data || data.length === 0) {
                        tbodyMarcas.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-4">No se encontraron marcas registradas para este artículo.</td></tr>`;
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
                        tbodyMarcas.appendChild(tr);
                    });
                })
                .catch(err => {
                    console.error("Error cargando marcas:", err);
                    tbodyMarcas.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">Error al cargar marcas</td></tr>`;
                });
        }
    });
</script>
@endsection
