@extends('employees.layouts.main')

@section('title', 'Metas, Estacionalidad y Forecast')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.1); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.1); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.1); }

        .table-responsive { overflow-x: auto; }

        /* Badge para metas manuales vs auto */
        .badge-meta-type {
            font-size: 0.75rem;
            padding: 0.3em 0.6em;
            border-radius: 50rem;
        }

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
    <h5 class="text-muted fw-bold">Proyectando metas y estacionalidad...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="title fw-bold text-dark">Metas, Estacionalidad y Forecast (Proyecciones)</h4>
                <p class="text-muted mb-0">Desempeño real vs. proyecciones ajustadas por estacionalidad histórica</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Consolidado (Todas) --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Mes de Referencia</label>
                    <!-- Solo mes porque las metas suelen ser mensuales -->
                    <input type="month" name="mes_meta" id="mes_meta" value="{{ substr($fechaFin, 0, 7) }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-bullseye me-2"></i> Evaluar Meta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPIs Dinámicos de Cumplimiento -->
    <div class="row mb-4" id="kpi-container">
        <!-- Renderizado vía JS -->
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Velocímetro General -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 text-center">
                    <h5 class="fw-bold mb-0">Cumplimiento Global del Mes</h5>
                </div>
                <div class="card-body p-4 position-relative d-flex justify-content-center align-items-center flex-column">
                    <div style="height: 180px; width: 100%;">
                        <canvas id="gaugeChart"></canvas>
                    </div>
                    <!-- El porcentaje en el centro se dibuja mediante un plugin o texto sobrepuesto, por ahora texto -->
                    <h2 class="fw-bold mt-2" id="kpi-cumplimiento-global">0%</h2>
                    <span class="text-muted small">Promedio ponderado</span>
                </div>
            </div>
        </div>

        <!-- Línea de Tiempo Histórica -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Evolución Histórica General vs Meta</h5>
                    <select class="form-select w-auto form-select-sm" id="indicadorEvolucionSelect">
                        <option value="ventas">Ventas Totales</option>
                        <option value="empenos">Empeños</option>
                        <option value="utilidad">Utilidad Operativa</option>
                    </select>
                </div>
                <div class="card-body p-4">
                    <canvas id="evolucionMetasChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Detalle Sucursales -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Detalle de Cumplimiento por Sucursal</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Indicador</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Real Alcanzado</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Meta Proyectada</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Diferencia</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Semáforo</th>
                                </tr>
                            </thead>
                            <tbody id="detalle-metas-body">
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

        let gaugeChart = null;
        let evolucionChart = null;

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

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

            fetch(`{{ route('metas-forecast.data') }}?${urlParams}`)
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

        function updateDashboard(data) {
            renderKPIs(data.kpis);
            
            const cumplimiento = data.cumplimientoGlobal || 0;
            document.getElementById('kpi-cumplimiento-global').innerText = cumplimiento.toFixed(1) + '%';
            document.getElementById('kpi-cumplimiento-global').className = 'fw-bold mt-2 ' + getSemaforoText(cumplimiento);

            updateGauge(cumplimiento);
            updateEvolucionChart(data.chartEvolucionMetaVsReal);

            const tbody = document.getElementById('detalle-metas-body');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aún no hay datos detallados para mostrar</td></tr>';
        }

        function renderKPIs(kpis) {
            const container = document.getElementById('kpi-container');
            container.innerHTML = '';

            if (!kpis || kpis.length === 0) return;

            kpis.forEach(k => {
                const isManual = k.isManual;
                const badgeType = isManual ? 'bg-warning text-dark' : 'bg-info text-white';
                const badgeText = isManual ? 'Meta Manual' : 'Meta Auto (Tendencia/Estacionalidad)';
                
                const percentColor = k.cumplimiento >= 100 ? 'text-success' : (k.cumplimiento >= 90 ? 'text-warning' : 'text-danger');
                
                const diffVal = k.diferencia;
                const diffColor = diffVal >= 0 ? 'text-success' : 'text-danger';
                const diffTxt = diffVal >= 0 ? `+${formatter.format(diffVal)}` : formatter.format(diffVal);

                const cardHtml = `
                <div class="col-12 col-md-6 col-xl-3 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between mb-2">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">${k.indicador}</h6>
                                <span class="badge ${badgeType} badge-meta-type">${badgeText}</span>
                            </div>
                            
                            <div class="d-flex align-items-end justify-content-between mb-2 mt-3">
                                <div>
                                    <small class="text-muted d-block">Real Alcanzado</small>
                                    <h4 class="fw-bold mb-0">${formatter.format(k.real)}</h4>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Meta Proyectada</small>
                                    <h6 class="fw-bold text-muted mb-0">${formatter.format(k.meta)}</h6>
                                </div>
                            </div>
                            
                            <div class="progress mt-3" style="height: 10px;">
                                <div class="progress-bar ${k.cumplimiento >= 100 ? 'bg-success' : (k.cumplimiento >= 90 ? 'bg-warning' : 'bg-danger')}" 
                                     role="progressbar" style="width: ${Math.min(k.cumplimiento, 100)}%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-bold fs-5 ${percentColor}">${k.cumplimiento.toFixed(1)}%</span>
                                <small class="${diffColor} fw-bold">${diffTxt}</small>
                            </div>
                        </div>
                    </div>
                </div>`;
                
                container.insertAdjacentHTML('beforeend', cardHtml);
            });
        }

        function getSemaforoText(pct) {
            if (pct >= 100) return 'text-success';
            if (pct >= 90) return 'text-warning';
            return 'text-danger';
        }

        function updateGauge(value) {
            const ctx = document.getElementById('gaugeChart');
            if (!ctx) return;
            
            if (gaugeChart) gaugeChart.destroy();

            // Un gauge falso usando un doughnut al que le quitamos la mitad inferior
            gaugeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Alcanzado', 'Faltante'],
                    datasets: [{
                        data: [Math.min(value, 100), Math.max(100 - value, 0)],
                        backgroundColor: [
                            value >= 100 ? '#198754' : (value >= 90 ? '#ffc107' : '#dc3545'),
                            '#e9ecef'
                        ],
                        borderWidth: 0,
                        circumference: 180,
                        rotation: 270,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '80%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });
        }

        function updateEvolucionChart(chartData) {
            const ctx = document.getElementById('evolucionMetasChart');
            if (!ctx) return;
            
            if (evolucionChart) evolucionChart.destroy();
            if (!chartData) return;

            evolucionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Desempeño Real',
                            data: chartData.real,
                            borderColor: '#0d6efd',
                            backgroundColor: '#0d6efd',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Meta Automática (Forecast)',
                            data: chartData.meta,
                            borderColor: '#198754', // Green
                            borderDash: [5, 5],
                            pointStyle: 'rectRot',
                            pointRadius: 5,
                            tension: 0.1,
                            fill: false
                        },
                        {
                            label: 'Real Año Anterior',
                            data: chartData.anoAnterior,
                            borderColor: '#6c757d', // Gray
                            tension: 0.3,
                            fill: false,
                            hidden: true // Escondido por defecto, se puede activar al clicar en leyenda
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
                            beginAtZero: true,
                            ticks: { callback: value => formatter.format(value) }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
