@extends('employees.layouts.main')

@section('title', 'Inventario y Piso de Venta')

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
    <h5 class="text-muted fw-bold">Analizando inventario...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Inventario y Piso de Venta(DEPOSITARIA)</h4>
            <p class="text-muted">Control de valor, rotación y antigüedad del inventario en piso</p>
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

    <!-- BALANCE DE FLUJO (PISO DE VENTA) -->
    <div class="row mb-4">
        <!-- Ingresos -->
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
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ingresos-total">$ 0.00</h2>
                    <span class="text-muted small">Inv. Inicial + Entradas</span>
                </div>
            </div>
        </div>

        <!-- Egresos -->
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
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-egresos-total">$ 0.00</h2>
                    <span class="text-muted small">Ventas + Salidas + Apartados</span>
                </div>
            </div>
        </div>

        <!-- Total de Inventario en Piso de Ventas -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-warning border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-inventario" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de inventario...">
                                Total de Inventario en Piso de Ventas
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-warning text-warning"><i class="bi bi-box-seam-fill"></i></div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-inventario-piso">$ 0.00</h2>
                    <span class="text-muted small">Ingresos - Egresos</span>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIS SECUNDARIOS -->
    <div class="row mb-4">
        <!-- Artículos en Piso -->
        <div class="col-12 col-md-6 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">Artículos en Piso</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-upc-scan"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0" id="kpi-total-articulos">0</h3>
                    <span class="text-muted small">Oro: <span class="fw-bold text-warning" id="kpi-count-oro">0</span> | Varios: <span class="fw-bold text-info" id="kpi-count-varios">0</span></span>
                </div>
            </div>
        </div>

        <!-- Pérdidas y Merma -->
        <div class="col-12 col-md-6 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">Pérdidas y Merma</h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-shield-x"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0" id="kpi-perdidas">$ 0.00</h3>
                    <span class="text-muted small">Artículos dados de baja o siniestrados</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Distribución de Antigüedad -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Distribución por Antigüedad</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="distribucionAntiguedadChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Valor por Sucursal -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Inventario y Antigüedad por Sucursal</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="valorSucursalChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Top Artículos Añejos -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Ranking: Artículos más añejos en piso</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Artículo</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Familia</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Valor Inv.</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Días en Piso</th>
                                </tr>
                            </thead>
                            <tbody id="top-articulos-body">
                                <tr><td colspan="5" class="text-center text-muted py-3">Cargando datos...</td></tr>
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
                    Mostrando las marcas más comunes en el piso de venta para este artículo.
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-uppercase text-muted small fw-bold">Marca</th>
                                <th class="text-uppercase text-muted small fw-bold text-center">Unidades</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Valor Inventario</th>
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

            fetch(`{{ route('inventario-piso.data') }}?${urlParams}`)
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

        function updateElementHTML(id, html) {
            const el = document.getElementById(id);
            if (el) el.innerHTML = html;
        }

        function updateDashboard(data) {
            // KPIs Principales - Flujo Financiero
            updateElementText('kpi-ingresos-total', formatter.format(data.ingresosTotales || 0));
            updateElementText('kpi-egresos-total', formatter.format(data.egresosTotales || 0));
            updateElementText('kpi-total-inventario-piso', formatter.format(data.inventarioPisoNeto || 0));
            
            // KPIs Secundarios
            updateElementText('kpi-total-articulos', numberFormatter.format(data.totalArticulosN || 0));
            updateElementText('kpi-count-oro', numberFormatter.format(data.countOro || 0));
            updateElementText('kpi-count-varios', numberFormatter.format(data.countVarios || 0));
            
            updateElementText('kpi-perdidas', formatter.format(data.perdidasMerma || 0));

            // Tooltip Ingresos
            const tooltipIngresosEl = document.getElementById('tooltip-ingresos');
            if (tooltipIngresosEl && typeof bootstrap !== 'undefined') {
                let tooltipHtml = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose de Ingresos (Entradas):</strong>
                        <div class="d-flex justify-content-between"><span>Inventario Inicial:</span> <span class="fw-bold text-success">${formatter.format(data.inventarioInicial || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Dotaciones (+):</span> <span class="fw-bold">${formatter.format(data.dotaciones || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Depositaria (+):</span> <span class="fw-bold">${formatter.format(data.depositaria || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Devolución Crédito (+):</span> <span class="fw-bold">${formatter.format(data.devolucion || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Remate Apartados (+):</span> <span class="fw-bold">${formatter.format(data.remate || 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>INGRESOS TOTALES</span> <span class="text-success">${formatter.format(data.ingresosTotales || 0)}</span></div>
                    </div>
                `;
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipIngresosEl);
                if (existingTooltip) existingTooltip.dispose();
                tooltipIngresosEl.setAttribute('data-bs-original-title', tooltipHtml);
                tooltipIngresosEl.setAttribute('title', tooltipHtml);
                new bootstrap.Tooltip(tooltipIngresosEl, { html: true, placement: 'top' });
            }

            // Tooltip Egresos
            const tooltipEgresosEl = document.getElementById('tooltip-egresos');
            if (tooltipEgresosEl && typeof bootstrap !== 'undefined') {
                let tooltipHtmlEgresos = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Desglose de Egresos (Salidas):</strong>
                        <div class="d-flex justify-content-between"><span>Ventas (-):</span> <span class="fw-bold text-danger">${formatter.format(data.ventas || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Apartados (-):</span> <span class="fw-bold">${formatter.format(data.apartado || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Salidas (Bajas) (-):</span> <span class="fw-bold">${formatter.format(data.salidas || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Traspasos (-):</span> <span class="fw-bold">${formatter.format(data.traspaso || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Refrendo Ext. (-):</span> <span class="fw-bold">${formatter.format(data.refrendoExtemporaneo || 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>EGRESOS TOTALES</span> <span class="text-danger">${formatter.format(data.egresosTotales || 0)}</span></div>
                    </div>
                `;
                const existingTooltipEgresos = bootstrap.Tooltip.getInstance(tooltipEgresosEl);
                if (existingTooltipEgresos) existingTooltipEgresos.dispose();
                tooltipEgresosEl.setAttribute('data-bs-original-title', tooltipHtmlEgresos);
                tooltipEgresosEl.setAttribute('title', tooltipHtmlEgresos);
                new bootstrap.Tooltip(tooltipEgresosEl, { html: true, placement: 'top' });
            }

            // Tooltip Piso Neto
            const tooltipInventarioEl = document.getElementById('tooltip-inventario');
            if (tooltipInventarioEl && typeof bootstrap !== 'undefined') {
                let tooltipHtmlInventario = `
                    <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 280px;">
                        <strong class="d-block mb-1 border-bottom pb-1">Flujo Neto en Piso de Ventas:</strong>
                        <div class="d-flex justify-content-between"><span>Ingresos Totales (+):</span> <span class="fw-bold text-success">${formatter.format(data.ingresosTotales || 0)}</span></div>
                        <div class="d-flex justify-content-between"><span>Egresos Totales (-):</span> <span class="fw-bold text-danger">${formatter.format(data.egresosTotales || 0)}</span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold"><span>NETO PISO DE VENTAS</span> <span class="text-primary">${formatter.format(data.inventarioPisoNeto || 0)}</span></div>
                    </div>
                `;
                const existingTooltipInventario = bootstrap.Tooltip.getInstance(tooltipInventarioEl);
                if (existingTooltipInventario) existingTooltipInventario.dispose();
                tooltipInventarioEl.setAttribute('data-bs-original-title', tooltipHtmlInventario);
                tooltipInventarioEl.setAttribute('title', tooltipHtmlInventario);
                new bootstrap.Tooltip(tooltipInventarioEl, { html: true, placement: 'top' });
            }

            // Tablas
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
                            <td class="py-3 text-muted">${escapeHtml(item.familia)}</td>
                            <td class="py-3">${escapeHtml(item.sucursal)}</td>
                            <td class="py-3 text-end fw-bold text-success">${formatter.format(item.valor)}</td>
                            <td class="pe-4 py-3 text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3 py-2">${item.dias} días</span>
                            </td>
                        `;
                        tr.addEventListener('click', function() {
                            mostrarMarcas(codPrenda, item.articulo || item.id);
                        });
                    } else {
                        tr.innerHTML = `
                            <td class="ps-4 py-3 fw-bold text-dark">${escapeHtml(item.articulo || item.id)}</td>
                            <td class="py-3 text-muted">${escapeHtml(item.familia)}</td>
                            <td class="py-3">${escapeHtml(item.sucursal)}</td>
                            <td class="py-3 text-end fw-bold text-success">${formatter.format(item.valor)}</td>
                            <td class="pe-4 py-3 text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3 py-2">${item.dias} días</span>
                            </td>
                        `;
                    }
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Aún no hay datos para mostrar</td></tr>';
            }

            // Gráficos
            updateStackedBarChart(data.chartDistribucionAntiguedad);
            updateMixedChart(data.chartValorAntiguedadSucursal);
        }

        function updateStackedBarChart(chartData) {
            const ctx = document.getElementById('distribucionAntiguedadChart');
            if (!ctx) return;
            
            if (distribucionAntiguedadChart) distribucionAntiguedadChart.destroy();
            if (!chartData) return;

            distribucionAntiguedadChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Oro',
                            data: chartData.data_oro,
                            backgroundColor: '#ffc107',
                        },
                        {
                            label: 'Varios',
                            data: chartData.data_varios,
                            backgroundColor: '#0dcaf0',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true }
                    }
                }
            });
        }

        function updateMixedChart(chartData) {
            const ctx = document.getElementById('valorSucursalChart');
            if (!ctx) return;
            
            if (valorSucursalChart) valorSucursalChart.destroy();
            if (!chartData) return;

            valorSucursalChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'line',
                            label: 'Antigüedad Prom. (días)',
                            data: chartData.antiguedad,
                            borderColor: '#dc3545',
                            backgroundColor: '#dc3545',
                            yAxisID: 'y1',
                            tension: 0.1
                        },
                        {
                            type: 'bar',
                            label: 'Valor de Inventario',
                            data: chartData.valores,
                            backgroundColor: '#0d6efd',
                            borderRadius: 4,
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            ticks: { callback: value => formatter.format(value) }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { callback: value => value + ' d' }
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
            const tbodyMarcas = document.getElementById('tbody-marcas-top');
            const subtitle = document.getElementById('modal-subtitle');
            const label = document.getElementById('modalMarcasLabel');
            
            if (!modalElement || !tbodyMarcas) return;

            label.innerHTML = `<i class="bi bi-tag-fill me-2"></i> Top 10 Marcas: ${escapeHtml(prendaNombre)}`;
            subtitle.innerText = `Mostrando las marcas más comunes en el piso de venta para este artículo.`;
            tbodyMarcas.innerHTML = '<tr><td colspan="3" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Cargando...</td></tr>';
            
            // Mostrar modal
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            const sucursalId = document.getElementById('sucursal_id').value;

            const params = new URLSearchParams({
                cod_prenda: codPrenda,
                sucursal_id: sucursalId
            }).toString();

            fetch(`{{ route('inventario-piso.top-marcas') }}?${params}`)
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
