@extends('employees.layouts.main')

@section('title', 'Créditos a Clientes')

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
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold">Analizando cartera de créditos...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Créditos a Clientes (Financiamiento Directo)</h4>
            <p class="text-muted">Desempeño, colocación, morosidad y rentabilidad de la cartera</p>
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

    <!-- KPIs Principales -->
    <div class="row mb-4 justify-content-center">
        <!-- Saldo Total -->
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

    <!-- KPIs Secundarios -->
    <div class="row mb-4">
        <!-- Recuperación y Rendimiento -->
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

        <!-- Intereses -->
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

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Saldo y Colocación -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Evolución de Saldo y Colocación</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="saldoColocacionChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Distribución Morosidad -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Distribución de Cartera (Días de Atraso)</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="morosidadChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Detalle Créditos -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Detalle de Créditos y Estatus</h5>
                    <!-- Podríamos poner un filtro adicional aquí en el futuro -->
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
                                <tr><td colspan="6" class="text-center text-muted py-3">Cargando datos...</td></tr>
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

        let saldoColocacionChart = null;
        let morosidadChart = null;

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

            fetch(`{{ route('creditos.data') }}?${urlParams}`)
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

        function updateDashboard(data) {
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

            // Tablas
            const tbody = document.getElementById('top-creditos-body');
            if (data.topCreditos && data.topCreditos.length > 0) {
                let html = '';
                data.topCreditos.forEach(c => {
                    let badgeClass = c.estatus == 1 ? 'bg-success' : (c.estatus == 2 ? 'bg-warning text-dark' : 'bg-secondary');
                    html += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${c.cliente || 'Desconocido'}</td>
                            <td class="py-3 text-muted">${c.sucursal}</td>
                            <td class="py-3 text-end">${formatter.format(c.monto_original)}</td>
                            <td class="py-3 text-end text-danger fw-bold">${formatter.format(c.saldo_actual)}</td>
                            <td class="py-3 text-end text-success">${formatter.format(c.intereses)}</td>
                            <td class="pe-4 py-3 text-end"><span class="badge ${badgeClass} rounded-pill">${c.estatus}</span></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay créditos activos</td></tr>';
            }

            // Gráficos
            updateMorosidadChart(data.chartCarteraMora);
            updateMixedChart(data.chartSaldoColocacion);
        }

        function updateMorosidadChart(chartData) {
            const ctx = document.getElementById('morosidadChart');
            if (!ctx) return;
            
            if (morosidadChart) morosidadChart.destroy();
            if (!chartData) return;

            morosidadChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: [
                            '#198754', // Al corriente (0-30 aunque es morita baja)
                            '#ffc107', // 31-60
                            '#fd7e14', // 61-90
                            '#dc3545'  // >90
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        function updateMixedChart(chartData) {
            const ctx = document.getElementById('saldoColocacionChart');
            if (!ctx) return;
            
            if (saldoColocacionChart) saldoColocacionChart.destroy();
            if (!chartData) return;

            saldoColocacionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'line',
                            label: 'Saldo Cartera',
                            data: chartData.saldo,
                            borderColor: '#0d6efd',
                            backgroundColor: '#0d6efd',
                            tension: 0.1
                        },
                        {
                            type: 'bar',
                            label: 'Colocación',
                            data: chartData.colocacion,
                            backgroundColor: 'rgba(25, 135, 84, 0.7)',
                            borderRadius: 4
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
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
