@extends('employees.layouts.main')

@section('title', 'Crédito Externo (Atrato)')

@section('styles')
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
            border-radius: 50%;
        }
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

        /* Estilos para Tarjetas de Zona */
        .zona-kpi-card {
            border-radius: 14px;
            background: #fff;
            padding: 16px 16px 14px 16px;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            text-decoration: none !important;
        }

        .zona-kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.09);
        }

        .zona-kpi-card.is-active {
            box-shadow: 0 8px 24px rgba(80, 20, 145, 0.16);
            border-width: 2px !important;
        }

        .zona-kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .zona-kpi-title {
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 2px;
            display: block;
        }

        .zona-kpi-monto {
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .zona-kpi-bar {
            height: 5px;
            border-radius: 3px;
            background: #f1f5f9;
            overflow: hidden;
            margin-top: 6px;
        }

        .zona-kpi-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        /* Badges de Tabla */
        .badge-plazo {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
        }

        .badge-metodo {
            background-color: #f3e8ff;
            color: #6b21a8;
            border: 1px solid #d8b4fe;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
        }

        .badge-evidence {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .table-detailed th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            background-color: #f8fafc;
            color: #475569;
            border-top: none;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-detailed td {
            vertical-align: middle;
            font-size: 0.83rem;
        }
    </style>
@endsection

@section('content')

<!-- Overlay de Carga -->
<div id="loading-overlay">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <div class="mt-3 fw-bold text-dark fs-5">Cargando información de Crédito Externo (Atrato)...</div>
    <div class="text-muted small">Consultando colocaciones, sucursales y métricas de financiamiento</div>
</div>

<div class="container-fluid py-4" id="dashboard-content" style="display: none;">

    <!-- Encabezado Principal -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold rounded-2">
                    <i class="bi bi-wallet2 me-1"></i>Financiamiento POS
                </span>
                <h3 class="fw-bold text-dark mb-0">Crédito Externo (Atrato)</h3>
            </div>
            <p class="text-muted mb-0 small mt-1">
                Monitoreo analítico y gerencial de ventas a crédito financiadas a través de la plataforma externa <strong>Atrato Pago</strong>.
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

    <!-- Filtros de Consulta -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form id="filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha Inicio</label>
                    <input type="date" class="form-control form-control-sm" id="fecha_inicio" name="fecha_inicio" value="{{ $fechaInicio }}">
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha Fin</label>
                    <input type="date" class="form-control form-control-sm" id="fecha_fin" name="fecha_fin" value="{{ $fechaFin }}">
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Zona / Razón Social</label>
                    <select class="form-select form-select-sm" id="zona" name="zona">
                        <option value="">-- Todas las Zonas --</option>
                        @foreach($zonas as $z)
                            <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Sucursal</label>
                    <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales as $s)
                            <option value="{{ $s->id }}" data-zona="{{ $s->zona }}">{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Plazo / Periodicidad</label>
                    <select class="form-select form-select-sm" id="plazo" name="plazo">
                        <option value="">-- Todos los Plazos --</option>
                        @foreach($plazos as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-semibold shadow-sm">
                        <i class="bi bi-funnel-fill me-1"></i>Filtrar
                    </button>
                    <button type="button" class="btn btn-light border btn-sm text-secondary" id="btn-reset-filters" title="Limpiar Filtros">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
                    <span class="small text-muted align-self-center me-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Atajos rápidos:</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="btn-quick-mes" style="font-size: 0.78rem;">Mes Actual</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="btn-quick-mes-anterior" style="font-size: 0.78rem;">Mes Anterior</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="btn-quick-historico" style="font-size: 0.78rem;">Todo el Histórico</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alerta Informativa cuando no hay créditos en el periodo actual -->
    <div id="alerta-sin-creditos" class="alert alert-warning border-warning-subtle shadow-sm rounded-3 py-2 px-3 mb-4" style="display: none;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle-fill fs-5 text-warning"></i>
                <span class="small text-dark">
                    No se han registrado créditos de Atrato en el periodo seleccionado (<strong id="alerta-periodo-nombre">este mes</strong>).
                    Hay un acumulado histórico registrado en <strong>Octubre 2026</strong> por <strong id="alerta-monto-historico">$21,448.00</strong> (4 créditos).
                </span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-warning btn-sm py-1 px-2 fw-bold text-dark shadow-sm" id="btn-alerta-ver-historico" style="font-size: 0.78rem;">
                    <i class="bi bi-clock-history me-1"></i>Ver Todo el Histórico
                </button>
                <button type="button" class="btn btn-outline-dark btn-sm py-1 px-2 fw-semibold" id="btn-alerta-ver-octubre" style="font-size: 0.78rem;">
                    Ver Octubre 2026
                </button>
            </div>
        </div>
    </div>

    <!-- ========== TARJETAS DE ZONAS (INDICADORES POR ZONA DEL PERIODO) ========== -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-geo-alt-fill text-primary me-1"></i>Ingresos Totales por Zona
                </h5>
                <small class="text-muted" id="subtitulo-zonas">Métricas del periodo seleccionado • Haz clic en una tarjeta de zona para filtrar el dashboard y sus sucursales</small>
            </div>
            <div id="zona-badge-indicator" style="display: none;">
                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 fw-semibold" id="btn-clear-zona" style="font-size: 0.78rem;">
                    <i class="bi bi-x-circle me-1"></i>Quitar filtro de zona
                </button>
            </div>
        </div>

        <div class="row g-3" id="container-zonas-cards">
            <!-- 1. Total General -->
            <div class="col-12 col-md-6 col-xl">
                <div class="zona-kpi-card is-active" id="card-zona-total" data-zona-id="" style="border: 2px solid #501491; background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);">
                    <div>
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="zona-kpi-icon" style="background: rgba(80, 20, 145, 0.12); color: #501491;">
                                <i class="bi bi-building"></i>
                            </div>
                            <span class="badge bg-primary text-white font-weight-bold" id="badge-total-estado" style="background-color: #501491 !important; font-size: 10px;">
                                <i class="bi bi-check-lg me-1"></i>Todo
                            </span>
                        </div>
                        <span class="zona-kpi-title" style="color: #501491;">Total General</span>
                        <div class="text-muted text-truncate fw-semibold mb-1 small">Todas las Zonas</div>
                        <h4 class="zona-kpi-monto mb-0" id="zona-total-monto" style="color: #321064;">$0.00</h4>
                    </div>
                    <div>
                        <div class="zona-kpi-bar">
                            <div class="zona-kpi-bar-fill" style="width: 100%; background: #501491;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <small class="text-muted fw-bold" id="zona-total-creditos"><i class="bi bi-file-earmark-text me-1"></i>0 créditos</small>
                            <small class="fw-bold" style="color: #501491;">Ver todo</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Zona Gapos -->
            <div class="col-12 col-md-6 col-xl">
                <div class="zona-kpi-card" id="card-zona-7" data-zona-id="7" style="border: 1px solid #e2e8f0; background: #ffffff;">
                    <div>
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="zona-kpi-icon" style="background: rgba(79, 70, 229, 0.12); color: #4f46e5;">
                                <i class="bi bi-building"></i>
                            </div>
                            <span class="badge bg-light text-muted border font-weight-bold" id="badge-pct-7" style="font-size: 10px;">0%</span>
                        </div>
                        <span class="zona-kpi-title" style="color: #4f46e5;">Zona Gapos</span>
                        <div class="text-muted text-truncate fw-semibold mb-1 small">GAPOS VM</div>
                        <h4 class="zona-kpi-monto mb-0" id="monto-zona-7" style="color: #4f46e5;">$0.00</h4>
                    </div>
                    <div>
                        <div class="zona-kpi-bar">
                            <div class="zona-kpi-bar-fill" id="bar-zona-7" style="width: 0%; background: #4f46e5;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <small class="text-muted fw-bold" id="creditos-zona-7"><i class="bi bi-file-earmark-text me-1"></i>0 créds.</small>
                            <small class="fw-bold" style="color: #4f46e5;">Filtrar →</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Zona Manuel -->
            <div class="col-12 col-md-6 col-xl">
                <div class="zona-kpi-card" id="card-zona-4" data-zona-id="4" style="border: 1px solid #e2e8f0; background: #ffffff;">
                    <div>
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="zona-kpi-icon" style="background: rgba(2, 132, 199, 0.12); color: #0284c7;">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <span class="badge bg-light text-muted border font-weight-bold" id="badge-pct-4" style="font-size: 10px;">0%</span>
                        </div>
                        <span class="zona-kpi-title" style="color: #0284c7;">Zona Manuel</span>
                        <div class="text-muted text-truncate fw-semibold mb-1 small">José Manuel G.P.</div>
                        <h4 class="zona-kpi-monto mb-0" id="monto-zona-4" style="color: #0284c7;">$0.00</h4>
                    </div>
                    <div>
                        <div class="zona-kpi-bar">
                            <div class="zona-kpi-bar-fill" id="bar-zona-4" style="width: 0%; background: #0284c7;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <small class="text-muted fw-bold" id="creditos-zona-4"><i class="bi bi-file-earmark-text me-1"></i>0 créds.</small>
                            <small class="fw-bold" style="color: #0284c7;">Filtrar →</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Zona Héctor -->
            <div class="col-12 col-md-6 col-xl">
                <div class="zona-kpi-card" id="card-zona-1" data-zona-id="1" style="border: 1px solid #e2e8f0; background: #ffffff;">
                    <div>
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="zona-kpi-icon" style="background: rgba(5, 150, 105, 0.12); color: #059669;">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <span class="badge bg-light text-muted border font-weight-bold" id="badge-pct-1" style="font-size: 10px;">0%</span>
                        </div>
                        <span class="zona-kpi-title" style="color: #059669;">Zona Héctor</span>
                        <div class="text-muted text-truncate fw-semibold mb-1 small">Héctor Eduardo G.P.</div>
                        <h4 class="zona-kpi-monto mb-0" id="monto-zona-1" style="color: #059669;">$0.00</h4>
                    </div>
                    <div>
                        <div class="zona-kpi-bar">
                            <div class="zona-kpi-bar-fill" id="bar-zona-1" style="width: 0%; background: #059669;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <small class="text-muted fw-bold" id="creditos-zona-1"><i class="bi bi-file-earmark-text me-1"></i>0 créds.</small>
                            <small class="fw-bold" style="color: #059669;">Filtrar →</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Zona Yeni -->
            <div class="col-12 col-md-6 col-xl">
                <div class="zona-kpi-card" id="card-zona-6" data-zona-id="6" style="border: 1px solid #e2e8f0; background: #ffffff;">
                    <div>
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="zona-kpi-icon" style="background: rgba(217, 119, 6, 0.12); color: #d97706;">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <span class="badge bg-light text-muted border font-weight-bold" id="badge-pct-6" style="font-size: 10px;">0%</span>
                        </div>
                        <span class="zona-kpi-title" style="color: #d97706;">Zona Yeni</span>
                        <div class="text-muted text-truncate fw-semibold mb-1 small">Yeni Posadas N.</div>
                        <h4 class="zona-kpi-monto mb-0" id="monto-zona-6" style="color: #d97706;">$0.00</h4>
                    </div>
                    <div>
                        <div class="zona-kpi-bar">
                            <div class="zona-kpi-bar-fill" id="bar-zona-6" style="width: 0%; background: #d97706;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <small class="text-muted fw-bold" id="creditos-zona-6"><i class="bi bi-file-earmark-text me-1"></i>0 créds.</small>
                            <small class="fw-bold" style="color: #d97706;">Filtrar →</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== KPIS DEL FILTRO / PERIODO ACTUAL ========== -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small" style="letter-spacing: 0.5px;">
            <i class="bi bi-sliders me-1"></i>Métricas del Filtro Actual
        </h6>
        <span class="badge bg-primary-subtle text-primary fw-semibold" id="label-conteo-creditos">0 créditos</span>
    </div>

    <div class="row g-3 mb-4">
        <!-- 1. Créditos Colocados -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100 card-hover">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape bg-primary-subtle text-primary me-3 flex-shrink-0">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Créditos Colocados</span>
                        <h4 class="fw-bold text-dark mb-0" id="kpi-total-creditos">0</h4>
                        <small class="text-muted">Total solicitudes aprobadas</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Monto Financiado (Atrato) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100 card-hover">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape bg-success-subtle text-success me-3 flex-shrink-0">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Monto Financiado</span>
                        <h4 class="fw-bold text-success mb-0" id="kpi-monto-total">$0.00</h4>
                        <small class="text-muted">Ticket Prom: <strong id="kpi-ticket-promedio">$0.00</strong></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Precio Venta vs Costo Base -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100 card-hover">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape bg-info-subtle text-info me-3 flex-shrink-0">
                        <i class="bi bi-tag-fill"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Precio de Venta</span>
                        <h4 class="fw-bold text-dark mb-0" id="kpi-precio-venta">$0.00</h4>
                        <small class="text-muted">Costo Base: <span id="kpi-precio-prestamo">$0.00</span></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Margen Financiero -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100 card-hover">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape bg-warning-subtle text-warning me-3 flex-shrink-0">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Margen Comercial</span>
                        <h4 class="fw-bold text-dark mb-0" id="kpi-margen-monto">$0.00</h4>
                        <small class="text-success fw-bold" id="kpi-margen-pct">0% de margen</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== GRÁFICAS ANALÍTICAS ========== -->
    <div class="row g-3 mb-4">
        <!-- Evolución Temporal Día por Día -->
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Evolución Temporal de Colocación</h6>
                        <small class="text-muted">Créditos de Atrato otorgados a lo largo del periodo seleccionado</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary active btn-metric" data-metric="monto">Monto ($)</button>
                        <button type="button" class="btn btn-outline-primary btn-metric" data-metric="creditos">Créditos (#)</button>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 300px;">
                        <canvas id="chartEvolucion"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribución por Zona -->
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Participación por Zona</h6>
                    <small class="text-muted">Distribución del monto financiado</small>
                </div>
                <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                    <div style="height: 200px;" class="position-relative">
                        <canvas id="chartZonas"></canvas>
                    </div>
                    <div id="chartZonas-legend" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Distribución por Plazo / Periodicidad -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Distribución por Plazo</h6>
                    <small class="text-muted">Preferencia de plazos de los clientes</small>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 200px;" class="position-relative">
                        <canvas id="chartPlazos"></canvas>
                    </div>
                    <div id="chartPlazos-legend" class="mt-3"></div>
                </div>
            </div>
        </div>

        <!-- Top Sucursales -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Top Sucursales</h6>
                    <small class="text-muted">Sucursales con mayor monto financiado</small>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 250px;">
                        <canvas id="chartSucursales"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Colaboradores -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">Top Asesores / Vendedores</h6>
                    <small class="text-muted">Líderes en colocación de Crédito Atrato</small>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 250px;">
                        <canvas id="chartColaboradores"></canvas>
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
                    <h5 class="fw-bold text-dark mb-0">Detalle de Créditos Atrato</h5>
                    <small class="text-muted">Listado pormenorizado de operaciones y financiamientos registrados</small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="tabla-search" class="form-control border-start-0" placeholder="Buscar folio, comprador, asesor...">
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
                <table class="table table-hover table-detailed mb-0" id="tabla-creditos">
                    <thead>
                        <tr>
                            <th class="ps-4">Folio</th>
                            <th>Fecha</th>
                            <th>Sucursal</th>
                            <th>Zona</th>
                            <th>Asesor</th>
                            <th>Comprador / Cliente</th>
                            <th>Artículo</th>
                            <th class="text-end">Costo Base</th>
                            <th class="text-end">Precio Venta</th>
                            <th class="text-end">Monto Crédito</th>
                            <th class="text-center">Plazo</th>
                            <th class="text-center pe-4">Método</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-body">
                        <tr>
                            <td colspan="12" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Cargando información...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
            <small class="text-muted fw-semibold" id="tabla-info">Mostrando 0 de 0 registros</small>
            <div class="btn-group btn-group-sm" id="tabla-pagination">
                <button type="button" class="btn btn-outline-secondary" id="btn-prev-page" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
                <button type="button" class="btn btn-outline-secondary" id="btn-next-page" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
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
        let chartZonasInstance = null;
        let chartPlazosInstance = null;
        let chartSucursalesInstance = null;
        let chartColaboradoresInstance = null;
        let currentChartMetric = 'monto'; // 'monto' o 'creditos'

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
        const btnResetFilters = document.getElementById('btn-reset-filters');
        const btnQuickMes = document.getElementById('btn-quick-mes');
        const btnQuickMesAnt = document.getElementById('btn-quick-mes-anterior');
        const btnQuickHist = document.getElementById('btn-quick-historico');
        const btnClearZona = document.getElementById('btn-clear-zona');
        const zonaSelect = document.getElementById('zona');
        const sucursalSelect = document.getElementById('sucursal_id');

        // Opciones originales del selector de sucursales para filtrado dinámico por zona
        const allSucursalOptions = Array.from(sucursalSelect.querySelectorAll('option'));

        function updateSucursalesDropdown(selectedZona) {
            const currentSelected = sucursalSelect.value;
            let isValid = false;

            sucursalSelect.innerHTML = '';

            allSucursalOptions.forEach(opt => {
                if (!opt.value) {
                    opt.textContent = selectedZona ? '-- Todas las Sucursales de la Zona --' : '-- Todas las Sucursales --';
                    sucursalSelect.appendChild(opt);
                    return;
                }

                const optZona = opt.getAttribute('data-zona') || '';
                if (!selectedZona || String(optZona) === String(selectedZona)) {
                    sucursalSelect.appendChild(opt);
                    if (opt.value === currentSelected) {
                        isValid = true;
                    }
                }
            });

            if (isValid) {
                sucursalSelect.value = currentSelected;
            } else {
                sucursalSelect.value = '';
            }
        }

        // Evento de cambio en el selector de Zona -> filtra las sucursales inmediatamente
        zonaSelect.addEventListener('change', function () {
            updateSucursalesDropdown(this.value);
        });

        // Toggle Métricas de Gráfica de Evolución
        document.querySelectorAll('.btn-metric').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.btn-metric').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentChartMetric = this.getAttribute('data-metric');
                renderEvolucionChart();
            });
        });

        // Preset Mes Actual
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

        // Preset Mes Anterior (Octubre 2026 donde se ubica el acumulado)
        if (btnQuickMesAnt) {
            btnQuickMesAnt.addEventListener('click', function () {
                document.getElementById('fecha_inicio').value = '2026-10-01';
                document.getElementById('fecha_fin').value = '2026-10-31';
                fetchData();
            });
        }

        // Preset Todo el Histórico
        if (btnQuickHist) {
            btnQuickHist.addEventListener('click', function () {
                document.getElementById('fecha_inicio').value = '';
                document.getElementById('fecha_fin').value = '';
                fetchData();
            });
        }

        // Botones dentro de la alerta de 0 créditos
        const btnAlertaHist = document.getElementById('btn-alerta-ver-historico');
        if (btnAlertaHist) {
            btnAlertaHist.addEventListener('click', function () {
                document.getElementById('fecha_inicio').value = '';
                document.getElementById('fecha_fin').value = '';
                fetchData();
            });
        }
        const btnAlertaOct = document.getElementById('btn-alerta-ver-octubre');
        if (btnAlertaOct) {
            btnAlertaOct.addEventListener('click', function () {
                document.getElementById('fecha_inicio').value = '2026-10-01';
                document.getElementById('fecha_fin').value = '2026-10-31';
                fetchData();
            });
        }

        // Limpiar Filtros
        if (btnResetFilters) {
            btnResetFilters.addEventListener('click', function () {
                const now = new Date();
                const y = now.getFullYear();
                const m = String(now.getMonth() + 1).padStart(2, '0');
                document.getElementById('fecha_inicio').value = `${y}-${m}-01`;
                document.getElementById('fecha_fin').value = now.toISOString().split('T')[0];
                document.getElementById('zona').value = '';
                updateSucursalesDropdown('');
                document.getElementById('sucursal_id').value = '';
                document.getElementById('plazo').value = '';
                fetchData();
            });
        }

        // Quitar Filtro de Zona
        if (btnClearZona) {
            btnClearZona.addEventListener('click', function () {
                zonaSelect.value = '';
                updateSucursalesDropdown('');
                fetchData();
            });
        }

        // Clic en tarjetas de Zona para filtrar rápidamente y actualizar selector de sucursales
        document.querySelectorAll('.zona-kpi-card').forEach(card => {
            card.addEventListener('click', function (e) {
                e.preventDefault();
                const targetZona = this.getAttribute('data-zona-id') || '';
                zonaSelect.value = targetZona;
                updateSucursalesDropdown(targetZona);
                fetchData();
            });
        });

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

        btnExportCsv.addEventListener('click', exportToCsv);

        // Petición Principal AJAX
        function fetchData() {
            loadingOverlay.style.display = 'flex';

            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);

            fetch(`{{ route('credito-externo.data') }}?${params.toString()}`, { credentials: 'same-origin' })
                .then(res => {
                    if (!res.ok) throw new Error('Error al consultar datos');
                    return res.json();
                })
                .then(data => {
                    rawData = data;
                    renderDashboard();
                    loadingOverlay.style.display = 'none';
                    dashboardContent.style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    alert('Ocurrió un error al cargar la información de Crédito Externo (Atrato).');
                    loadingOverlay.style.display = 'none';
                });
        }

        function renderDashboard() {
            if (!rawData) return;

            // Actualizar Badge de Periodo
            const fIni = document.getElementById('fecha_inicio').value;
            const fFin = document.getElementById('fecha_fin').value;
            const badgeTexto = document.getElementById('badge-periodo-texto');
            if (fIni && fFin) {
                badgeTexto.textContent = `${fIni} al ${fFin}`;
            } else if (fIni) {
                badgeTexto.textContent = `Desde ${fIni}`;
            } else if (fFin) {
                badgeTexto.textContent = `Hasta ${fFin}`;
            } else {
                badgeTexto.textContent = 'Histórico Completo';
            }

            // Alerta Informativa si el periodo actual no tiene colocaciones registradas
            const alertaEl = document.getElementById('alerta-sin-creditos');
            const totalCredsPeriodo = (rawData.metricas_periodo && rawData.metricas_periodo.total_creditos) || 0;
            const histMonto = (rawData.kpi_total_general && rawData.kpi_total_general.total_historico_monto) || 0;
            const histCreds = (rawData.kpi_total_general && rawData.kpi_total_general.total_historico_creds) || 0;

            if (totalCredsPeriodo === 0 && histCreds > 0) {
                alertaEl.style.display = 'block';
                document.getElementById('alerta-periodo-nombre').textContent = badgeTexto.textContent;
                document.getElementById('alerta-monto-historico').textContent = `${currencyFormatter.format(histMonto)} (${numberFormatter.format(histCreds)} créditos)`;
            } else {
                alertaEl.style.display = 'none';
            }

            // 1. Render Zonas Cards
            renderZonasCards();

            // 2. Render KPIs de Filtro
            renderPeriodoKpis();

            // 3. Render Gráficas
            renderEvolucionChart();
            renderZonasChart();
            renderPlazosChart();
            renderSucursalesChart();
            renderColaboradoresChart();

            // 4. Render Tabla
            filterTableData();
        }

        function renderZonasCards() {
            const currentZona = zonaSelect.value;
            const zData = rawData.kpis_zonas || [];
            const totalGen = rawData.kpi_total_general || { total_monto: 0, total_creditos: 0 };

            // Total General Card
            document.getElementById('zona-total-monto').textContent = currencyFormatter.format(totalGen.total_monto);
            document.getElementById('zona-total-creditos').innerHTML = `<i class="bi bi-file-earmark-text me-1"></i>${numberFormatter.format(totalGen.total_creditos)} créditos`;

            const cardTotal = document.getElementById('card-zona-total');
            if (!currentZona) {
                cardTotal.classList.add('is-active');
                cardTotal.style.border = '2px solid #501491';
                cardTotal.style.background = 'linear-gradient(135deg, #ffffff 0%, #faf5ff 100%)';
                document.getElementById('badge-total-estado').innerHTML = '<i class="bi bi-check-lg me-1"></i>Todo';
            } else {
                cardTotal.classList.remove('is-active');
                cardTotal.style.border = '1px solid #e2e8f0';
                cardTotal.style.background = '#ffffff';
                document.getElementById('badge-total-estado').textContent = '100%';
            }

            // Cada Zona
            zData.forEach(item => {
                const montoEl = document.getElementById(`monto-zona-${item.id}`);
                const credsEl = document.getElementById(`creditos-zona-${item.id}`);
                const barEl = document.getElementById(`bar-zona-${item.id}`);
                const badgeEl = document.getElementById(`badge-pct-${item.id}`);
                const cardEl = document.getElementById(`card-zona-${item.id}`);

                if (montoEl) montoEl.textContent = currencyFormatter.format(item.total_monto);
                if (credsEl) credsEl.innerHTML = `<i class="bi bi-file-earmark-text me-1"></i>${numberFormatter.format(item.total_creditos)} créds.`;
                if (barEl) barEl.style.width = `${item.porcentaje}%`;
                if (badgeEl) badgeEl.textContent = `${item.porcentaje}%`;

                if (cardEl) {
                    if (String(currentZona) === String(item.id)) {
                        cardEl.classList.add('is-active');
                        cardEl.style.border = `2px solid ${item.color}`;
                        cardEl.style.background = item.bg_gradient;
                        if (badgeEl) {
                            badgeEl.className = 'badge font-weight-bold text-white';
                            badgeEl.style.backgroundColor = item.color;
                            badgeEl.innerHTML = '<i class="bi bi-funnel-fill me-1"></i>Filtrado';
                        }
                    } else {
                        cardEl.classList.remove('is-active');
                        cardEl.style.border = '1px solid #e2e8f0';
                        cardEl.style.background = '#ffffff';
                        if (badgeEl) {
                            badgeEl.className = 'badge bg-light text-muted border font-weight-bold';
                            badgeEl.style.backgroundColor = '';
                            badgeEl.textContent = `${item.porcentaje}%`;
                        }
                    }
                }
            });

            // Botón Quitar Filtro de Zona
            const indicatorEl = document.getElementById('zona-badge-indicator');
            if (currentZona) {
                indicatorEl.style.display = 'block';
            } else {
                indicatorEl.style.display = 'none';
            }
        }

        function renderPeriodoKpis() {
            const m = rawData.metricas_periodo || {};
            document.getElementById('kpi-total-creditos').textContent = numberFormatter.format(m.total_creditos || 0);
            document.getElementById('label-conteo-creditos').textContent = `${numberFormatter.format(m.total_creditos || 0)} créditos`;
            document.getElementById('kpi-monto-total').textContent = currencyFormatter.format(m.monto_total || 0);
            document.getElementById('kpi-ticket-promedio').textContent = currencyFormatter.format(m.ticket_promedio || 0);
            document.getElementById('kpi-precio-venta').textContent = currencyFormatter.format(m.precio_venta_total || 0);
            document.getElementById('kpi-precio-prestamo').textContent = currencyFormatter.format(m.precio_prestamo_total || 0);
            document.getElementById('kpi-margen-monto').textContent = currencyFormatter.format(m.diferencia_financiera || 0);
            document.getElementById('kpi-margen-pct').textContent = `${m.margen_porcentaje || 0}% de margen`;
        }

        // 1. Gráfica de Evolución Temporal
        function renderEvolucionChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartEvolucion').getContext('2d');
            const dataEvol = rawData.evolucion_diaria || [];

            const diasEs = {
                'mon': 'Lun', 'tue': 'Mar', 'wed': 'Mié', 'thu': 'Jue', 'fri': 'Vie', 'sat': 'Sáb', 'sun': 'Dom',
                'lun': 'Lun', 'mar': 'Mar', 'mié': 'Mié', 'mie': 'Mié', 'jue': 'Jue', 'vie': 'Vie', 'sáb': 'Sáb', 'sab': 'Sáb', 'dom': 'Dom'
            };

            const labels = dataEvol.map(d => {
                const rawDia = (d.dia_semana || '').toLowerCase().trim();
                const dia = diasEs[rawDia] || d.dia_semana || '';
                return dia ? `${d.fecha_formato} (${dia})` : d.fecha_formato;
            });

            const isMonto = currentChartMetric === 'monto';

            let datasets = [];
            if (isMonto) {
                datasets = [{
                    label: 'Monto Financiado ($)',
                    data: dataEvol.map(d => d.monto),
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.15)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }];
            } else {
                datasets = [{
                    label: 'Créditos Colocados (#)',
                    data: dataEvol.map(d => d.creditos),
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6, 182, 212, 0.4)',
                    type: 'bar',
                    borderRadius: 4,
                }];
            }

            if (chartEvolucionInstance) chartEvolucionInstance.destroy();

            chartEvolucionInstance = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return isMonto
                                        ? ` Monto: ${currencyFormatter.format(ctx.raw)}`
                                        : ` Créditos: ${numberFormatter.format(ctx.raw)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: val => isMonto ? currencyFormatter.format(val) : numberFormatter.format(val)
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // 2. Gráfica Donut de Zonas
        function renderZonasChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartZonas').getContext('2d');
            const dataZonas = rawData.ranking_zonas || [];

            const labels = dataZonas.map(z => z.zona);
            const dataVals = dataZonas.map(z => z.monto_total);
            const colors = ['#4f46e5', '#0284c7', '#059669', '#d97706', '#8b5cf6'];

            if (chartZonasInstance) chartZonasInstance.destroy();

            chartZonasInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: dataVals,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                    return ` ${ctx.label}: ${currencyFormatter.format(ctx.raw)} (${pct}%)`;
                                }
                            }
                        }
                    },
                    cutout: '68%',
                }
            });

            // Leyenda HTML
            const legendEl = document.getElementById('chartZonas-legend');
            const total = dataVals.reduce((a, b) => a + b, 0);
            legendEl.innerHTML = labels.map((l, i) => {
                const val = dataVals[i];
                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                return `
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <div class="text-truncate me-2">
                            <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: ${colors[i % colors.length]};"></span>
                            <span class="text-dark fw-semibold">${l}</span>
                        </div>
                        <span class="text-muted text-nowrap">${currencyFormatter.format(val)} <strong class="text-dark">(${pct}%)</strong></span>
                    </div>
                `;
            }).join('');
        }

        // 3. Gráfica Donut de Plazos
        function renderPlazosChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartPlazos').getContext('2d');
            const dataPlazos = rawData.ranking_plazos || [];

            const labels = dataPlazos.map(p => p.plazo);
            const dataVals = dataPlazos.map(p => p.total);
            const colors = ['#0284c7', '#059669', '#f59e0b', '#8b5cf6', '#ec4899'];

            if (chartPlazosInstance) chartPlazosInstance.destroy();

            chartPlazosInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: dataVals,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                    return ` ${ctx.label}: ${numberFormatter.format(ctx.raw)} créditos (${pct}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%',
                }
            });

            // Leyenda HTML
            const legendEl = document.getElementById('chartPlazos-legend');
            const total = dataVals.reduce((a, b) => a + b, 0);
            legendEl.innerHTML = labels.map((l, i) => {
                const count = dataVals[i];
                const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
                return `
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <div class="text-truncate me-2">
                            <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: ${colors[i % colors.length]};"></span>
                            <span class="text-dark fw-semibold">${l}</span>
                        </div>
                        <span class="text-muted text-nowrap">${numberFormatter.format(count)} <strong class="text-dark">(${pct}%)</strong></span>
                    </div>
                `;
            }).join('');
        }

        // 4. Gráfica Top Sucursales
        function renderSucursalesChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartSucursales').getContext('2d');
            const dataSucs = (rawData.ranking_sucursales || []).slice(0, 7);

            const labels = dataSucs.map(s => s.sucursal);
            const dataVals = dataSucs.map(s => s.monto_total);

            if (chartSucursalesInstance) chartSucursalesInstance.destroy();

            chartSucursalesInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Monto ($)',
                        data: dataVals,
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` Monto: ${currencyFormatter.format(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { callback: val => currencyFormatter.format(val) },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { autoSkip: false }
                        }
                    }
                }
            });
        }

        // 5. Gráfica Top Colaboradores
        function renderColaboradoresChart() {
            if (!rawData) return;
            const ctx = document.getElementById('chartColaboradores').getContext('2d');
            const dataCols = (rawData.ranking_colaboradores || []).slice(0, 7);

            const labels = dataCols.map(c => c.nombre);
            const dataVals = dataCols.map(c => c.total);

            if (chartColaboradoresInstance) chartColaboradoresInstance.destroy();

            chartColaboradoresInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Créditos (#)',
                        data: dataVals,
                        backgroundColor: '#6366f1',
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` Créditos: ${numberFormatter.format(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { autoSkip: false }
                        }
                    }
                }
            });
        }

        // Filtrado en Cliente para Tabla
        function filterTableData() {
            const allItems = (rawData && rawData.creditos) ? rawData.creditos : [];
            const q = searchInput.value.toLowerCase().trim();

            if (!q) {
                filteredArticles = allItems;
            } else {
                filteredArticles = allItems.filter(item => {
                    return (String(item.id || '')).toLowerCase().includes(q) ||
                           (item.colaborador || '').toLowerCase().includes(q) ||
                           (item.comprador || '').toLowerCase().includes(q) ||
                           (item.articulo || '').toLowerCase().includes(q) ||
                           (item.sucursal || '').toLowerCase().includes(q) ||
                           (item.zona || '').toLowerCase().includes(q);
                });
            }

            renderTable();
        }

        // Renderizado de Tabla con Paginación
        function renderTable() {
            const tbody = document.getElementById('tabla-body');
            const total = filteredArticles.length;

            if (total === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No se encontraron registros de crédito externo con los filtros seleccionados.
                        </td>
                    </tr>
                `;
                document.getElementById('tabla-info').textContent = 'Mostrando 0 de 0 registros';
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

            tbody.innerHTML = pageItems.map(item => {
                return `
                    <tr>
                        <td class="ps-4 fw-bold text-primary">#${item.id}</td>
                        <td class="text-nowrap small text-muted">${item.fecha_formato || item.created_at}</td>
                        <td class="fw-semibold text-dark">${item.sucursal}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary border">${item.zona}</span></td>
                        <td class="small">${item.colaborador}</td>
                        <td class="fw-semibold text-dark">${item.comprador}</td>
                        <td class="small text-muted" style="max-width: 260px;" title="${item.articulo}">
                            <div class="text-truncate">${item.articulo}</div>
                        </td>
                        <td class="text-end text-muted small">${currencyFormatter.format(item.precio_prestamo)}</td>
                        <td class="text-end fw-semibold text-dark">${currencyFormatter.format(item.precio_venta)}</td>
                        <td class="text-end fw-bold text-success">${currencyFormatter.format(item.monto_credito)}</td>
                        <td class="text-center"><span class="badge-plazo">${item.plazo}</span></td>
                        <td class="text-center pe-4"><span class="badge-metodo">${item.metodo_pago}</span></td>
                    </tr>
                `;
            }).join('');

            document.getElementById('tabla-info').textContent = `Mostrando ${startIdx + 1} a ${endIdx} de ${numberFormatter.format(total)} créditos`;
            btnPrevPage.disabled = (currentPage === 1);
            btnNextPage.disabled = (currentPage === maxPage);
        }

        // Exportación CSV
        function exportToCsv() {
            if (!filteredArticles || filteredArticles.length === 0) {
                alert('No hay datos para exportar.');
                return;
            }

            const headers = [
                'Folio',
                'Fecha_Creacion',
                'Sucursal',
                'Zona',
                'Colaborador',
                'Comprador_Cliente',
                'Articulo_ID',
                'Descripcion_Articulo',
                'Costo_Base_Prestamo',
                'Precio_Venta',
                'Monto_Credito_Atrato',
                'Plazo_Periodicidad',
                'Metodo_Pago',
                'Observaciones',
                'Estado'
            ];

            const rows = filteredArticles.map(item => [
                item.id || '',
                item.created_at || '',
                `"${(item.sucursal || '').replace(/"/g, '""')}"`,
                `"${(item.zona || '').replace(/"/g, '""')}"`,
                `"${(item.colaborador || '').replace(/"/g, '""')}"`,
                `"${(item.comprador || '').replace(/"/g, '""')}"`,
                `"${(item.articulo_id || '').replace(/"/g, '""')}"`,
                `"${(item.articulo || '').replace(/"/g, '""')}"`,
                item.precio_prestamo || 0,
                item.precio_venta || 0,
                item.monto_credito || 0,
                `"${(item.plazo || '').replace(/"/g, '""')}"`,
                `"${(item.metodo_pago || '').replace(/"/g, '""')}"`,
                `"${(item.observaciones || '').replace(/"/g, '""')}"`,
                `"${(item.estado || '').replace(/"/g, '""')}"`
            ]);

            const csvContent = '\uFEFF' + [
                headers.join(','),
                ...rows.map(r => r.join(','))
            ].join('\r\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', `Credito_Externo_Atrato_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Carga Inicial
        fetchData();
    });
</script>
@endsection

