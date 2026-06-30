@extends('employees.layouts.main')

@section('title', 'Clientes y Comportamiento de Uso')

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
    <h5 class="text-muted fw-bold">Analizando comportamiento de clientes...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Clientes y Comportamiento de Uso</h4>
            <p class="text-muted">Análisis de perfil de cliente, frecuencia de operaciones y LTV</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal (Registro o Principal)</label>
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
        <!-- Clientes Activos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Clientes Activos</h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-clientes">0</h2>
                    <span class="text-muted small">
                        <span class="text-success fw-bold" id="kpi-nuevos-pct">0%</span> Nuevos | 
                        <span class="text-info fw-bold" id="kpi-recurrentes-pct">0%</span> Recurrentes
                    </span>
                </div>
            </div>
        </div>

        <!-- Frecuencia Promedio -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Frecuencia Promedio</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-frecuencia-promedio">0.0</h2>
                    <span class="text-muted small">Empeños por cliente en el periodo</span>
                </div>
            </div>
        </div>

        <!-- Lifetime Value (LTV) -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">LTV Promedio</h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-gem"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-ltv-promedio">$ 0.00</h2>
                    <div class="mt-2 text-muted small">
                        Intereses + Compras + Certificados + Liquidaciones
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Secundarios -->
    <div class="row mb-4">
        <!-- Retención y Pérdida de Prendas -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-shape bg-light-warning text-warning me-3">
                            <i class="bi bi-unlock-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Comportamiento de Prendas</h6>
                            <h3 class="fw-bold text-dark mb-0"><span id="kpi-desempeno-pct" class="text-success">0%</span> Desempeñadas</h3>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <div id="progress-desempeno" class="progress-bar bg-success" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                                <div id="progress-perdidas" class="progress-bar bg-danger" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted mt-1 d-block"><span id="kpi-perdidas-pct" class="text-danger fw-bold">0%</span> Perdidas en remate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Préstamos vs Intereses pagados -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-shape bg-light-secondary text-secondary me-3">
                            <i class="bi bi-bank"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Monto Global Prestado</h6>
                            <h3 class="fw-bold text-dark mb-0" id="kpi-monto-prestado">$ 0.00</h3>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Intereses Pagados</small>
                            <span class="fw-bold text-success" id="kpi-intereses-pagados">$ 0.00</span>
                        </div>
                        <div class="col-6 border-start">
                            <small class="text-muted d-block">Dispersión Geográfica</small>
                            <span class="fw-bold text-primary"><span id="kpi-sucursales-cliente">0.0</span> sucursales/cte</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasa de Deserción -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3 card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Tasa de Deserción</h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-churn-rate">0.0%</h2>
                    <div class="mt-2 text-muted small">
                        Clientes sin actividad en los últimos 90 días
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Segmentaciones Semáforo Cliente -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Segmentación por Frecuencia</h5>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="segmentacionFrecuenciaChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- LTV -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Análisis Valor de Vida (LTV)</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="ltvChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Distribución RFM -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Distribución de Segmentos RFM</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="rfmChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablas de Análisis -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <h5 class="fw-bold mb-0">Análisis Detallado de Clientes</h5>
                        <!-- Nav tabs -->
                        <ul class="nav nav-pills" id="clientTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold" id="top-clientes-tab" data-bs-toggle="tab" data-bs-target="#top-clientes-pane" type="button" role="tab" aria-controls="top-clientes-pane" aria-selected="true">
                                    <i class="bi bi-star-fill me-1"></i> Top Clientes (LTV)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" id="rfm-tab" data-bs-toggle="tab" data-bs-target="#rfm-pane" type="button" role="tab" aria-controls="rfm-pane" aria-selected="false">
                                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> Segmentación RFM
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold position-relative" id="risk-tab" data-bs-toggle="tab" data-bs-target="#risk-pane" type="button" role="tab" aria-controls="risk-pane" aria-selected="false">
                                    <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i> Alertas de Riesgo
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="risk-badge" style="display: none;">0</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="tab-content" id="clientTabsContent">
                        <!-- Tab 1: Top Clientes -->
                        <div class="tab-pane fade show active" id="top-clientes-pane" role="tabpanel" aria-labelledby="top-clientes-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Cliente</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Sucursales</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Saldo Actual</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Intereses Históricos</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Compras en Piso</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Certificados</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Liquidaciones</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">LTV Estimado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-clientes-body">
                                        <tr><td colspan="8" class="text-center text-muted py-3">Cargando datos...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab 2: Segmentación RFM -->
                        <div class="tab-pane fade" id="rfm-pane" role="tabpanel" aria-labelledby="rfm-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Cliente</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Última Actividad</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Frecuencia</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Volumen LTV</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Score R-F-M</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Segmento</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rfm-clientes-body">
                                        <tr><td colspan="6" class="text-center text-muted py-3">Cargando datos...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab 3: Alertas de Riesgo -->
                        <div class="tab-pane fade" id="risk-pane" role="tabpanel" aria-labelledby="risk-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Cliente</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Días Inactivo</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Transacciones Históricas</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Monto LTV Histórico</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Nivel de Riesgo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="risk-clientes-body">
                                        <tr><td colspan="5" class="text-center text-muted py-3">Cargando datos...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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

        let frecuenciaChart = null;
        let ltvChartInst = null;
        let rfmChartInst = null;

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

            fetch(`{{ route('clientes.data') }}?${urlParams}`)
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
            updateElementText('kpi-total-clientes', numberFormatter.format(data.totalClientes || 0));
            updateElementText('kpi-nuevos-pct', `${(data.nuevosPorcentaje || 0).toFixed(1)}%`);
            updateElementText('kpi-recurrentes-pct', `${(data.recurrentesPorcentaje || 0).toFixed(1)}%`);
            
            updateElementText('kpi-frecuencia-promedio', (data.frecuenciaPromedio || 0).toFixed(1));
            updateElementText('kpi-ltv-promedio', formatter.format(data.ltvPromedio || 0));
            
            updateElementText('kpi-desempeno-pct', `${(data.porcentajeDesempeno || 0).toFixed(1)}%`);
            updateElementText('kpi-perdidas-pct', `${(data.porcentajePerdidas || 0).toFixed(1)}%`);
            
            const pDesempeno = document.getElementById('progress-desempeno');
            const pPerdidas = document.getElementById('progress-perdidas');
            if (pDesempeno) pDesempeno.style.width = `${data.porcentajeDesempeno || 0}%`;
            if (pPerdidas) pPerdidas.style.width = `${data.porcentajePerdidas || 0}%`;

            updateElementText('kpi-monto-prestado', formatter.format(data.montoTotalPrestado || 0));
            updateElementText('kpi-intereses-pagados', formatter.format(data.interesesTotalesPagados || 0));
            updateElementText('kpi-sucursales-cliente', (data.sucursalesPromedioPorCliente || 1.0).toFixed(1));
            
            // New KPI: Churn Rate
            updateElementText('kpi-churn-rate', `${(data.churnRate || 0).toFixed(1)}%`);

            // Tablas Top Clientes
            const tbody = document.getElementById('top-clientes-body');
            if (data.topClientes && data.topClientes.length > 0) {
                let tableHtml = '';
                data.topClientes.forEach(item => {
                    tableHtml += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${item.nombre}</td>
                            <td class="py-3 text-center"><span class="badge bg-secondary rounded-pill px-3 py-2">${item.sucursales}</span></td>
                            <td class="py-3 text-end">${formatter.format(item.saldo)}</td>
                            <td class="py-3 text-end text-success fw-bold">${formatter.format(item.intereses)}</td>
                            <td class="py-3 text-end">${formatter.format(item.compras_piso || 0)}</td>
                            <td class="py-3 text-end">${formatter.format(item.certificados || 0)}</td>
                            <td class="py-3 text-end">${formatter.format(item.liquidaciones || 0)}</td>
                            <td class="pe-4 py-3 text-end fw-bold text-primary">${formatter.format(item.ltv)}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = tableHtml;
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aún no hay datos para mostrar</td></tr>';
            }

            // Tablas RFM
            const rfmTbody = document.getElementById('rfm-clientes-body');
            if (data.rfmTable && data.rfmTable.length > 0) {
                let tableHtml = '';
                data.rfmTable.forEach(item => {
                    let badgeClass = 'bg-secondary';
                    if (item.segmento === 'Campeones') badgeClass = 'bg-success';
                    else if (item.segmento === 'Fieles') badgeClass = 'bg-info text-dark';
                    else if (item.segmento === 'Nuevos / Prometedores') badgeClass = 'bg-primary';
                    else if (item.segmento === 'En Riesgo / Atención') badgeClass = 'bg-warning text-dark';
                    else if (item.segmento === 'Hibernando / Perdidos') badgeClass = 'bg-danger';

                    tableHtml += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${item.nombre}</td>
                            <td class="py-3 text-center">${item.recencia_texto}</td>
                            <td class="py-3 text-center"><span class="badge bg-light text-dark border px-3 py-2 fw-semibold">${item.frecuencia} transacciones</span></td>
                            <td class="py-3 text-end fw-semibold text-dark">${formatter.format(item.monto)}</td>
                            <td class="py-3 text-center"><code class="fs-6 fw-bold text-secondary">${item.score}</code></td>
                            <td class="pe-4 py-3 text-center"><span class="badge ${badgeClass} px-3 py-2">${item.segmento}</span></td>
                        </tr>
                    `;
                });
                rfmTbody.innerHTML = tableHtml;
            } else {
                rfmTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aún no hay datos para mostrar</td></tr>';
            }

            // Tablas Alertas de Riesgo
            const riskTbody = document.getElementById('risk-clientes-body');
            const riskBadge = document.getElementById('risk-badge');
            if (data.clientesRiesgo && data.clientesRiesgo.length > 0) {
                if (riskBadge) {
                    riskBadge.innerText = data.clientesRiesgo.length;
                    riskBadge.style.display = 'inline-block';
                }
                let tableHtml = '';
                data.clientesRiesgo.forEach(item => {
                    let riskBadgeClass = item.nivel_riesgo === 'Riesgo Alto' ? 'bg-danger' : 'bg-warning text-dark';
                    tableHtml += `
                        <tr>
                            <td class="ps-4 py-3 fw-bold text-dark">${item.nombre}</td>
                            <td class="py-3 text-center fw-bold text-danger">${item.dias_inactivo} días</td>
                            <td class="py-3 text-center">${item.frecuencia} transacciones</td>
                            <td class="py-3 text-end fw-semibold text-dark">${formatter.format(item.monto)}</td>
                            <td class="pe-4 py-3 text-center"><span class="badge ${riskBadgeClass} px-3 py-2 fw-bold"><i class="bi bi-exclamation-octagon me-1"></i>${item.nivel_riesgo}</span></td>
                        </tr>
                    `;
                });
                riskTbody.innerHTML = tableHtml;
            } else {
                if (riskBadge) riskBadge.style.display = 'none';
                riskTbody.innerHTML = '<tr><td colspan="5" class="text-center text-success fw-semibold py-4"><i class="bi bi-check-circle-fill me-2"></i>No hay clientes de alto valor en riesgo de abandono</td></tr>';
            }

            // Gráficos
            updateDoughnutChart(data.chartSegmentacionFrecuencia);
            updateBarChart(data.chartLTV);
            updateRFMChart(data.chartRFM);
        }

        function updateDoughnutChart(chartData) {
            const ctx = document.getElementById('segmentacionFrecuenciaChart');
            if (!ctx) return;
            
            if (frecuenciaChart) frecuenciaChart.destroy();
            if (!chartData) return;

            frecuenciaChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: [
                            '#6c757d', // Ocasionales
                            '#fd7e14', // Regulares
                            '#198754'  // Frecuentes
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

        function updateBarChart(chartData) {
            const ctx = document.getElementById('ltvChart');
            if (!ctx) return;
            
            if (ltvChartInst) ltvChartInst.destroy();
            if (!chartData) return;

            ltvChartInst = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'LTV Promedio',
                        data: chartData.data,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
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
                            beginAtZero: true,
                            ticks: { callback: value => formatter.format(value) }
                        }
                    }
                }
            });
        }

        function updateRFMChart(chartData) {
            const ctx = document.getElementById('rfmChart');
            if (!ctx) return;
            
            if (rfmChartInst) rfmChartInst.destroy();
            if (!chartData) return;

            rfmChartInst = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Clientes por Segmento',
                        data: chartData.data,
                        backgroundColor: [
                            '#198754', // Campeones
                            '#0dcaf0', // Fieles
                            '#0d6efd', // Nuevos / Prometedores
                            '#ffc107', // En Riesgo
                            '#dc3545'  // Perdidos
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
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
