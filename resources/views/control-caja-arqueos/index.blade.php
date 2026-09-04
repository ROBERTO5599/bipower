@extends('employees.layouts.main')

@section('title', 'Control de Caja y Arqueos')

@section('styles')
<style type="text/css">
    /* Premium Dashboard Styles */
    .dashboard-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
    }
    
    .card-kpi {
        border: none;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    .card-kpi:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px 0 rgba(0, 0, 0, 0.08);
    }
    
    .card-kpi::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .kpi-purple::before { background: #667eea; }
    .kpi-blue::before { background: #3b82f6; }
    .kpi-green::before { background: #1cc88a; }
    .kpi-red::before { background: #e74a3b; }
    .kpi-orange::before { background: #fd7e14; }
    
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
    }
    
    .bg-light-purple { background: rgba(102, 126, 234, 0.1); color: #667eea; }
    .bg-light-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .bg-light-green { background: rgba(28, 200, 138, 0.1); color: #1cc88a; }
    .bg-light-red { background: rgba(231, 74, 59, 0.1); color: #e74a3b; }
    .bg-light-orange { background: rgba(253, 126, 20, 0.1); color: #fd7e14; }

    /* Custom elegant tabs */
    .nav-pills-custom .nav-link {
        color: #667eea;
        font-weight: 600;
        border-radius: 30px;
        padding: 10px 25px;
        margin-right: 10px;
        transition: all 0.3s ease;
        border: 1px solid rgba(102, 126, 234, 0.2);
        background-color: #fff;
    }

    .nav-pills-custom .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    /* Custom table container */
    .tablero-table-container {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.05);
        background: #fff;
    }

    .table-custom thead th {
        background-color: #f8f9fc;
        color: #5a5c69;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 15px 12px;
        border-bottom: 2px solid #e3e6f0;
        vertical-align: middle;
    }

    .table-custom tbody td {
        padding: 14px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #e3e6f0;
        font-size: 0.85rem;
    }

    .table-custom tbody tr:hover td {
        background-color: #f8f9fc;
    }

    .font-monospace-custom {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    /* Loading Overlay */
    #loading-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.85);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
    }

    .chart-container {
        position: relative;
        height: 350px;
        width: 100%;
    }

    /* Quick Range Button styling */
    .quick-range-btn {
        transition: all 0.2s ease;
        font-weight: 500;
    }
    .quick-range-btn.active {
        background-color: #667eea;
        color: #fff;
        border-color: #667eea;
    }

    /* Print styles */
    @media print {
        body * {
            visibility: hidden;
        }
        #print-section, #print-section * {
            visibility: visible;
        }
        #print-section {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        #sidebar-wrapper, #menu-toggle, #filter-form, .btn, #loading-overlay, .navbar, .nav-pills-custom {
            display: none !important;
        }
    }
</style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-grow text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Procesando Datos...</span>
    </div>
    <h5 class="text-dark fw-bold mb-1">Cargando Control de Caja y Arqueos...</h5>
    <p class="text-muted small">Por favor espere mientras consolidamos la información de las sucursales.</p>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="title fw-bold text-dark mb-1">Control de Caja y Arqueos</h4>
                <p class="text-muted mb-0">Consolidado de saldos, arqueos y diferencias en efectivo por sucursal</p>
            </div>
            <div>
                <button onclick="window.print();" class="btn btn-outline-primary shadow-sm fw-bold">
                    <i class="bi bi-printer me-2"></i>Imprimir Reporte
                </button>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="sucursal_id" class="form-label fw-bold text-muted small text-uppercase">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label fw-bold text-muted small text-uppercase">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="{{ $fechaInicio }}">
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label fw-bold text-muted small text-uppercase">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="{{ $fechaFin }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary fw-bold sidebar-gradient border-0 py-2">
                        <i class="bi bi-search me-2"></i>Consultar
                    </button>
                </div>
            </form>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="today">Hoy</button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn active" data-range="month">Este Mes</button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="prev-month">Mes Anterior</button>
                <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="year">Este Año</button>
            </div>
        </div>
    </div>

    <!-- Pestañas de Cierres vs Arqueos -->
    <div class="mb-4">
        <ul class="nav nav-pills nav-pills-custom" id="moduleTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="cierres-tab" data-bs-toggle="pill" data-bs-target="#cierres-pane" type="button" role="tab" aria-controls="cierres-pane" aria-selected="true">
                    <i class="bi bi-calendar2-check-fill me-2"></i>Cierres Diarios Oficiales
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="arqueos-tab" data-bs-toggle="pill" data-bs-target="#arqueos-pane" type="button" role="tab" aria-controls="arqueos-pane" aria-selected="false">
                    <i class="bi bi-shield-check-fill me-2"></i>Arqueos / Auditorías de Caja
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="print-section">
        <!-- Pestaña de Cierres -->
        <div class="tab-pane fade show active" id="cierres-pane" role="tabpanel" aria-labelledby="cierres-tab" tabindex="0">
            <!-- KPIs -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-purple p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">Saldo de Caja al Cierre</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom" id="kpi-cierre-saldo">$0.00</h3>
                                <span class="text-muted small" id="kpi-cierre-saldo-fecha">Fecha: N/A</span>
                            </div>
                            <div class="kpi-icon bg-light-purple">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-blue p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">Saldo en Bóveda al Cierre</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom text-primary" id="kpi-cierre-boveda">$0.00</h3>
                                <span class="text-muted small" id="kpi-cierre-boveda-fecha">Al último cierre</span>
                            </div>
                            <div class="kpi-icon bg-light-blue">
                                <i class="bi bi-safe2-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-orange p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">Diferencias Acumuladas</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom" id="kpi-cierre-diferencia">$0.00</h3>
                                <span class="badge bg-light-orange text-dark border-0 mt-1" id="kpi-cierre-diferencia-frec">Frecuencia: 0 veces</span>
                            </div>
                            <div class="kpi-icon bg-light-orange">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-green p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">% Arqueo Cuadrado</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom" id="kpi-cierre-cuadrado">100%</h3>
                                <span class="text-muted small" id="kpi-cierre-meta">Meta: 100.0%</span>
                            </div>
                            <div class="kpi-icon bg-light-green">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico -->
            <div class="card shadow-sm border-0 mb-4 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">Evolución de Saldos: Físico vs Sistema (Cierres)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container">
                        <canvas id="cierresChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabla Detalle -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark">Detalle de Cierres Diarios</h5>
                    <div style="width: 250px;">
                        <input type="text" id="search-cierres" class="form-control form-control-sm" placeholder="Buscar en tabla...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle" id="table-cierres">
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Caja</th>
                                    <th>Fecha</th>
                                    <th>Inicio</th>
                                    <th>Espera Sistema</th>
                                    <th>Contado Físico</th>
                                    <th>Diferencia</th>
                                    <th>Usuario</th>
                                    <th>Validador</th>
                                    <th>Bóveda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestaña de Arqueos -->
        <div class="tab-pane fade" id="arqueos-pane" role="tabpanel" aria-labelledby="arqueos-tab" tabindex="0">
            <!-- KPIs -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-purple p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">Saldo de Caja en Arqueo</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom" id="kpi-arqueo-saldo">$0.00</h3>
                                <span class="text-muted small" id="kpi-arqueo-saldo-fecha">Fecha: N/A</span>
                            </div>
                            <div class="kpi-icon bg-light-purple">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-blue p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">Saldo en Bóveda en Arqueo</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom text-primary" id="kpi-arqueo-boveda">$0.00</h3>
                                <span class="text-muted small" id="kpi-arqueo-boveda-fecha">Al último arqueo</span>
                            </div>
                            <div class="kpi-icon bg-light-blue">
                                <i class="bi bi-safe2-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-orange p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">Diferencias Acumuladas</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom" id="kpi-arqueo-diferencia">$0.00</h3>
                                <span class="badge bg-light-orange text-dark border-0 mt-1" id="kpi-arqueo-diferencia-frec">Frecuencia: 0 veces</span>
                            </div>
                            <div class="kpi-icon bg-light-orange">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card card-kpi kpi-green p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted fw-bold text-uppercase mb-1 small">% Arqueo Cuadrado</h6>
                                <h3 class="fw-bold mb-0 font-monospace-custom" id="kpi-arqueo-cuadrado">100%</h3>
                                <span class="text-muted small" id="kpi-arqueo-meta">Meta: 100.0%</span>
                            </div>
                            <div class="kpi-icon bg-light-green">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico -->
            <div class="card shadow-sm border-0 mb-4 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">Evolución de Saldos: Físico vs Sistema (Arqueos)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container">
                        <canvas id="arqueosChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabla Detalle -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark">Detalle de Arqueos / Auditorías</h5>
                    <div style="width: 250px;">
                        <input type="text" id="search-arqueos" class="form-control form-control-sm" placeholder="Buscar en tabla...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle" id="table-arqueos">
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Caja</th>
                                    <th>Fecha</th>
                                    <th>Inicio</th>
                                    <th>Espera Sistema</th>
                                    <th>Contado Físico</th>
                                    <th>Diferencia</th>
                                    <th>Usuario</th>
                                    <th>Validador</th>
                                    <th>Bóveda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
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
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        const formatter = new Intl.NumberFormat('es-MX', {
            style: 'currency', currency: 'MXN', minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        const loadingOverlay = document.getElementById('loading-overlay');
        const dashboardContent = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const sucursalSelect = document.getElementById('sucursal_id');

        let rawData = { cierres: [], arqueos: [] };
        let chartInstances = {};

        // Sincronizar sucursal activa desde sesión
        const sessionSucursalId = @json(session('sucursal_id', ''));
        if (sessionSucursalId !== null && sessionSucursalId !== '' && !sucursalSelect.value) {
            sucursalSelect.value = sessionSucursalId;
        }

        // Carga inicial
        loadData();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            loadData();
        });

        // Manejo de rangos rápidos
        document.querySelectorAll('.quick-range-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const range = this.dataset.range;
                const startInput = document.getElementById('fecha_inicio');
                const endInput = document.getElementById('fecha_fin');

                const today = new Date();
                let start, end;

                if (range === 'today') {
                    start = end = today;
                } else if (range === 'month') {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = today;
                } else if (range === 'prev-month') {
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                } else if (range === 'year') {
                    start = new Date(today.getFullYear(), 0, 1);
                    end = today;
                }

                startInput.value = start.toISOString().split('T')[0];
                endInput.value = end.toISOString().split('T')[0];

                loadData();
            });
        });

        function showLoading() {
            loadingOverlay.style.display = 'flex';
        }

        function hideLoading() {
            loadingOverlay.style.display = 'none';
            dashboardContent.style.display = 'block';
        }

        function loadData() {
            showLoading();
            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            fetch(`{{ route('control-caja-arqueos.data') }}?${params}`)
                .then(res => res.json())
                .then(data => {
                    rawData = data;
                    updateDashboard(data);
                })
                .catch(err => {
                    console.error("Error cargando reporte caja y arqueos:", err);
                    alert("Ocurrió un error al obtener la información de las cajas.");
                })
                .finally(() => {
                    hideLoading();
                });
        }

        function updateDashboard(data) {
            // 1. Update KPIs
            updateKpis('cierre', data.kpisCierres);
            updateKpis('arqueo', data.kpisArqueos);

            // 2. Draw Charts
            drawEvolutionChart('cierresChart', data.chartCierres);
            drawEvolutionChart('arqueosChart', data.chartArqueos);

            // 3. Render Tables
            renderTable('table-cierres', data.cierres);
            renderTable('table-arqueos', data.arqueos);
        }

        function updateKpis(prefix, kpis) {
            const elSaldo = document.getElementById(`kpi-${prefix}-saldo`);
            const elSaldoFecha = document.getElementById(`kpi-${prefix}-saldo-fecha`);
            const elBoveda = document.getElementById(`kpi-${prefix}-boveda`);
            const elBovedaFecha = document.getElementById(`kpi-${prefix}-boveda-fecha`);
            const elDiferencia = document.getElementById(`kpi-${prefix}-diferencia`);
            const elDiferenciaFrec = document.getElementById(`kpi-${prefix}-diferencia-frec`);
            const elCuadrado = document.getElementById(`kpi-${prefix}-cuadrado`);

            if (elSaldo) elSaldo.textContent = formatter.format(kpis.ultimo_saldo || 0.0);
            if (elSaldoFecha) elSaldoFecha.textContent = kpis.ultimo_saldo_fecha ? `Al día ${kpis.ultimo_saldo_fecha}` : 'Sin datos';

            if (elBoveda) elBoveda.textContent = formatter.format(kpis.ultimo_saldo_boveda || 0.0);
            if (elBovedaFecha) elBovedaFecha.textContent = kpis.ultimo_saldo_fecha ? `Al día ${kpis.ultimo_saldo_fecha}` : 'Sin datos';
            
            if (elDiferencia) {
                elDiferencia.textContent = formatter.format(kpis.diferencia_acumulada || 0.0);
                // Color depending on positive/negative
                if (kpis.diferencia_acumulada < -0.01) {
                    elDiferencia.className = "fw-bold mb-0 font-monospace-custom text-danger";
                } else if (kpis.diferencia_acumulada > 0.01) {
                    elDiferencia.className = "fw-bold mb-0 font-monospace-custom text-warning";
                } else {
                    elDiferencia.className = "fw-bold mb-0 font-monospace-custom text-success";
                }
            }
            if (elDiferenciaFrec) elDiferenciaFrec.textContent = `Frecuencia: ${kpis.frecuencia_diferencias || 0} veces`;

            if (elCuadrado) {
                elCuadrado.textContent = `${kpis.pct_cuadrado || 100.0}%`;
                // Color based on standard threshold
                if (kpis.pct_cuadrado >= 98.0) {
                    elCuadrado.className = "fw-bold mb-0 font-monospace-custom text-success";
                } else if (kpis.pct_cuadrado >= 90.0) {
                    elCuadrado.className = "fw-bold mb-0 font-monospace-custom text-warning";
                } else {
                    elCuadrado.className = "fw-bold mb-0 font-monospace-custom text-danger";
                }
            }
        }

        function drawEvolutionChart(canvasId, chartData) {
            if (chartInstances[canvasId]) {
                chartInstances[canvasId].destroy();
            }

            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            chartInstances[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Saldo Físico Contado',
                            data: chartData.fisico,
                            borderColor: '#1cc88a',
                            backgroundColor: 'rgba(28, 200, 138, 0.1)',
                            borderWidth: 2.5,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Saldo Esperado Sistema',
                            data: chartData.sistema,
                            borderColor: '#4e73df',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5
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
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `${context.dataset.label}: ${formatter.format(context.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => formatter.format(value)
                            }
                        }
                    }
                }
            });
        }

        function renderTable(tableId, records) {
            const tbody = document.querySelector(`#${tableId} tbody`);
            if (!tbody) return;

            tbody.innerHTML = '';

            if (records.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>No se encontraron registros en el período seleccionado.
                        </td>
                    </tr>
                `;
                return;
            }

            records.forEach(row => {
                const tr = document.createElement('tr');
                tr.className = "table-row-item";
                
                // Color difference column
                let diffBadge = '';
                if (Math.abs(row.diferencia) <= 0.01) {
                    diffBadge = `<span class="badge bg-success-subtle text-success border-0 px-2 py-1">Cuadrado</span>`;
                } else {
                    const color = row.diferencia < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
                    diffBadge = `<span class="${color}">${formatter.format(row.diferencia)}</span>`;
                }

                // Format dates
                const fechaCierre = row.f_cierre ? row.f_cierre.split(' ')[1] || '' : '';
                const formatFecha = row.fecha.split('-').reverse().join('/');

                tr.innerHTML = `
                    <td class="fw-bold text-dark">${row.sucursal_nombre}</td>
                    <td>${row.caja_nombre}</td>
                    <td>
                        <div>${formatFecha}</div>
                        <div class="text-muted small">${fechaCierre}</div>
                    </td>
                    <td class="font-monospace-custom">${formatter.format(row.termino)}</td>
                    <td class="font-monospace-custom fw-semibold">${formatter.format(row.total_general)}</td>
                    <td>${diffBadge}</td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 130px;" title="${row.usuario_nombre}">
                            ${row.usuario_nombre}
                        </span>
                    </td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 130px;" title="${row.usuario_valida_nombre}">
                            ${row.usuario_valida_nombre}
                        </span>
                    </td>
                    <td class="font-monospace-custom text-muted">${formatter.format(row.total_boveda)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Live table filtering (Snappy Clientside Search)
        function registerSearch(inputId, tableId, dataKey) {
            const input = document.getElementById(inputId);
            input.addEventListener('keyup', function () {
                const q = this.value.toLowerCase();
                const filtered = rawData[dataKey].filter(row => {
                    return row.sucursal_nombre.toLowerCase().includes(q) ||
                           row.caja_nombre.toLowerCase().includes(q) ||
                           row.usuario_nombre.toLowerCase().includes(q) ||
                           row.usuario_valida_nombre.toLowerCase().includes(q) ||
                           row.fecha.includes(q);
                });
                renderTable(tableId, filtered);
            });
        }

        registerSearch('search-cierres', 'table-cierres', 'cierres');
        registerSearch('search-arqueos', 'table-arqueos', 'arqueos');

        // Re-draw chart on tab change (fixes Chart.js canvas sizing bug inside hidden bootstrap tabs)
        document.getElementById('arqueos-tab').addEventListener('shown.bs.tab', function () {
            drawEvolutionChart('arqueosChart', rawData.chartArqueos);
        });
        document.getElementById('cierres-tab').addEventListener('shown.bs.tab', function () {
            drawEvolutionChart('cierresChart', rawData.chartCierres);
        });
    });
</script>
@endsection
