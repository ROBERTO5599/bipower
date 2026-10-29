@extends('employees.layouts.main')

@section('title', 'Módulo de Caminado | Power BI')

@section('styles')
    <!-- Select2 & DataTables Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.25s ease;
        }
        .icon-shape {
            width: 3.2rem;
            height: 3.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            border-radius: 10px;
        }
        .bg-light-primary { background-color: rgba(79, 70, 229, 0.12); color: #4f46e5; }
        .bg-light-success { background-color: rgba(16, 185, 129, 0.12); color: #10b981; }
        .bg-light-danger { background-color: rgba(239, 68, 68, 0.12); color: #ef4444; }
        .bg-light-warning { background-color: rgba(245, 158, 11, 0.15); color: #d97706; }
        .bg-light-info { background-color: rgba(6, 182, 212, 0.12); color: #0891b2; }
        .bg-light-purple { background-color: rgba(139, 92, 246, 0.12); color: #8b5cf6; }

        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* Pestañas Power BI */
        .nav-tabs-caminado {
            border-bottom: 2px solid #e2e8f0;
            background-color: #ffffff;
            padding: 8px 12px 0 12px;
            border-radius: 12px 12px 0 0;
        }
        .nav-tabs-caminado .nav-link {
            font-weight: 700;
            font-size: 0.85rem;
            color: #64748b;
            border: 1px solid transparent;
            margin-right: 6px;
            border-radius: 8px 8px 0 0;
            padding: 9px 18px;
            transition: all 0.2s ease;
        }
        .nav-tabs-caminado .nav-link:hover {
            color: #4f46e5;
            background-color: #f8fafc;
        }
        .nav-tabs-caminado .nav-link.active {
            color: #4f46e5;
            background-color: #ffffff;
            border-color: #e2e8f0 #e2e8f0 #ffffff #e2e8f0;
            border-bottom: 3px solid #4f46e5;
            box-shadow: 0 -2px 8px rgba(79, 70, 229, 0.08);
        }

        /* Badges de Categoría y Estado */
        .badge-cat-oro { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-cat-varios { background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge-cat-autos { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        .table-caminado { font-size: 0.82rem; }
        .table-caminado th {
            vertical-align: middle !important;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #f8fafc;
            color: #475569;
            padding: 8px 6px !important;
        }
        .table-caminado td {
            vertical-align: middle !important;
            padding: 7px 6px !important;
        }

        /* Ficha Impresión */
        .ficha-documento {
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .firma-box {
            border-top: 1px solid #1e293b;
            width: 220px;
            text-align: center;
            padding-top: 6px;
            font-weight: 600;
            font-size: 0.82rem;
            margin: 30px auto 0 auto;
        }

        @media print {
            body * { visibility: hidden; }
            #seccion-fichas-imprimir, #seccion-fichas-imprimir * { visibility: visible; }
            #seccion-fichas-imprimir { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .ficha-documento { page-break-after: always; border: 1px solid #000; box-shadow: none; }
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-dark fw-bold">Consultando información guardada de Caminados...</h5>
    <p class="text-muted small">Cargando auditorías, prendas revisadas y métricas consolidadas de sobrevalúo...</p>
</div>

<div class="container-fluid p-3 p-md-4" id="dashboard-content" style="display: none;">

    {{-- ENCABEZADO PRINCIPAL --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold rounded-2">
                    <i class="bi bi-hand-index-fill me-1"></i>Power BI Analytics
                </span>
                <h3 class="fw-bold text-dark mb-0">Módulo de Caminado</h3>
            </div>
            <p class="text-muted mb-0 small mt-1">
                Visualización integral de <strong>información guardada</strong>: sesiones de auditoría, prendas evaluadas, detección de sobrevalúo y efectividad por valuador.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-outline-success btn-sm fw-semibold shadow-sm" id="btn-export-csv" title="Descargar CSV con las prendas filtradas">
                <i class="bi bi-file-earmark-spreadsheet-fill me-1 text-success"></i>Exportar CSV
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold shadow-sm" id="btn-refrescar-datos" title="Actualizar datos">
                <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
            </button>
            <button class="btn btn-sm btn-outline-secondary shadow-sm fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#seccion-captura-pos" aria-expanded="false">
                <i class="bi bi-plus-circle me-1"></i>Nueva Auditoría POS
            </button>
        </div>
    </div>

    {{-- SECCIÓN COLAPSABLE SECUNDARIA: HERRAMIENTA DE CAPTURA EN VIVO (POR SI DESEAN AUDITAR OTRA SUCURSAL) --}}
    <div class="collapse mb-4 no-print" id="seccion-captura-pos">
        <div class="card border border-warning-subtle shadow-sm rounded-3" style="background-color: #fffdf5;">
            <div class="card-header bg-warning-subtle py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark small"><i class="bi bi-cloud-arrow-down-fill me-2 text-warning"></i>Herramienta de Captura en Vivo (Conexión Directa al POS Prendario)</span>
                <span class="badge bg-warning text-dark">Auditoría en Directo</span>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-2">
                    Esta sección permite conectarse en tiempo real a la base de datos de una sucursal para extraer contratos del día y guardar una nueva sesión de caminado. 
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Sucursal a Auditar</label>
                        <select class="form-select form-select-sm" id="captura_sucursal_id">
                            <option value="">-- Seleccionar Sucursal --</option>
                            @foreach ($sucursales as $suc)
                                <option value="{{ $suc->id }}" data-id-valora="{{ $suc->id_valora_mas }}">{{ $suc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Fecha Empeños (Inicio / Fin)</label>
                        <input type="date" class="form-control form-control-sm" id="captura_fecha_inicio" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Fecha Revisión</label>
                        <input type="date" class="form-control form-control-sm" id="captura_fecha_revision" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-warning btn-sm fw-bold w-100" id="btn-iniciar-captura-pos">
                            <i class="bi bi-play-circle-fill me-1"></i>Consultar POS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BARRA DE FILTROS POWER BI --}}
    <div class="card shadow-sm border-0 mb-4 rounded-3 no-print">
        <div class="card-body p-3 p-md-4">
            <form id="filter-form" class="row g-3 align-items-end" onsubmit="return false;">
                
                {{-- Sucursal --}}
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Sucursal</label>
                    <select name="sucursal_id" id="filtro_sucursal_id" class="form-select form-select-sm">
                        <option value="all">-- Todas las Sucursales --</option>
                        @foreach ($sucursales as $suc)
                            <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha Inicio --}}
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Revisión Desde</label>
                    <input type="date" name="fecha_inicio" id="filtro_fecha_inicio" value="{{ $fechaInicio }}" class="form-control form-control-sm">
                </div>

                {{-- Fecha Fin --}}
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Revisión Hasta</label>
                    <input type="date" name="fecha_fin" id="filtro_fecha_fin" value="{{ $fechaFin }}" class="form-control form-control-sm">
                </div>

                {{-- Valuador --}}
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Valuador</label>
                    <select name="valuador" id="filtro_valuador" class="form-select form-select-sm">
                        <option value="all">-- Todos los Valuadores --</option>
                        @foreach ($valuadores as $val)
                            <option value="{{ $val }}">{{ $val }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Auditor --}}
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Auditor</label>
                    <select name="auditor_id" id="filtro_auditor_id" class="form-select form-select-sm">
                        <option value="all">-- Todos los Auditores --</option>
                        @foreach ($auditores as $aud)
                            <option value="{{ $aud['id'] }}">{{ $aud['nombre'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Botones de Filtrado --}}
                <div class="col-12 col-sm-6 col-lg-1 text-end">
                    <button type="button" class="btn btn-primary btn-sm w-100 fw-semibold" id="btn-aplicar-filtros">
                        <i class="bi bi-funnel-fill me-1"></i>Filtrar
                    </button>
                </div>

            </form>

            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted fw-semibold me-1">Acceso Rápido:</span>
                    <button type="button" class="btn btn-outline-secondary btn-xs btn-filtro-rapido" data-dias="0">Hoy</button>
                    <button type="button" class="btn btn-outline-secondary btn-xs btn-filtro-rapido" data-dias="30">Últimos 30 días</button>
                    <button type="button" class="btn btn-outline-secondary btn-xs btn-filtro-rapido active" data-dias="180">Últimos 6 meses</button>
                    <button type="button" class="btn btn-outline-secondary btn-xs btn-filtro-rapido" data-dias="all">Todo el Historial</button>
                </div>

                <div class="form-check form-switch mt-2 mt-md-0">
                    <input class="form-check-input" type="checkbox" id="check-solo-sobrevaluo">
                    <label class="form-check-input-label small fw-semibold text-danger cursor-pointer" for="check-solo-sobrevaluo">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Mostrar solo prendas con sobrevalúo
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- TARJETAS KPI EJECUTIVAS (POWER BI CARDS) --}}
    <div class="row g-3 mb-4 no-print">
        
        {{-- Caminados Registrados --}}
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100 card-hover rounded-3" style="background: #ffffff; border-left: 4px solid #4f46e5 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Caminados Guardados</span>
                            <h3 class="fw-bold mb-0 text-dark mt-1" id="kpi-total-caminados">0</h3>
                        </div>
                        <div class="icon-shape bg-light-primary"><i class="bi bi-clock-history"></i></div>
                    </div>
                    <small class="text-primary fw-semibold mt-2 d-block" style="font-size: 0.68rem;">Sesiones registradas</small>
                </div>
            </div>
        </div>

        {{-- Prendas Auditadas --}}
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100 card-hover rounded-3" style="background: #ffffff; border-left: 4px solid #6366f1 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Prendas Auditadas</span>
                            <h3 class="fw-bold mb-0 text-dark mt-1" id="kpi-total-articulos">0</h3>
                        </div>
                        <div class="icon-shape bg-light-purple"><i class="bi bi-box-seam"></i></div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size: 0.68rem;" id="kpi-sub-revisados">0 evaluadas</small>
                </div>
            </div>
        </div>

        {{-- Prendas Sobrevaluadas --}}
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100 card-hover rounded-3" style="background: #ffffff; border-left: 4px solid #ef4444 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Sobrevaluadas</span>
                            <h3 class="fw-bold mb-0 text-danger mt-1" id="kpi-total-sobrevaluados">0</h3>
                        </div>
                        <div class="icon-shape bg-light-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    </div>
                    <small class="text-danger fw-semibold mt-2 d-block" style="font-size: 0.68rem;">Con hallazgo de riesgo</small>
                </div>
            </div>
        </div>

        {{-- Tasa Sobrevalúo --}}
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100 card-hover rounded-3" style="background: #ffffff; border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Tasa de Sobrevalúo</span>
                            <h3 class="fw-bold mb-0 text-warning mt-1" id="kpi-tasa-sobrevaluo">0.0%</h3>
                        </div>
                        <div class="icon-shape bg-light-warning"><i class="bi bi-percent"></i></div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size: 0.68rem;">Incidencia sobre cartera</small>
                </div>
            </div>
        </div>

        {{-- Monto Auditado --}}
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100 card-hover rounded-3" style="background: #ffffff; border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Monto Auditado</span>
                            <h4 class="fw-bold mb-0 text-success mt-1" id="kpi-monto-prestamo">$0.00</h4>
                        </div>
                        <div class="icon-shape bg-light-success"><i class="bi bi-cash-stack"></i></div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size: 0.68rem;">Cartera revisada</small>
                </div>
            </div>
        </div>

        {{-- Monto Sobrevalúo --}}
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100 card-hover rounded-3" style="background: #ffffff; border-left: 4px solid #dc2626 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem;">Monto Sobrevalúo</span>
                            <h4 class="fw-bold mb-0 text-danger mt-1" id="kpi-monto-sobrevaluo">$0.00</h4>
                        </div>
                        <div class="icon-shape bg-light-danger"><i class="bi bi-graph-down-arrow"></i></div>
                    </div>
                    <small class="text-danger fw-semibold mt-2 d-block" style="font-size: 0.68rem;">Riesgo total identificado</small>
                </div>
            </div>
        </div>

    </div>

    {{-- GRÁFICAS ANALÍTICAS POWER BI --}}
    <div class="row g-3 mb-4 no-print">
        
        {{-- Gráfica 1: Top Sucursales Sobrevalúo --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 px-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Top Sucursales con Mayor Sobrevalúo ($ vs Prendas)
                    </h6>
                    <span class="badge bg-light text-secondary border">Métricas Guardadas</span>
                </div>
                <div class="card-body p-3 pt-0">
                    <div style="height: 250px; position: relative;">
                        <canvas id="chartTopSucursales"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Gráfica 2: Estados Físicos y Categorías --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 px-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Estado Físico de Prendas
                    </h6>
                    <span class="badge bg-light text-secondary border">Condición</span>
                </div>
                <div class="card-body p-3 pt-0">
                    <div style="height: 250px; position: relative;">
                        <canvas id="chartEstadoFisico"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- PESTAÑAS DE INFORMACIÓN GUARDADA --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        
        <ul class="nav nav-tabs nav-tabs-caminado no-print" id="tabsCaminadoGuardado" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-sesiones-nav" data-bs-toggle="tab" data-bs-target="#tab-sesiones" type="button" role="tab">
                    <i class="bi bi-journal-check me-1"></i> Sesiones de Caminado
                    <span class="badge bg-primary ms-1" id="badge-count-sesiones">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-prendas-nav" data-bs-toggle="tab" data-bs-target="#tab-prendas" type="button" role="tab">
                    <i class="bi bi-box-seam me-1"></i> Detalle de Prendas Auditadas
                    <span class="badge bg-secondary ms-1" id="badge-count-prendas">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-concentrado-nav" data-bs-toggle="tab" data-bs-target="#tab-concentrado" type="button" role="tab">
                    <i class="bi bi-people-fill me-1"></i> Concentrado por Valuador
                    <span class="badge bg-secondary ms-1" id="badge-count-valuadores">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-danger" id="tab-fichas-nav" data-bs-toggle="tab" data-bs-target="#tab-fichas" type="button" role="tab">
                    <i class="bi bi-file-earmark-text-fill me-1"></i> Fichas de Sobreavalúo
                    <span class="badge bg-danger ms-1" id="badge-count-fichas">0</span>
                </button>
            </li>
        </ul>

        <div class="card-body p-3">
            <div class="tab-content" id="tabsCaminadoContent">

                {{-- PESTAÑA 1: SESIONES DE CAMINADO GUARDADAS --}}
                <div class="tab-pane fade show active" id="tab-sesiones" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm text-center align-middle table-caminado mb-0" id="tabla-sesiones" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th># ID</th>
                                    <th>Fecha Revisión</th>
                                    <th>Fecha Empeños</th>
                                    <th>Sucursal</th>
                                    <th>Auditor</th>
                                    <th>Estatus</th>
                                    <th>Total Artículos</th>
                                    <th>Revisados</th>
                                    <th>Sobrevaluados</th>
                                    <th>Monto Prestado</th>
                                    <th>Monto Sobrevalúo</th>
                                    <th>% Sobrevalúo</th>
                                    <th class="no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-sesiones">
                                <tr><td colspan="13" class="text-center py-4 text-muted">Cargando sesiones...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- PESTAÑA 2: DETALLE DE PRENDAS AUDITADAS (EXPLORADOR MAESTRO) --}}
                <div class="tab-pane fade" id="tab-prendas" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-sm text-center align-middle table-caminado mb-0" id="tabla-prendas" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Caminado #</th>
                                    <th>Sucursal</th>
                                    <th>Fecha</th>
                                    <th>Contrato</th>
                                    <th>Folio / Bolsa</th>
                                    <th>Categoría</th>
                                    <th>Artículo</th>
                                    <th>Descripción Prenda</th>
                                    <th>Valuador</th>
                                    <th>Préstamo Otorgado</th>
                                    <th>Préstamo Real</th>
                                    <th>Estado Físico</th>
                                    <th>Desc. OK</th>
                                    <th>Acc. OK</th>
                                    <th>Sobrevalúo</th>
                                    <th>Monto Sobrevalúo</th>
                                    <th>% Sobrevalúo</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-prendas">
                                <tr><td colspan="18" class="text-center py-4 text-muted">Cargando prendas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- PESTAÑA 3: CONCENTRADO POR VALUADOR --}}
                <div class="tab-pane fade" id="tab-concentrado" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm text-center align-middle table-caminado mb-0" id="tabla-concentrado-valuadores" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th class="text-start ps-3">Valuador</th>
                                    <th>Sucursal</th>
                                    <th>Operaciones</th>
                                    <th>Monto Empeñado</th>
                                    <th>% Participación</th>
                                    <th class="text-success">Desc. Correctas</th>
                                    <th>Efectividad Desc.</th>
                                    <th class="text-danger">Sobrevaluados</th>
                                    <th>Monto Sobrevalúo</th>
                                    <th>% Sobrevalúo</th>
                                    <th>Efectividad Valuando</th>
                                    <th class="bg-light fw-bold text-primary">Efectividad General</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-concentrado-valuadores">
                                <tr><td colspan="12" class="text-center py-4 text-muted">Cargando valuadores...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- PESTAÑA 4: FICHAS DE SOBREAVALÚO (DOCUMENTO OFICIAL IMPRIMIBLE) --}}
                <div class="tab-pane fade" id="tab-fichas" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                        <span class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Actas oficiales de compromiso para cada valuador con prendas sobrevaluadas detectadas en los filtros activos.
                        </span>
                        <button type="button" class="btn btn-outline-dark btn-sm px-3" onclick="window.print()">
                            <i class="bi bi-printer-fill me-1"></i>Imprimir Fichas de Sobreavalúo
                        </button>
                    </div>

                    <div id="seccion-fichas-imprimir">
                        <div id="contenedor-fichas">
                            <div class="alert alert-light text-center py-5 border shadow-sm text-muted">
                                <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                                <span class="fw-bold">No se han registrado prendas con sobrevalúo en el periodo seleccionado.</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- MODAL DE DETALLE DE SESIÓN INDIVIDUAL --}}
<div class="modal fade no-print" id="modal-detalle-sesion" tabindex="-1" aria-labelledby="modalDetalleSesionLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalDetalleSesionLabel">
                    <i class="bi bi-journal-text me-2 text-primary"></i>Detalle de Caminado #<span id="modal-detalle-id"></span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" class="btn btn-outline-success btn-sm" id="btn-descargar-csv-modal">
                        <i class="bi bi-file-earmark-excel-fill text-success me-1"></i>Descargar CSV
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3 bg-light p-2 rounded small">
                    <div class="col-md-3"><strong>Sucursal:</strong> <span id="modal-det-sucursal">-</span></div>
                    <div class="col-md-3"><strong>Fecha Revisión:</strong> <span id="modal-det-fecha-rev">-</span></div>
                    <div class="col-md-3"><strong>Fecha Empeños:</strong> <span id="modal-det-fecha-rec">-</span></div>
                    <div class="col-md-3"><strong>Auditor:</strong> <span id="modal-det-auditor">-</span></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center align-middle table-caminado" id="tabla-modal-detalles" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Contrato</th>
                                <th>Categoría</th>
                                <th>Artículo</th>
                                <th>Descripción</th>
                                <th>Valuador</th>
                                <th>Monto</th>
                                <th>Préstamo Real</th>
                                <th>Estado</th>
                                <th>Desc. OK</th>
                                <th>Acc. OK</th>
                                <th>Sobrevalúo</th>
                                <th>Monto Sobrevalúo</th>
                                <th>% Sobrevalúo</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-modal-detalles">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(document).ready(function() {

    let dtSesiones = null;
    let dtPrendas = null;
    let dtConcentrado = null;
    let dtModalDetalle = null;

    let chartTopSucursales = null;
    let chartEstadoFisico = null;

    let globalPrendasFiltradas = [];

    const dtLang = {
        decimal: "",
        emptyTable: "No hay información registrada en este periodo",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "Mostrando 0 a 0 de 0 registros",
        infoFiltered: "(filtrado de _MAX_ registros en total)",
        lengthMenu: "Mostrar _MENU_ registros",
        loadingRecords: "Cargando...",
        processing: "Procesando...",
        search: "Buscar:",
        zeroRecords: "Sin registros encontrados",
        paginate: {
            first: "Primero",
            last: "Último",
            next: "Siguiente",
            previous: "Anterior"
        }
    };

    function formatMoney(val) {
        return '$' + parseFloat(val || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Inicializar Select2 en filtros
    $('#filtro_sucursal_id, #filtro_valuador, #filtro_auditor_id').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // 1. CARGA PRINCIPAL DE DATOS GUARDADOS DESDE /caminado/data
    function cargarDatosGuardados() {
        $('#loading-overlay').fadeIn(150);

        const params = {
            sucursal_id: $('#filtro_sucursal_id').val(),
            fecha_inicio: $('#filtro_fecha_inicio').val(),
            fecha_fin: $('#filtro_fecha_fin').val(),
            valuador: $('#filtro_valuador').val(),
            auditor_id: $('#filtro_auditor_id').val(),
            solo_sobrevaluo: $('#check-solo-sobrevaluo').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '{{ route("caminado.data") }}',
            method: 'GET',
            data: params,
            success: function(resp) {
                $('#loading-overlay').fadeOut(200);
                $('#dashboard-content').fadeIn(200);

                if (!resp.success) {
                    Swal.fire('Error', resp.message || 'Error al obtener datos', 'error');
                    return;
                }

                // A. Actualizar KPI Cards
                const kpis = resp.kpis;
                $('#kpi-total-caminados').text(kpis.total_caminados);
                $('#kpi-total-articulos').text(kpis.total_articulos);
                $('#kpi-sub-revisados').text(`${kpis.total_revisados} evaluadas`);
                $('#kpi-total-sobrevaluados').text(kpis.total_sobrevaluados);
                $('#kpi-tasa-sobrevaluo').text(kpis.tasa_sobrevaluo + '%');
                $('#kpi-monto-prestamo').text(formatMoney(kpis.monto_total_prestamo));
                $('#kpi-monto-sobrevaluo').text(formatMoney(kpis.monto_total_sobrevaluo));

                $('#badge-count-sesiones').text(resp.sesiones.length);
                $('#badge-count-prendas').text(resp.prendas.length);
                $('#badge-count-valuadores').text(resp.concentrado.length);

                globalPrendasFiltradas = resp.prendas;

                // B. Renderizar Gráficas
                renderGraficaTopSucursales(resp.charts.top_sucursales);
                renderGraficaEstados(resp.charts.estados);

                // C. Renderizar Tablas
                renderTablaSesiones(resp.sesiones);
                renderTablaPrendas(resp.prendas);
                renderTablaConcentrado(resp.concentrado);
                renderFichasSobrevaluo(resp.prendas);
            },
            error: function(xhr) {
                $('#loading-overlay').fadeOut(200);
                $('#dashboard-content').fadeIn(200);
                Swal.fire('Error', 'No se pudieron consultar los datos del caminado', 'error');
            }
        });
    }

    // 2. RENDER GRÁFICA TOP SUCURSALES
    function renderGraficaTopSucursales(topSuc) {
        const ctx = document.getElementById('chartTopSucursales');
        if (!ctx) return;

        if (chartTopSucursales) chartTopSucursales.destroy();

        if (!topSuc || topSuc.length === 0) {
            return;
        }

        const labels = topSuc.map(s => s.sucursal);
        const montos = topSuc.map(s => s.monto_sobrevaluo);
        const prendas = topSuc.map(s => s.sobrevaluados);

        chartTopSucursales = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Monto Sobrevalúo ($)',
                        data: montos,
                        backgroundColor: 'rgba(239, 68, 68, 0.85)',
                        borderColor: '#dc2626',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Prendas Sobrevaluadas',
                        data: prendas,
                        backgroundColor: 'rgba(79, 70, 229, 0.85)',
                        borderColor: '#4338ca',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                if (c.dataset.yAxisID === 'y') return c.dataset.label + ': ' + formatMoney(c.raw);
                                return c.dataset.label + ': ' + c.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear', position: 'left',
                        ticks: { callback: v => '$' + Number(v).toLocaleString('es-MX'), font: { size: 10 } }
                    },
                    y1: {
                        type: 'linear', position: 'right', grid: { drawOnChartArea: false },
                        ticks: { font: { size: 10 } }
                    },
                    x: { ticks: { font: { size: 10 } } }
                }
            }
        });
    }

    // 3. RENDER GRÁFICA ESTADOS FÍSICOS
    function renderGraficaEstados(estados) {
        const ctx = document.getElementById('chartEstadoFisico');
        if (!ctx) return;

        if (chartEstadoFisico) chartEstadoFisico.destroy();

        const labels = Object.keys(estados || {});
        const data = Object.values(estados || {});

        chartEstadoFisico = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        '#10b981', // Excelente
                        '#3b82f6', // Bueno
                        '#f59e0b', // Regular
                        '#ef4444', // Malo
                        '#94a3b8'  // Sin calificar
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // 4. RENDER TABLA SESIONES DE CAMINADO
    function renderTablaSesiones(sesiones) {
        if (dtSesiones) {
            dtSesiones.destroy();
            dtSesiones = null;
        }

        let html = '';
        sesiones.forEach(function(s) {
            const badgeEstatus = s.estatus === 'en_proceso' 
                ? '<span class="badge bg-warning text-dark">En Proceso</span>' 
                : '<span class="badge bg-success">Completado</span>';

            html += `
                <tr>
                    <td class="fw-bold text-primary">#${s.id}</td>
                    <td>${s.fecha_revision}</td>
                    <td>${s.fecha_recepcion}</td>
                    <td class="fw-semibold text-dark text-start ps-3">${s.sucursal}</td>
                    <td class="small text-start ps-2">${s.auditor}</td>
                    <td>${badgeEstatus}</td>
                    <td>${s.total_articulos}</td>
                    <td><span class="badge bg-light text-dark border">${s.total_revisados}</span></td>
                    <td><span class="badge ${s.total_sobrevaluados > 0 ? 'bg-danger' : 'bg-secondary'}">${s.total_sobrevaluados}</span></td>
                    <td class="text-end fw-semibold">${formatMoney(s.monto_prestamo)}</td>
                    <td class="text-end fw-bold text-danger">${formatMoney(s.monto_sobrevaluo)}</td>
                    <td class="fw-bold">${s.pct_sobrevaluo}%</td>
                    <td class="no-print">
                        <button type="button" class="btn btn-outline-info btn-xs btn-ver-sesion" data-id="${s.id}" title="Ver todas las prendas de este caminado">
                            <i class="bi bi-eye me-1"></i>Ver Prendas
                        </button>
                        <a href="/caminado/exportar/${s.id}" class="btn btn-outline-success btn-xs ms-1" title="Descargar Excel / CSV">
                            <i class="bi bi-file-earmark-excel"></i>
                        </a>
                    </td>
                </tr>
            `;
        });

        $('#tbody-sesiones').html(html);

        if (sesiones.length > 0) {
            dtSesiones = $('#tabla-sesiones').DataTable({
                pageLength: 15,
                order: [[0, 'desc']],
                language: dtLang,
                dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>'
            });
        }
    }

    // 5. RENDER TABLA DETALLE DE PRENDAS AUDITADAS
    function renderTablaPrendas(prendas) {
        if (dtPrendas) {
            dtPrendas.destroy();
            dtPrendas = null;
        }

        let html = '';
        prendas.forEach(function(p) {
            const catBadge = p.categoria.includes('METAL') || p.categoria.includes('ORO') ? 'badge-cat-oro' : (p.categoria.includes('AUTO') ? 'badge-cat-autos' : 'badge-cat-varios');
            const descBadge = p.descripcion_correcta === 1 || p.descripcion_correcta === true 
                ? '<span class="text-success fw-bold">SI</span>' 
                : (p.descripcion_correcta === 0 || p.descripcion_correcta === false ? '<span class="text-danger fw-bold">NO</span>' : '-');
            const accBadge = p.accesorios_completos === 1 || p.accesorios_completos === true 
                ? '<span class="text-success fw-bold">SI</span>' 
                : (p.accesorios_completos === 0 || p.accesorios_completos === false ? '<span class="text-danger fw-bold">NO</span>' : '-');
            const sobreBadge = p.sobrevaluo == 1 || p.monto_sobrevaluo > 0 
                ? '<span class="badge bg-danger">SI</span>' 
                : '<span class="badge bg-light text-muted border">NO</span>';

            html += `
                <tr>
                    <td class="text-primary fw-bold">#${p.caminado_id}</td>
                    <td class="fw-semibold small text-start ps-2">${p.sucursal}</td>
                    <td class="small">${p.fecha_revision}</td>
                    <td class="fw-bold">${p.contrato}</td>
                    <td>${p.folio}</td>
                    <td><span class="badge ${catBadge}">${p.categoria}</span></td>
                    <td class="text-start small fw-semibold">${p.articulo}</td>
                    <td class="text-start small" style="max-width: 250px;" title="${p.descripcion}">${p.descripcion}</td>
                    <td class="small fw-semibold text-dark">${p.usuario}</td>
                    <td class="text-end fw-semibold">${formatMoney(p.monto)}</td>
                    <td class="text-end">${formatMoney(p.prestamo_real)}</td>
                    <td><span class="badge bg-light text-dark border">${p.estado_articulo}</span></td>
                    <td>${descBadge}</td>
                    <td>${accBadge}</td>
                    <td>${sobreBadge}</td>
                    <td class="text-end fw-bold text-danger">${p.monto_sobrevaluo > 0 ? formatMoney(p.monto_sobrevaluo) : '$0.00'}</td>
                    <td class="fw-bold">${p.pct_sobrevaluo > 0 ? p.pct_sobrevaluo + '%' : '-'}</td>
                    <td class="small text-start">${p.observaciones || ''}</td>
                </tr>
            `;
        });

        $('#tbody-prendas').html(html);

        if (prendas.length > 0) {
            dtPrendas = $('#tabla-prendas').DataTable({
                pageLength: 25,
                scrollX: true,
                dom: '<"d-flex justify-content-between align-items-center mb-2"<"html5buttons"B>f>rt<"d-flex justify-content-between align-items-center mt-2"lip>',
                buttons: [
                    { extend: 'copy', text: '<i class="bi bi-clipboard"></i> Copiar', className: 'btn btn-outline-secondary btn-sm' },
                    { extend: 'csv', text: '<i class="bi bi-filetype-csv"></i> CSV', className: 'btn btn-outline-secondary btn-sm', title: 'Prendas_Auditadas_Caminado' },
                    { extend: 'excel', text: '<i class="bi bi-file-earmark-excel text-success"></i> Excel', className: 'btn btn-outline-secondary btn-sm', title: 'Prendas_Auditadas_Caminado' },
                    { extend: 'pdf', text: '<i class="bi bi-file-earmark-pdf text-danger"></i> PDF', className: 'btn btn-outline-secondary btn-sm', orientation: 'landscape', pageSize: 'LEGAL' },
                    { extend: 'print', text: '<i class="bi bi-printer"></i> Imprimir', className: 'btn btn-outline-secondary btn-sm' }
                ],
                language: dtLang
            });
        }
    }

    // 6. RENDER CONCENTRADO POR VALUADOR
    function renderTablaConcentrado(concentrado) {
        if (dtConcentrado) {
            dtConcentrado.destroy();
            dtConcentrado = null;
        }

        let html = '';
        concentrado.forEach(function(v) {
            html += `
                <tr>
                    <td class="text-start ps-3 fw-bold text-dark">${v.usuario}</td>
                    <td class="small">${v.sucursal}</td>
                    <td class="fw-semibold">${v.total_operaciones}</td>
                    <td class="text-end fw-semibold">${formatMoney(v.monto_empeno)}</td>
                    <td>${v.pct_participacion}%</td>
                    <td class="text-success fw-bold">${v.desc_correctas}</td>
                    <td>${v.efectividad_desc}%</td>
                    <td class="text-danger fw-bold">${v.sobrevaluados}</td>
                    <td class="text-end fw-bold text-danger">${formatMoney(v.monto_sobrevaluo)}</td>
                    <td>${v.pct_sobrevaluo}%</td>
                    <td>${v.efectividad_valuando}%</td>
                    <td class="fw-bold text-primary">${v.efectividad_general}%</td>
                </tr>
            `;
        });

        $('#tbody-concentrado-valuadores').html(html);

        if (concentrado.length > 0) {
            dtConcentrado = $('#tabla-concentrado-valuadores').DataTable({
                pageLength: 20,
                language: dtLang,
                dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>'
            });
        }
    }

    // 7. RENDER FICHAS DE SOBREAVALÚO (DOCUMENTO OFICIAL IMPRIMIBLE)
    function renderFichasSobrevaluo(prendas) {
        const sobrevaluadas = prendas.filter(p => p.sobrevaluo == 1 || p.monto_sobrevaluo > 0);
        $('#badge-count-fichas').text(sobrevaluadas.length);

        if (sobrevaluadas.length === 0) {
            $('#contenedor-fichas').html(`
                <div class="alert alert-light text-center py-5 border shadow-sm text-muted">
                    <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                    <span class="fw-bold">No se han registrado prendas con sobrevalúo en los filtros seleccionados.</span>
                </div>
            `);
            return;
        }

        // Agrupar por Valuador y Caminado
        const grupos = {};
        sobrevaluadas.forEach(function(item) {
            const key = (item.usuario || 'SIN_VALUADOR') + '_' + item.caminado_id;
            if (!grupos[key]) {
                grupos[key] = {
                    usuario: item.usuario || 'VALUADOR',
                    sucursal: item.sucursal,
                    caminado_id: item.caminado_id,
                    fecha_revision: item.fecha_revision,
                    articulos: []
                };
            }
            grupos[key].articulos.push(item);
        });

        let html = '';
        Object.keys(grupos).forEach(function(k) {
            const g = grupos[k];
            let filas = '';
            let totalMontoSobre = 0;

            g.articulos.forEach(function(a) {
                totalMontoSobre += parseFloat(a.monto_sobrevaluo || 0);
                filas += `
                    <tr>
                        <td class="fw-bold">${a.contrato}</td>
                        <td>${a.folio}</td>
                        <td class="text-start">${a.articulo} - ${a.descripcion}</td>
                        <td>${a.estado_articulo}</td>
                        <td class="text-end">${formatMoney(a.monto)}</td>
                        <td class="text-end">${formatMoney(a.prestamo_real)}</td>
                        <td class="text-end fw-bold text-danger">${formatMoney(a.monto_sobrevaluo)}</td>
                        <td class="fw-bold">${a.pct_sobrevaluo}%</td>
                        <td class="text-start small">${a.observaciones || ''}</td>
                    </tr>
                `;
            });

            html += `
                <div class="ficha-documento mb-4">
                    <div class="text-center border-bottom pb-2 mb-3">
                        <h5 class="fw-bold text-danger mb-0 text-uppercase">ACTA DE COMPROMISO Y NOTIFICACIÓN DE SOBREAVALÚO</h5>
                        <small class="text-muted">Caminado de Auditoría #${g.caminado_id} | Sucursal ${g.sucursal} | Fecha: ${g.fecha_revision}</small>
                    </div>

                    <p class="small text-muted mb-3" style="text-align: justify;">
                        Por medio de la presente, se hace constar que tras la revisión física y documental efectuada a las prendas empeñadas en la sucursal <strong>${g.sucursal}</strong> asignadas al valuador <strong>${g.usuario}</strong>, se detectaron discrepancias entre el préstamo otorgado y el avalúo oficial permitido, generándose el siguiente dictamen de sobrevalúo:
                    </p>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm text-center align-middle" style="font-size: 0.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Contrato</th>
                                    <th>Folio</th>
                                    <th>Prenda</th>
                                    <th>Estado</th>
                                    <th>Préstamo Otorgado</th>
                                    <th>Préstamo Real</th>
                                    <th class="text-danger">Sobrevalúo</th>
                                    <th>% Sobrevalúo</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${filas}
                            </tbody>
                            <tfoot class="table-secondary fw-bold">
                                <tr>
                                    <td colspan="6" class="text-end pe-3">TOTAL SOBREAVALÚO DETECTADO:</td>
                                    <td class="text-end text-danger">${formatMoney(totalMontoSobre)}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="row mt-4 pt-3">
                        <div class="col-6 text-center">
                            <div class="firma-box">
                                ${g.usuario}<br>
                                <small class="text-muted fw-normal">Valuador Responsable</small>
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="firma-box">
                                Auditor de Caminado<br>
                                <small class="text-muted fw-normal">Auditoría Interna / Regional</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#contenedor-fichas').html(html);
    }

    // 8. EVENTO CLICK EN "VER PRENDAS" DESDE SESIONES
    $(document).on('click', '.btn-ver-sesion', function() {
        const id = $(this).data('id');
        
        // Cargar modal con el detalle específico de ese caminado
        $('#modal-detalle-id').text(id);
        $('#btn-descargar-csv-modal').attr('href', `/caminado/exportar/${id}`);

        $.ajax({
            url: `/caminado/historial/${id}`,
            method: 'GET',
            success: function(resp) {
                if (!resp.success || !resp.caminado) return;

                const c = resp.caminado;
                $('#modal-det-sucursal').text(c.sucursal ? c.sucursal.nombre : 'N/A');
                $('#modal-det-fecha-rev').text(c.fecha_revision || '-');
                $('#modal-det-fecha-rec').text(c.fecha_recepcion || '-');
                $('#modal-det-auditor').text(c.usuario ? (c.usuario.nombre_completo || c.usuario.nick_name) : 'N/A');

                let rows = '';
                (c.detalles || []).forEach(function(d) {
                    const descBadge = d.descripcion_correcta === 1 || d.descripcion_correcta === true ? '<span class="text-success fw-bold">SI</span>' : '<span class="text-danger fw-bold">NO</span>';
                    const accBadge = d.accesorios_completos === 1 || d.accesorios_completos === true ? '<span class="text-success fw-bold">SI</span>' : '<span class="text-danger fw-bold">NO</span>';
                    const sobreBadge = d.sobrevaluo == 1 || d.monto_sobrevaluo > 0 ? '<span class="badge bg-danger">SI</span>' : '<span class="badge bg-secondary">NO</span>';

                    rows += `
                        <tr>
                            <td>${d.folio || '-'}</td>
                            <td class="fw-bold">${d.contrato || '-'}</td>
                            <td>${d.categoria || 'VARIOS'}</td>
                            <td class="fw-semibold text-start">${d.articulo || '-'}</td>
                            <td class="small text-start" style="max-width: 250px;">${d.descripcion || '-'}</td>
                            <td>${d.usuario || '-'}</td>
                            <td class="text-end">${formatMoney(d.monto)}</td>
                            <td class="text-end">${formatMoney(d.prestamo_real)}</td>
                            <td><span class="badge bg-light text-dark border">${d.estado_articulo || '-'}</span></td>
                            <td>${descBadge}</td>
                            <td>${accBadge}</td>
                            <td>${sobreBadge}</td>
                            <td class="text-end fw-bold text-danger">${formatMoney(d.monto_sobrevaluo)}</td>
                            <td>${parseFloat(d.pct_sobrevaluo || 0).toFixed(1)}%</td>
                            <td class="small text-start">${d.observaciones || ''}</td>
                        </tr>
                    `;
                });

                if (dtModalDetalle) {
                    dtModalDetalle.destroy();
                    dtModalDetalle = null;
                }

                $('#tbody-modal-detalles').html(rows);

                $('#modal-detalle-sesion').modal('show');

                setTimeout(function() {
                    dtModalDetalle = $('#tabla-modal-detalles').DataTable({
                        pageLength: 20,
                        scrollX: true,
                        language: dtLang
                    });
                }, 300);
            }
        });
    });

    // 9. FILTROS RÁPIDOS DE FECHA
    $('.btn-filtro-rapido').on('click', function() {
        $('.btn-filtro-rapido').removeClass('active');
        $(this).addClass('active');

        const dias = $(this).data('dias');
        const hoy = new Date();
        const hoyStr = hoy.toISOString().split('T')[0];

        if (dias === 'all') {
            $('#filtro_fecha_inicio').val('2025-01-01');
            $('#filtro_fecha_fin').val(hoyStr);
        } else if (dias === 0) {
            $('#filtro_fecha_inicio').val(hoyStr);
            $('#filtro_fecha_fin').val(hoyStr);
        } else {
            const fechaDesde = new Date();
            fechaDesde.setDate(hoy.getDate() - parseInt(dias));
            $('#filtro_fecha_inicio').val(fechaDesde.toISOString().split('T')[0]);
            $('#filtro_fecha_fin').val(hoyStr);
        }

        cargarDatosGuardados();
    });

    // 10. APLICAR FILTROS
    $('#btn-aplicar-filtros').on('click', function() {
        cargarDatosGuardados();
    });

    $('#check-solo-sobrevaluo').on('change', function() {
        cargarDatosGuardados();
    });

    $('#btn-refrescar-datos').on('click', function() {
        cargarDatosGuardados();
    });

    // 11. EXPORTAR CSV GLOBAL DE PRENDAS
    $('#btn-export-csv').on('click', function() {
        if (!globalPrendasFiltradas || globalPrendasFiltradas.length === 0) {
            Swal.fire('Atención', 'No hay prendas filtradas para exportar', 'warning');
            return;
        }

        let csv = '\uFEFF'; // BOM UTF-8
        csv += 'Caminado ID,Sucursal,Fecha Revisión,Contrato,Folio/Bolsa,Categoría,Artículo,Descripción,Valuador,Préstamo Otorgado,Préstamo Real,Estado Físico,Desc Correcta,Acc Comletos,Sobrevalúo,Monto Sobrevalúo,% Sobrevalúo,Observaciones\n';

        globalPrendasFiltradas.forEach(function(p) {
            const descOk = p.descripcion_correcta === 1 ? 'SI' : (p.descripcion_correcta === 0 ? 'NO' : '-');
            const accOk = p.accesorios_completos === 1 ? 'SI' : (p.accesorios_completos === 0 ? 'NO' : '-');
            const sobre = p.sobrevaluo == 1 ? 'SI' : 'NO';

            const cleanDesc = (p.descripcion || '').replace(/[\r\n",]/g, ' ');
            const cleanObs = (p.observaciones || '').replace(/[\r\n",]/g, ' ');

            csv += `"${p.caminado_id}","${p.sucursal}","${p.fecha_revision}","${p.contrato}","${p.folio}","${p.categoria}","${p.articulo}","${cleanDesc}","${p.usuario}",${p.monto},${p.prestamo_real},"${p.estado_articulo}","${descOk}","${accOk}","${sobre}",${p.monto_sobrevaluo},${p.pct_sobrevaluo},"${cleanObs}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Caminado_Prendas_Guardadas_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    // 12. HERRAMIENTA POS PRENDARIO (CAPTURA SECUNDARIA)
    $('#btn-iniciar-captura-pos').on('click', function() {
        const sucId = $('#captura_sucursal_id').val();
        const fIni = $('#captura_fecha_inicio').val();
        const fRev = $('#captura_fecha_revision').val();

        if (!sucId) {
            Swal.fire('Atención', 'Seleccione la sucursal para consultar', 'warning');
            return;
        }

        Swal.fire({
            title: 'Consultando base prendaria...',
            text: 'Conectando a sistema_prendario de la sucursal',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ route("caminado.articulos") }}',
            method: 'GET',
            data: { sucursal_id: sucId, fecha_recepcion_inicio: fIni },
            success: function(resp) {
                Swal.close();
                if (!resp.success || !resp.articulos) {
                    Swal.fire('Error', resp.message || 'No se obtuvieron artículos', 'error');
                    return;
                }

                Swal.fire({
                    icon: 'info',
                    title: `${resp.total} artículos encontrados`,
                    text: `La sucursal ${resp.sucursal_nombre} tiene ${resp.total} prendas empeñadas. Puede gestionarlas desde la Intranet o guardarlas como nuevo caminado.`
                });
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar a la base prendaria de la sucursal', 'error');
            }
        });
    });

    // Cargar automáticamente datos al iniciar
    cargarDatosGuardados();

});
</script>
@endsection