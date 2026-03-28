@extends('employees.layouts.main')

@section('title', 'Certificados de Confianza')

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
    <h5 class="text-muted fw-bold">Analizando certificados de confianza...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Certificados de Confianza</h4>
            <p class="text-muted">Control de volumen, ingresos y uso de garantías adicionales</p>
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
        <!-- Total Certificados -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Certificados Vendidos</h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-certificados">0</h2>
                    <span class="text-muted small">
                        En el <span class="fw-bold text-dark" id="kpi-ventas-pct">0%</span> de las ventas
                    </span>
                </div>
            </div>
        </div>

        <!-- Monto Cobrado -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Ingreso Bruto</h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-monto-cobrado">$ 0.00</h2>
                    <span class="text-muted small">Por venta de certificados</span>
                </div>
            </div>
        </div>

        <!-- Ingreso Neto -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Margen o Ingreso Neto</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-piggy-bank-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-ingreso-neto">$ 0.00</h2>
                    <div class="mt-2 text-muted small">
                        Costos asociados: <span class="fw-bold text-danger" id="kpi-costos">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Secundarios - Tasa de Uso -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-shape bg-light-warning text-warning me-3">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Tasa de Efectividad o Uso de Garantía</h6>
                            <h3 class="fw-bold text-dark mb-0"><span id="kpi-tasa-uso-pct" class="text-danger">0%</span> Utilizados</h3>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <div id="progress-uso" class="progress-bar bg-danger" role="progressbar" style="width: 10%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100"></div>
                                <div id="progress-nouso" class="progress-bar bg-success" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted"><span id="kpi-cert-uso" class="text-danger fw-bold">0</span> Utilizados (siniestro/falla)</small>
                                <small class="text-muted"><span id="kpi-cert-nouso" class="text-success fw-bold">0</span> No Utilizados (expiraron o vigentes)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Volúmen por Sucursal/Mes -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Colocación por Sucursal (Cantidad y Monto)</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="colocacionChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Distribución Plazos -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Distribución por Plazo</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="plazosChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablas de Detalle -->
    <div class="row mb-4">
        <!-- Por Familia -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Top por Familia/Artículo</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Familia / Artículo</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Cantidad</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Ingreso</th>
                                </tr>
                            </thead>
                            <tbody id="top-familias-body">
                                <tr><td colspan="4" class="text-center text-muted py-3">Cargando datos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por Cliente -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Top Clientes con Certificados</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Cliente</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Cantidad</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Inversión</th>
                                </tr>
                            </thead>
                            <tbody id="top-clientes-body">
                                <tr><td colspan="3" class="text-center text-muted py-3">Cargando datos...</td></tr>
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

        let colocacionChart = null;
        let plazosChart = null;

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

            fetch(`{{ route('certificados.data') }}?${urlParams}`)
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
            updateElementText('kpi-total-certificados', numberFormatter.format(data.totalCertificados || 0));
            updateElementText('kpi-ventas-pct', `${(data.ventasConCertificadoPct || 0).toFixed(1)}%`);
            updateElementText('kpi-monto-cobrado', formatter.format(data.montoCobrado || 0));
            
            updateElementText('kpi-ingreso-neto', formatter.format(data.ingresoNeto || 0));
            updateElementText('kpi-costos', formatter.format(data.costosAsociados || 0));
            
            updateElementText('kpi-tasa-uso-pct', `${(data.tasaUsoPct || 0).toFixed(1)}%`);
            updateElementText('kpi-cert-uso', numberFormatter.format(data.certificadosUtilizados || 0));
            updateElementText('kpi-cert-nouso', numberFormatter.format(data.certificadosNoUtilizados || 0));

            const pUso = document.getElementById('progress-uso');
            const pNoUso = document.getElementById('progress-nouso');
            if (pUso) pUso.style.width = `${data.tasaUsoPct || 0}%`;
            if (pNoUso) pNoUso.style.width = `${100 - (data.tasaUsoPct || 0)}%`;

            // Tablas
            const tFamilias = document.getElementById('top-familias-body');
            if (data.topCertificadosFamilia && data.topCertificadosFamilia.length > 0) {
                let htmlF = '';
                data.topCertificadosFamilia.forEach(f => {
                    htmlF += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${f.familia}</td>
                            <td class="py-3 text-center text-muted">${f.sucursal}</td>
                            <td class="py-3 text-end"><span class="badge bg-secondary rounded-pill">${f.cantidad}</span></td>
                            <td class="pe-4 py-3 text-end text-success fw-bold">${formatter.format(f.monto)}</td>
                        </tr>
                    `;
                });
                tFamilias.innerHTML = htmlF;
            } else {
                tFamilias.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Sin ventas de certificados</td></tr>';
            }

            const tClientes = document.getElementById('top-clientes-body');
            if (data.topClientesCertificados && data.topClientesCertificados.length > 0) {
                let htmlC = '';
                data.topClientesCertificados.forEach(c => {
                    htmlC += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${c.cliente}</td>
                            <td class="py-3 text-end"><span class="badge bg-secondary rounded-pill">${c.cantidad}</span></td>
                            <td class="pe-4 py-3 text-end text-success fw-bold">${formatter.format(c.monto)}</td>
                        </tr>
                    `;
                });
                tClientes.innerHTML = htmlC;
            } else {
                tClientes.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Sin ventas de certificados</td></tr>';
            }

            // Gráficos
            updatePlazosChart(data.chartDistribucionPlazo);
            updateMixedChart(data.chartCertificadosSucursal);
        }

        function updatePlazosChart(chartData) {
            const ctx = document.getElementById('plazosChart');
            if (!ctx) return;
            
            if (plazosChart) plazosChart.destroy();
            if (!chartData) return;

            plazosChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: [
                            '#0d6efd', // 15 días
                            '#198754', // 30 días
                            '#6c757d'  // Otros
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
            const ctx = document.getElementById('colocacionChart');
            if (!ctx) return;
            
            if (colocacionChart) colocacionChart.destroy();
            if (!chartData) return;

            colocacionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'line',
                            label: 'Cantidad Certificados',
                            data: chartData.cantidad,
                            borderColor: '#dc3545',
                            backgroundColor: '#dc3545',
                            yAxisID: 'y1',
                            tension: 0.1
                        },
                        {
                            type: 'bar',
                            label: 'Monto Cobrado ($)',
                            data: chartData.monto,
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
                            ticks: { callback: value => value }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
