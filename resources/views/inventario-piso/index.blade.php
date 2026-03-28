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
            <h4 class="title fw-bold text-dark">Inventario y Piso de Venta</h4>
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
                <!-- Para inventario suele ser una foto al día actual, pero dejamos filtro de fechas si aplica a rotación -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Corte</label>
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

    <!-- KPIs Principales -->
    <div class="row mb-4 justify-content-center">
        <!-- Valor Total Inventario -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Valor Inv. en Piso</h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-valor-inventario">$ 0.00</h2>
                    <span class="text-muted small">Oro: <span class="fw-bold" id="kpi-valor-oro">$ 0.00</span> | Varios: <span class="fw-bold" id="kpi-valor-varios">$ 0.00</span></span>
                </div>
            </div>
        </div>

        <!-- Número de Artículos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Artículos en Piso</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-upc-scan"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-articulos">0</h2>
                    <span class="text-muted small">Oro: <span class="fw-bold" id="kpi-count-oro">0</span> | Varios: <span class="fw-bold" id="kpi-count-varios">0</span></span>
                </div>
            </div>
        </div>

        <!-- Antigüedad Promedio -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Antigüedad Promedio</h6>
                        <div class="icon-shape bg-light-warning text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-antiguedad-promedio">0 días</h2>
                    <div class="mt-2 text-muted small">
                        >30d: <span class="fw-bold" id="kpi-porcentaje-30">0%</span> | >60d: <span class="fw-bold" id="kpi-porcentaje-60">0%</span> | >90d: <span class="text-danger fw-bold" id="kpi-porcentaje-90">0%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Secundarios -->
    <div class="row mb-4">
        <!-- Rotación de Inventario -->
        <div class="col-12 col-xl-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-shape bg-light-success text-success me-3">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Rotación de Inventario</h6>
                            <h3 class="fw-bold text-dark mb-0" id="kpi-rotacion">0.0x</h3>
                        </div>
                    </div>
                    <span class="text-muted small">Ventas / Inventario Promedio (anualizado/período)</span>
                </div>
            </div>
        </div>

        <!-- Mermas y Pérdidas -->
        <div class="col-12 col-xl-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-shape bg-light-danger text-danger me-3">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Pérdidas y Merma</h6>
                            <h3 class="fw-bold text-dark mb-0" id="kpi-perdidas">$ 0.00</h3>
                        </div>
                    </div>
                    <span class="text-muted small">Valor de artículos dados de baja o siniestrados</span>
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
            // KPIs Principales
            updateElementText('kpi-valor-inventario', formatter.format(data.valorTotalInventario || 0));
            updateElementText('kpi-valor-oro', formatter.format(data.valorOro || 0));
            updateElementText('kpi-valor-varios', formatter.format(data.valorVarios || 0));
            
            updateElementText('kpi-total-articulos', numberFormatter.format(data.totalArticulosN || 0));
            updateElementText('kpi-count-oro', numberFormatter.format(data.countOro || 0));
            updateElementText('kpi-count-varios', numberFormatter.format(data.countVarios || 0));
            
            updateElementText('kpi-antiguedad-promedio', `${numberFormatter.format(data.antiguedadPromedioDias || 0)} días`);
            
            updateElementText('kpi-porcentaje-30', `${(data.porcentajeMas30 || 0).toFixed(1)}%`);
            updateElementText('kpi-porcentaje-60', `${(data.porcentajeMas60 || 0).toFixed(1)}%`);
            updateElementText('kpi-porcentaje-90', `${(data.porcentajeMas90 || 0).toFixed(1)}%`);

            updateElementText('kpi-rotacion', `${(data.rotacionInventario || 0).toFixed(2)}x`);
            updateElementText('kpi-perdidas', formatter.format(data.perdidasMerma || 0));

            // Tablas
            const tbody = document.getElementById('top-articulos-body');
            if (data.topArticulosAnejos && data.topArticulosAnejos.length > 0) {
                let tableHtml = '';
                data.topArticulosAnejos.forEach(item => {
                    let badgeClass = item.dias > 90 ? 'bg-danger' : (item.dias > 60 ? 'bg-warning text-dark' : 'bg-secondary');
                    tableHtml += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${item.articulo || item.id}</td>
                            <td class="py-3 text-muted">${item.familia}</td>
                            <td class="py-3">${item.sucursal}</td>
                            <td class="py-3 text-end fw-bold text-success">${formatter.format(item.valor)}</td>
                            <td class="pe-4 py-3 text-center">
                                <span class="badge ${badgeClass} rounded-pill px-3 py-2">${item.dias} días</span>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = tableHtml;
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
    });
</script>
@endsection
