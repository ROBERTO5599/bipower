@extends('employees.layouts.main')

@section('title', 'Empeños y Cartera')

@section('styles')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
        .icon-shape {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border-radius: 50%;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .bg-light-purple { background-color: rgba(111, 66, 193, 0.1); color: #6f42c1; }
        
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
        .table-ranking th { font-weight: 600; text-transform: uppercase; font-size: 0.8rem; color: #6c757d; }
        .table-ranking td { font-size: 0.9rem; vertical-align: middle; }
    </style>
@endsection

@section('content')
<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <h5 class="text-muted fw-bold">Calculando métricas...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark mb-1">Operaciones y Cartera</h4>
            <p class="text-muted">Análisis de transacciones, métricas de cartera y comportamiento de artículos.</p>
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
                            <option value="{{ $sucursal->id_valora_mas }}">{{ $sucursal->nombre }}</option>
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

    <!-- MAIN KPIs ROW 1 -->
    <div class="row mb-4">
        <!-- Empeños -->
        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-primary border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">Empeños</h6>
                        <div class="icon-shape bg-light-primary"><i class="bi bi-tags-fill"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0" id="kpi-empenos-monto">$ 0.00</h3>
                    <div class="d-flex justify-content-between mt-2">
                        <span class="text-muted small fw-semibold" id="kpi-empenos-contratos">0 Contratos</span>
                        <span class="badge bg-primary rounded-pill d-flex align-items-center" id="kpi-empenos-promedio">...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refrendos -->
        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-info border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">Refrendos</h6>
                        <div class="icon-shape bg-light-info"><i class="bi bi-arrow-repeat"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0" id="kpi-refrendos-monto">$ 0.00</h3>
                    <span class="text-muted small fw-semibold" id="kpi-refrendos-total">0 Operaciones</span>
                </div>
            </div>
        </div>

        <!-- Desempeños -->
        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-success border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">Desempeños</h6>
                        <div class="icon-shape bg-light-success"><i class="bi bi-box-arrow-right"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0" id="kpi-desempenos-monto">$ 0.00</h3>
                    <span class="text-muted small fw-semibold" id="kpi-desempenos-total">0 Operaciones</span>
                </div>
            </div>
        </div>
        
        <!-- Cartera Total -->
        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-warning border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.8rem;">Cartera Total</h6>
                        <div class="icon-shape bg-light-warning"><i class="bi bi-briefcase-fill"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0" id="kpi-cartera-monto">$ 0.00</h3>
                    <div class="d-flex justify-content-between mt-2">
                        <span class="text-success small fw-semibold"><i class="bi bi-check-circle"></i> <span id="kpi-cartera-vigente"></span></span>
                        <span class="text-danger small fw-semibold"><i class="bi bi-exclamation-triangle"></i> <span id="kpi-cartera-vencida"></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECONDARY KPIs ROW 2 -->
    <div class="row mb-4">
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body text-center p-4">
                    <h6 class="text-muted mb-2">Tasa Real de Interés (Mes)</h6>
                    <h2 class="display-6 fw-bold text-purple mb-0" id="kpi-tasa-real">0.0%</h2>
                    <p class="text-muted small mt-1 mb-0">Calculado s/ cartera total</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body text-center p-4">
                    <h6 class="text-muted mb-2">Promedio Días a Desempeño</h6>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-dias-promedio">0</h2>
                    <p class="text-muted small mt-1 mb-0">Días transcurridos</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body text-center p-4">
                    <h6 class="text-muted mb-2">Tasa de Efectividad (% Sobreavalúo)</h6>
                    <h2 class="display-6 fw-bold text-info mb-0" id="kpi-sobreavaluo">0.0%</h2>
                    <p class="text-muted small mt-1 mb-0">Préstamo promedio sobre avalúo</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="row mb-4">
        <!-- Cartera por Mora -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-4">Distribución de Cartera por Días de Atraso</h5>
                    <div style="height: 300px;">
                        <canvas id="moraChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cartera por Categoría -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-4">Cartera por Tipo</h5>
                    <div style="height: 300px;">
                        <canvas id="carteraTipoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLES -->
    <div class="row mb-4">
        <!-- Ranking Empeños -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="bi bi-star-fill text-warning me-2"></i> Top 5 Artículos más Empeñados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-ranking mb-0">
                            <thead>
                                <tr>
                                    <th>Artículo</th>
                                    <th class="text-center">Operaciones</th>
                                    <th class="text-end">Monto Total</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-empenados">
                                <!-- Filas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ranking Desempeños -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="bi bi-box-arrow-right text-success me-2"></i> Top 5 Artículos más Desempeñados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-ranking mb-0">
                            <thead>
                                <tr>
                                    <th>Artículo</th>
                                    <th class="text-center">Operaciones</th>
                                    <th class="text-end">Monto Total</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-desempenados">
                                <!-- Filas dinámicas -->
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        
        let moraChartInstance = null;
        let carteraTipoChartInstance = null;

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const numberFormatter = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 });

        loadData();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            fetch(`{{ route('operaciones-cartera.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    updateDashboard(data);
                })
                .catch(err => {
                    console.error("Error:", err);
                    alert("Error cargando la información");
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function updateElement(id, value) {
            const el = document.getElementById(id);
            if(el) el.innerHTML = value;
        }

        function buildTableRows(tableId, dataList) {
            const tbody = document.getElementById(tableId);
            if (!tbody) return;
            tbody.innerHTML = '';
            
            if (!dataList || dataList.length === 0) {
                tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted">No hay datos disponibles</td></tr>`;
                return;
            }

            dataList.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-truncate" style="max-width: 250px;" title="${item.articulo}">${item.articulo}</td>
                    <td class="text-center fw-bold">${item.total}</td>
                    <td class="text-end text-primary fw-bold">${formatter.format(item.monto)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function updateDashboard(data) {
            // Main KPIs
            updateElement('kpi-empenos-monto', formatter.format(data.empenos.monto_total));
            updateElement('kpi-empenos-contratos', `${data.empenos.total_contratos} Contratos`);
            updateElement('kpi-empenos-promedio', `Prom: ${formatter.format(data.empenos.prestamo_promedio)}`);
            
            updateElement('kpi-refrendos-monto', formatter.format(data.refrendos.monto));
            updateElement('kpi-refrendos-total', `${data.refrendos.total} Operaciones`);
            
            updateElement('kpi-desempenos-monto', formatter.format(data.desempenos.monto));
            updateElement('kpi-desempenos-total', `${data.desempenos.total} Operaciones`);

            const carteraTotal = data.cartera.vigente + data.cartera.vencida;
            updateElement('kpi-cartera-monto', formatter.format(carteraTotal));
            updateElement('kpi-cartera-vigente', `Vigente: ${formatter.format(data.cartera.vigente)}`);
            updateElement('kpi-cartera-vencida', `Vencida: ${formatter.format(data.cartera.vencida)}`);

            // Secondary KPIs
            updateElement('kpi-tasa-real', `${numberFormatter.format(data.intereses.tasa_real_mensual_pct)}%`);
            updateElement('kpi-dias-promedio', Math.round(data.tiempos.promedio_dias));
            updateElement('kpi-sobreavaluo', `${numberFormatter.format(data.empenos.sobreavaluo_pct)}%`);

            // Tables
            buildTableRows('tbody-empenados', data.rankings.articulos_empenados);
            buildTableRows('tbody-desempenados', data.rankings.articulos_desempenados);

            // Chart: Mora
            renderMoraChart(data.mora);

            // Chart: Cartera Tipo
            renderCarteraTipoChart(data.cartera);
        }

        function renderMoraChart(moraData) {
            const ctx = document.getElementById('moraChart').getContext('2d');
            if (moraChartInstance) { moraChartInstance.destroy(); }

            moraChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['0-30 Días', '31-60 Días', '61-90 Días', '+90 Días'],
                    datasets: [{
                        label: 'Monto en Mora ($)',
                        data: [
                            moraData['0_30'], 
                            moraData['31_60'], 
                            moraData['61_90'], 
                            moraData['mas_90']
                        ],
                        backgroundColor: [
                            'rgba(13, 202, 240, 0.7)', // info
                            'rgba(255, 193, 7, 0.7)',  // warning
                            'rgba(253, 126, 20, 0.7)', // orange
                            'rgba(220, 53, 69, 0.7)'   // danger
                        ],
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { callback: function(value) { return '$' + Intl.NumberFormat('es-MX', { notation: "compact" }).format(value); } }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) { return ' ' + formatter.format(context.raw); }
                            }
                        }
                    }
                }
            });
        }

        function renderCarteraTipoChart(carteraData) {
            const ctx = document.getElementById('carteraTipoChart').getContext('2d');
            if (carteraTipoChartInstance) { carteraTipoChartInstance.destroy(); }

            carteraTipoChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Oro/Alhajas', 'Varios', 'Autos'],
                    datasets: [{
                        data: [carteraData.oro, carteraData.varios, carteraData.auto],
                        backgroundColor: [
                            '#f1c40f', // gold
                            '#3498db', // blue
                            '#e74c3c'  // red
                        ],
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20, usePointStyle: true }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) { return ' ' + formatter.format(context.raw); }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
