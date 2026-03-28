@extends('employees.layouts.main')

@section('title', 'Gastos y Estados Financieros')

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
    <h5 class="text-muted fw-bold">Analizando estructura financiera...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Gastos y Estados Financieros</h4>
            <p class="text-muted">Análisis de rentabilidad, estructura de gastos y finanzas integradas</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal (Para Estado de Resultados)</label>
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

    <!-- Pestañas internas para Estados Financieros -->
    <ul class="nav nav-tabs mb-4" id="finanzasTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="resultados-tab" data-bs-toggle="tab" data-bs-target="#resultados" type="button" role="tab" aria-controls="resultados" aria-selected="true">Estado de Resultados</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="balance-tab" data-bs-toggle="tab" data-bs-target="#balance" type="button" role="tab" aria-controls="balance" aria-selected="false">Balance General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="flujo-tab" data-bs-toggle="tab" data-bs-target="#flujo" type="button" role="tab" aria-controls="flujo" aria-selected="false">Flujo de Efectivo</button>
        </li>
    </ul>

    <div class="tab-content" id="finanzasTabsContent">
        <!-- 1. ESTADO DE RESULTADOS OP -->
        <div class="tab-pane fade show active" id="resultados" role="tabpanel" aria-labelledby="resultados-tab">
            
            <!-- KPIs Principales Estado Resultados -->
            <div class="row mb-4 justify-content-center">
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Utilidad Bruta</h6>
                                <div class="icon-shape bg-light-primary text-primary">
                                    <i class="bi bi-arrow-up-right-circle"></i>
                                </div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-utilidad-bruta">$ 0.00</h2>
                            <span class="text-muted small">Ingresos Totales: <span class="fw-bold" id="kpi-ingresos-totales">$ 0.00</span></span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Utilidad Operativa</h6>
                                <div class="icon-shape bg-light-info text-info">
                                    <i class="bi bi-gear-wide-connected"></i>
                                </div>
                            </div>
                            <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-utilidad-operativa">$ 0.00</h2>
                            <span class="text-muted small">Gastos Operativos: <span class="fw-bold text-danger" id="kpi-gastos-operativos">$ 0.00</span></span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Utilidad Neta</h6>
                                <div class="icon-shape bg-light-success text-success">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                            </div>
                            <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-utilidad-neta">$ 0.00</h2>
                            <div class="mt-2 text-muted small">
                                Margen Neto: <span class="fw-bold text-success" id="kpi-margen-neto">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ratios y Análisis de Gastos -->
            <div class="row mb-4">
                <div class="col-12 col-xl-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Indicadores de Gastos Operativos (vs Utilidad Bruta)</h5>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">Nómina / Utilidad Bruta</span>
                                    <span class="fw-bold" id="kpi-ratio-nomina">0%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div id="prog-nomina" class="progress-bar bg-info" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">Renta / Utilidad Bruta</span>
                                    <span class="fw-bold" id="kpi-ratio-renta">0%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div id="prog-renta" class="progress-bar bg-warning" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">Gastos Totales / Utilidad Bruta</span>
                                    <span class="fw-bold text-danger" id="kpi-ratio-total">0%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div id="prog-total" class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Composición de Gastos</h5>
                        </div>
                        <div class="card-body p-4 d-flex justify-content-center align-items-center">
                            <canvas id="composicionGastosChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12 mb-3">
                    <div class="card shadow-sm border-0 h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Evolución de Ingresos y Utilidades</h5>
                        </div>
                        <div class="card-body p-4">
                            <canvas id="evolucionResultadosChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Estado de Resultados -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h5 class="fw-bold mb-0">Estado de Resultados Detallado</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <tbody id="estado-resultados-body">
                                        <tr><td class="text-center text-muted py-5">Generando estado financiero...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 2. BALANCE GENERAL -->
        <div class="tab-pane fade" id="balance" role="tabpanel" aria-labelledby="balance-tab">
            <div class="row mb-4 justify-content-center">
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-2">Total Activos</h6>
                            <h2 class="display-6 fw-bold text-primary mb-0" id="kpi-activos">$ 0.00</h2>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-2">Total Pasivos</h6>
                            <h2 class="display-6 fw-bold text-danger mb-0" id="kpi-pasivos">$ 0.00</h2>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-2">Capital Contable</h6>
                            <h2 class="display-6 fw-bold text-success mb-0" id="kpi-capital">$ 0.00</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Balance General Detallado</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody id="balance-general-body">
                                <tr><td class="text-center text-muted py-5">Generando balance...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. FLUJO DE EFECTIVO -->
        <div class="tab-pane fade" id="flujo" role="tabpanel" aria-labelledby="flujo-tab">
            <div class="row mb-4 justify-content-center">
                <div class="col-12 col-xl-3 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4 text-center">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-2">Operación</h6>
                            <h3 class="fw-bold text-dark mb-0" id="kpi-flujo-op">$ 0.00</h3>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-3 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4 text-center">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-2">Inversión</h6>
                            <h3 class="fw-bold text-dark mb-0" id="kpi-flujo-inv">$ 0.00</h3>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-3 mb-3">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-body p-4 text-center">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-2">Financiamiento</h6>
                            <h3 class="fw-bold text-dark mb-0" id="kpi-flujo-fin">$ 0.00</h3>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-3 mb-3">
                    <div class="card shadow-sm border-0 card-hover bg-primary h-100 rounded-3">
                        <div class="card-body p-4 text-center">
                            <h6 class="text-white text-uppercase fw-bold ls-1 mb-2">Flujo Neto Efectivo</h6>
                            <h2 class="fw-bold text-white mb-0" id="kpi-flujo-neto">$ 0.00</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
                <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                <div>
                    <strong>Nota sobre el Flujo de Efectivo:</strong> Este reporte requiere la integración total de los saldos bancarios. La versión actual muestra un consolidado estimado con fines comerciales.
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

        let composicionChart = null;
        let evolucionChart = null;

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

            fetch(`{{ route('gastos-finanzas.data') }}?${urlParams}`)
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
            // ER
            updateElementText('kpi-utilidad-bruta', formatter.format(data.utilidadBruta || 0));
            updateElementText('kpi-ingresos-totales', formatter.format(data.ingresosTotales || 0));
            
            updateElementText('kpi-utilidad-operativa', formatter.format(data.utilidadOperativa || 0));
            updateElementText('kpi-gastos-operativos', formatter.format(data.gastosOperativos || 0));
            
            updateElementText('kpi-utilidad-neta', formatter.format(data.utilidadNeta || 0));
            updateElementText('kpi-margen-neto', `${(data.margenNetoPct || 0).toFixed(1)}%`);

            // Ratios (Barras)
            const pNomina = data.nominaSobreUtilidadBrutaPct || 0;
            const pRenta = data.rentaSobreUtilidadBrutaPct || 0;
            const pTotal = data.gastosTotalesSobreUBPct || 0;
            
            updateElementText('kpi-ratio-nomina', `${pNomina.toFixed(1)}%`);
            updateElementText('kpi-ratio-renta', `${pRenta.toFixed(1)}%`);
            updateElementText('kpi-ratio-total', `${pTotal.toFixed(1)}%`);

            document.getElementById('prog-nomina').style.width = `${Math.min(pNomina, 100)}%`;
            document.getElementById('prog-renta').style.width = `${Math.min(pRenta, 100)}%`;
            document.getElementById('prog-total').style.width = `${Math.min(pTotal, 100)}%`;

            // Balance
            updateElementText('kpi-activos', formatter.format(data.totalActivos || 0));
            updateElementText('kpi-pasivos', formatter.format(data.totalPasivos || 0));
            updateElementText('kpi-capital', formatter.format(data.capitalContable || 0));

            // Flujo
            updateElementText('kpi-flujo-op', formatter.format(data.flujoOperacion || 0));
            updateElementText('kpi-flujo-inv', formatter.format(data.flujoInversion || 0));
            updateElementText('kpi-flujo-fin', formatter.format(data.flujoFinanciamiento || 0));
            updateElementText('kpi-flujo-neto', formatter.format(data.flujoNeto || 0));

            // Gráficos
            updateDoughnutChart(data.chartComposicionGastos);
            updateLineChart(data.chartEvolucionIngresosUtilidad);
            
            // Render dummy tables
            renderFakeER(data);
            renderFakeBalance(data);
        }

        function updateDoughnutChart(chartData) {
            const ctx = document.getElementById('composicionGastosChart');
            if (!ctx) return;
            
            if (composicionChart) composicionChart.destroy();
            if (!chartData) return;

            composicionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: [
                            '#0d6efd', // Nomina
                            '#fd7e14', // Renta
                            '#198754', // Servicios
                            '#0dcaf0', // Publi
                            '#ffc107', // Mant
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

        function updateLineChart(chartData) {
            const ctx = document.getElementById('evolucionResultadosChart');
            if (!ctx) return;
            
            if (evolucionChart) evolucionChart.destroy();
            if (!chartData) return;

            evolucionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: chartData.ingresos,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Utilidad Bruta',
                            data: chartData.utilidadBruta,
                            borderColor: '#198754',
                            backgroundColor: 'transparent',
                            tension: 0.3
                        },
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidadNeta,
                            borderColor: '#6f42c1',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            borderDash: [5, 5]
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
        
        function renderFakeER(data) {
            const tb = document.getElementById('estado-resultados-body');
            tb.innerHTML = `
                <tr><td class="fw-bold text-primary">Ingresos Totales</td><td class="text-end fw-bold text-primary">${formatter.format(data.ingresosTotales)}</td></tr>
                <tr><td class="ps-4">Costos de Venta</td><td class="text-end text-danger">- ${formatter.format(data.costoVentas)}</td></tr>
                <tr class="table-light"><td class="fw-bold">Utilidad Bruta</td><td class="text-end fw-bold">${formatter.format(data.utilidadBruta)}</td></tr>
                <tr><td class="ps-4">Gastos Operativos (Nómina, Renta, etc.)</td><td class="text-end text-danger">- ${formatter.format(data.gastosOperativos)}</td></tr>
                <tr class="table-light"><td class="fw-bold">Utilidad Operativa</td><td class="text-end fw-bold">${formatter.format(data.utilidadOperativa)}</td></tr>
                <tr><td class="ps-4">Gastos Financieros</td><td class="text-end text-danger">- ${formatter.format(data.gastosFinancieros)}</td></tr>
                <tr><td class="ps-4">Impuestos</td><td class="text-end text-danger">- ${formatter.format(data.impuestos)}</td></tr>
                <tr class="table-success"><td class="fw-bold fs-5">Utilidad Neta</td><td class="text-end fw-bold fs-5">${formatter.format(data.utilidadNeta)}</td></tr>
            `;
        }

        function renderFakeBalance(data) {
            const tb = document.getElementById('balance-general-body');
            tb.innerHTML = `
                <tr class="table-light"><td class="fw-bold text-primary">ACTIVOS</td><td class="text-end fw-bold text-primary">${formatter.format(data.totalActivos)}</td></tr>
                <tr><td class="ps-4">Efectivo y Bancos</td><td class="text-end">${formatter.format(data.totalActivos * 0.1)}</td></tr>
                <tr><td class="ps-4">Cartera de Empeños y Créditos</td><td class="text-end">${formatter.format(data.totalActivos * 0.6)}</td></tr>
                <tr><td class="ps-4">Inventarios en Piso</td><td class="text-end">${formatter.format(data.totalActivos * 0.3)}</td></tr>
                
                <tr class="table-light"><td class="fw-bold text-danger">PASIVOS</td><td class="text-end fw-bold text-danger">${formatter.format(data.totalPasivos)}</td></tr>
                <tr><td class="ps-4">Créditos Bancarios</td><td class="text-end">${formatter.format(data.totalPasivos * 0.8)}</td></tr>
                <tr><td class="ps-4">Cuentas por Pagar</td><td class="text-end">${formatter.format(data.totalPasivos * 0.2)}</td></tr>
                
                <tr class="table-light"><td class="fw-bold text-success">CAPITAL CONTABLE</td><td class="text-end fw-bold text-success">${formatter.format(data.capitalContable)}</td></tr>
                <tr><td class="ps-4">Capital Social</td><td class="text-end">${formatter.format(data.totalActivos - data.totalPasivos)}</td></tr>
            `;
        }
    });
</script>
@endsection
