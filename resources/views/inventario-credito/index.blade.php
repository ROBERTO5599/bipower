@extends('employees.layouts.main')

@section('title', 'Inventario en Crédito')

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
        
        .metric-tooltip {
            position: relative;
            display: inline-block;
            border-bottom: 1px dashed #0d6efd;
            cursor: help;
        }
        .metric-tooltip:hover {
            color: #0d6efd;
        }
        .tooltip-inner {
            max-width: 380px !important;
            padding: 12px 16px !important;
            font-size: 0.82rem !important;
            background-color: #0f172a !important;
            color: #ffffff !important;
            border-radius: 10px !important;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5), 0 8px 10px -6px rgba(0,0,0,0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            text-align: left !important;
        }
        .tooltip-inner code {
            color: #38bdf8 !important;
            background-color: rgba(255,255,255,0.12);
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        .tooltip-inner hr {
            border-color: rgba(255,255,255,0.15) !important;
            margin: 6px 0 !important;
        }
        .tooltip-inner .text-muted {
            color: #cbd5e1 !important;
        }
        
        .badge-tipo {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
        
        .kpi-card {
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold">Analizando inventario en Crédito...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Inventario en Crédito</h4>
            <p class="text-muted">Control de la cartera de crédito activa, colocación de enganches, cobros y saldos vencidos</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                @if(auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin')
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Regional</label>
                    <select name="regional_user_id" id="regional_user_id" class="form-select" onchange="const url = new URL(window.location.href); if(this.value){ url.searchParams.set('regional_user_id', this.value); } else { url.searchParams.delete('regional_user_id'); } url.searchParams.delete('sucursal_id'); window.location.href = url.toString();">
                        <option value="">-- Todos los Regionales --</option>
                        @foreach($regionales ?? [] as $reg)
                            <option value="{{ $reg->id }}" {{ (isset($regionalUserId) && $regionalUserId == $reg->id) ? 'selected' : '' }}>
                                {{ $reg->nombre_completo }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="{{ (auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin') ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}" {{ request('sucursal_id') == $sucursal->id_valora_mas ? 'selected' : '' }}>
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ substr($fechaInicio, 0, 10) }}" class="form-control">
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

    <!-- ========== KPIs PRINCIPALES ========== -->
    <div class="row mb-4">
        <!-- KPI 1: INGRESOS TOTALES -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-success border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-ingresos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de ingresos...">
                                Ingresos
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-success text-success"><i class="bi bi-arrow-down-left-circle-fill"></i></div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ingresos">$ 0.00</h2>
                    <span class="text-muted small">Inv. Inicial + Enganche de Crédito</span>
                </div>
            </div>
        </div>

        <!-- KPI 2: EGRESOS TOTALES -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-danger border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-egresos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de egresos...">
                                Egresos
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-danger text-danger"><i class="bi bi-arrow-up-right-circle-fill"></i></div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-egresos">$ 0.00</h2>
                    <span class="text-muted small">Liquidación + Devolución</span>
                </div>
            </div>
        </div>

        <!-- KPI 3: TOTAL DE INVENTARIO EN CRÉDITOS -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-warning border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-inventario" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de inventario en créditos...">
                                Total de Inventario en Créditos
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-warning text-warning"><i class="bi bi-box-seam-fill"></i></div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-inventario-piso">$ 0.00</h2>
                    <span class="text-muted small">Mercancía activa con clientes</span>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Secundarios -->
    <div class="row mb-4">
        <div class="col-12 col-sm-6 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 kpi-card h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-articulos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando detalle de artículos...">
                                Artículos en Crédito <i class="bi bi-info-circle ms-1 text-info small"></i>
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-upc-scan fs-3"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-dark mb-0" id="kpi-total-articulos" style="font-size: 2rem;">0</h2>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 kpi-card h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-antiguedad" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando detalle de antigüedad...">
                                Antigüedad Promedio <i class="bi bi-info-circle ms-1 text-warning small"></i>
                            </span>
                        </h6>
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
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-rotacion" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando detalle de rotación...">
                                Rotación de Cobro <i class="bi bi-info-circle ms-1 text-success small"></i>
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-arrow-repeat fs-3"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-dark mb-0" id="kpi-rotacion" style="font-size: 2rem;">0.00x</h2>
                    <span class="text-muted small">Ritmo de recuperación</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== GRÁFICOS ========== -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Distribución por Antigüedad del Crédito</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="distribucionAntiguedadChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Créditos y Antigüedad por Sucursal</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="valorSucursalChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== TOP ARTÍCULOS MÁS ANTIGUOS ========== -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Ranking: Saldos de Crédito más antiguos</h5>
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
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        let distribucionAntiguedadChart = null;
        let valorSucursalChart = null;

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const numberFormatter = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');

        loadData();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            const formData = new FormData(form);
            const urlParams = new URLSearchParams(formData).toString();

            fetch(`{{ route('inventario-credito.data') }}?${urlParams}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(data => {
                    updateDashboard(data);
                })
                .catch(error => {
                    console.error("Error:", error);
                    showError();
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function showError() {
            const errorHtml = `
                <div class="alert alert-danger mx-4 mt-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Error al cargar los datos. Por favor, intente nuevamente o contacte al administrador.
                </div>
            `;
            document.querySelector('#dashboard-content .row:first-child').insertAdjacentHTML('afterend', errorHtml);
        }

        function updateElementText(id, text) {
            const el = document.getElementById(id);
            if (el) el.innerText = text;
        }

        function updateDashboard(data) {
            // ========== KPI 1: INGRESOS TOTALES ==========
            const ingresos = data.ingresosTotales || 0;
            updateElementText('kpi-ingresos', formatter.format(ingresos));

            // Tooltip Ingresos
            const tooltipIngresosEl = document.getElementById('tooltip-ingresos');
            if (tooltipIngresosEl && typeof bootstrap !== 'undefined') {
                let tooltipHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.83rem; line-height: 1.5; min-width: 270px;">
                        <div class="fw-bold mb-1 border-bottom pb-1 text-success"><i class="bi bi-arrow-down-left-circle-fill me-1"></i>Artículos y Enganches que Entraron</div>
                        <div class="mb-2">Suma del valor de los productos que se entregaron a crédito más los enganches pagados por los clientes.</div>
                        <div class="d-flex justify-content-between"><span>Valor de productos:</span> <span class="fw-bold text-success">${formatter.format(data.inventarioInicial || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Enganches cobrados:</span> <span class="fw-bold">${formatter.format(data.enganche || 0)}</span></div>
                        <div class="d-flex justify-content-between border-top pt-1 fw-bold"><span>Total entrante:</span> <span class="text-success">${formatter.format(ingresos)}</span></div>
                    </div>
                `;
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipIngresosEl);
                if (existingTooltip) existingTooltip.dispose();
                tooltipIngresosEl.setAttribute('data-bs-original-title', tooltipHtml);
                tooltipIngresosEl.setAttribute('title', tooltipHtml);
                new bootstrap.Tooltip(tooltipIngresosEl, { html: true, placement: 'top' });
            }

            // ========== KPI 2: EGRESOS TOTALES ==========
            const egresos = data.egresosTotales || 0;
            updateElementText('kpi-egresos', formatter.format(egresos));

            // Tooltip Egresos
            const tooltipEgresosEl = document.getElementById('tooltip-egresos');
            if (tooltipEgresosEl && typeof bootstrap !== 'undefined') {
                let tooltipHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.83rem; line-height: 1.5; min-width: 270px;">
                        <div class="fw-bold mb-1 border-bottom pb-1 text-danger"><i class="bi bi-arrow-up-right-circle-fill me-1"></i>Artículos Terminados o Devueltos</div>
                        <div class="mb-2">Mercancía que salió de crédito porque los clientes terminaron de pagarla o la devolvieron a la sucursal.</div>
                        <div class="d-flex justify-content-between"><span>Cuentas ya liquidadas:</span> <span class="fw-bold text-danger">${formatter.format(data.liquidacion || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Prendas devueltas:</span> <span class="fw-bold">${formatter.format(data.devolucion || 0)}</span></div>
                        <div class="d-flex justify-content-between border-top pt-1 fw-bold"><span>Total saliente:</span> <span class="text-danger">${formatter.format(egresos)}</span></div>
                    </div>
                `;
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipEgresosEl);
                if (existingTooltip) existingTooltip.dispose();
                tooltipEgresosEl.setAttribute('data-bs-original-title', tooltipHtml);
                tooltipEgresosEl.setAttribute('title', tooltipHtml);
                new bootstrap.Tooltip(tooltipEgresosEl, { html: true, placement: 'top' });
            }

            // ========== KPI 3: TOTAL DE INVENTARIO EN CRÉDITOS ==========
            const saldoPorCobrar = data.saldoPorCobrar || 0;
            
            updateElementText('kpi-total-inventario-piso', formatter.format(saldoPorCobrar));
            updateElementText('kpi-valor-inventario', formatter.format(data.valorTotalInventario || 0));
            updateElementText('kpi-valor-venta-total', formatter.format(data.valorVentaTotal || 0));

            // Tooltip Inventario
            const tooltipInventarioEl = document.getElementById('tooltip-inventario');
            if (tooltipInventarioEl && typeof bootstrap !== 'undefined') {
                let tooltipHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.83rem; line-height: 1.5; min-width: 270px;">
                        <div class="fw-bold mb-1 border-bottom pb-1 text-warning"><i class="bi bi-box-seam-fill me-1"></i>Mercancía Activa con Clientes</div>
                        <div class="mb-2">Valor total de los artículos que actualmente tienen los clientes en sus manos mientras continúan pagando su crédito.</div>
                        <div class="d-flex justify-content-between border-top pt-1 fw-bold"><span>Saldo en mercancía activa:</span> <span class="text-primary">${formatter.format(saldoPorCobrar)}</span></div>
                    </div>
                `;
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipInventarioEl);
                if (existingTooltip) existingTooltip.dispose();
                tooltipInventarioEl.setAttribute('data-bs-original-title', tooltipHtml);
                tooltipInventarioEl.setAttribute('title', tooltipHtml);
                new bootstrap.Tooltip(tooltipInventarioEl, { html: true, placement: 'top' });
            }

            // ========== KPIs SECUNDARIOS ==========
            updateElementText('kpi-total-articulos', numberFormatter.format(data.totalArticulosN || 0));
            const tooltipArticulosEl = document.getElementById('tooltip-articulos');
            if (tooltipArticulosEl && typeof bootstrap !== 'undefined') {
                const existing = bootstrap.Tooltip.getInstance(tooltipArticulosEl);
                if (existing) existing.dispose();
                tooltipArticulosEl.setAttribute('data-bs-original-title', `
                    <div class="text-start" style="font-size:0.83rem; line-height: 1.5; min-width: 260px;">
                        <div class="fw-bold mb-1 border-bottom pb-1 text-info"><i class="bi bi-upc-scan me-1"></i>Artículos Financiados</div>
                        <div class="mb-2">Cantidad total de piezas o productos (joyas, varios, autos) que los clientes se llevaron a crédito y siguen pagando.</div>
                        <div class="d-flex justify-content-between border-top pt-1 fw-bold"><span>Total de artículos:</span> <span class="text-info">${numberFormatter.format(data.totalArticulosN || 0)} piezas</span></div>
                    </div>
                `);
                new bootstrap.Tooltip(tooltipArticulosEl, { html: true, placement: 'top' });
            }
            
            updateElementText('kpi-antiguedad-promedio', `${numberFormatter.format(data.antiguedadPromedioDias || 0)} días`);
            const tooltipAntiguedadEl = document.getElementById('tooltip-antiguedad');
            if (tooltipAntiguedadEl && typeof bootstrap !== 'undefined') {
                const existing = bootstrap.Tooltip.getInstance(tooltipAntiguedadEl);
                if (existing) existing.dispose();
                tooltipAntiguedadEl.setAttribute('data-bs-original-title', `
                    <div class="text-start" style="font-size:0.83rem; line-height: 1.5; min-width: 260px;">
                        <div class="fw-bold mb-1 border-bottom pb-1 text-warning"><i class="bi bi-hourglass-split me-1"></i>Tiempo Promedio con el Crédito</div>
                        <div class="mb-2">Días que en promedio llevan los clientes con sus artículos desde que se les entregó el crédito.</div>
                        <div class="d-flex justify-content-between"><span>Con más de 1 mes:</span> <span class="fw-bold">${(data.porcentajeMas30 || 0).toFixed(1)}%</span></div>
                        <div class="d-flex justify-content-between"><span>Con más de 2 meses:</span> <span class="fw-bold">${(data.porcentajeMas60 || 0).toFixed(1)}%</span></div>
                        <div class="d-flex justify-content-between"><span>Con más de 3 meses:</span> <span class="fw-bold text-danger">${(data.porcentajeMas90 || 0).toFixed(1)}%</span></div>
                        <div class="d-flex justify-content-between border-top pt-1 fw-bold"><span>Promedio general:</span> <span class="text-warning">${numberFormatter.format(data.antiguedadPromedioDias || 0)} días</span></div>
                    </div>
                `);
                new bootstrap.Tooltip(tooltipAntiguedadEl, { html: true, placement: 'top' });
            }

            updateElementText('kpi-porcentaje-30', `${(data.porcentajeMas30 || 0).toFixed(1)}%`);
            updateElementText('kpi-porcentaje-60', `${(data.porcentajeMas60 || 0).toFixed(1)}%`);
            updateElementText('kpi-porcentaje-90', `${(data.porcentajeMas90 || 0).toFixed(1)}%`);

            updateElementText('kpi-rotacion', `${(data.rotacionInventario || 0).toFixed(2)}x`);
            const tooltipRotacionEl = document.getElementById('tooltip-rotacion');
            if (tooltipRotacionEl && typeof bootstrap !== 'undefined') {
                const existing = bootstrap.Tooltip.getInstance(tooltipRotacionEl);
                if (existing) existing.dispose();
                tooltipRotacionEl.setAttribute('data-bs-original-title', `
                    <div class="text-start" style="font-size:0.83rem; line-height: 1.5; min-width: 260px;">
                        <div class="fw-bold mb-1 border-bottom pb-1 text-success"><i class="bi bi-arrow-repeat me-1"></i>Velocidad de Cobro</div>
                        <div class="mb-2">Mide qué tan rápido se recupera en abonos cobrados el valor de los artículos que están en la calle.</div>
                        <div class="d-flex justify-content-between"><span>Abonos cobrados:</span> <span class="fw-bold text-success">${formatter.format(data.pagoCredito || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Mercancía en la calle:</span> <span class="fw-bold">${formatter.format(saldoPorCobrar)}</span></div>
                        <div class="d-flex justify-content-between border-top pt-1 fw-bold"><span>Ritmo de cobro:</span> <span class="text-success">${(data.rotacionInventario || 0).toFixed(2)} veces</span></div>
                    </div>
                `);
                new bootstrap.Tooltip(tooltipRotacionEl, { html: true, placement: 'top' });
            }

            // Tabla de artículos añejos
            const tbody = document.getElementById('top-articulos-body');
            tbody.innerHTML = '';
            if (data.topArticulosAnejos && data.topArticulosAnejos.length > 0) {
                data.topArticulosAnejos.forEach(item => {
                    const tr = document.createElement('tr');
                    const codPrenda = item.cod_prenda;
                    let badgeClass = item.dias > 90 ? 'bg-danger' : (item.dias > 60 ? 'bg-warning text-dark' : 'bg-secondary');
                    
                    if (codPrenda) {
                        tr.className = 'cursor-pointer';
                        tr.title = 'Haz clic para ver marcas';
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
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay datos para mostrar</td></tr>';
            }

            // Gráficos
            if (data.chartDistribucionAntiguedad) {
                updateStackedBarChart(data.chartDistribucionAntiguedad);
            }
            if (data.chartValorAntiguedadSucursal) {
                updateMixedChart(data.chartValorAntiguedadSucursal);
            }
        }

        function updateStackedBarChart(chartData) {
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
                                label: function(context) {
                                    return `${context.dataset.label}: ${numberFormatter.format(context.raw)} créditos`;
                                }
                            }
                        }
                    },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Número de Créditos' } } }
                }
            });
        }

        function updateMixedChart(chartData) {
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
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Antigüedad Prom. (días)') {
                                        return `${context.dataset.label}: ${context.raw.toFixed(1)} días`;
                                    } else {
                                        return `${context.dataset.label}: ${formatter.format(context.raw)}`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            ticks: { callback: value => formatter.format(value) },
                            title: { display: true, text: 'Saldo por Cobrar ($)' }
                        },
                        y1: { 
                            position: 'right', 
                            grid: { drawOnChartArea: false },
                            ticks: { callback: value => value + ' d' },
                            title: { display: true, text: 'Antigüedad Promedio (días)' }
                        }
                    }
                }
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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
            tbodyMarcas.innerHTML = '<tr><td colspan="3" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Cargando...</td></tr>';
            
            // Mostrar modal
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            const sucursalId = document.getElementById('sucursal_id').value;

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
