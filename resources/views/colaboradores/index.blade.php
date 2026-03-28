@extends('employees.layouts.main')

@section('title', 'Productividad Personal y Nómina')

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

        /* Metas */
        .badge-meta {
            font-size: 0.8rem;
            padding: 0.4em 0.8em;
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
    <h5 class="text-muted fw-bold">Calculando productividad del personal...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Colaboradores, Nómina y Productividad</h4>
            <p class="text-muted mb-0">Análisis del costo laboral, rendimiento individual y ratios de utilidad</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales Consolidadas --</option>
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
        <!-- Nómina Total -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Costo de Nómina</h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-nomina-total">$ 0.00</h2>
                    <span class="text-muted small">
                        Plafitilla: <span class="fw-bold text-dark" id="kpi-num-empleados">0</span> colaboradores
                    </span>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Costo Promedio / Empleado</span>
                        <span class="fw-bold small text-danger" id="kpi-costo-promedio">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promedio Ventas y Utilidad Bruta por Empleado -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Resultados por Empleado</h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted text-uppercase">Venta Promedio</small>
                        <h3 class="fw-bold text-dark mb-0" id="kpi-venta-promedio">$ 0.00</h3>
                    </div>
                    <hr class="my-2">
                    <div>
                        <small class="text-muted text-uppercase">Utilidad Bruta Promedio</small>
                        <h4 class="fw-bold text-success mb-0" id="kpi-ut-bruta-promedio">$ 0.00</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ratios de Productividad -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Ratios de Retorno (ROI Laboral)</h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div>
                            <small class="text-muted d-block">Utilidad Bruta / Costo</small>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ratio-ub">0.0<span class="fs-5">x</span></h2>
                        </div>
                        <span class="badge bg-success badge-meta" title="Meta Objetivo: 3x - 4x">Meta: 3x-4x</span>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <small class="text-muted d-block">Utilidad Neta / Costo</small>
                            <h4 class="fw-bold text-primary mb-0" id="kpi-ratio-un">0.0x</h4>
                        </div>
                        <span class="badge bg-info badge-meta" title="Meta Objetivo: 1x - 1.3x">Meta: ~1.2x</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Ratios por Sucursal/Empleado -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Productividad Top 5 Empleados vs Costo Promedio</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="ratiosSucursalChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Composición Operaciones -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Volúmen Operativo</h5>
                    <small class="text-muted"><span id="kpi-mov-total" class="fw-bold">0</span> Movimientos atendidos</small>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="operacionesChart" height="200"></canvas>
                </div>
                <div class="card-footer bg-white border-0 text-center pb-4">
                    <span class="text-muted small">Promedio: <strong class="text-dark" id="kpi-mov-promedio">0</strong> por empleado</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Ranking Empleados -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Ranking de Colaboradores por Productividad</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Colaborador</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Operaciones</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ventas ($)</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-success">Utilidad Bruta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-danger">Costo Est.</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center" title="Ratio UB / Costo">Retorno (ROI)</th>
                                </tr>
                            </thead>
                            <tbody id="ranking-empleados-body">
                                <tr><td colspan="7" class="text-center text-muted py-3">Cargando datos...</td></tr>
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

        let ratiosChart = null;
        let operacionesChart = null;

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

            fetch(`{{ route('colaboradores.data') }}?${urlParams}`)
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
            // KPIs Nómina y Empleados
            updateElementText('kpi-nomina-total', formatter.format(data.nominaTotal || 0));
            updateElementText('kpi-num-empleados', numberFormatter.format(data.numEmpleados || 0));
            updateElementText('kpi-costo-promedio', formatter.format(data.costoPromedioEmpleado || 0));

            // KPIs Ventas y Utilidad Bruta
            updateElementText('kpi-venta-promedio', formatter.format(data.ventaPromedioEmpleado || 0));
            updateElementText('kpi-ut-bruta-promedio', formatter.format(data.utilidadBrutaPromedioEmpleado || 0));

            // Ratios (ROI)
            updateElementText('kpi-ratio-ub', (data.ratioUBvsCosto || 0).toFixed(2));
            updateElementText('kpi-ratio-un', (data.ratioUNvsCosto || 0).toFixed(2) + 'x');

            // Volúmen
            updateElementText('kpi-mov-total', numberFormatter.format(data.movimientosTotales || 0));
            updateElementText('kpi-mov-promedio', numberFormatter.format(data.movimientosPromedioEmpleado || 0));

            // Tablas
            const tbody = document.getElementById('ranking-empleados-body');
            tbody.innerHTML = '';
            
            if (data.rankingColaboradores && data.rankingColaboradores.length > 0) {
                data.rankingColaboradores.forEach(row => {
                    const ratio = data.costoPromedioEmpleado > 0 ? (row.utilidad_bruta / data.costoPromedioEmpleado).toFixed(1) : '∞';
                    tbody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold">${row.empleado || 'Vendedor Sistema'}</td>
                            <td class="fw-normal text-muted small">${row.sucursal}</td>
                            <td class="text-center fw-bold">${numberFormatter.format(row.tickets_totales)}</td>
                            <td class="text-end fw-bold">${formatter.format(row.ventas_monto)}</td>
                            <td class="text-end text-success fw-bold">${formatter.format(row.utilidad_bruta)}</td>
                            <td class="text-end text-danger fw-bold">${formatter.format(data.costoPromedioEmpleado)}</td>
                            <td class="pe-4 text-center fw-bold">${ratio}x</td>
                        </tr>
                    `;
                });

                // Top 5 Empleados
                const top5 = data.rankingColaboradores.slice(0, 5);
                const chartData = {
                    labels: top5.map(r => r.empleado),
                    ratio_ub: top5.map(r => data.costoPromedioEmpleado > 0 ? parseFloat((r.utilidad_bruta / data.costoPromedioEmpleado).toFixed(2)) : 0),
                    ratio_un: top5.map(r => 0)
                };
                updateBarChart(chartData);

            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Aún no hay datos para mostrar</td></tr>';
            }

            // Gráficos
            updateDoughnutChart(data.chartComposicionOperaciones);
        }

        function updateBarChart(chartData) {
            const ctx = document.getElementById('ratiosSucursalChart');
            if (!ctx) return;
            
            if (ratiosChart) ratiosChart.destroy();
            if (!chartData) return;

            ratiosChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ratio UB / Costo',
                            data: chartData.ratio_ub,
                            backgroundColor: 'rgba(25, 135, 84, 0.8)', // Success
                            borderRadius: 4
                        },
                        {
                            label: 'Ratio UN / Costo',
                            data: chartData.ratio_un,
                            backgroundColor: 'rgba(13, 110, 253, 0.8)', // Primary
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        annotation: {
                            // Se podría usar el chartjs-plugin-annotation para pintar las líneas de meta (3x y 1.2x)
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Múltiplo (x veces)' }
                        }
                    }
                }
            });
        }

        function updateDoughnutChart(chartData) {
            const ctx = document.getElementById('operacionesChart');
            if (!ctx) return;
            
            if (operacionesChart) operacionesChart.destroy();
            if (!chartData) return;

            operacionesChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: [
                            '#0d6efd', // Ventas
                            '#198754', // Empeños
                            '#ffc107', // Refrendos
                            '#0dcaf0', // Desempeños
                            '#6c757d'  // Otros
                        ]
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
