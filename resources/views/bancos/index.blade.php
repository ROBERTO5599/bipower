@extends('employees.layouts.main')

@section('title', 'Bancos y Flujos de Efectivo')

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
    <h5 class="text-muted fw-bold">Analizando flujos bancarios...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="title fw-bold text-dark">Bancos y Flujos de Efectivo</h4>
                <p class="text-muted mb-0">Monitoreo de saldos bancarios y movimientos por cuenta</p>
            </div>
            <a href="{{ route('gastos-finanzas.index') }}" class="btn btn-outline-primary shadow-sm">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>Ver Estados Financieros
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas Consolidado --</option>
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
                    <label class="form-label fw-semibold">Fecha Hasta (Corte)</label>
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
        <!-- Saldo Total Bancos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Saldo Bancario (Corte)</h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-bank2"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-saldo-total">$ 0.00</h2>
                    <span class="text-muted small">
                        Promedio mes: <span class="fw-bold" id="kpi-saldo-promedio">$ 0.00</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Flujo Neto -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Flujo Neto del Periodo</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-shuffle"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-flujo-neto">$ 0.00</h2>
                    <span class="text-muted small">Entradas menos salidas</span>
                </div>
            </div>
        </div>

        <!-- Entradas y Salidas -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 bg-light">
                <div class="card-body p-4">
                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-3">Resumen de Movimientos</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-success fw-bold"><i class="bi bi-arrow-down-circle me-1"></i> Entradas</span>
                        <span class="fw-bold text-success" id="kpi-total-entradas">$ 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-danger fw-bold"><i class="bi bi-arrow-up-circle me-1"></i> Salidas</span>
                        <span class="fw-bold text-danger" id="kpi-total-salidas">$ 0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Comisiones Totales</span>
                        <span class="text-danger small" id="kpi-comisiones">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos Superiores -->
    <div class="row mb-4">
        <!-- Evolución de saldos -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Evolución de Saldos por Cuenta</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="evolucionSaldosChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Flujos Mensuales (Barras) -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Entradas vs Salidas</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="flujosMensualesChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos Inferiores (Composición) -->
    <div class="row mb-4">
        <!-- Origen de Entradas -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Origen de Entradas</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="origenEntradasChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Destino de Salidas -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Distribución de Salidas</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="tipoSalidasChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Detalle Cuentas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Resumen por Cuenta Bancaria</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Banco / Cuenta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Saldo Inicial</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-success">Entradas</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-danger">Salidas</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-danger">Comisiones</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end text-primary">Saldo Final</th>
                                </tr>
                            </thead>
                            <tbody id="detalle-cuentas-body">
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

        let evolucionSaldosChart = null;
        let flujosMensualesChart = null;
        let origenEntradasChart = null;
        let tipoSalidasChart = null;

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

            fetch(`{{ route('bancos.data') }}?${urlParams}`)
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
            updateElementText('kpi-saldo-total', formatter.format(data.saldoTotalBancos || 0));
            updateElementText('kpi-saldo-promedio', formatter.format(data.saldoPromedio || 0));
            
            updateElementText('kpi-flujo-neto', formatter.format(data.flujoNetoMensual || 0));
            
            updateElementText('kpi-total-entradas', formatter.format(data.totalEntradas || 0));
            updateElementText('kpi-total-salidas', formatter.format(data.totalSalidas || 0));
            updateElementText('kpi-comisiones', formatter.format(data.comisionesTotales || 0));

            // Tablas
            const tbody = document.getElementById('detalle-cuentas-body');
            tbody.innerHTML = '';
            if (data.detalleCuentas && data.detalleCuentas.length > 0) {
                data.detalleCuentas.forEach(row => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold">${row.banco} <br> <span class="fw-normal text-muted small">${row.cuenta}</span></td>
                            <td class="text-end text-muted">-</td>
                            <td class="text-end text-success fw-bold">${formatter.format(row.entradas)}</td>
                            <td class="text-end text-danger fw-bold">${formatter.format(row.salidas)}</td>
                            <td class="text-end text-danger">-</td>
                            <td class="pe-4 text-end text-primary fw-bold">${formatter.format(row.flujo)}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aún no hay datos para mostrar</td></tr>';
            }

            // Gráficos
            updateLineChart(data.chartEvolucionSaldos);
            updateBarChart(data.chartFlujosMensuales);
            updateDoughnutChart(data.chartEntradasPorOrigen, 'origenEntradasChart', ['#198754', '#20c997', '#0dcaf0', '#0d6efd']);
            updateDoughnutChart(data.chartSalidasPorTipo, 'tipoSalidasChart', ['#dc3545', '#fd7e14', '#ffc107', '#6c757d']);
        }

        function updateLineChart(chartData) {
            const ctx = document.getElementById('evolucionSaldosChart');
            if (!ctx) return;
            
            if (evolucionSaldosChart) evolucionSaldosChart.destroy();
            if (!chartData) return;

            // Al ser multilínea, procesaríamos las series. Proveemos un dataset dummy por ahora si no hay.
            const datasets = [];
            if (chartData.flujo_efectivo) {
                datasets.push({
                    label: 'Flujo Efectivo Caja',
                    data: chartData.flujo_efectivo,
                    borderColor: '#198754',
                    tension: 0.1
                });
            }
            if (chartData.flujo_bancos) {
                datasets.push({
                    label: 'Flujo Bancos (TPV)',
                    data: chartData.flujo_bancos,
                    borderColor: '#0d6efd',
                    tension: 0.1
                });
            }

            evolucionSaldosChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: datasets.length ? datasets : [{ label: 'Saldo General', data: [], borderColor: '#0d6efd' }]
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

        function updateBarChart(chartData) {
            const ctx = document.getElementById('flujosMensualesChart');
            if (!ctx) return;
            
            if (flujosMensualesChart) flujosMensualesChart.destroy();
            if (!chartData) return;

            flujosMensualesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto',
                        data: chartData.data,
                        backgroundColor: [
                            'rgba(25, 135, 84, 0.8)', // Entradas (verde)
                            'rgba(220, 53, 69, 0.8)',  // Salidas (rojo)
                            'rgba(13, 110, 253, 0.8)'  // Neto (azul)
                        ],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            ticks: { callback: value => formatter.format(value) }
                        }
                    }
                }
            });
        }

        function updateDoughnutChart(chartData, elementId, colors) {
            const ctx = document.getElementById(elementId);
            if (!ctx) return;
            
            let chartInstance = Chart.getChart(elementId);
            if (chartInstance) chartInstance.destroy();

            if (!chartData) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        }
    });
</script>
@endsection
