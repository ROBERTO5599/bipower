@extends('employees.layouts.main')

@section('title', 'Metas, Estacionalidad y Forecast')

@section('styles')
    <style type="text/css">
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
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
        .text-verde { color: #198754; }
        .text-amarillo { color: #ffc107; }
        .text-rojo { color: #dc3545; }
        .bg-verde { background-color: #198754; color: white; }
        .bg-amarillo { background-color: #ffc107; color: black; }
        .bg-rojo { background-color: #dc3545; color: white; }
        .gauge-container {
            width: 100%;
            height: 200px;
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Calculando Proyecciones...</span>
    </div>
    <h5 class="text-muted fw-bold">Compilando e infiriendo modelos históricos...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Metas Predictivas y Forecast Estacional</h4>
            <p class="text-muted">Proyección científica combinando tendencia lineal y estacionalidad corporativa</p>
        </div>
    </div>

    <!-- Filtros Inteligentes -->
    <div class="card shadow-sm border-0 mb-4 rounded-3 bg-white">     
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas Consolidadas --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Profundidad Histórica</label>
                    <select name="meses_historico" id="meses_historico" class="form-select">
                        <option value="12" selected>Últimos 12 Meses</option>
                        <option value="18">Últimos 18 Meses</option>
                        <option value="24">Últimos 24 Meses</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Crecimiento Objetivo (%)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-graph-up-arrow"></i></span>
                        <input type="number" step="0.1" name="crecimiento" id="crecimiento" value="{{ $crecimiento }}" class="form-control">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-calculator"></i> Refactorizar Metas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Indicadores Velocímetros Principales -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3 text-center p-3">
                <h6 class="text-muted fw-bold text-uppercase ls-1">Ventas Reales vs Meta</h6>
                <div id="gaugeVentas" class="gauge-container mb-2"></div>
                <h4 class="fw-bold mb-0" id="txtRevVentas">$ 0</h4>
                <small class="text-muted">Meta: <span id="txtMetaVentas">$ 0</span></small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3 text-center p-3">
                <h6 class="text-muted fw-bold text-uppercase ls-1">Empeños Reales vs Meta</h6>
                <div id="gaugeEmpenos" class="gauge-container mb-2"></div>
                <h4 class="fw-bold mb-0" id="txtRevEmpenos">$ 0</h4>
                <small class="text-muted">Meta: <span id="txtMetaEmpenos">$ 0</span></small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3 text-center p-3">
                <h6 class="text-muted fw-bold text-uppercase ls-1">Intereses Cobrados</h6>
                <div id="gaugeIntereses" class="gauge-container mb-2"></div>
                <h4 class="fw-bold mb-0" id="txtRevIntereses">$ 0</h4>
                <small class="text-muted">Meta: <span id="txtMetaIntereses">$ 0</span></small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3 text-center p-3">
                <h6 class="text-muted fw-bold text-uppercase ls-1">Utilidad Operativa</h6>
                <div id="gaugeUtilidad" class="gauge-container mb-2"></div>
                <h4 class="fw-bold mb-0" id="txtRevUtilidad">$ 0</h4>
                <small class="text-muted">Meta: <span id="txtMetaUtilidad">$ 0</span></small>
            </div>
        </div>
    </div>

    <!-- Tendencia Histórica y Proyección -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Comportamiento Predictivo de Ventas Globales</h5>
                    <span class="badge bg-light text-primary">Tendencia + Crecimiento.</span>
                </div>
                <div class="card-body p-4">
                    <canvas id="ventasTimelineChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Sucursales -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Desglose de Objetivos por Sucursal</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold text-start">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Cumpl. General</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Ventas</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Meta Venta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Empeños</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Meta Empeño</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Utilidad Op.</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold">Meta Utilidad</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-sucursales-body">
                                <tr><td colspan="8" class="text-center text-muted py-4">Cargando...</td></tr>
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
<!-- ECharts para Gauges Profesionales -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        let ventasTimelineChart = null;
        let gauges = {};

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');

        // Setup ECharts
        const gaugeOptions = {
            series: [{
                type: 'gauge',
                startAngle: 180,
                endAngle: 0,
                min: 0,
                max: 100, // Dinámico
                axisLine: {
                    lineStyle: {
                        width: 15,
                        color: [
                            [0.7, '#ffc107'], // 0-70% amarillo
                            [0.9, '#fd7e14'], // 70-90% naranja/precaucion
                            [1, '#198754']   // >90% verde
                        ]
                    }
                },
                pointer: { itemStyle: { color: 'auto' } },
                axisTick: { distance: -15, length: 8, lineStyle: { color: '#fff', width: 2 } },
                splitLine: { distance: -15, length: 15, lineStyle: { color: '#fff', width: 2 } },
                axisLabel: { color: 'auto', distance: 20, fontSize: 10 },
                detail: {
                    valueAnimation: true,
                    formatter: '{value}%',
                    color: 'auto',
                    fontSize: 20,
                    offsetCenter: [0, '30%']
                },
                data: [{ value: 0 }]
            }]
        };

        gauges['ventas'] = echarts.init(document.getElementById('gaugeVentas'));
        gauges['empenos'] = echarts.init(document.getElementById('gaugeEmpenos'));
        gauges['intereses'] = echarts.init(document.getElementById('gaugeIntereses'));
        gauges['utilidad'] = echarts.init(document.getElementById('gaugeUtilidad'));

        for (let g in gauges) {
            gauges[g].setOption(gaugeOptions);
        }

        loadData();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            fetch(`{{ route('metas-forecast.data') }}?${new URLSearchParams(new FormData(form)).toString()}`)
                .then(r => r.json())
                .then(data => {
                    updateDashboard(data);
                })
                .catch(err => console.error(err))
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function updateGauge(gaugeId, real, meta) {
            let pct = meta > 0 ? (real / meta) * 100 : 0;
            if (pct > 100) {
                // Adaptar el maximo del gauge si pasamos el limite
                gauges[gaugeId].setOption({ series: [{ max: Math.ceil(pct), data: [{ value: pct.toFixed(1) }] }] });
            } else {
                gauges[gaugeId].setOption({ series: [{ max: 100, data: [{ value: pct.toFixed(1) }] }] });
            }
        }

        function updateDashboard(data) {
            // Textos Globales
            document.getElementById('txtRevVentas').innerText = formatter.format(data.globals.ventas.real);
            document.getElementById('txtMetaVentas').innerText = formatter.format(data.globals.ventas.meta);
            updateGauge('ventas', data.globals.ventas.real, data.globals.ventas.meta);

            document.getElementById('txtRevEmpenos').innerText = formatter.format(data.globals.empenos.real);
            document.getElementById('txtMetaEmpenos').innerText = formatter.format(data.globals.empenos.meta);
            updateGauge('empenos', data.globals.empenos.real, data.globals.empenos.meta);

            document.getElementById('txtRevIntereses').innerText = formatter.format(data.globals.intereses.real);
            document.getElementById('txtMetaIntereses').innerText = formatter.format(data.globals.intereses.meta);
            updateGauge('intereses', data.globals.intereses.real, data.globals.intereses.meta);

            document.getElementById('txtRevUtilidad').innerText = formatter.format(data.globals.utilidad.real);
            document.getElementById('txtMetaUtilidad').innerText = formatter.format(data.globals.utilidad.meta);
            updateGauge('utilidad', data.globals.utilidad.real, data.globals.utilidad.meta);

            // Chart Timeline
            updateTimelineChart(data.chartTimeline);

            // Render Table
            renderTable(data.branchKPIs);
        }

        function renderTable(kpis) {
            const tbody = document.getElementById('tabla-sucursales-body');
            tbody.innerHTML = '';
            
            kpis.forEach(kpi => {
                let colorClass = kpi.semaforo === 'verde' ? 'bg-verde' : (kpi.semaforo === 'amarillo' ? 'bg-amarillo' : 'bg-rojo');
                
                tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark text-start">
                            ${kpi.id} 
                            ${kpi.is_manual ? '<i class="bi bi-person-fill ms-1 text-primary" title="Meta Manual"></i>' : '<i class="bi bi-robot ms-1 text-muted" title="Meta Automática"></i>'}
                        </td>
                        <td class="py-3">
                            <span class="badge ${colorClass} px-3 py-2">${kpi.pct_ventas.toFixed(1)}%</span>
                        </td>
                        <td class="py-3 fw-bold text-primary">${formatter.format(kpi.real_ventas)}</td>
                        <td class="py-3 text-muted border-end">${formatter.format(kpi.meta_ventas)}</td>
                        
                        <td class="py-3 fw-bold text-dark">${formatter.format(kpi.real_empenos)}</td>
                        <td class="py-3 text-muted border-end">${formatter.format(kpi.meta_empenos)}</td>

                        <td class="py-3 fw-bold text-success">${formatter.format(kpi.real_utilidad)}</td>
                        <td class="pe-4 py-3 text-muted">${formatter.format(kpi.meta_utilidad)}</td>
                    </tr>
                `;
            });

            if (kpis.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">Sin datos de sucursales</td></tr>';
            }
        }

        function updateTimelineChart(chartData) {
            const ctx = document.getElementById('ventasTimelineChart');
            if (ventasTimelineChart) ventasTimelineChart.destroy();

            ventasTimelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ventas Reales',
                            data: chartData.real,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Meta / Tendencia Ajustada',
                            data: chartData.tendencia,
                            borderColor: '#ffc107',
                            borderDash: [5, 5],
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: 'Año Anterior',
                            data: chartData.ly,
                            borderColor: '#6c757d',
                            borderWidth: 2,
                            opacity: 0.5,
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) { return context.dataset.label + ': ' + formatter.format(context.raw); }
                            }
                        }
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
