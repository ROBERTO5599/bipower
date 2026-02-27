@extends('employees.layouts.main')

@section('title', 'Resumen Ejecutivo')

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
    <h5 class="text-muted fw-bold">Calculando métricas globales...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Resumen Ejecutivo Global</h4>
            <p class="text-muted">Vista rápida del desempeño global de Valora Más</p>
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

    <!-- Tarjetas KPI Principales (Financieros) -->
    <div class="row mb-4">
        <!-- Ingresos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Ingresos Totales</h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ingresos">$ 0.00</h2>
                    <span class="text-muted small">Ventas + Intereses</span>
                </div>
            </div>
        </div>

        <!-- Gastos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Gastos Operativos</h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-gastos">$ 0.00</h2>
                    <span class="text-muted small">Total egresos registrados</span>
                </div>
            </div>
        </div>

        <!-- Utilidad Neta -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Utilidad Neta</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-utilidad">$ 0.00</h2>
                    <span class="badge bg-secondary mt-2" id="kpi-margen">0% Margen</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y Desglose -->
    <div class="row mb-4">
        <!-- Gráfico de Comparativa Financiera -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Comparativa Financiera Global</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="financialChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- KPI Cards Secundarios -->
        <div class="col-md-4">
            <!-- Inventario -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-warning text-warning me-3">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">Inventario (Piso)</h6>
                            <h4 class="fw-bold text-dark mb-0" id="kpi-inventario">$ 0.00</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empeños -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-primary text-primary me-3">
                            <i class="bi bi-tags-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">Empeños (Nuevos)</h6>
                            <h4 class="fw-bold text-dark mb-0" id="kpi-empeno">$ 0.00</h4>
                            <small class="text-muted" id="kpi-empeno-contratos">0 Contratos</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Composición Inventario -->
            <div class="card shadow-sm border-0 rounded-3">
                 <div class="card-body">
                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-3">Composición Inventario</h6>
                    <canvas id="inventoryChart" height="200"></canvas>
                 </div>
            </div>
        </div>
    </div>

    <!-- Tabla Semáforo por Sucursal -->
    <div class="row" id="branch-table-container" style="display: none;">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Desempeño por Sucursal</h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#branchPerformanceChart" aria-expanded="false">
                        <i class="bi bi-bar-chart-line"></i> Ver Gráfico
                    </button>
                </div>
                <div class="card-body p-0">
                    <!-- Collapse para gráfico comparativo -->
                    <div class="collapse p-4" id="branchPerformanceChart">
                         <canvas id="branchesChart" height="100"></canvas>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ingresos</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Gastos</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Utilidad Neta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Margen %</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Inv. Total</th>
                                </tr>
                            </thead>
                            <tbody id="branch-table-body">
                                <!-- Filas generadas por JS -->
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

        // Chart instances
        let finChart = null;
        let invChart = null;
        let branchChart = null;

        // Formatter
        const formatter = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
        });

        // Elements
        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const branchTableContainer = document.getElementById('branch-table-container');

        // Load data initially
        loadData();

        // Handle form submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            // Show loader
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5'; // Dim background

            const urlParams = new URLSearchParams(new FormData(form)).toString();

            fetch(`{{ route('resumen-ejecutivo.data') }}?${urlParams}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error en la red');
                    return response.json();
                })
                .then(data => {
                    updateDashboard(data);
                })
                .catch(error => {
                    console.error("Error cargando datos:", error);
                    alert("Ocurrió un error al cargar la información.");
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function updateDashboard(data) {
            // 1. Update KPI Cards
            document.getElementById('kpi-ingresos').innerText = formatter.format(data.totalIngresos);
            document.getElementById('kpi-gastos').innerText = formatter.format(data.totalEgresos);

            const utilEl = document.getElementById('kpi-utilidad');
            utilEl.innerText = formatter.format(data.utilidadNeta);
            utilEl.className = `display-6 fw-bold mb-0 ${data.utilidadNeta >= 0 ? 'text-dark' : 'text-danger'}`;

            const margenEl = document.getElementById('kpi-margen');
            const margenVal = data.totalIngresos > 0 ? ((data.utilidadNeta / data.totalIngresos) * 100).toFixed(1) : 0;
            margenEl.innerText = `${margenVal}% Margen`;
            margenEl.className = `badge mt-2 ${data.utilidadNeta >= 0 ? 'bg-success' : 'bg-danger'}`;

            document.getElementById('kpi-inventario').innerText = formatter.format(data.inventarioPisoVentaTotal);
            document.getElementById('kpi-empeno').innerText = formatter.format(data.empenosData.prestamo);
            document.getElementById('kpi-empeno-contratos').innerText = `${data.empenosData.contratos} Contratos`;

            // 2. Update Charts
            updateFinancialChart(data.chartFinanciero);
            updateInventoryChart(data.chartInventario);

            // 3. Update Table and Branch Chart
            const isSingleBranch = document.getElementById('sucursal_id').value !== "";
            if (!isSingleBranch && Object.keys(data.branchKPIs).length > 0) {
                branchTableContainer.style.display = 'flex';
                updateBranchTable(data.branchKPIs);
                updateBranchChart(data.chartSucursales);
            } else {
                branchTableContainer.style.display = 'none';
            }
        }

        function updateFinancialChart(chartData) {
            const ctx = document.getElementById('financialChart');
            if (finChart) finChart.destroy();

            finChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto (MXN)',
                        data: chartData.data,
                        backgroundColor: [
                            'rgba(25, 135, 84, 0.7)',
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(13, 202, 240, 0.7)'
                        ],
                        borderColor: [
                            'rgba(25, 135, 84, 1)',
                            'rgba(220, 53, 69, 1)',
                            'rgba(13, 202, 240, 1)'
                        ],
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        function updateInventoryChart(chartData) {
            const ctx = document.getElementById('inventoryChart');
            if (invChart) invChart.destroy();

            invChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#FFD700', '#C0C0C0', '#fd7e14', '#0dcaf0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } },
                    cutout: '70%'
                }
            });
        }

        function updateBranchTable(branchKPIs) {
            const tbody = document.getElementById('branch-table-body');
            tbody.innerHTML = '';

            for (const [nombre, kpi] of Object.entries(branchKPIs)) {
                const utilClass = kpi.utilidad_neta >= 0 ? 'text-success' : 'text-danger';
                const margen = kpi.margen_bruto_pct;
                const badgeClass = margen > 30 ? 'bg-success' : (margen > 15 ? 'bg-warning text-dark' : 'bg-danger');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-4 fw-semibold text-dark">${nombre}</td>
                    <td class="text-end">${formatter.format(kpi.ingresos)}</td>
                    <td class="text-end">${formatter.format(kpi.gastos)}</td>
                    <td class="text-end fw-bold ${utilClass}">${formatter.format(kpi.utilidad_neta)}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass} rounded-pill">${margen.toFixed(1)}%</span>
                    </td>
                    <td class="pe-4 text-end">${formatter.format(kpi.inventario_total)}</td>
                `;
                tbody.appendChild(tr);
            }
        }

        function updateBranchChart(chartData) {
            const ctx = document.getElementById('branchesChart');
            if (branchChart) branchChart.destroy();

            branchChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: chartData.ingresos,
                            backgroundColor: 'rgba(118, 75, 162, 0.6)',
                            borderRadius: 4
                        },
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidades,
                            backgroundColor: 'rgba(13, 202, 240, 0.6)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

    });
</script>
@endsection