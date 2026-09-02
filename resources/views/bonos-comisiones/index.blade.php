@extends('employees.layouts.main')

@section('title', 'Bonos y Comisiones')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.12) !important;
        }
        .icon-shape-lg {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            border-radius: 1rem;
        }
        .bg-gradient-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #2dce89 0%, #2dcecc 100%);
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #fb6340 0%, #fbb140 100%);
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
        }
        
        .bg-soft-primary { background-color: rgba(102, 126, 234, 0.12); color: #667eea; }
        .bg-soft-success { background-color: rgba(45, 206, 137, 0.12); color: #2dce89; }
        .bg-soft-warning { background-color: rgba(251, 99, 64, 0.12); color: #fb6340; }
        .bg-soft-info { background-color: rgba(17, 205, 239, 0.12); color: #11cdef; }
        .bg-soft-danger { background-color: rgba(245, 54, 92, 0.12); color: #f5365c; }

        .progress-custom {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
        }

        .badge-level {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.7em;
            border-radius: 50rem;
        }
        .badge-junior { background-color: #e2e8f0; color: #475569; }
        .badge-senior { background-color: #dbeafe; color: #1e40af; }
        .badge-master { background-color: #fef3c7; color: #92400e; }

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
        .table-responsive { overflow-x: auto; }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* Tooltip Personalizado */
        .tooltip .tooltip-inner {
            max-width: 320px;
            padding: 12px 16px;
            background-color: #0f172a;
            color: #f8fafc;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            font-size: 0.85rem;
            text-align: left;
            line-height: 1.5;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .tooltip .tooltip-inner code {
            background: rgba(255, 255, 255, 0.15);
            color: #38bdf8;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .tooltip-title {
            font-weight: 700;
            color: #38bdf8;
            font-size: 0.9rem;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .tooltip-formula-badge {
            background: rgba(14, 165, 233, 0.18);
            border: 1px solid rgba(56, 189, 248, 0.35);
            color: #7dd3fc;
            border-radius: 6px;
            padding: 4px 8px;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.78rem;
            margin: 6px 0 2px 0;
            display: block;
            word-break: break-word;
        }
        .help-icon {
            cursor: help;
            color: #94a3b8;
            font-size: 0.85em;
            margin-left: 3px;
            transition: all 0.2s ease;
        }
        .help-icon:hover {
            color: #3b82f6 !important;
            transform: scale(1.2);
        }
        .has-tooltip {
            cursor: help;
        }
    </style>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-dark fw-bold">Calculando bonos, comisiones y metas...</h5>
    <p class="text-muted small mb-0">Consolidando información multisucursal</p>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <!-- Encabezado -->
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-award-fill text-primary me-2"></i>Módulo de Bonos y Comisiones
            </h3>
            <p class="text-muted mb-0">Seguimiento de compensación variable, cumplimiento de metas y esquemas activos</p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <button class="btn btn-outline-secondary me-2" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Imprimir Reporte
            </button>
            <button class="btn btn-success shadow-sm" onclick="exportarExcel()">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Datos
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">SUCURSAL</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select border-0 bg-light">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">PUESTO / PERFIL</label>
                    <select name="puesto" id="puesto" class="form-select border-0 bg-light">
                        <option value="">-- Todos los Puestos --</option>
                        <option value="Encargado">Encargado</option>
                        <option value="Cajero">Cajero</option>
                        <option value="Valuador">Valuador</option>
                        <option value="Promotor">Promotor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-secondary small">BUSCAR EMPLEADO</label>
                    <input type="text" name="empleado" id="empleado" class="form-control border-0 bg-light" placeholder="Nombre de colaborador...">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">FECHA DESDE</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}" class="form-control border-0 bg-light">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-secondary small">FECHA HASTA</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control border-0 bg-light">
                </div>
                <div class="col-12 text-end pt-2">
                    <button type="button" id="btn-reset" class="btn btn-light text-muted me-2">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar Filtros
                    </button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-filter me-1"></i>Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TARJETAS KPIS PRINCIPALES (Indicadores 140, 142, 145, 149) -->
    <div class="row g-4 mb-4">
        <!-- KPI 140: Total Bonos y Comisiones Pagados -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 card-hover h-100"
                 data-bs-toggle="tooltip" data-bs-html="true"
                 data-bs-title="<div class='tooltip-title'><i class='bi-cash-stack'></i> Total Pagado (KPI 140)</div>Suma total de incentivos variables generados por comisiones y bonos de meta.<div class='tooltip-formula-badge'>Comisiones Totales + Bonos de Meta</div>">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Total Pagado (KPI 140) <i class="bi bi-info-circle help-icon"></i></span>
                            <h3 class="fw-bold text-dark mt-2 mb-0" id="kpi-total-pagado">$0.00</h3>
                        </div>
                        <div class="icon-shape-lg bg-soft-primary text-primary">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small pt-2 border-top">
                        <span>Comisiones: <b class="text-dark" id="kpi-sub-comisiones">$0</b></span>
                        <span>Bonos: <b class="text-dark" id="kpi-sub-bonos">$0</b></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 142: % Bono Alcanzado vs Meta Individual -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 card-hover h-100"
                 data-bs-toggle="tooltip" data-bs-html="true"
                 data-bs-title="<div class='tooltip-title'><i class='bi-bullseye'></i> Avance vs Meta (KPI 142)</div>Promedio del porcentaje alcanzado por el personal respecto a su meta de ventas ($30,000 MXN).<div class='tooltip-formula-badge'>Promedio( (Ventas / $30,000) × 100 )</div>">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Avance vs Meta (KPI 142) <i class="bi bi-info-circle help-icon"></i></span>
                            <h3 class="fw-bold text-dark mt-2 mb-0" id="kpi-cumplimiento-meta">0.0%</h3>
                        </div>
                        <div class="icon-shape-lg bg-soft-success text-success">
                            <i class="bi bi-bullseye"></i>
                        </div>
                    </div>
                    <div class="progress progress-custom mb-2">
                        <div class="progress-bar bg-success" id="kpi-progress-bar" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Cumplimiento promedio global</span>
                        <span id="kpi-empleados-count">0 Empleados</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 145: Relación Bonos+Comisiones vs Utilidad Bruta -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 card-hover h-100"
                 data-bs-toggle="tooltip" data-bs-html="true"
                 data-bs-title="<div class='tooltip-title'><i class='bi-percent'></i> % Costo Variable (KPI 145)</div>Porcentaje del margen bruto total de la empresa repartido en bonos y comisiones.<div class='tooltip-formula-badge'>( (Bonos + Comisiones) / Utilidad Bruta ) × 100</div>">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">% Costo Variable (KPI 145) <i class="bi bi-info-circle help-icon"></i></span>
                            <h3 class="fw-bold text-dark mt-2 mb-0" id="kpi-costo-variable">0.00%</h3>
                        </div>
                        <div class="icon-shape-lg bg-soft-warning text-warning">
                            <i class="bi bi-percent"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small pt-2 border-top">
                        <span>Bonos + Comisiones / Utilidad Bruta</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 149: Costo Total Bonos/Comisiones % de Nómina -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 card-hover h-100"
                 data-bs-toggle="tooltip" data-bs-html="true"
                 data-bs-title="<div class='tooltip-title'><i class='bi-calculator'></i> Costo vs Nómina (KPI 149)</div>Proporción del gasto variable respecto al costo de sueldo fijo pactado (Nómina Base).<div class='tooltip-formula-badge'>( (Bonos + Comisiones) / Nómina Base Total ) × 100</div>">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Costo vs Nómina (KPI 149) <i class="bi bi-info-circle help-icon"></i></span>
                            <h3 class="fw-bold text-dark mt-2 mb-0" id="kpi-costo-nomina">0.00%</h3>
                        </div>
                        <div class="icon-shape-lg bg-soft-info text-info">
                            <i class="bi bi-calculator"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small pt-2 border-top">
                        <span>Nómina Base Total: <b class="text-dark" id="kpi-sub-nomina">$0</b></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE GRÁFICOS INTERACTIVOS (143 y 147) -->
    <div class="row g-4 mb-4">
        <!-- Gráfico 143: Comisiones por Empleado vs Meta -->
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-bar-chart-fill text-primary me-2"></i>Comisiones Generadas vs Meta (KPI 143)
                        </h5>
                        <p class="text-muted small mb-0">Comparativa individual de comisiones alcanzadas contra la meta establecida</p>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div style="height: 320px;">
                        <canvas id="chartComisionesVsMetaCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico 147: Evolución Mensual de Bonos y Comisiones por Sucursal -->
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 p-4 pb-0">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-graph-up-arrow text-success me-2"></i>Evolución Mensual (KPI 147)
                    </h5>
                    <p class="text-muted small mb-0">Histórico de bonos y comisiones por sucursal en el tiempo</p>
                </div>
                <div class="card-body p-4">
                    <div style="height: 320px;">
                        <canvas id="chartEvolucionMensualCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NAVEGACIÓN DE VISTAS DETALLADAS -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <ul class="nav nav-pills card-header-pills gap-2" id="module-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-2 fw-semibold" id="tab-desglose-btn" data-bs-toggle="pill" data-bs-target="#tab-desglose" type="button" role="tab">
                        <i class="bi bi-table me-2"></i>Desglose y Compensación (141, 150)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-2 fw-semibold" id="tab-esquemas-btn" data-bs-toggle="pill" data-bs-target="#tab-esquemas" type="button" role="tab">
                        <i class="bi bi-layers me-2"></i>Esquemas y Ranking (144, 146)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-2 fw-semibold" id="tab-alertas-btn" data-bs-toggle="pill" data-bs-target="#tab-alertas" type="button" role="tab">
                        <i class="bi bi-bell me-2"></i>Alertas de Umbral y Puestos (148, 151)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="module-tabs-content">

                <!-- TAB 1: DESGLOSE COMPLETO POR EMPLEADO (141 & 150) -->
                <div class="tab-pane fade show active" id="tab-desglose" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">
                            Desglose Individual de Compensación Total (Nómina + Bonos + Comisiones)
                        </h6>
                        <span class="badge bg-light text-muted fw-normal" id="badge-total-registros">0 registros</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabla-desglose">
                            <thead class="table-light text-secondary small text-uppercase">
                                <tr>
                                    <th data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-person-fill'></i> Empleado</div>Nombre del colaborador.<div class='tooltip-formula-badge'>Identificador Único</div>">Empleado <i class="bi bi-info-circle help-icon"></i></th>
                                    <th data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-building'></i> Sucursal</div>Sucursal donde opera el colaborador.<div class='tooltip-formula-badge'>Ubicación Operativa</div>">Sucursal <i class="bi bi-info-circle help-icon"></i></th>
                                    <th data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-briefcase-fill'></i> Puesto Base</div>Cargo o función asignada.<div class='tooltip-formula-badge'>Perfil Contratado</div>">Puesto <i class="bi bi-info-circle help-icon"></i></th>
                                    <th data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-award-fill'></i> Nivel Alcanzado</div>Categoría por ventas Varios acumuladas:<br>• <b>Junior:</b> &lt; $15k (5%)<br>• <b>Senior:</b> $15k-$25k (7%)<br>• <b>Master:</b> &gt; $25k (9%)<div class='tooltip-formula-badge'>Ventas Varios Acumuladas</div>">Nivel <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-end" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-cart-check-fill'></i> Ventas Totales</div>Suma de operaciones de venta realizadas.<div class='tooltip-formula-badge'>V.Varios + V.Metal + V.Liquidados + Créditos</div>">Ventas Totales <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-end" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-graph-up'></i> Utilidad Bruta</div>Margen bruto generado por ventas.<div class='tooltip-formula-badge'>Ventas - Costo Adquisición / Préstamo</div>">Utilidad Bruta <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-end" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-wallet2'></i> Nómina Base</div>Sueldo fijo ordinario pactado por contrato.<div class='tooltip-formula-badge'>Gasto Sueldo en Sistema ($12k Encargado / $8k Base)</div>">Nómina Base <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-end text-primary" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-percent'></i> Comisión Total</div>Suma de comisiones variables ganadas.<div class='tooltip-formula-badge'>Comision Varios + 7% Metal + 4% Liquidados + 2% Crédito</div>">Comisión <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-end text-success" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-trophy-fill'></i> Bono por Meta</div>Premio adicional por cumplir objetivo ($30,000):<br>• <b>≥120%:</b> $2,500<br>• <b>≥100%:</b> $1,500<br>• <b>≥85%:</b> $800<div class='tooltip-formula-badge'>Evaluación % Meta</div>">Bono Meta <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-end fw-bold" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-piggy-bank-fill'></i> Compensación Total</div>Pago total acumulado percibido.<div class='tooltip-formula-badge'>Nómina Base + Comisiones + Bono Meta</div>">Comp. Total <i class="bi bi-info-circle help-icon"></i></th>
                                    <th class="text-center" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-speedometer2'></i> % Cumplimiento Meta</div>Porcentaje logrado sobre la meta de $30,000.<div class='tooltip-formula-badge'>(Ventas Totales / $30,000) × 100</div>">% Meta <i class="bi bi-info-circle help-icon"></i></th>
                                </tr>
                            </thead>
                            <tbody id="tbody-desglose">
                                <!-- Se llena dinámicamente vía JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: ESQUEMAS ACTIVOS & RANKING TOP (144 & 146) -->
                <div class="tab-pane fade" id="tab-esquemas" role="tabpanel">
                    <div class="row g-4">
                        <!-- 144. Tabla Esquemas Activos -->
                        <div class="col-lg-7">
                            <div class="border rounded-4 p-4">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-file-earmark-text text-primary me-2"></i>Esquema de Comisión Activo por Empleado (144)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead class="table-light small text-uppercase">
                                            <tr>
                                                <th>Empleado</th>
                                                <th>Puesto</th>
                                                <th>Nivel Activo</th>
                                                <th>Varios (Tasa/Min)</th>
                                                <th>Metal</th>
                                                <th>Liquidados</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-esquemas">
                                            <!-- JS Populate -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 146. Tabla Ranking Top -->
                        <div class="col-lg-5">
                            <div class="border rounded-4 p-4 bg-light">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-trophy text-warning me-2"></i>Top 10 Ranking de Comisiones (146)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small text-uppercase">
                                            <tr>
                                                <th>#</th>
                                                <th>Empleado</th>
                                                <th>Sucursal</th>
                                                <th class="text-end">Comisión</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-ranking">
                                            <!-- JS Populate -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: ALERTAS DE UMBRAL & MÉTRICAS POR PUESTO (148 & 151) -->
                <div class="tab-pane fade" id="tab-alertas" role="tabpanel">
                    <div class="row g-4">
                        <!-- 148. Tabla de Alertas de Umbral -->
                        <div class="col-lg-6">
                            <div class="border border-warning border-opacity-50 rounded-4 p-4 bg-soft-warning bg-opacity-10">
                                <h6 class="fw-bold text-warning-emphasis mb-3">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Empleados Próximos a Cambiar de Umbral (148)
                                </h6>
                                <p class="text-muted small">Colaboradores cercanos a alcanzar la meta para cambiar de nivel de comisión</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small text-uppercase">
                                            <tr>
                                                <th>Empleado</th>
                                                <th>Nivel Actual</th>
                                                <th>Siguiente</th>
                                                <th class="text-end">Venta Varios</th>
                                                <th class="text-end">Faltante</th>
                                                <th class="text-center">% Faltante</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-alertas">
                                            <!-- JS Populate -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- 151. Tabla Métrica Compensación por Puesto -->
                        <div class="col-lg-6">
                            <div class="border rounded-4 p-4">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-briefcase text-info me-2"></i>Compensación Promedio Total por Puesto (151)
                                </h6>
                                <p class="text-muted small">Promedio de nómina base + comisiones y bonos por puesto</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light small text-uppercase">
                                            <tr>
                                                <th>Puesto / Perfil</th>
                                                <th class="text-center">Personal</th>
                                                <th class="text-end">Nómina Prom.</th>
                                                <th class="text-end">Comisión Prom.</th>
                                                <th class="text-end fw-bold">Total Prom.</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-puestos">
                                            <!-- JS Populate -->
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
</div>
@endsection

@section('scripts')
<script>
    let chartVsMetaInstance = null;
    let chartEvolucionInstance = null;
    let rawDataGlobal = null;

    document.addEventListener('DOMContentLoaded', function () {
        cargarDatos();

        document.getElementById('filter-form').addEventListener('submit', function (e) {
            e.preventDefault();
            cargarDatos();
        });

        document.getElementById('btn-reset').addEventListener('click', function () {
            document.getElementById('sucursal_id').value = '';
            document.getElementById('puesto').value = '';
            document.getElementById('empleado').value = '';
            cargarDatos();
        });
    });

    function cargarDatos() {
        document.getElementById('loading-overlay').style.display = 'flex';
        document.getElementById('dashboard-content').style.display = 'none';

        const formData = new FormData(document.getElementById('filter-form'));
        const params = new URLSearchParams(formData).toString();

        fetch(`{{ route('bonos-comisiones.data') }}?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            rawDataGlobal = data;
            renderKPIs(data.kpis);
            renderChartVsMeta(data.chart_comisiones_vs_meta);
            renderChartEvolucion(data.chart_evolucion_mensual);
            renderTablaDesglose(data.desglose_empleados);
            renderEsquemasActivos(data.esquemas_activos);
            renderRankingTop(data.ranking_top);
            renderAlertasUmbral(data.alertas_umbral);
            renderPromedioPuesto(data.compensacion_promedio_puesto);

            document.getElementById('loading-overlay').style.display = 'none';
            document.getElementById('dashboard-content').style.display = 'block';
            setTimeout(initTooltips, 150);
        })
        .catch(error => {
            console.error('Error al cargar datos:', error);
            document.getElementById('loading-overlay').style.display = 'none';
            alert('Ocurrió un error al cargar la información. Por favor intente nuevamente.');
        });
    }

    function renderKPIs(kpis) {
        document.getElementById('kpi-total-pagado').textContent = formatCurrency(kpis.total_bonos_comisiones);
        document.getElementById('kpi-sub-comisiones').textContent = formatCurrency(kpis.total_comisiones);
        document.getElementById('kpi-sub-bonos').textContent = formatCurrency(kpis.total_bonos);

        document.getElementById('kpi-cumplimiento-meta').textContent = `${kpis.promedio_cumplimiento_meta}%`;
        document.getElementById('kpi-progress-bar').style.width = `${Math.min(100, kpis.promedio_cumplimiento_meta)}%`;
        document.getElementById('kpi-empleados-count').textContent = `${kpis.total_empleados} Empleados`;

        document.getElementById('kpi-costo-variable').textContent = `${kpis.relacion_costo_variable_utilidad}%`;
        document.getElementById('kpi-costo-nomina').textContent = `${kpis.costo_respecto_nomina}%`;
        document.getElementById('kpi-sub-nomina').textContent = formatCurrency(kpis.total_nomina);
    }

    function renderChartVsMeta(chartData) {
        const ctx = document.getElementById('chartComisionesVsMetaCanvas').getContext('2d');
        if (chartVsMetaInstance) chartVsMetaInstance.destroy();

        chartVsMetaInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Comisión Generada',
                        data: chartData.comisiones,
                        backgroundColor: '#667eea',
                        borderRadius: 6
                    },
                    {
                        label: 'Meta del Periodo',
                        data: chartData.metas,
                        backgroundColor: '#e2e8f0',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    function renderChartEvolucion(chartData) {
        const ctx = document.getElementById('chartEvolucionMensualCanvas').getContext('2d');
        if (chartEvolucionInstance) chartEvolucionInstance.destroy();

        chartEvolucionInstance = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    function renderTablaDesglose(empleados) {
        const tbody = document.getElementById('tbody-desglose');
        tbody.innerHTML = '';
        document.getElementById('badge-total-registros').textContent = `${empleados.length} registros`;

        if (!empleados || empleados.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted">No se encontraron registros para los filtros seleccionados</td></tr>`;
            return;
        }

        empleados.forEach(emp => {
            const badgeClass = emp.perfil_alcanzado === 'Master' ? 'badge-master' : (emp.perfil_alcanzado === 'Senior' ? 'badge-senior' : 'badge-junior');
            const tr = document.createElement('tr');

            const ttNomina = `data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-wallet2'></i> Nómina Base: ${formatCurrency(emp.nomina_base)}</div>Sueldo fijo ordinario pactado por contrato.<div class='tooltip-formula-badge'>Pago Garantizado</div>"`;
            const ttComision = `data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-percent'></i> Comisiones: ${formatCurrency(emp.comisiones_total)}</div>Comisiones acumuladas en Varios, Metal, Liquidados y Créditos.<div class='tooltip-formula-badge'>Comisión Variable</div>"`;
            const ttBono = `data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-trophy-fill'></i> Bono por Meta: ${formatCurrency(emp.bono_meta)}</div>Incentivo por alcanzar el ${emp.porcentaje_cumplimiento_meta}% de su meta ($30,000).<div class='tooltip-formula-badge'>Bono por Cumplimiento</div>"`;
            const ttCompTotal = `data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<div class='tooltip-title'><i class='bi-piggy-bank-fill'></i> Compensación Total</div>Ingreso final percibido por el colaborador.<div class='tooltip-formula-badge'>${formatCurrency(emp.nomina_base)} (Base) + ${formatCurrency(emp.comisiones_total)} (Com) + ${formatCurrency(emp.bono_meta)} (Bono) = ${formatCurrency(emp.compensacion_total)}</div>"`;

            tr.innerHTML = `
                <td class="fw-bold text-dark">${emp.empleado}</td>
                <td><span class="badge bg-light text-dark border">${emp.sucursal}</span></td>
                <td>${emp.perfil_base}</td>
                <td><span class="badge ${badgeClass}">${emp.perfil_alcanzado}</span></td>
                <td class="text-end">${formatCurrency(emp.ventas_total)}</td>
                <td class="text-end">${formatCurrency(emp.utilidad_total)}</td>
                <td class="text-end has-tooltip" ${ttNomina}>${formatCurrency(emp.nomina_base)}</td>
                <td class="text-end text-primary fw-bold has-tooltip" ${ttComision}>${formatCurrency(emp.comisiones_total)}</td>
                <td class="text-end text-success fw-bold has-tooltip" ${ttBono}>${formatCurrency(emp.bono_meta)}</td>
                <td class="text-end fw-bold text-dark has-tooltip" ${ttCompTotal}>${formatCurrency(emp.compensacion_total)}</td>
                <td class="text-center">
                    <span class="badge ${emp.porcentaje_cumplimiento_meta >= 100 ? 'bg-success' : 'bg-secondary'}">
                        ${emp.porcentaje_cumplimiento_meta}%
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderEsquemasActivos(esquemas) {
        const tbody = document.getElementById('tbody-esquemas');
        tbody.innerHTML = '';
        if (!esquemas || esquemas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted">Sin datos</td></tr>`;
            return;
        }
        esquemas.slice(0, 10).forEach(eq => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold">${eq.empleado}</td>
                <td>${eq.puesto}</td>
                <td><span class="badge bg-primary-subtle text-primary border">${eq.perfil_alcanzado}</span></td>
                <td class="small">${eq.esquema_varios}</td>
                <td class="small text-muted">${eq.tasa_metal}</td>
                <td class="small text-muted">${eq.tasa_liquidados}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderRankingTop(ranking) {
        const tbody = document.getElementById('tbody-ranking');
        tbody.innerHTML = '';
        if (!ranking || ranking.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted">Sin datos</td></tr>`;
            return;
        }
        ranking.forEach((rk, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold text-muted">${index + 1}</td>
                <td class="fw-bold text-dark">${rk.empleado}</td>
                <td><small class="text-muted">${rk.sucursal}</small></td>
                <td class="text-end text-success fw-bold">${formatCurrency(rk.comisiones_total)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderAlertasUmbral(alertas) {
        const tbody = document.getElementById('tbody-alertas');
        tbody.innerHTML = '';
        if (!alertas || alertas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted">No hay colaboradores próximos al umbral en este periodo</td></tr>`;
            return;
        }
        alertas.forEach(al => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold text-dark">${al.empleado}</td>
                <td><span class="badge bg-secondary">${al.nivel_actual}</span></td>
                <td><span class="badge bg-warning text-dark">${al.siguiente_nivel}</span></td>
                <td class="text-end">${formatCurrency(al.ventas_actuales)}</td>
                <td class="text-end text-danger fw-semibold">${formatCurrency(al.faltante_monto)}</td>
                <td class="text-center"><span class="badge bg-danger">${al.faltante_porcentaje}%</span></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderPromedioPuesto(puestos) {
        const tbody = document.getElementById('tbody-puestos');
        tbody.innerHTML = '';
        if (!puestos || puestos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-muted">Sin datos</td></tr>`;
            return;
        }
        puestos.forEach(pt => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold text-dark">${pt.puesto}</td>
                <td class="text-center"><span class="badge bg-light text-dark border">${pt.num_empleados}</span></td>
                <td class="text-end">${formatCurrency(pt.nomina_promedio)}</td>
                <td class="text-end text-primary">${formatCurrency(pt.comisiones_promedio)}</td>
                <td class="text-end fw-bold">${formatCurrency(pt.compensacion_promedio_total)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN'
        }).format(amount || 0);
    }

    function initTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                const instance = bootstrap.Tooltip.getInstance(el);
                if (instance) instance.dispose();
                new bootstrap.Tooltip(el, { container: 'body' });
            });
        }
    }

    function exportarExcel() {
        if (!rawDataGlobal || !rawDataGlobal.desglose_empleados) {
            alert('No hay datos disponibles para exportar.');
            return;
        }
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Empleado,Sucursal,Puesto,Nivel,Ventas Totales,Utilidad Bruta,Nomina Base,Comision,Bono Meta,Compensacion Total\n";

        rawDataGlobal.desglose_empleados.forEach(emp => {
            csvContent += `"${emp.empleado}","${emp.sucursal}","${emp.perfil_base}","${emp.perfil_alcanzado}",${emp.ventas_total},${emp.utilidad_total},${emp.nomina_base},${emp.comisiones_total},${emp.bono_meta},${emp.compensacion_total}\n`;
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `bonos_comisiones_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
@endsection
