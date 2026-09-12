@extends('employees.layouts.main')

@section('title', 'Bancos y Flujos de Efectivo')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.25s ease;
        }
        .icon-shape {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            border-radius: 50%;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.1); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.1); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.1); }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.1); }
        .bg-light-purple { background-color: rgba(111, 66, 193, 0.1); }

        .table-responsive { overflow-x: auto; }

        /* Spinner */
        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }

        .formula-badge {
            font-size: 0.75rem;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.35rem 0.6rem;
            color: #6c757d;
        }

        /* Dotted underline tooltip trigger en el título (Estilo Depositaría) */
        .metric-tooltip {
            position: relative;
            display: inline-block;
            border-bottom: 1px dotted #6c757d;
            cursor: help;
        }
        .metric-tooltip:hover {
            color: #0d6efd;
        }

        /* Tooltip de desglose (Estilo Flujo Operativo / Depositaría) */
        .tooltip-inner {
            max-width: 340px !important;
            padding: 10px 14px !important;
            font-size: 0.8rem !important;
            background-color: #18181b !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            text-align: left !important;
        }
        .tooltip-inner hr {
            border-color: rgba(255, 255, 255, 0.2) !important;
            margin: 6px 0 !important;
            opacity: 1 !important;
        }
        .tooltip-inner .text-muted { color: #cbd5e1 !important; }
        .tooltip-inner .text-dark { color: #ffffff !important; }
        .tooltip-inner .text-danger { color: #f87171 !important; }
        .tooltip-inner .text-success { color: #4ade80 !important; }
        .tooltip-inner .text-primary { color: #60a5fa !important; }
        .tooltip-inner .text-info { color: #38bdf8 !important; }
        .tooltip-inner .text-warning { color: #fbbf24 !important; }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold">Analizando flujos bancarios y saldos...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <!-- Encabezado -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="title fw-bold text-dark mb-1">
                    <i class="bi bi-bank2 text-primary me-2"></i>Bancos y Flujos de Efectivo
                </h4>
                <p class="text-muted mb-0">Monitoreo de saldos por cuenta bancaria, entradas por origen, salidas por tipo y comisiones TPV</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                @if(auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin')
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Regional</label>
                    <select name="regional_user_id" id="regional_user_id" class="form-select" onchange="const url = new URL(window.location.href); if(this.value){ url.searchParams.set('regional_user_id', this.value); } else { url.searchParams.delete('regional_user_id'); } url.searchParams.delete('sucursal_id'); window.location.href = url.toString();">
                        <option value="">-- Todos los Regionales --</option>
                        @foreach($regionales ?? [] as $reg)
                            <option value="{{ $reg->id }}" {{ (isset($regionalUserId) && $regionalUserId == $reg->id) ? 'selected' : '' }}>
                                {{ $reg->nombre_completo }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="{{ (auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin') ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas Consolidado --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}" {{ request('sucursal_id') == $sucursal->id_valora_mas ? 'selected' : '' }}>
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
                    <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPIs Principales -->
    <div class="row mb-4 g-3">
        <!-- Saldo Total Bancos & Promedio -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3" id="card-saldo-bancos">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold small mb-0">
                            <span class="metric-tooltip" id="tooltip-saldo-bancos" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de saldo bancario...">
                                Saldo Bancos (Tarjetas)
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-bank2"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-2" id="kpi-saldo-total">$ 0.00</h3>
                    <div class="text-muted small">
                        Promedio Diario: <span class="fw-semibold text-dark" id="kpi-saldo-promedio">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Saldo Mínimo y Máximo por Mes -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3" id="card-limites-saldo">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold small mb-0">
                            <span class="metric-tooltip" id="tooltip-limites-saldo" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de rangos...">
                                Límites de Saldo (Mes)
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-warning text-warning">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-semibold">Mínimo:</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-6 fw-bold" id="kpi-saldo-minimo">$ 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-semibold">Máximo:</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-6 fw-bold" id="kpi-saldo-maximo">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flujo Libre Neto -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3" id="card-flujo-neto">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold small mb-0">
                            <span class="metric-tooltip" id="tooltip-flujo-neto" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de flujo neto...">
                                Flujo Libre Neto
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-shuffle"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark mb-2" id="kpi-flujo-neto">$ 0.00</h3>
                    <div class="text-muted small">
                        Entradas Totales − Salidas Totales
                    </div>
                </div>
            </div>
        </div>

        <!-- Comisiones Bancarias Totales -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3" id="card-comisiones-tpv">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold small mb-0">
                            <span class="metric-tooltip" id="tooltip-comisiones-tpv" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de comisiones TPV...">
                                Comisiones TPV Totales
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-percent"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-danger mb-2" id="kpi-comisiones">$ 0.00</h3>
                    <div class="text-muted small">
                        Tasa Promedio: <span class="fw-semibold text-danger" id="kpi-tasa-comision">0.00%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requerimiento 79: Gráfico de Líneas - Saldo en el Tiempo por Cuenta Bancaria -->
    <div class="row mb-4">
        <div class="col-12 col-xl-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-graph-up text-primary me-2"></i>Evolución de Saldos por Cuenta Bancaria
                        </h5>
                        <small class="text-muted">Flujo diario acumulado por cada entidad/cuenta</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div style="height: 300px; position: relative;">
                        <canvas id="evolucionSaldosChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Flujos Mensuales (Barras Comparativas) -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-bar-chart-line text-success me-2"></i>Entradas vs Salidas
                    </h5>
                    <small class="text-muted">Balance neto general</small>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <div style="height: 300px; width: 100%; position: relative;">
                        <canvas id="flujosMensualesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Composición de Flujos (Gráficos Doughnut) -->
    <div class="row mb-4">
        <!-- Origen de Entradas -->
        <div class="col-12 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-pie-chart text-success me-2"></i>Origen de Entradas Principales
                    </h5>
                    <small class="text-muted">Distribución de ingresos por concepto</small>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <div style="height: 250px; width: 100%; position: relative;">
                        <canvas id="origenEntradasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Destino de Salidas -->
        <div class="col-12 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-pie-chart-fill text-danger me-2"></i>Tipo de Salidas Financieras
                    </h5>
                    <small class="text-muted">Destino de egresos e inversiones</small>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <div style="height: 250px; width: 100%; position: relative;">
                        <canvas id="tipoSalidasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Entradas por Origen -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-arrow-down-left-circle-fill text-success me-2"></i>Entradas por Origen
                        </h5>
                        <small class="text-muted">Ventas bazar, refrendos, desempeños, pagos de crédito y dotaciones de caja</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Origen / Concepto de Ingreso</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ingreso Efectivo ($)</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ingreso Tarjeta TPV ($)</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-success">Total Entradas ($)</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center" style="min-width: 140px;">% del Total</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-entradas-origen-body">
                                <tr><td colspan="5" class="text-center text-muted py-4">Cargando entradas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Salidas por Tipo -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-arrow-up-right-circle-fill text-danger me-2"></i>Salidas por Tipo
                        </h5>
                        <small class="text-muted">Inversión en empeños nuevos, gastos operativos, nómina y retiros</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Tipo / Categoría de Salida</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Salida Efectivo ($)</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Salida Tarjeta / Banco ($)</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-danger">Total Salidas ($)</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center" style="min-width: 140px;">% del Total</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-salidas-tipo-body">
                                <tr><td colspan="5" class="text-center text-muted py-4">Cargando salidas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Comisiones Bancarias por Cuenta -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-credit-card-2-front-fill text-primary me-2"></i>Comisiones Bancarias Totales y por Cuenta
                        </h5>
                        <small class="text-muted">Desglose de cobros TPV por banco, cuenta titular, comisión retenida e ingreso neto</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Banco / Entidad</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal / Cuenta Titular</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Transacciones TPV</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Monto Procesado ($)</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end text-danger">Comisión Estimada ($)</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end text-success">Monto Neto Depositado ($)</th>
                                </tr>
                            </thead>
                            <tbody id="detalle-cuentas-body">
                                <tr><td colspan="6" class="text-center text-muted py-4">Cargando cuentas bancarias...</td></tr>
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
        const numberFormatter = new Intl.NumberFormat('es-MX');

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
                    if (!response.ok) throw new Error('Network response failed');
                    return response.json();
                })
                .then(data => {
                    updateDashboard(data);
                })
                .catch(error => {
                    console.error("Error cargando bancos:", error);
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

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setCustomTooltip(id, html) {
            const el = document.getElementById(id);
            if (el && typeof bootstrap !== 'undefined') {
                const existing = bootstrap.Tooltip.getInstance(el);
                if (existing) existing.dispose();
                el.setAttribute('data-bs-original-title', html);
                el.setAttribute('title', html);
                new bootstrap.Tooltip(el, { html: true, placement: 'top' });
            }
        }

        function updateDashboard(data) {
            // 1. KPIs Principales
            updateElementText('kpi-saldo-total', formatter.format(data.saldoTotalBancos || 0));
            updateElementText('kpi-saldo-promedio', formatter.format(data.saldoPromedio || 0));
            
            updateElementText('kpi-saldo-minimo', formatter.format(data.saldoMinimo || 0));
            updateElementText('kpi-saldo-maximo', formatter.format(data.saldoMaximo || 0));
            
            updateElementText('kpi-flujo-neto', formatter.format(data.flujoNetoMensual || 0));
            
            updateElementText('kpi-comisiones', formatter.format(data.comisionesTotales || 0));
            updateElementText('kpi-tasa-comision', `${(data.tasaComisionPromedio || 0).toFixed(2)}%`);

            // Tooltips Explicativos de Fórmulas (Estilo Depositaría)
            const saldoBancos = data.saldoTotalBancos || 0;
            const saldoProm = data.saldoPromedio || 0;
            const numDias = (data.chartEvolucionSaldos && data.chartEvolucionSaldos.labels && data.chartEvolucionSaldos.labels.length > 0) 
                ? data.chartEvolucionSaldos.labels.length 
                : 1;

            setCustomTooltip('tooltip-saldo-bancos', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 230px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-white">Desglose de Saldo Bancario:</strong>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Total Recaudado TPV:</span> <span class="fw-bold text-white">${formatter.format(saldoBancos)}</span></div>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Días en Período:</span> <span class="fw-bold text-white">${numDias} días</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span class="text-white">SALDO PROM. DIARIO</span> <span class="text-primary">${formatter.format(saldoProm)}</span></div>
                </div>
            `);

            const sMin = data.saldoMinimo || 0;
            const sMax = data.saldoMaximo || 0;
            setCustomTooltip('tooltip-limites-saldo', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 230px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-white">Desglose de Rangos Operativos:</strong>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Saldo Mínimo:</span> <span class="fw-bold text-danger">${formatter.format(sMin)}</span></div>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Saldo Máximo:</span> <span class="fw-bold text-success">${formatter.format(sMax)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span class="text-white">SALDO PROMEDIO</span> <span class="text-primary">${formatter.format(saldoProm)}</span></div>
                </div>
            `);

            const entTot = data.totalEntradas || 0;
            const salTot = data.totalSalidas || 0;
            const flNeto = data.flujoNetoMensual || 0;
            setCustomTooltip('tooltip-flujo-neto', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 240px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-white">Desglose de Flujo Libre Neto:</strong>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Entradas Totales:</span> <span class="fw-bold text-success">${formatter.format(entTot)}</span></div>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Salidas Totales:</span> <span class="fw-bold text-danger">${formatter.format(salTot)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span class="text-white">FLUJO LIBRE NETO</span> <span class="${flNeto >= 0 ? 'text-success' : 'text-danger'}">${formatter.format(flNeto)}</span></div>
                </div>
            `);

            const comTot = data.comisionesTotales || 0;
            const tasaCom = data.tasaComisionPromedio || 0;
            setCustomTooltip('tooltip-comisiones-tpv', `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 240px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-white">Desglose de Comisiones TPV:</strong>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Monto Recaudado TPV:</span> <span class="fw-bold text-white">${formatter.format(saldoBancos)}</span></div>
                    <div class="d-flex justify-content-between gap-3"><span class="text-white">Comisión Retenida:</span> <span class="fw-bold text-danger">${formatter.format(comTot)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span class="text-white">TASA RETENCIÓN</span> <span class="text-danger">${tasaCom.toFixed(2)}%</span></div>
                </div>
            `);

            // 2. Tabla Requerimiento 82: Entradas por Origen
            const tbodyEntradas = document.getElementById('tabla-entradas-origen-body');
            if (tbodyEntradas) {
                const entradas = data.entradasPorOrigen || [];
                if (entradas.length === 0) {
                    tbodyEntradas.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay registros de entradas para el período</td></tr>';
                } else {
                    let htmlEnt = '';
                    entradas.forEach(e => {
                        htmlEnt += `
                            <tr>
                                <td class="ps-4 py-3 fw-bold text-dark">
                                    <i class="bi bi-arrow-down-circle text-success me-2"></i>${escapeHtml(e.concepto)}
                                </td>
                                <td class="py-3 text-end text-dark">${formatter.format(e.efectivo || 0)}</td>
                                <td class="py-3 text-end text-primary fw-semibold">${formatter.format(e.tarjeta || 0)}</td>
                                <td class="py-3 text-end text-success fw-bold">${formatter.format(e.total || 0)}</td>
                                <td class="pe-4 py-3 text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: ${e.pct_total}%;"></div>
                                        </div>
                                        <span class="small fw-semibold text-muted" style="width: 45px; text-align: right;">${e.pct_total}%</span>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    tbodyEntradas.innerHTML = htmlEnt;
                }
            }

            // 3. Tabla Requerimiento 83: Salidas por Tipo
            const tbodySalidas = document.getElementById('tabla-salidas-tipo-body');
            if (tbodySalidas) {
                const salidas = data.salidasPorTipo || [];
                if (salidas.length === 0) {
                    tbodySalidas.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay registros de salidas para el período</td></tr>';
                } else {
                    let htmlSal = '';
                    salidas.forEach(s => {
                        htmlSal += `
                            <tr>
                                <td class="ps-4 py-3 fw-bold text-dark">
                                    <i class="bi bi-arrow-up-circle text-danger me-2"></i>${escapeHtml(s.concepto)}
                                </td>
                                <td class="py-3 text-end text-dark">${formatter.format(s.efectivo || 0)}</td>
                                <td class="py-3 text-end text-primary fw-semibold">${formatter.format(s.tarjeta || 0)}</td>
                                <td class="py-3 text-end text-danger fw-bold">${formatter.format(s.total || 0)}</td>
                                <td class="pe-4 py-3 text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-danger" role="progressbar" style="width: ${s.pct_total}%;"></div>
                                        </div>
                                        <span class="small fw-semibold text-muted" style="width: 45px; text-align: right;">${s.pct_total}%</span>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    tbodySalidas.innerHTML = htmlSal;
                }
            }

            // 4. Tabla Requerimiento 84: Comisiones por Cuenta
            const tbodyCuentas = document.getElementById('detalle-cuentas-body');
            if (tbodyCuentas) {
                const cuentas = data.detalleCuentas || [];
                if (cuentas.length === 0) {
                    tbodyCuentas.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay movimientos bancarios TPV registrados</td></tr>';
                } else {
                    let htmlCta = '';
                    cuentas.forEach(c => {
                        htmlCta += `
                            <tr>
                                <td class="ps-4 py-3 fw-bold text-dark">
                                    <i class="bi bi-bank text-primary me-2"></i>${escapeHtml(c.banco)}
                                </td>
                                <td class="py-3 text-muted">
                                    ${escapeHtml(c.sucursal)} <br>
                                    <span class="small text-muted">${escapeHtml(c.cuenta)}</span>
                                </td>
                                <td class="py-3 text-center fw-semibold">${numberFormatter.format(c.transacciones || 0)} trx</td>
                                <td class="py-3 text-end fw-bold text-dark">${formatter.format(c.monto_tarjeta || 0)}</td>
                                <td class="py-3 text-end text-danger fw-semibold">${formatter.format(c.comision_total || 0)}</td>
                                <td class="pe-4 py-3 text-end text-success fw-bold">${formatter.format(c.neto_depositado || 0)}</td>
                            </tr>
                        `;
                    });
                    tbodyCuentas.innerHTML = htmlCta;
                }
            }

            // 5. Actualizar Gráficos
            updateMultiLineChart(data.chartEvolucionSaldos);
            updateBarChart(data.chartFlujosMensuales);
            updateDoughnutChart(data.chartEntradasPorOrigen, 'origenEntradasChart', ['#198754', '#20c997', '#0dcaf0', '#0d6efd', '#6f42c1']);
            updateDoughnutChart(data.chartSalidasPorTipo, 'tipoSalidasChart', ['#dc3545', '#fd7e14', '#ffc107', '#6c757d', '#d63384']);
        }

        // Gráfico Requerimiento 79: Multi-línea por cuenta/banco
        function updateMultiLineChart(chartData) {
            const ctx = document.getElementById('evolucionSaldosChart');
            if (!ctx) return;
            
            if (evolucionSaldosChart) evolucionSaldosChart.destroy();
            if (!chartData || !chartData.datasets) return;

            evolucionSaldosChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: chartData.datasets || []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${formatter.format(context.raw)}`;
                                }
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
                            'rgba(25, 135, 84, 0.85)', // Entradas (verde)
                            'rgba(220, 53, 69, 0.85)',  // Salidas (rojo)
                            'rgba(13, 110, 253, 0.85)'  // Neto (azul)
                        ],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatter.format(context.raw);
                                }
                            }
                        }
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

            if (!chartData || !chartData.labels) return;

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
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${formatter.format(context.raw)}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
