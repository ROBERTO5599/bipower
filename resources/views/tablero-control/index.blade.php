@extends('employees.layouts.main')

@section('title', 'Tablero de Control')

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
    
    .kpi-blue::before { background: #4e73df; }
    .kpi-green::before { background: #1cc88a; }
    .kpi-yellow::before { background: #f6c23e; }
    .kpi-red::before { background: #e74a3b; }
    
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
    
    .bg-light-blue { background: rgba(78, 115, 223, 0.1); color: #4e73df; }
    .bg-light-green { background: rgba(28, 200, 138, 0.1); color: #1cc88a; }
    .bg-light-yellow { background: rgba(246, 194, 62, 0.1); color: #f6c23e; }
    .bg-light-red { background: rgba(231, 74, 59, 0.1); color: #e74a3b; }

    /* Custom elegant table */
    .tablero-table-container {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.05);
        background: #fff;
    }

    .table-tablero {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table-tablero thead th {
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

    .table-tablero tbody td {
        padding: 12px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #e3e6f0;
        font-size: 0.85rem;
    }

    .table-tablero tbody tr:hover td {
        background-color: #f8f9fc;
    }

    .row-group-header {
        background-color: #f1f3f9 !important;
        font-weight: 700;
        color: #4e73df;
        font-size: 0.85rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        border-bottom: 2px solid #d1d3e2;
        padding: 10px 15px !important;
    }

    /* Progress Indicators */
    .cell-progress-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .cell-progress-bar {
        height: 5px;
        border-radius: 3px;
        background-color: #eaecf4;
        overflow: hidden;
        margin-top: 4px;
        width: 100%;
    }

    .cell-progress-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s ease;
    }

    /* Status badge */
    .badge-progress {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 50rem;
        font-weight: 700;
    }

    .badge-progress-success { background-color: rgba(28, 200, 138, 0.15); color: #1cc88a; }
    .badge-progress-warning { background-color: rgba(246, 194, 62, 0.15); color: #f6c23e; }
    .badge-progress-danger { background-color: rgba(231, 74, 59, 0.15); color: #e74a3b; }

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

    .quick-range-btn {
        transition: all 0.2s ease;
        font-weight: 500;
    }
    
    .quick-range-btn.active {
        background-color: #4e73df;
        color: #fff;
        border-color: #4e73df;
    }

    .col-indicator {
        min-width: 260px;
    }

    .col-category {
        min-width: 160px;
    }

    .font-monospace-custom {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .row-blue td {
        background-color: #cfe2ff !important;
    }
    .row-white td {
        background-color: #ffffff !important;
    }
    /* Custom elegant tooltips */
    .metric-tooltip {
        border-bottom: 1px dotted #6c757d;
        cursor: help;
    }
</style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-grow text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Procesando Métricas...</span>
    </div>
    <h5 class="text-dark fw-bold mb-1">Calculando Tablero de Control...</h5>
    <p class="text-muted small">Esto puede tardar unos segundos mientras consolidamos múltiples bases de datos.</p>
</div>

<div class="container-fluid py-4" id="dashboard-content" style="opacity: 0;">
    <!-- Header -->
    <div class="row align-items-center mb-4 p-3 dashboard-header mx-0">
        <div class="col-md-6">
            <h3 class="text-dark fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Tablero de Control</h3>
            <p class="text-muted mb-0">Consolidado mensual de metas y avances por categoría de producto.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <span class="badge bg-primary px-3 py-2 fs-7" id="active-range-badge">Rango Activo: Cargando...</span>
        </div>
    </div>

    <!-- Filtros Inteligentes -->
    <div class="card shadow-sm border-0 mb-4 rounded-4 bg-white">     
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small uppercase">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select border-2 rounded-3">
                        <option value="">-- Todas Consolidadas --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small uppercase">Rápido</label>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-secondary quick-range-btn active" data-range="este-mes">Este Mes</button>
                        <button type="button" class="btn btn-outline-secondary quick-range-btn" data-range="mes-anterior">Mes Anterior</button>
                        <button type="button" class="btn btn-outline-secondary quick-range-btn" data-range="este-anio">Este Año</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-muted small uppercase">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}" class="form-control border-2 rounded-3">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-muted small uppercase">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ $fechaFin }}" class="form-control border-2 rounded-3">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3" title="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPIs Cards Summary Row -->
    <div class="row mb-4">
        <!-- Ventas Summary Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-kpi kpi-blue h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <span class="metric-tooltip" id="tooltip-ventas-kpi" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">Ventas Consolidadas</span>
                        </h6>
                        <h4 class="h4 mb-0 fw-bold text-gray-800 font-monospace-custom" id="summary-ventas-avance">$ 0.00</h4>
                        <div class="text-xs text-muted mt-2">
                            Meta: <span id="summary-ventas-meta" class="font-monospace-custom">$ 0.00</span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-light-blue" id="summary-ventas-percent">0%</div>
                </div>
                <div class="cell-progress-bar mt-3">
                    <div class="cell-progress-fill bg-primary" id="summary-ventas-progress-bar" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Empeños Summary Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-kpi kpi-green h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <span class="metric-tooltip" id="tooltip-empenos-kpi" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">Empeños Consolizados</span>
                        </h6>
                        <h4 class="h4 mb-0 fw-bold text-gray-800 font-monospace-custom" id="summary-empenos-avance">$ 0.00</h4>
                        <div class="text-xs text-muted mt-2">
                            Meta: <span id="summary-empenos-meta" class="font-monospace-custom">$ 0.00</span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-light-green" id="summary-empenos-percent">0%</div>
                </div>
                <div class="cell-progress-bar mt-3">
                    <div class="cell-progress-fill bg-success" id="summary-empenos-progress-bar" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Interés + Utilidad Vta Summary Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-kpi kpi-yellow h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <span class="metric-tooltip" id="tooltip-interesutil-kpi" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">Interés + Util Vta</span>
                        </h6>
                        <h4 class="h4 mb-0 fw-bold text-gray-800 font-monospace-custom" id="summary-interesutil-avance">$ 0.00</h4>
                        <div class="text-xs text-muted mt-2">
                            Meta: <span id="summary-interesutil-meta" class="font-monospace-custom">$ 0.00</span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-light-yellow" id="summary-interesutil-percent">0%</div>
                </div>
                <div class="cell-progress-bar mt-3">
                    <div class="cell-progress-fill bg-warning" id="summary-interesutil-progress-bar" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Utilidad Neta del Mes Summary Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-kpi kpi-red h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            <span class="metric-tooltip" id="tooltip-neta-kpi" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose...">Utilidad Neta Estimada</span>
                        <h4 class="h4 mb-0 fw-bold text-gray-800 font-monospace-custom" id="summary-neta-avance">$ 0.00</h4>
                        <div class="text-xs text-muted mt-2">
                            Meta: <span id="summary-neta-meta" class="font-monospace-custom">$ 0.00</span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-light-red" id="summary-neta-percent">0%</div>
                </div>
                <div class="cell-progress-bar mt-3">
                    <div class="cell-progress-fill bg-danger" id="summary-neta-progress-bar" style="width: 0%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid / Matrix Table -->
    <div class="row">
        <div class="col-12">
            <div class="tablero-table-container">
                <div class="table-responsive">
                    <table class="table table-tablero align-middle text-center">
                        <thead>
                            <tr>
                                <th class="text-start col-indicator ps-4">Indicador / Rubro</th>
                                <th class="col-category">Mercancía General</th>
                                <th class="col-category">Oro</th>
                                <th class="col-category">Plata</th>
                                <th class="col-category">Autos</th>
                                <th class="col-category pe-4">Total Consolidado</th>
                            </tr>
                        </thead>
                        <tbody id="tablero-body">
                            <!-- Rows will be injected dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        const formatter = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Initialize tooltips statically if any exist on page load
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        const loadingOverlay = document.getElementById('loading-overlay');
        const dashboardContent = document.getElementById('dashboard-content');
        const filterForm = document.getElementById('filter-form');
        const inputInicio = document.getElementById('fecha_inicio');
        const inputFin = document.getElementById('fecha_fin');
        const activeRangeBadge = document.getElementById('active-range-badge');

        // Layout indicators definitions to keep keys in matching case with backend
        const sections = [
            {
                title: "Operaciones Prendarias",
                indicators: [
                    { key: "INVENTARIO", name: "Inventario Depositaria", icon: "bi-archive-fill" },
                    { key: "Empeño", name: "Empeño", icon: "bi-journal-plus" },
                    { key: "Refrendos", name: "Refrendos", icon: "bi-arrow-repeat" },
                    { key: "Desempeño", name: "Desempeño", icon: "bi-journal-minus" },
                    { key: "Ventas", name: "Ventas (Apartado y Directa)", icon: "bi-cart-check-fill" },
                    { key: "Intereses", name: "Intereses Cobrados", icon: "bi-cash-coin" },
                    { key: "Remate", name: "Remate", icon: "bi-lightning-fill" },
                    { key: "Bazar", name: "Bazar", icon: "bi-shop" },
                    { key: "Utilidad del Mes Por venta", name: "Utilidad del Mes por Venta", icon: "bi-percent" },
                    { key: "Interés + Util Vta", name: "Interés + Utilidad de Venta", icon: "bi-plus-circle-fill", highlight: true },
                    { key: "Gastos del Mes", name: "Gastos del Mes", icon: "bi-dash-circle-fill" },
                    { key: "Utilidad Neta del Mes", name: "Utilidad Neta del Mes", icon: "bi-wallet2", highlight: true }
                ]
            },
            {
                title: "Créditos (Mercancía General)",
                indicators: [
                    { key: "Créditos Vigentes", name: "Créditos Vigentes", icon: "bi-credit-card-fill" },
                    { key: "Créditos Colocados", name: "Créditos Colocados (Colocación)", icon: "bi-upload" },
                    { key: "Pago Crédito/Abono a Créditos", name: "Pago a Créditos (Cobranza)", icon: "bi-download" },
                    { key: "Liquidación de Créditos", name: "Liquidación de Créditos", icon: "bi-check2-circle" },
                    { key: "Utilidad del Crédito", name: "Utilidad del Crédito", icon: "bi-graph-up" },
                    { key: "Crédito Vencido Mensual - Préstamo", name: "Crédito Vencido Mensual (Préstamo)", icon: "bi-exclamation-triangle" },
                    { key: "Crédito Vencido Mensual - Cantidad Pagada", name: "Crédito Vencido Mensual (Cobrado)", icon: "bi-check-circle" },
                    { key: "Crédito Vencido Mensual - Adeudo", name: "Crédito Vencido Mensual (Adeudo)", icon: "bi-x-circle" },
                    { key: "Crédito Vencido Global - Préstamo", name: "Crédito Vencido Global (Préstamo)", icon: "bi-exclamation-octagon" },
                    { key: "Crédito Vencido Global - Cantidad Pagada", name: "Crédito Vencido Global (Cobrado)", icon: "bi-check2-all" },
                    { key: "Crédito Vencido Global - Adeudo", name: "Crédito Vencido Global (Adeudo)", icon: "bi-x-octagon" },
                    { key: "Devoluciones", name: "Devoluciones", icon: "bi-arrow-left-right" }
                ]
            },
            {
                title: "Garantías (Mercancía General)",
                indicators: [
                    { key: "Garantías - Ventas", name: "Garantías de Ventas", icon: "bi-shield-check" },
                    { key: "Garantías - Apartados Liquidados", name: "Garantías de Apartados Liquidados", icon: "bi-shield-lock" },
                    { key: "Garantías - Enganche Crédito", name: "Garantías de Enganche de Crédito", icon: "bi-shield-plus" }
                ]
            }
        ];

        const categories = ["MERCANCIA GENERAL", "ORO", "PLATA", "AUTOS"];

        // Quick date picker buttons
        document.querySelectorAll('.quick-range-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const range = this.dataset.range;
                const today = new Date();
                let start = new Date();
                let end = new Date();

                if (range === 'este-mes') {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = today;
                } else if (range === 'mes-anterior') {
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                } else if (range === 'este-anio') {
                    start = new Date(today.getFullYear(), 0, 1);
                    end = today;
                }

                inputInicio.value = formatDateString(start);
                inputFin.value = formatDateString(end);
                
                fetchTableroData();
            });
        });

        function formatDateString(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // remove active class from quick buttons if dates manual change
            document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
            fetchTableroData();
        });

        // Initialize first load
        fetchTableroData();

        function fetchTableroData() {
            loadingOverlay.style.display = 'flex';
            dashboardContent.style.opacity = '0.3';

            const sucursalId = document.getElementById('sucursal_id').value;
            const params = new URLSearchParams({
                fecha_inicio: inputInicio.value,
                fecha_fin: inputFin.value,
                sucursal_id: sucursalId
            });

            // Update badge text
            const sucursalSelect = document.getElementById('sucursal_id');
            const selectedText = sucursalSelect.options[sucursalSelect.selectedIndex].text;
            activeRangeBadge.innerText = `Rango: ${inputInicio.value} al ${inputFin.value} | ${selectedText}`;

            fetch(`{{ route('tablero-control.data') }}?${params.toString()}`)
                .then(res => {
                    if (!res.ok) throw new Error("HTTP error " + res.status);
                    return res.json();
                })
                .then(data => {
                    renderTablero(data);
                    renderSummaryKPIs(data);
                })
                .catch(err => {
                    console.error("Error loading tablero data:", err);
                    alert("Ocurrió un error al cargar la información. Por favor, intente de nuevo.");
                })
                .finally(() => {
                    loadingOverlay.style.display = 'none';
                    dashboardContent.style.opacity = '1';
                });
        }

        function calculatePercentage(avance, meta) {
            if (meta <= 0) {
                return avance > 0 ? 100 : 0;
            }
            return (avance / meta) * 100;
        }

        function getProgressColorClass(percent) {
            if (percent >= 100) return "bg-success";
            if (percent >= 70) return "bg-warning";
            return "bg-danger";
        }

        function getBadgeColorClass(percent) {
            if (percent >= 100) return "badge-progress-success";
            if (percent >= 70) return "badge-progress-warning";
            return "badge-progress-danger";
        }

        // Helper to set custom HTML tooltip safely
        const setHtmlTooltip = (id, htmlContent) => {
            const tooltipEl = document.getElementById(id);
            if (tooltipEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const existingTooltip = bootstrap.Tooltip.getInstance(tooltipEl);
                    if (existingTooltip) {
                        existingTooltip.dispose();
                    }
                    tooltipEl.setAttribute('data-bs-original-title', htmlContent);
                    tooltipEl.setAttribute('title', htmlContent);
                    new bootstrap.Tooltip(tooltipEl, { html: true, placement: 'top' });
                }
            }
        };

        function renderSummaryKPIs(data) {
            // Helper function to consolidate key metrics across all categories
            const consolidateKey = (indicatorKey) => {
                let totalAvance = 0;
                let totalMeta = 0;
                if (data[indicatorKey]) {
                    Object.keys(data[indicatorKey]).forEach(cat => {
                        totalAvance += data[indicatorKey][cat].avance || 0;
                        totalMeta += data[indicatorKey][cat].meta || 0;
                    });
                }
                return { avance: totalAvance, meta: totalMeta };
            };

            const ventasSum = consolidateKey("Ventas");
            const empenosSum = consolidateKey("Empeño");
            const interestutilSum = consolidateKey("Interés + Util Vta");
            const netaSum = consolidateKey("Utilidad Neta del Mes");
            const liquidacionCreditosSum = consolidateKey("Liquidación de Créditos");

            // Also get separate components of Sales
            const ventasDirectasSum = consolidateKey("Ventas Directas");
            const apartadosLiquidadosSum = consolidateKey("Apartados Liquidados");

            // 1. Ventas Consolidadas Card (Ventas + Liquidación de Créditos)
            const totalVentasAvance = ventasSum.avance + liquidacionCreditosSum.avance;
            const totalVentasMeta = ventasSum.meta + liquidacionCreditosSum.meta;

            document.getElementById('summary-ventas-avance').innerText = formatter.format(totalVentasAvance);
            document.getElementById('summary-ventas-meta').innerText = formatter.format(totalVentasMeta);
            const ventasPct = calculatePercentage(totalVentasAvance, totalVentasMeta);
            document.getElementById('summary-ventas-percent').innerText = `${Math.round(ventasPct)}%`;
            document.getElementById('summary-ventas-percent').className = `kpi-icon ${getBadgeColorClass(ventasPct)}`;
            document.getElementById('summary-ventas-progress-bar').style.width = `${Math.min(ventasPct, 100)}%`;
            document.getElementById('summary-ventas-progress-bar').className = `cell-progress-fill ${getProgressColorClass(ventasPct)}`;

            const tooltipVentasHtml = `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 240px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Desglose de Ventas Consolidadas:</strong>
                    <div class="d-flex justify-content-between"><span>Ventas Directas:</span> <span class="fw-bold">${formatter.format(ventasDirectasSum.avance)}</span></div>
                    <div class="d-flex justify-content-between"><span>Apartados Liquidados:</span> <span class="fw-bold">${formatter.format(apartadosLiquidadosSum.avance)}</span></div>
                    <div class="d-flex justify-content-between"><span>Liquidación de Créditos:</span> <span class="fw-bold">${formatter.format(liquidacionCreditosSum.avance)}</span></div>
                    <div class="border-top my-1"></div>
                    <div class="d-flex justify-content-between text-primary"><strong>Total Avance:</strong> <span class="fw-bold">${formatter.format(totalVentasAvance)}</span></div>
                    <div class="d-flex justify-content-between text-muted"><strong>Total Meta:</strong> <span class="fw-bold">${formatter.format(totalVentasMeta)}</span></div>
                </div>
            `;
            setHtmlTooltip('tooltip-ventas-kpi', tooltipVentasHtml);

            // 2. Empeños Consolizados Card
            document.getElementById('summary-empenos-avance').innerText = formatter.format(empenosSum.avance);
            document.getElementById('summary-empenos-meta').innerText = formatter.format(empenosSum.meta);
            const empenosPct = calculatePercentage(empenosSum.avance, empenosSum.meta);
            document.getElementById('summary-empenos-percent').innerText = `${Math.round(empenosPct)}%`;
            document.getElementById('summary-empenos-percent').className = `kpi-icon ${getBadgeColorClass(empenosPct)}`;
            document.getElementById('summary-empenos-progress-bar').style.width = `${Math.min(empenosPct, 100)}%`;
            document.getElementById('summary-empenos-progress-bar').className = `cell-progress-fill ${getProgressColorClass(empenosPct)}`;

            let empenosMGen = 0, empenosOro = 0, empenosPlata = 0, empenosAutos = 0;
            if (data["Empeño"]) {
                empenosMGen = data["Empeño"]["MERCANCIA GENERAL"]?.avance || 0;
                empenosOro = data["Empeño"]["ORO"]?.avance || 0;
                empenosPlata = data["Empeño"]["PLATA"]?.avance || 0;
                empenosAutos = data["Empeño"]["AUTOS"]?.avance || 0;
            }

            const tooltipEmpenosHtml = `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 240px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Desglose de Empeños por Categoría:</strong>
                    <div class="d-flex justify-content-between"><span>Mercancía General:</span> <span class="fw-bold">${formatter.format(empenosMGen)}</span></div>
                    <div class="d-flex justify-content-between"><span>Oro:</span> <span class="fw-bold">${formatter.format(empenosOro)}</span></div>
                    <div class="d-flex justify-content-between"><span>Plata:</span> <span class="fw-bold">${formatter.format(empenosPlata)}</span></div>
                    <div class="d-flex justify-content-between"><span>Autos:</span> <span class="fw-bold">${formatter.format(empenosAutos)}</span></div>
                    <div class="border-top my-1"></div>
                    <div class="d-flex justify-content-between text-success"><strong>Total Avance:</strong> <span class="fw-bold">${formatter.format(empenosSum.avance)}</span></div>
                    <div class="d-flex justify-content-between text-muted"><strong>Total Meta:</strong> <span class="fw-bold">${formatter.format(empenosSum.meta)}</span></div>
                </div>
            `;
            setHtmlTooltip('tooltip-empenos-kpi', tooltipEmpenosHtml);

            // 3. Interés + Util Vta Card
            document.getElementById('summary-interesutil-avance').innerText = formatter.format(interestutilSum.avance);
            document.getElementById('summary-interesutil-meta').innerText = formatter.format(interestutilSum.meta);
            const interestutilPct = calculatePercentage(interestutilSum.avance, interestutilSum.meta);
            document.getElementById('summary-interesutil-percent').innerText = `${Math.round(interestutilPct)}%`;
            document.getElementById('summary-interesutil-percent').className = `kpi-icon ${getBadgeColorClass(interestutilPct)}`;
            document.getElementById('summary-interesutil-progress-bar').style.width = `${Math.min(interestutilPct, 100)}%`;
            document.getElementById('summary-interesutil-progress-bar').className = `cell-progress-fill ${getProgressColorClass(interestutilPct)}`;

            const interesesSum = consolidateKey("Intereses");
            const utilVentaSum = consolidateKey("Utilidad del Mes Por venta");
            const utilCreditoSum = consolidateKey("Utilidad del Crédito");

            const tooltipInteresUtilHtml = `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 240px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Desglose de Interés + Utilidades:</strong>
                    <div class="d-flex justify-content-between"><span>Intereses Cobrados:</span> <span class="fw-bold">${formatter.format(interesesSum.avance)}</span></div>
                    <div class="d-flex justify-content-between"><span>Utilidad por Venta:</span> <span class="fw-bold">${formatter.format(utilVentaSum.avance)}</span></div>
                    <div class="d-flex justify-content-between"><span>Utilidad de Crédito:</span> <span class="fw-bold">${formatter.format(utilCreditoSum.avance)}</span></div>
                    <div class="border-top my-1"></div>
                    <div class="d-flex justify-content-between text-warning text-dark"><strong>Total Avance:</strong> <span class="fw-bold">${formatter.format(interestutilSum.avance)}</span></div>
                    <div class="d-flex justify-content-between text-muted"><strong>Total Meta:</strong> <span class="fw-bold">${formatter.format(interestutilSum.meta)}</span></div>
                </div>
            `;
            setHtmlTooltip('tooltip-interesutil-kpi', tooltipInteresUtilHtml);

            // 4. Utilidad Neta Card
            document.getElementById('summary-neta-avance').innerText = formatter.format(netaSum.avance);
            document.getElementById('summary-neta-meta').innerText = formatter.format(netaSum.meta);
            const netaPct = calculatePercentage(netaSum.avance, netaSum.meta);
            document.getElementById('summary-neta-percent').innerText = `${Math.round(netaPct)}%`;
            document.getElementById('summary-neta-percent').className = `kpi-icon ${getBadgeColorClass(netaPct)}`;
            document.getElementById('summary-neta-progress-bar').style.width = `${Math.min(netaPct, 100)}%`;
            document.getElementById('summary-neta-progress-bar').className = `cell-progress-fill ${getProgressColorClass(netaPct)}`;

            const gastosSum = consolidateKey("Gastos del Mes");

            const tooltipNetaHtml = `
                <div class="text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 240px;">
                    <strong class="d-block mb-1 border-bottom pb-1">Desglose de Utilidad Neta:</strong>
                    <div class="d-flex justify-content-between text-success"><span>(+) Interés + Util Vta:</span> <span class="fw-bold">${formatter.format(interestutilSum.avance)}</span></div>
                    <div class="d-flex justify-content-between text-danger"><span>(-) Gastos del Mes:</span> <span class="fw-bold">${formatter.format(gastosSum.avance)}</span></div>
                    <div class="border-top my-1"></div>
                    <div class="d-flex justify-content-between text-primary"><strong>Utilidad Neta:</strong> <span class="fw-bold">${netaSum.avance >= 0 ? formatter.format(netaSum.avance) : '-' + formatter.format(Math.abs(netaSum.avance))}</span></div>
                    <div class="d-flex justify-content-between text-muted"><strong>Total Meta:</strong> <span class="fw-bold">${formatter.format(netaSum.meta)}</span></div>
                </div>
            `;
            setHtmlTooltip('tooltip-neta-kpi', tooltipNetaHtml);
        }

        function renderTablero(data) {
            const tbody = document.getElementById('tablero-body');
            tbody.innerHTML = '';

            sections.forEach(section => {
                // Render section header row
                tbody.innerHTML += `
                    <tr>
                        <td colspan="6" class="row-group-header text-start ps-4">
                            <i class="bi bi-folder2-open me-2"></i>${section.title}
                        </td>
                    </tr>
                `;

                let indicatorIndex = 0;

                section.indicators.forEach(ind => {
                    // Check if we have data for this indicator key
                    const indData = data[ind.key] || {};
                    
                    // Precompute totals
                    let rowTotalMeta = 0;
                    let rowTotalAvance = 0;

                    categories.forEach(cat => {
                        rowTotalMeta += indData[cat] ? indData[cat].meta : 0;
                        rowTotalAvance += indData[cat] ? indData[cat].avance : 0;
                    });

                    let rowCells = '';
                    
                    // Render cell values for each category
                    categories.forEach(cat => {
                        const cellMeta = indData[cat] ? indData[cat].meta : 0;
                        const cellAvance = indData[cat] ? indData[cat].avance : 0;
                        const cellPct = calculatePercentage(cellAvance, cellMeta);
                        const progressColor = getProgressColorClass(cellPct);
                        const badgeColor = getBadgeColorClass(cellPct);

                        // If all values are 0, render a clean blank cell
                        if (cellMeta === 0 && cellAvance === 0) {
                            rowCells += `
                                <td class="text-muted text-center font-monospace-custom">—</td>
                            `;
                        } else {
                            rowCells += `
                                <td class="text-end font-monospace-custom">
                                    <div class="d-flex flex-column text-end cell-progress-container">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark fs-7">${formatter.format(cellAvance)}</span>
                                            <span class="badge ${badgeColor} badge-progress ms-2">${Math.round(cellPct)}%</span>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.75rem;">
                                            <span>Meta:</span>
                                            <span>${formatter.format(cellMeta)}</span>
                                        </div>
                                        <div class="cell-progress-bar">
                                            <div class="cell-progress-fill ${progressColor}" style="width: ${Math.min(cellPct, 100)}%;"></div>
                                        </div>
                                    </div>
                                </td>
                            `;
                        }
                    });

                    // Total consolidado column calculations
                    const totalPct = calculatePercentage(rowTotalAvance, rowTotalMeta);
                    const totalProgressColor = getProgressColorClass(totalPct);
                    const totalBadgeColor = getBadgeColorClass(totalPct);

                    let totalCell = '';
                    if (rowTotalMeta === 0 && rowTotalAvance === 0) {
                        totalCell = `<td class="text-muted text-center font-monospace-custom pe-4">—</td>`;
                    } else {
                        totalCell = `
                            <td class="text-end font-monospace-custom bg-light-subtle pe-4" style="background-color: rgba(78, 115, 223, 0.03) !important;">
                                <div class="d-flex flex-column text-end cell-progress-container">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-primary fs-7">${formatter.format(rowTotalAvance)}</span>
                                        <span class="badge ${totalBadgeColor} badge-progress ms-2">${Math.round(totalPct)}%</span>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted" style="font-size: 0.75rem;">
                                        <span>Meta:</span>
                                        <span>${formatter.format(rowTotalMeta)}</span>
                                    </div>
                                    <div class="cell-progress-bar">
                                        <div class="cell-progress-fill ${totalProgressColor}" style="width: ${Math.min(totalPct, 100)}%;"></div>
                                    </div>
                                </div>
                            </td>
                        `;
                    }

                    // Determine background row class
                    let rowBgClass = '';
                    if (ind.highlight) {
                        rowBgClass = 'fw-bold table-primary';
                    } else {
                        rowBgClass = (indicatorIndex % 2 === 0) ? 'row-blue' : 'row-white';
                        indicatorIndex++;
                    }

                    tbody.innerHTML += `
                        <tr class="${rowBgClass}">
                            <td class="text-start ps-4 fw-semibold text-dark">
                                <i class="bi ${ind.icon} text-primary me-2"></i>${ind.name}
                            </td>
                            ${rowCells}
                            ${totalCell}
                        </tr>
                    `;
                });
            });
        }
    });
</script>
@endsection
