@extends('employees.layouts.main')

@section('title', 'Inventario Rematado')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.08) !important;
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
        .bg-light-success { background-color: rgba(25, 135, 84, 0.12); color: #198754; }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.12); color: #dc3545; }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.12); color: #0dcaf0; }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.15); color: #d97706; }
        .bg-light-primary { background-color: rgba(99, 102, 241, 0.12); color: #6366f1; }
        .bg-light-purple { background-color: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
        .bg-light-secondary { background-color: rgba(108, 117, 125, 0.12); color: #6c757d; }

        .table-responsive { overflow-x: auto; }

        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }

        .badge-cat {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            border-radius: 6px;
        }

        .badge-cat-oro { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-cat-plata { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .badge-cat-varios { background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge-cat-autos { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        .badge-status-piso { background-color: #fef3c7; color: #b45309; }
        .badge-status-vendida { background-color: #d1fae5; color: #065f46; }
        .badge-status-apartada { background-color: #ede9fe; color: #5b21b6; }
        .badge-status-baja { background-color: #fee2e2; color: #991b1b; }
        .badge-status-desempeno { background-color: #cffafe; color: #155e75; }

        .table-detailed th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #64748b;
            letter-spacing: 0.5px;
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .table-detailed td {
            font-size: 0.85rem;
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-dark fw-bold">Analizando inventario rematado...</h5>
    <p class="text-muted small">Consultando histórico de pase a almoneda y fechas de pase a venta...</p>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <!-- Encabezado -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold rounded-2">
                    <i class="bi bi-hammer me-1"></i>Pase a Almoneda
                </span>
                <h3 class="fw-bold text-dark mb-0">Inventario Rematado</h3>
            </div>
            <p class="text-muted mb-0 small mt-1">
                Seguimiento integral de prendas adjudicadas por vencimiento de contrato (<code class="text-primary fw-semibold">historico_articulos</code> y fecha de pase a venta <code class="text-primary fw-semibold">p_venta</code>).
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-outline-success shadow-sm btn-sm fw-semibold" id="btn-export-csv">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar CSV
            </button>
            <span class="badge bg-white text-secondary border py-2 px-3 shadow-sm rounded-pill fw-normal" id="label-periodo-badge">
                <i class="bi bi-calendar-range me-1 text-primary"></i><span id="badge-periodo-texto">Cargando...</span>
            </span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-3 p-md-4">
            <form id="filter-form" class="row g-3 align-items-end">
                @if(auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin')
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Regional</label>
                    <select name="regional_user_id" id="regional_user_id" class="form-select form-select-sm" onchange="const url = new URL(window.location.href); if(this.value){ url.searchParams.set('regional_user_id', this.value); } else { url.searchParams.delete('regional_user_id'); } url.searchParams.delete('sucursal_id'); window.location.href = url.toString();">
                        <option value="">-- Todos --</option>
                        @foreach($regionales ?? [] as $reg)
                            <option value="{{ $reg->id }}" {{ (isset($regionalUserId) && $regionalUserId == $reg->id) ? 'selected' : '' }}>
                                {{ $reg->nombre_completo }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="{{ (auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin') ? 'col-12 col-sm-6 col-lg-2' : 'col-12 col-sm-6 col-lg-3' }}">
                    <label class="form-label small fw-semibold text-muted mb-1">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select form-select-sm">
                        <option value="all" {{ (request('sucursal_id') == 'all' || !request('sucursal_id')) ? 'selected' : '' }}>-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}" {{ (string)request('sucursal_id') === (string)$sucursal->id_valora_mas ? 'selected' : '' }}>
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ substr($fechaInicio, 0, 10) }}" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Categoría</label>
                    <select name="categoria" id="categoria" class="form-select form-select-sm">
                        <option value="todas" {{ request('categoria', 'todas') === 'todas' ? 'selected' : '' }}>Todas las prendas</option>
                        <option value="oro" {{ request('categoria') === 'oro' ? 'selected' : '' }}>Oro</option>
                        <option value="plata" {{ request('categoria') === 'plata' ? 'selected' : '' }}>Plata</option>
                        <option value="varios" {{ request('categoria') === 'varios' ? 'selected' : '' }}>Mercancía General / Varios</option>
                        <option value="autos" {{ request('categoria') === 'autos' ? 'selected' : '' }}>Autos</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-1"></i>Filtrar
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-quick-mes" title="Mes Actual">
                        Mes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== KPIs PRINCIPALES ========== -->
    <div class="row g-3 mb-4">
        <!-- KPI 1: Total Préstamo Rematado -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-success border-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            Capital Rematado (Costo)
                        </span>
                        <div class="icon-shape bg-light-success"><i class="bi bi-cash-stack"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" id="kpi-total-prestamo">$ 0.00</h3>
                    <span class="text-muted small">Monto prestado en contratos adjudicados</span>
                </div>
            </div>
        </div>

        <!-- KPI 2: Valor Comercial (Precio Almoneda) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-primary border-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            Valor Almoneda (Precio)
                        </span>
                        <div class="icon-shape bg-light-primary"><i class="bi bi-tag-fill"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" id="kpi-total-precio">$ 0.00</h3>
                    <span class="text-muted small">Precio venta asignado al rematar</span>
                </div>
            </div>
        </div>

        <!-- KPI 3: Margen Proyectado -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-purple border-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            Margen Proyectado
                        </span>
                        <div class="icon-shape bg-light-purple"><i class="bi bi-graph-up-arrow"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" id="kpi-margen-monto">$ 0.00</h3>
                    <span class="text-success fw-semibold small" id="kpi-margen-pct">+0.0% sobre costo</span>
                </div>
            </div>
        </div>

        <!-- KPI 4: Total Artículos y Contratos -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-warning border-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            Prendas Rematadas
                        </span>
                        <div class="icon-shape bg-light-warning"><i class="bi bi-box-seam-fill"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" id="kpi-total-articulos">0</h3>
                    <span class="text-muted small" id="kpi-total-contratos">0 contratos adjudicados</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== KPIs SECUNDARIOS POR CATEGORÍA ========== -->
    <div class="row g-3 mb-4">
        <!-- Oro -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge badge-cat-oro"><i class="bi bi-gem me-1"></i>Oro</span>
                        <span class="badge bg-light text-dark fw-bold" id="cat-oro-items">0 piezas</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-0 mt-2" id="cat-oro-prestamo">$ 0.00</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Almoneda: <span id="cat-oro-precio" class="fw-semibold text-dark">$ 0.00</span></small>
                </div>
            </div>
        </div>
        <!-- Plata -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge badge-cat-plata"><i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i>Plata</span>
                        <span class="badge bg-light text-dark fw-bold" id="cat-plata-items">0 piezas</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-0 mt-2" id="cat-plata-prestamo">$ 0.00</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Almoneda: <span id="cat-plata-precio" class="fw-semibold text-dark">$ 0.00</span></small>
                </div>
            </div>
        </div>
        <!-- Varios -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge badge-cat-varios"><i class="bi bi-tv me-1"></i>Mercancía General</span>
                        <span class="badge bg-light text-dark fw-bold" id="cat-varios-items">0 artículos</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-0 mt-2" id="cat-varios-prestamo">$ 0.00</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Almoneda: <span id="cat-varios-precio" class="fw-semibold text-dark">$ 0.00</span></small>
                </div>
            </div>
        </div>
        <!-- Autos -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge badge-cat-autos"><i class="bi bi-car-front-fill me-1"></i>Autos</span>
                        <span class="badge bg-light text-dark fw-bold" id="cat-autos-items">0 vehículos</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-0 mt-2" id="cat-autos-prestamo">$ 0.00</h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Almoneda: <span id="cat-autos-precio" class="fw-semibold text-dark">$ 0.00</span></small>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== GRÁFICOS ========== -->
    <div class="row g-3 mb-4">
        <!-- Evolución Diaria de Remates -->
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Evolución Diaria de Remates (<code class="text-primary">p_venta</code>)</h6>
                        <small class="text-muted">Distribución de capital e importe comercial rematado a lo largo del periodo</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary active btn-metric" data-metric="monto">Monto ($)</button>
                        <button type="button" class="btn btn-outline-primary btn-metric" data-metric="articulos">Prendas</button>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 300px;">
                        <canvas id="chartEvolucion"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribución por Categoría -->
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Composición por Categoría</h6>
                    <small class="text-muted">Proporción de capital rematado</small>
                </div>
                <div class="card-body p-3 p-md-4 d-flex flex-column align-items-center justify-content-center">
                    <div style="height: 220px; width: 100%;">
                        <canvas id="chartCategorias"></canvas>
                    </div>
                    <div class="mt-3 w-100" id="legend-categorias"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Desplazamiento Actual de lo Rematado -->
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Estatus Actual de lo Rematado</h6>
                    <small class="text-muted">¿En dónde están las prendas que se remataron?</small>
                </div>
                <div class="card-body p-3 p-md-4 d-flex flex-column align-items-center justify-content-center">
                    <div style="height: 220px; width: 100%;">
                        <canvas id="chartEstatusActual"></canvas>
                    </div>
                    <div class="mt-3 w-100" id="legend-estatus"></div>
                </div>
            </div>
        </div>

        <!-- Comparativo por Sucursal -->
        <div class="col-12 col-md-6 col-xl-8">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Volumen Rematado por Sucursal</h6>
                    <small class="text-muted">Comparativo de capital prestado rematado por cada sucursal</small>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 280px;">
                        <canvas id="chartSucursales"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== TABLA DETALLADA ========== -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white border-0 pt-3 px-4 pb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Detalle de Mercancía Rematada</h5>
                    <small class="text-muted">Listado pormenorizado de prendas pasadas a venta con fecha histórica <code class="text-primary">p_venta</code></small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="tabla-search" class="form-control border-start-0" placeholder="Buscar contrato, prenda, sucursal...">
                    </div>
                    <select id="tabla-page-size" class="form-select form-select-sm" style="width: auto;">
                        <option value="15">15 por pág.</option>
                        <option value="30" selected>30 por pág.</option>
                        <option value="50">50 por pág.</option>
                        <option value="100">100 por pág.</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-detailed mb-0" id="tabla-articulos">
                    <thead>
                        <tr>
                            <th class="ps-4">Contrato</th>
                            <th>Sucursal</th>
                            <th>Fecha Remate (p_venta)</th>
                            <th>Categoría</th>
                            <th>Descripción Prenda</th>
                            <th class="text-end">Préstamo</th>
                            <th class="text-end">Precio Almoneda</th>
                            <th class="text-end">Margen %</th>
                            <th class="text-center pe-4">Estatus Actual</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-body">
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Cargando información...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
            <small class="text-muted" id="tabla-info">Mostrando 0 de 0 prendas</small>
            <div class="btn-group btn-group-sm" id="tabla-pagination">
                <button type="button" class="btn btn-outline-secondary" id="btn-prev-page" disabled>
                    <i class="bi bi-chevron-left me-1"></i>Anterior
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-next-page" disabled>
                    Siguiente<i class="bi bi-chevron-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const currencyFormatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const numberFormatter = new Intl.NumberFormat('es-MX');

        let rawData = null;
        let chartEvolucionInstance = null;
        let chartCategoriasInstance = null;
        let chartEstatusInstance = null;
        let chartSucursalesInstance = null;
        let currentChartMetric = 'monto'; // 'monto' o 'articulos'

        // Estado de la tabla
        let currentPage = 1;
        let pageSize = 30;
        let filteredArticles = [];

        // Elementos DOM
        const filterForm = document.getElementById('filter-form');
        const loadingOverlay = document.getElementById('loading-overlay');
        const dashboardContent = document.getElementById('dashboard-content');
        const searchInput = document.getElementById('tabla-search');
        const pageSizeSelect = document.getElementById('tabla-page-size');
        const btnPrevPage = document.getElementById('btn-prev-page');
        const btnNextPage = document.getElementById('btn-next-page');
        const btnExportCsv = document.getElementById('btn-export-csv');
        const btnQuickMes = document.getElementById('btn-quick-mes');

        // Botones de métrica
        document.querySelectorAll('.btn-metric').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.btn-metric').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentChartMetric = this.getAttribute('data-metric');
                renderEvolucionChart();
            });
        });

        // Botón Mes Rápido
        if (btnQuickMes) {
            btnQuickMes.addEventListener('click', function () {
                const now = new Date();
                const y = now.getFullYear();
                const m = String(now.getMonth() + 1).padStart(2, '0');
                const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
                document.getElementById('fecha_inicio').value = `${y}-${m}-01`;
                document.getElementById('fecha_fin').value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
                fetchData();
            });
        }

        // Envío de Filtro
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchData();
        });

        // Búsqueda en tabla
        searchInput.addEventListener('input', function () {
            currentPage = 1;
            filterTableData();
        });

        // Tamaño de página
        pageSizeSelect.addEventListener('change', function () {
            pageSize = parseInt(this.value, 10);
            currentPage = 1;
            renderTable();
        });

        // Paginación
        btnPrevPage.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        });

        btnNextPage.addEventListener('click', function () {
            const maxPage = Math.ceil(filteredArticles.length / pageSize);
            if (currentPage < maxPage) {
                currentPage++;
                renderTable();
            }
        });

        // Exportar a CSV
        btnExportCsv.addEventListener('click', function () {
            exportToCsv();
        });

        // Carga Inicial
        fetchData();

        function fetchData() {
            loadingOverlay.style.display = 'flex';

            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);

            fetch(`/inventario-rematado/data?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    loadingOverlay.style.display = 'none';
                    dashboardContent.style.display = 'block';

                    if (!data.success) {
                        alert('Error al consultar datos: ' + (data.error || 'Error desconocido'));
                        return;
                    }

                    rawData = data;
                    document.getElementById('badge-periodo-texto').textContent = data.rango_fechas_label || `${data.fecha_inicio} al ${data.fecha_fin}`;

                    updateKPIs(data);
                    renderEvolucionChart();
                    renderCategoriasChart();
                    renderEstatusChart();
                    renderSucursalesChart();

                    filteredArticles = data.articulos || [];
                    currentPage = 1;
                    filterTableData();
                })
                .catch(err => {
                    loadingOverlay.style.display = 'none';
                    dashboardContent.style.display = 'block';
                    console.error('Error fetching data:', err);
                    alert('Ocurrió un error al cargar la información.');
                });
        }

        function updateKPIs(data) {
            const k = data.kpis;
            document.getElementById('kpi-total-prestamo').textContent = currencyFormatter.format(k.total_prestamo || 0);
            document.getElementById('kpi-total-precio').textContent = currencyFormatter.format(k.total_precio || 0);
            document.getElementById('kpi-margen-monto').textContent = currencyFormatter.format(k.margen_monto || 0);
            document.getElementById('kpi-margen-pct').textContent = `+${(k.margen_pct || 0).toFixed(1)}% sobre costo`;
            document.getElementById('kpi-total-articulos').textContent = numberFormatter.format(k.total_articulos || 0);
            document.getElementById('kpi-total-contratos').textContent = `${numberFormatter.format(k.total_contratos || 0)} contratos adjudicados`;

            // Categorías
            const c = data.por_categoria;
            if (c.Oro) {
                document.getElementById('cat-oro-items').textContent = `${numberFormatter.format(c.Oro.articulos)} piezas`;
                document.getElementById('cat-oro-prestamo').textContent = currencyFormatter.format(c.Oro.prestamo);
                document.getElementById('cat-oro-precio').textContent = currencyFormatter.format(c.Oro.precio);
            }
            if (c.Plata) {
                document.getElementById('cat-plata-items').textContent = `${numberFormatter.format(c.Plata.articulos)} piezas`;
                document.getElementById('cat-plata-prestamo').textContent = currencyFormatter.format(c.Plata.prestamo);
                document.getElementById('cat-plata-precio').textContent = currencyFormatter.format(c.Plata.precio);
            }
            if (c.Varios) {
                document.getElementById('cat-varios-items').textContent = `${numberFormatter.format(c.Varios.articulos)} arts`;
                document.getElementById('cat-varios-prestamo').textContent = currencyFormatter.format(c.Varios.prestamo);
                document.getElementById('cat-varios-precio').textContent = currencyFormatter.format(c.Varios.precio);
            }
            if (c.Autos) {
                document.getElementById('cat-autos-items').textContent = `${numberFormatter.format(c.Autos.articulos)} autos`;
                document.getElementById('cat-autos-prestamo').textContent = currencyFormatter.format(c.Autos.prestamo);
                document.getElementById('cat-autos-precio').textContent = currencyFormatter.format(c.Autos.precio);
            }
        }

        // 1. Gráfica de Evolución Diaria
        function renderEvolucionChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartEvolucion').getContext('2d');
            const dataEvol = rawData.evolucion_diaria || [];

            const labels = dataEvol.map(d => `${d.fecha_formato} (${d.dia_semana})`);
            const isMonto = currentChartMetric === 'monto';

            let datasets = [];

            if (isMonto) {
                datasets = [
                    {
                        label: 'Precio Almoneda ($)',
                        data: dataEvol.map(d => d.precio),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                    },
                    {
                        label: 'Capital Rematado ($)',
                        data: dataEvol.map(d => d.prestamo),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                    }
                ];
            } else {
                datasets = [
                    {
                        label: 'Prendas Rematadas',
                        data: dataEvol.map(d => d.articulos),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.3)',
                        type: 'bar',
                        borderRadius: 4,
                    }
                ];
            }

            if (chartEvolucionInstance) chartEvolucionInstance.destroy();

            chartEvolucionInstance = new Chart(ctx, {
                type: isMonto ? 'line' : 'bar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const val = context.parsed.y || 0;
                                    return isMonto
                                        ? ` ${context.dataset.label}: ${currencyFormatter.format(val)}`
                                        : ` ${context.dataset.label}: ${numberFormatter.format(val)} piezas`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (val) {
                                    return isMonto ? currencyFormatter.format(val) : numberFormatter.format(val);
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // 2. Gráfica de Categorías
        function renderCategoriasChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartCategorias').getContext('2d');
            const cats = rawData.por_categoria || {};

            const labels = ['Oro', 'Plata', 'Varios', 'Autos'];
            const dataVals = labels.map(l => cats[l] ? cats[l].prestamo : 0);
            const colors = ['#f59e0b', '#94a3b8', '#6366f1', '#10b981'];

            if (chartCategoriasInstance) chartCategoriasInstance.destroy();

            chartCategoriasInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: dataVals,
                        backgroundColor: colors,
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const val = context.raw || 0;
                                    const info = cats[label];
                                    const count = info ? info.articulos : 0;
                                    return ` ${label}: ${currencyFormatter.format(val)} (${numberFormatter.format(count)} arts)`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Leyenda personalizada
            const legendEl = document.getElementById('legend-categorias');
            const total = dataVals.reduce((a, b) => a + b, 0);
            legendEl.innerHTML = labels.map((l, i) => {
                const val = dataVals[i];
                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                return `
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <div>
                            <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: ${colors[i]};"></span>
                            <span class="text-dark fw-semibold">${l}</span>
                        </div>
                        <span class="text-muted">${currencyFormatter.format(val)} <strong class="text-dark">(${pct}%)</strong></span>
                    </div>
                `;
            }).join('');
        }

        // 3. Gráfica de Estatus Actual
        function renderEstatusChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartEstatusActual').getContext('2d');
            const estatusMap = rawData.por_estatus_actual || {};

            const labels = Object.keys(estatusMap);
            const dataVals = labels.map(k => estatusMap[k].prestamo);
            const colors = labels.map(k => estatusMap[k].color || '#64748b');

            if (chartEstatusInstance) chartEstatusInstance.destroy();

            chartEstatusInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: dataVals,
                        backgroundColor: colors,
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const val = context.raw || 0;
                                    const info = estatusMap[label];
                                    const count = info ? info.articulos : 0;
                                    return ` ${label}: ${currencyFormatter.format(val)} (${numberFormatter.format(count)} arts)`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Leyenda personalizada
            const legendEl = document.getElementById('legend-estatus');
            const total = dataVals.reduce((a, b) => a + b, 0);
            legendEl.innerHTML = labels.map((l, i) => {
                const val = dataVals[i];
                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                return `
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <div>
                            <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: ${colors[i]};"></span>
                            <span class="text-dark fw-semibold">${l}</span>
                        </div>
                        <span class="text-muted">${currencyFormatter.format(val)} <strong class="text-dark">(${pct}%)</strong></span>
                    </div>
                `;
            }).join('');
        }

        // 4. Gráfica de Sucursales
        function renderSucursalesChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartSucursales').getContext('2d');
            const sucs = rawData.por_sucursal || [];

            const labels = sucs.map(s => s.nombre);
            const prestamos = sucs.map(s => s.prestamo);
            const precios = sucs.map(s => s.precio);

            if (chartSucursalesInstance) chartSucursalesInstance.destroy();

            chartSucursalesInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Capital Rematado ($)',
                            data: prestamos,
                            backgroundColor: '#10b981',
                            borderRadius: 4
                        },
                        {
                            label: 'Precio Almoneda ($)',
                            data: precios,
                            backgroundColor: '#6366f1',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return ` ${context.dataset.label}: ${currencyFormatter.format(context.raw || 0)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (val) {
                                    return currencyFormatter.format(val);
                                }
                            }
                        },
                        x: {
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });
        }

        // Filtrado y Renderizado de Tabla
        function filterTableData() {
            if (!rawData || !rawData.articulos) return;

            const q = searchInput.value.toLowerCase().trim();

            if (!q) {
                filteredArticles = rawData.articulos;
            } else {
                filteredArticles = rawData.articulos.filter(item => {
                    return (
                        (item.cod_contrato && item.cod_contrato.toLowerCase().includes(q)) ||
                        (item.sucursal && item.sucursal.toLowerCase().includes(q)) ||
                        (item.descripcion && item.descripcion.toLowerCase().includes(q)) ||
                        (item.observaciones && item.observaciones.toLowerCase().includes(q)) ||
                        (item.categoria && item.categoria.toLowerCase().includes(q)) ||
                        (item.estatus_actual && item.estatus_actual.toLowerCase().includes(q))
                    );
                });
            }

            renderTable();
        }

        function renderTable() {
            const tbody = document.getElementById('tabla-body');
            const total = filteredArticles.length;

            if (total === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No se encontraron artículos rematados en el periodo o con los filtros seleccionados.
                        </td>
                    </tr>
                `;
                document.getElementById('tabla-info').textContent = 'Mostrando 0 de 0 prendas';
                btnPrevPage.disabled = true;
                btnNextPage.disabled = true;
                return;
            }

            const maxPage = Math.ceil(total / pageSize);
            if (currentPage > maxPage) currentPage = maxPage;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = Math.min(startIdx + pageSize, total);
            const pageItems = filteredArticles.slice(startIdx, endIdx);

            let html = '';
            pageItems.forEach(item => {
                let badgeCatClass = 'badge-cat-varios';
                if (item.categoria === 'Oro') badgeCatClass = 'badge-cat-oro';
                else if (item.categoria === 'Plata') badgeCatClass = 'badge-cat-plata';
                else if (item.categoria === 'Autos') badgeCatClass = 'badge-cat-autos';

                let badgeStatusClass = 'badge-status-piso';
                if (item.cod_estatus_actual === 3 || item.cod_estatus_actual === 12) badgeStatusClass = 'badge-status-vendida';
                else if (item.cod_estatus_actual === 4) badgeStatusClass = 'badge-status-apartada';
                else if (item.cod_estatus_actual === 5 || item.cod_estatus_actual === 10) badgeStatusClass = 'badge-status-baja';
                else if (item.cod_estatus_actual === 6) badgeStatusClass = 'badge-status-desempeno';

                const margenPesos = item.precio - item.prestamo;

                html += `
                    <tr>
                        <td class="ps-4 font-monospace fw-bold text-dark">
                            ${item.cod_contrato && item.cod_contrato !== '-' ? '#' + item.cod_contrato : '<span class="text-muted">-</span>'}
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border">${item.sucursal}</span>
                        </td>
                        <td>
                            <span class="text-dark fw-semibold">${item.fecha_formato}</span>
                        </td>
                        <td>
                            <span class="badge ${badgeCatClass} badge-cat">${item.categoria}</span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="${item.observaciones || item.descripcion}">
                                ${item.descripcion}
                            </div>
                            <small class="text-muted text-truncate d-block" style="max-width: 250px; font-size: 0.73rem;" title="${item.observaciones}">
                                ${item.observaciones || 'Sin observaciones'}
                            </small>
                        </td>
                        <td class="text-end font-monospace fw-bold text-dark">
                            ${currencyFormatter.format(item.prestamo)}
                        </td>
                        <td class="text-end font-monospace fw-bold text-primary">
                            ${currencyFormatter.format(item.precio)}
                        </td>
                        <td class="text-end font-monospace">
                            <span class="fw-semibold text-success">+${item.margen_pct}%</span>
                            <br><small class="text-muted" style="font-size: 0.72rem;">${currencyFormatter.format(margenPesos)}</small>
                        </td>
                        <td class="text-center pe-4">
                            <span class="badge ${badgeStatusClass} badge-cat">${item.estatus_actual}</span>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            document.getElementById('tabla-info').textContent = `Mostrando ${startIdx + 1} a ${endIdx} de ${numberFormatter.format(total)} prendas rematadas`;
            btnPrevPage.disabled = currentPage === 1;
            btnNextPage.disabled = currentPage === maxPage;
        }

        function exportToCsv() {
            if (!filteredArticles || filteredArticles.length === 0) {
                alert('No hay datos para exportar.');
                return;
            }

            const headers = [
                'Contrato',
                'Sucursal',
                'Fecha_Remate_P_Venta',
                'Tipo_Prenda',
                'Categoria',
                'Descripcion',
                'Observaciones',
                'Prestamo_Costo',
                'Precio_Almoneda',
                'Avaluo',
                'Margen_Monto',
                'Margen_Porcentaje',
                'Estatus_Actual'
            ];

            const rows = filteredArticles.map(item => [
                item.cod_contrato || '',
                `"${(item.sucursal || '').replace(/"/g, '""')}"`,
                item.fecha_remate || '',
                item.tipo_prenda || '',
                item.categoria || '',
                `"${(item.descripcion || '').replace(/"/g, '""')}"`,
                `"${(item.observaciones || '').replace(/"/g, '""')}"`,
                item.prestamo || 0,
                item.precio || 0,
                item.avaluo || 0,
                (item.precio || 0) - (item.prestamo || 0),
                item.margen_pct || 0,
                `"${(item.estatus_actual || '').replace(/"/g, '""')}"`
            ]);

            let csvContent = '\uFEFF' + headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const fechaIni = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            a.href = url;
            a.download = `inventario_rematado_${fechaIni}_al_${fechaFin}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    });
</script>
@endsection
