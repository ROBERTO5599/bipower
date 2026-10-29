@extends('employees.layouts.main')

@section('title', 'Gastos y Finanzas — Valora Más')

@section('styles')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.85rem 2.2rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.25s ease;
        }
        
        /* Icon Shapes idénticos a los otros módulos */
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
        .bg-light-secondary { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }

        /* Contenedor y Tablas Financieras NIF */
        .financial-table-container {
            max-height: 720px;
            overflow-x: auto;
            overflow-y: auto;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
        }

        .table-financial {
            font-size: 0.83rem;
            white-space: nowrap;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-financial thead th {
            position: sticky;
            top: 0;
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
            border-bottom: 2px solid #172554;
            padding: 9px 12px;
            z-index: 10;
        }

        .table-financial th.sticky-col,
        .table-financial td.sticky-col {
            position: sticky;
            left: 0;
            z-index: 12;
            background-color: #ffffff;
            font-weight: 500;
            text-align: left;
            min-width: 320px;
            max-width: 340px;
            border-right: 2px solid #cbd5e1;
            box-shadow: 2px 0 5px rgba(0,0,0,0.04);
        }

        .table-financial thead th.sticky-col {
            z-index: 15;
            background-color: #1e3a8a;
            color: #ffffff;
        }

        .table-financial th.col-sucursal {
            background-color: #1e3a8a;
            color: #ffffff;
            min-width: 140px;
        }
        .table-financial td.col-sucursal {
            background-color: #fefce8;
            color: #1e293b;
            text-align: right;
            font-variant-numeric: tabular-nums;
            border-right: 1px solid #fef08a;
        }

        .table-financial th.col-total-red {
            background-color: #0f172a !important;
            color: #ffffff !important;
            font-weight: 800;
            min-width: 160px;
        }
        .table-financial td.col-total-red {
            background-color: #f8fafc !important;
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            font-variant-numeric: tabular-nums;
            border-left: 2px solid #cbd5e1;
        }

        .tr-section-header td {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 12px;
            font-size: 0.81rem;
        }

        .tr-subtotal td {
            background-color: #f1f5f9 !important;
            font-weight: 700;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        .tr-total-major td {
            background-color: #e2e8f0 !important;
            font-weight: 800;
            font-size: 0.87rem;
            border-top: 2px solid #64748b;
            border-bottom: 2px double #1e293b;
        }

        .tr-utilidad-neta td {
            background-color: #dcfce7 !important;
            color: #14532d !important;
            font-weight: 800;
            font-size: 0.90rem;
            border-top: 2px solid #22c55e;
            border-bottom: 3px double #15803d;
        }

        .tr-control-balanced td {
            background-color: #ecfdf5 !important;
            color: #065f46 !important;
            font-weight: 700;
            border-top: 2px solid #10b981;
            border-bottom: 2px solid #10b981;
        }

        .tr-control-alert td {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
            font-weight: 800;
            border-top: 2px solid #ef4444;
            border-bottom: 2px solid #ef4444;
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% { background-color: #fee2e2; }
            50% { background-color: #fecaca; }
            100% { background-color: #fee2e2; }
        }

        /* Pestañas */
        .nav-pills .nav-link {
            font-weight: 600;
            color: #475569;
            border-radius: 8px;
            padding: 9px 16px;
            margin-right: 6px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.88rem;
        }
        .nav-pills .nav-link.active {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2);
        }

        /* Spinner Overlay */
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

        .table-ranking th { font-weight: 700; text-transform: uppercase; font-size: 0.78rem; color: #475569; }
        .table-ranking td { font-size: 0.85rem; vertical-align: middle; }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status" style="width: 3.2rem; height: 3.2rem;"></div>
    <h5 class="text-dark fw-bold mb-1">Calculando Estados Financieros e Indicadores...</h5>
    <p class="text-muted small">Consultando cartera, margen de bazar, gastos y balances en tiempo real</p>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    
    <!-- Encabezado con Botones de Acción -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold text-dark mb-0">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i>Gastos y Finanzas Integradas
                </h3>
                <span class="badge bg-success-subtle text-success border border-success fw-bold px-2 py-1">
                    <i class="bi bi-shield-check me-1"></i>Formato Oficial NIF Real
                </span>
            </div>
            <p class="text-muted mb-0">
                Tablero de control ejecutivo, estados financieros devengados, rentabilidad por sucursal y conciliación contable.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimir / PDF
            </button>
            <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Exportar a Excel (.xlsx)
            </button>
        </div>
    </div>

    <!-- Barra de Filtros idéntica a los otros módulos -->
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
                        <option value="all">-- Todas las Sucursales (Consolidado) --</option>
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
                    <label class="form-label fw-semibold">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>

                <!-- Filtros rápidos de fecha -->
                <div class="col-12 mt-2 pt-2 border-top d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small fw-semibold me-1"><i class="bi bi-calendar-range me-1 text-primary"></i>Filtros rápidos de fecha:</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-fecha" data-quick="hoy">Hoy</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-fecha" data-quick="ayer">Ayer</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-fecha" data-quick="semana">Esta Semana</button>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-quick-fecha active" data-quick="mes">Este Mes</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-fecha" data-quick="mes_anterior">Mes Anterior</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-fecha" data-quick="anio">Año Actual</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- FILA 1: INDICADORES PRINCIPALES DE RENTABILIDAD Y P&L    -->
    <!-- ======================================================== -->
    <div class="row mb-4">
        <!-- Ingresos Intereses Netos -->
        <div class="col-12 col-md-4 col-xl-2 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.76rem;">Ingresos Intereses</h6>
                        <div class="icon-shape bg-light-primary text-primary" style="width: 2.8rem; height: 2.8rem; font-size: 1.2rem;">
                            <i class="bi bi-percent"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-dark mb-1" id="kpi-ingresos-intereses">$ 0.00</h4>
                    <span class="text-muted small" style="font-size: 0.74rem;">Refrendos + Desempeños netos IVA</span>
                </div>
            </div>
        </div>

        <!-- Margen de Bazar -->
        <div class="col-12 col-md-4 col-xl-2 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-info border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.76rem;">Margen Bazar</h6>
                        <div class="icon-shape bg-light-info text-info" style="width: 2.8rem; height: 2.8rem; font-size: 1.2rem;">
                            <i class="bi bi-shop"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-info mb-1" id="kpi-utilidad-bazar">$ 0.00</h4>
                    <span class="text-muted small" style="font-size: 0.74rem;" id="kpi-bazar-subtext">Precio Venta - Costo</span>
                </div>
            </div>
        </div>

        <!-- Utilidad Bruta Total -->
        <div class="col-12 col-md-4 col-xl-2 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.76rem;">Utilidad Bruta</h6>
                        <div class="icon-shape bg-light-success text-success" style="width: 2.8rem; height: 2.8rem; font-size: 1.2rem;">
                            <i class="bi bi-arrow-up-right-circle-fill"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-success mb-1" id="kpi-utilidad-bruta">$ 0.00</h4>
                    <span class="text-muted small" style="font-size: 0.74rem;">Intereses + Bazar + Otros</span>
                </div>
            </div>
        </div>

        <!-- Gastos Operación -->
        <div class="col-12 col-md-4 col-xl-2 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-danger border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.76rem;">Gastos Operación</h6>
                        <div class="icon-shape bg-light-danger text-danger" style="width: 2.8rem; height: 2.8rem; font-size: 1.2rem;">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-danger mb-1" id="kpi-gastos-operacion">$ 0.00</h4>
                    <span class="text-muted small" style="font-size: 0.74rem;" id="kpi-gastos-ratio">Nómina, Renta, Servicios</span>
                </div>
            </div>
        </div>

        <!-- Utilidad Neta -->
        <div class="col-12 col-md-4 col-xl-2 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-purple border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.76rem;">Utilidad Neta</h6>
                        <div class="icon-shape bg-light-purple text-purple" style="width: 2.8rem; height: 2.8rem; font-size: 1.2rem;">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-dark mb-1" id="kpi-utilidad-neta">$ 0.00</h4>
                    <span class="badge bg-purple-subtle text-purple fw-bold" style="font-size: 0.75rem;" id="kpi-margen-neto">0.0% Margen</span>
                </div>
            </div>
        </div>

        <!-- Control de Balance NIF -->
        <div class="col-12 col-md-4 col-xl-2 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3" id="kpi-balance-card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.76rem;">Control Balance</h6>
                        <div class="icon-shape" id="kpi-balance-icon" style="width: 2.8rem; height: 2.8rem; font-size: 1.2rem;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1" id="kpi-balance-status">$ 0.00</h4>
                    <span class="small fw-semibold" style="font-size: 0.74rem;" id="kpi-balance-subtext">Activo = Pasivo + Capital</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- FILA 2: ACTIVOS, CARTERA, BAZAR Y FLUJO NETO             -->
    <!-- ======================================================== -->
    <div class="row mb-4">
        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-warning border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.78rem;">Cartera Vigente</h6>
                        <div class="icon-shape bg-light-warning text-warning" style="width: 2.5rem; height: 2.5rem; font-size: 1.1rem;"><i class="bi bi-folder-check"></i></div>
                    </div>
                    <h4 class="fw-bold text-dark mb-0" id="kpi-cartera-vigente">$ 0.00</h4>
                    <span class="text-muted small">Préstamo prendario activo</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-secondary border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.78rem;">Inventario Bazar (Costo)</h6>
                        <div class="icon-shape bg-light-secondary text-secondary" style="width: 2.5rem; height: 2.5rem; font-size: 1.1rem;"><i class="bi bi-boxes"></i></div>
                    </div>
                    <h4 class="fw-bold text-dark mb-0" id="kpi-inventario-bazar">$ 0.00</h4>
                    <span class="text-muted small">Costo original de prendas en piso</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-primary border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.78rem;">Total Activos</h6>
                        <div class="icon-shape bg-light-primary text-primary" style="width: 2.5rem; height: 2.5rem; font-size: 1.1rem;"><i class="bi bi-bank"></i></div>
                    </div>
                    <h4 class="fw-bold text-dark mb-0" id="kpi-total-activos">$ 0.00</h4>
                    <span class="text-muted small">Circulante + Fijo Neto</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-bottom border-success border-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0" style="font-size: 0.78rem;">Flujo Neto de Efectivo</h6>
                        <div class="icon-shape bg-light-success text-success" style="width: 2.5rem; height: 2.5rem; font-size: 1.1rem;"><i class="bi bi-arrow-left-right"></i></div>
                    </div>
                    <h4 class="fw-bold text-dark mb-0" id="kpi-flujo-neto">$ 0.00</h4>
                    <span class="text-muted small">Entradas - Salidas en Caja + Bancos</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- GRÁFICAS EJECUTIVAS: COMPOSICIÓN Y COMPARATIVA           -->
    <!-- ======================================================== -->
    <div class="row mb-4">
        <div class="col-12 col-lg-5 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pie-chart me-2 text-danger"></i>Composición de Gastos Operativos</h6>
                    <span class="badge bg-light text-muted border">Por Rubro</span>
                </div>
                <div class="card-body p-3 d-flex justify-content-center align-items-center" style="position: relative; height: 280px;">
                    <canvas id="gastosChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Rentabilidad por Sucursal (Utilidad Bruta vs Gastos vs Neta)</h6>
                    <span class="badge bg-light text-muted border">Cifras Reales</span>
                </div>
                <div class="card-body p-3" style="position: relative; height: 280px;">
                    <canvas id="sucursalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- PESTAÑAS DETALLADAS ESTILO POWER BI                      -->
    <!-- ======================================================== -->
    <ul class="nav nav-pills mb-3" id="finanzasTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ranking-tab" data-bs-toggle="pill" data-bs-target="#ranking" type="button" role="tab">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Comparativa de Sucursales
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pnl-tab" data-bs-toggle="pill" data-bs-target="#pnl" type="button" role="tab">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Estado de Resultados (P&L NIF)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="balance-tab" data-bs-toggle="pill" data-bs-target="#balance" type="button" role="tab">
                <i class="bi bi-wallet2 me-1"></i> Balance General & Control
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="flujo-tab" data-bs-toggle="pill" data-bs-target="#flujo" type="button" role="tab">
                <i class="bi bi-cash-stack me-1"></i> Flujo de Efectivo
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="guia-tab" data-bs-toggle="pill" data-bs-target="#guia" type="button" role="tab">
                <i class="bi bi-journal-bookmark-fill me-1"></i> Metodología NIF Casa de Empeño
            </button>
        </li>
    </ul>

    <div class="tab-content" id="finanzasTabsContent">
        
        <!-- 1. COMPARATIVA DE SUCURSALES (RANKING) -->
        <div class="tab-pane fade show active" id="ranking" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Desempeño Financiero por Sucursal</h6>
                        <span class="text-muted small">Cifras reales de cada sucursal ordenadas por Utilidad Neta</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="ranking-search" class="form-control form-control-sm" placeholder="Buscar sucursal..." style="width: 200px;">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-ranking mb-0" id="ranking-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Sucursal</th>
                                    <th class="text-end">Intereses Netos</th>
                                    <th class="text-end">Venta Bazar</th>
                                    <th class="text-end">Margen Bazar</th>
                                    <th class="text-end">Utilidad Bruta</th>
                                    <th class="text-end">Gastos Operación</th>
                                    <th class="text-end">Utilidad Neta</th>
                                    <th class="text-center" style="width: 140px;">Margen Neto</th>
                                    <th class="text-center">Balance NIF</th>
                                </tr>
                            </thead>
                            <tbody id="ranking-tbody">
                                <tr><td colspan="10" class="text-center py-5 text-muted">Cargando comparativa...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ESTADO DE RESULTADOS (P&L OFICIAL) -->
        <div class="tab-pane fade" id="pnl" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">ESTADO DE RESULTADOS — FORMATO CASA DE EMPEÑO</h6>
                        <span class="text-muted small" id="pnl-periodo-label">Período Seleccionado</span>
                    </div>
                    <span class="badge bg-primary">Base Devengada · Cifras Reales MXN</span>
                </div>
                <div class="card-body p-0">
                    <div class="financial-table-container">
                        <table class="table table-financial table-hover" id="table-pnl">
                            <thead>
                                <tr id="pnl-thead-row">
                                    <th class="sticky-col">Concepto</th>
                                    <th class="col-total-red">TOTAL RED</th>
                                </tr>
                            </thead>
                            <tbody id="pnl-tbody">
                                <tr><td colspan="15" class="text-center py-5 text-muted">Cargando Estado de Resultados...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. BALANCE GENERAL & CONTROL -->
        <div class="tab-pane fade" id="balance" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">BALANCE GENERAL — VALORA MÁS</h6>
                        <span class="text-muted small" id="balance-periodo-label">Al corte del período</span>
                    </div>
                    <span class="badge bg-dark">Entidad Legal: Valora Más</span>
                </div>
                <div class="card-body p-0">
                    <div class="financial-table-container">
                        <table class="table table-financial table-hover" id="table-balance">
                            <thead>
                                <tr id="balance-thead-row">
                                    <th class="sticky-col">Concepto</th>
                                    <th class="col-total-red">CONSOLIDADO RED</th>
                                </tr>
                            </thead>
                            <tbody id="balance-tbody">
                                <tr><td colspan="15" class="text-center py-5 text-muted">Cargando Balance General...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. FLUJO DE EFECTIVO -->
        <div class="tab-pane fade" id="flujo" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">ESTADO DE FLUJO DE EFECTIVO — VALORA MÁS</h6>
                        <span class="text-muted small">Método directo, clasificado por actividad (NIF B-2)</span>
                    </div>
                    <span class="badge bg-secondary">Caja + Bancos</span>
                </div>
                <div class="card-body p-0">
                    <div class="financial-table-container">
                        <table class="table table-financial table-hover" id="table-flujo">
                            <thead>
                                <tr id="flujo-thead-row">
                                    <th class="sticky-col">Concepto</th>
                                    <th class="col-total-red">TOTAL RED</th>
                                </tr>
                            </thead>
                            <tbody id="flujo-tbody">
                                <tr><td colspan="15" class="text-center py-5 text-muted">Cargando Flujo de Efectivo...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. METODOLOGÍA NIF -->
        <div class="tab-pane fade" id="guia" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-dark text-white p-4">
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-book-half me-2"></i>METODOLOGÍA CONTABLE NIF PARA CASA DE EMPEÑO
                    </h5>
                    <span class="text-light opacity-75">Guía de operación y control de Estados Financieros · Valora Más</span>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-primary border-0 shadow-sm mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb-fill me-2"></i>Por qué este formato es distinto de un P&L genérico</h6>
                        <p class="mb-0">
                            Una casa de empeño tiene dos negocios distintos operando bajo un mismo techo: 
                            el <strong>préstamo prendario</strong> (que gana dinero por <em>INTERÉS</em>) y la <strong>venta de bazar</strong> (que gana dinero por <em>MARGEN</em> sobre mercancía). 
                            Un Estado de Resultados correcto los separa, porque mezclarlos oculta cuál de los dos está funcionando y cuál no.
                        </p>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-white shadow-sm">
                                <h6 class="fw-bold text-primary mb-2">1. Base devengada, no flujo de caja</h6>
                                <p class="text-muted small mb-0">
                                    El Estado de Resultados se construye sobre lo <strong>DEVENGADO</strong> (lo que se ganó en el período). El interés vigente se reconoce mes a mes aunque el cliente no haya acudido a refrendar.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-white shadow-sm">
                                <h6 class="fw-bold text-dark mb-2">2. Costo de venta del bazar (Monto prestado original)</h6>
                                <p class="text-muted small mb-0">
                                    Cuando una prenda pasa a bazar, tiene como costo el <strong>monto originalmente prestado</strong>. Al venderse, la ganancia real es Precio de Venta MENOS ese costo.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-white shadow-sm">
                                <h6 class="fw-bold text-info mb-2">3. IVA trasladado sobre intereses (16%)</h6>
                                <p class="text-muted small mb-0">
                                    El interés causa 16% de IVA. Ese monto se entera al SAT, por lo que debe descontarse de los intereses para presentar el ingreso neto de la empresa.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-white shadow-sm">
                                <h6 class="fw-bold text-secondary mb-2">4. Depreciación de activos</h6>
                                <p class="text-muted small mb-0">
                                    Vehículos, cómputo y mobiliario pierden valor con el uso. Bajo NIF, ese desgaste es un gasto del período por línea recta.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-white shadow-sm">
                                <h6 class="fw-bold text-danger mb-2">5. Reservas de cartera y bazar</h6>
                                <p class="text-muted small mb-0">
                                    Cartera vencida sin refrendo después de 90 días &rarr; reservar monto no cubierto; bazar con más de 270 días en piso &rarr; reservar porcentaje de deterioro.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-white shadow-sm border-danger">
                                <h6 class="fw-bold text-danger mb-2">6. La Fila de Control en Cero</h6>
                                <p class="text-muted small mb-0">
                                    La última fila del Balance calcula <strong>Activo menos (Pasivo + Capital) y se pinta de rojo si no es cero</strong>, asegurando el amarre matemático.
                                </p>
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
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        let globalData = null;
        let chartGastosInstance = null;
        let chartSucursalesInstance = null;

        const formatter = new Intl.NumberFormat('es-MX', { 
            style: 'currency', 
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2 
        });

        const pctFormatter = new Intl.NumberFormat('es-MX', { 
            minimumFractionDigits: 1, 
            maximumFractionDigits: 1 
        });

        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');

        // Manejo de botones rápidos de fecha (idéntico a operaciones-cartera)
        const quickButtons = document.querySelectorAll('.btn-quick-fecha');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                quickButtons.forEach(b => {
                    b.classList.remove('active', 'btn-outline-primary');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('active', 'btn-outline-primary');

                const range = this.getAttribute('data-quick');
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const fmtDate = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

                const inputInicio = document.getElementById('fecha_inicio');
                const inputFin = document.getElementById('fecha_fin');

                if (range === 'hoy') {
                    inputInicio.value = fmtDate(now);
                    inputFin.value = fmtDate(now);
                } else if (range === 'ayer') {
                    const yest = new Date(now);
                    yest.setDate(now.getDate() - 1);
                    inputInicio.value = fmtDate(yest);
                    inputFin.value = fmtDate(yest);
                } else if (range === 'semana') {
                    const startWeek = new Date(now);
                    const day = now.getDay() || 7;
                    startWeek.setDate(now.getDate() - day + 1);
                    inputInicio.value = fmtDate(startWeek);
                    inputFin.value = fmtDate(now);
                } else if (range === 'mes') {
                    const startMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                    inputInicio.value = fmtDate(startMonth);
                    inputFin.value = fmtDate(now);
                } else if (range === 'mes_anterior') {
                    const startLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const endLastMonth = new Date(now.getFullYear(), now.getMonth(), 0);
                    inputInicio.value = fmtDate(startLastMonth);
                    inputFin.value = fmtDate(endLastMonth);
                } else if (range === 'anio') {
                    const startYear = new Date(now.getFullYear(), 0, 1);
                    inputInicio.value = fmtDate(startYear);
                    inputFin.value = fmtDate(now);
                }

                loadFinancialData();
            });
        });

        // Carga inicial
        loadFinancialData();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadFinancialData();
        });

        // Búsqueda en ranking
        document.getElementById('ranking-search').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('#ranking-tbody tr');
            rows.forEach(r => {
                const text = r.innerText.toLowerCase();
                r.style.display = text.includes(query) ? '' : 'none';
            });
        });

        function loadFinancialData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.4';

            const formData = new FormData(form);
            const urlParams = new URLSearchParams(formData).toString();

            fetch(`{{ route('gastos-finanzas.data') }}?${urlParams}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error al consultar datos financieros');
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Error al procesar estados financieros');
                        return;
                    }
                    globalData = data;
                    renderDashboard(data);
                })
                .catch(error => {
                    console.error("Error financiero:", error);
                    alert("Ocurrió un error al cargar la información financiera.");
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function fmt(val) {
            if (val === null || val === undefined) return '-';
            const num = parseFloat(val);
            if (isNaN(num)) return '-';
            if (num === 0) return '-';
            if (num < 0) return `(${formatter.format(Math.abs(num))})`;
            return formatter.format(num);
        }

        function fmtPct(val) {
            if (val === null || val === undefined) return '0.0%';
            const num = parseFloat(val);
            if (isNaN(num)) return '0.0%';
            return `${pctFormatter.format(num)}%`;
        }

        function renderDashboard(data) {
            const kpis = data.kpis || {};
            const sucursales = data.sucursales || [];
            const pnl = data.pnl || {};
            const totalRedPnl = data.total_red_pnl || {};
            const balance = data.balance || {};
            const totalRedBalance = data.total_red_balance || {};
            const flujo = data.flujo || {};
            const totalRedFlujo = data.total_red_flujo || {};
            const ranking = data.ranking_sucursales || [];

            // 1. Actualizar KPIs Principales
            document.getElementById('kpi-ingresos-intereses').innerText = formatter.format(kpis.ingresos_intereses || 0);
            document.getElementById('kpi-utilidad-bazar').innerText = formatter.format(kpis.utilidad_bazar || 0);
            document.getElementById('kpi-utilidad-bruta').innerText = formatter.format(kpis.utilidad_bruta || 0);
            document.getElementById('kpi-gastos-operacion').innerText = formatter.format(kpis.gastos_operacion || 0);
            document.getElementById('kpi-utilidad-neta').innerText = formatter.format(kpis.utilidad_neta || 0);
            document.getElementById('kpi-margen-neto').innerText = `${fmtPct(kpis.margen_neto)} Margen Neto`;

            const gastosRatio = kpis.utilidad_bruta > 0 ? (kpis.gastos_operacion / kpis.utilidad_bruta) * 100 : 0;
            document.getElementById('kpi-gastos-ratio').innerText = `Representa ${fmtPct(gastosRatio)} de la Utilidad Bruta`;

            document.getElementById('kpi-cartera-vigente').innerText = formatter.format(kpis.cartera_vigente || 0);
            document.getElementById('kpi-inventario-bazar').innerText = formatter.format(kpis.inventario_bazar || 0);
            document.getElementById('kpi-total-activos').innerText = formatter.format(kpis.total_activo || 0);
            document.getElementById('kpi-flujo-neto').innerText = formatter.format(kpis.flujo_neto || 0);

            // Control de Balance NIF
            const balanceCard = document.getElementById('kpi-balance-card');
            const balanceStatus = document.getElementById('kpi-balance-status');
            const balanceSubtext = document.getElementById('kpi-balance-subtext');
            const balanceIcon = document.getElementById('kpi-balance-icon');
            const dif = Math.abs(kpis.diferencia_balance || 0);

            if (dif < 0.05) {
                balanceCard.className = 'card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-success border-4 bg-light-success';
                balanceStatus.className = 'fw-bold mb-1 text-success';
                balanceStatus.innerText = 'CUADRADO ($0.00)';
                balanceSubtext.className = 'small text-success fw-semibold';
                balanceSubtext.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Balance Amarrado';
                balanceIcon.className = 'icon-shape bg-white text-success';
            } else {
                balanceCard.className = 'card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-danger border-4 bg-light-danger';
                balanceStatus.className = 'fw-bold mb-1 text-danger';
                balanceStatus.innerText = `DESCUADRE: ${formatter.format(dif)}`;
                balanceSubtext.className = 'small text-danger fw-bold';
                balanceSubtext.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Requiere Revisión';
                balanceIcon.className = 'icon-shape bg-white text-danger';
            }

            // Etiquetas de período
            const fi = document.getElementById('fecha_inicio').value;
            const ff = document.getElementById('fecha_fin').value;
            const periodoText = `Del ${fi} al ${ff}`;
            const pnlLabel = document.getElementById('pnl-periodo-label');
            if (pnlLabel) pnlLabel.innerText = periodoText;
            const balLabel = document.getElementById('balance-periodo-label');
            if (balLabel) balLabel.innerText = `Al corte del ${ff}`;

            // 2. Gráficas
            renderGastosChart(data.chart_gastos);
            renderSucursalesChart(data.chart_sucursales);

            // 3. Tabla Ranking de Sucursales
            renderRankingTable(ranking);

            // 4. Tablas Oficiales NIF
            renderPnlTable(sucursales, pnl, totalRedPnl);
            renderBalanceTable(sucursales, balance, totalRedBalance);
            renderFlujoTable(sucursales, flujo, totalRedFlujo);
        }

        // ==========================================
        // GRÁFICA DE GASTOS (Doughnut)
        // ==========================================
        function renderGastosChart(chartData) {
            const ctx = document.getElementById('gastosChart');
            if (!ctx) return;
            if (chartGastosInstance) chartGastosInstance.destroy();

            chartGastosInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: [
                            '#0d6efd', // Nómina
                            '#fd7e14', // Renta
                            '#198754', // Servicios
                            '#ffc107', // Mantenimiento
                            '#0dcaf0', // Publicidad
                            '#6c757d'  // Seguridad / Varios
                        ],
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${formatter.format(ctx.raw)}`
                            }
                        }
                    }
                }
            });
        }

        // ==========================================
        // GRÁFICA DE RENTABILIDAD POR SUCURSAL (Bars)
        // ==========================================
        function renderSucursalesChart(chartData) {
            const ctx = document.getElementById('sucursalesChart');
            if (!ctx) return;
            if (chartSucursalesInstance) chartSucursalesInstance.destroy();

            chartSucursalesInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Utilidad Bruta',
                            data: chartData.utilidad_bruta,
                            backgroundColor: 'rgba(25, 135, 84, 0.8)',
                            borderRadius: 4
                        },
                        {
                            label: 'Gastos Operación',
                            data: chartData.gastos_operacion,
                            backgroundColor: 'rgba(220, 53, 69, 0.75)',
                            borderRadius: 4
                        },
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidad_neta,
                            backgroundColor: 'rgba(111, 66, 193, 0.85)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { font: { size: 10 } } },
                        y: {
                            ticks: {
                                callback: val => '$' + Intl.NumberFormat('es-MX', { notation: 'compact' }).format(val)
                            }
                        }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.dataset.label}: ${formatter.format(ctx.raw)}`
                            }
                        }
                    }
                }
            });
        }

        // ==========================================
        // TABLA: RANKING Y COMPARATIVA DE SUCURSALES
        // ==========================================
        function renderRankingTable(ranking) {
            const tbody = document.getElementById('ranking-tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            if (ranking.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No hay sucursales para mostrar.</td></tr>';
                return;
            }

            ranking.forEach((s, idx) => {
                const tr = document.createElement('tr');
                const isPositive = s.utilidad_neta >= 0;
                const mPct = s.margen_neto || 0;
                const barColor = mPct >= 30 ? 'bg-success' : (mPct > 10 ? 'bg-primary' : 'bg-warning');

                tr.innerHTML = `
                    <td class="ps-4 fw-bold text-muted">${idx + 1}</td>
                    <td class="fw-bold text-dark">
                        <i class="bi bi-building me-1 text-primary"></i> ${escapeHtml(s.nombre)}
                    </td>
                    <td class="text-end">${formatter.format(s.ingresos_intereses)}</td>
                    <td class="text-end">${formatter.format(s.venta_bazar)}</td>
                    <td class="text-end text-info fw-semibold">${formatter.format(s.utilidad_bazar)}</td>
                    <td class="text-end fw-bold text-success">${formatter.format(s.utilidad_bruta)}</td>
                    <td class="text-end text-danger">${formatter.format(s.gastos_operacion)}</td>
                    <td class="text-end fw-bold ${isPositive ? 'text-dark' : 'text-danger'}">${formatter.format(s.utilidad_neta)}</td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <span class="small fw-bold">${fmtPct(mPct)}</span>
                            <div class="progress" style="width: 50px; height: 6px;">
                                <div class="progress-bar ${barColor}" style="width: ${Math.min(Math.max(mPct, 5), 100)}%;"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        ${Math.abs(s.diferencia_balance) < 0.05 
                            ? '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check2"></i> Cuadrado</span>' 
                            : '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="bi bi-exclamation-triangle"></i> Descuadre</span>'}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // ==========================================
        // TABLA: ESTADO DE RESULTADOS NIF REAL
        // ==========================================
        function renderPnlTable(sucursales, dataMap, totalRed) {
            const theadRow = document.getElementById('pnl-thead-row');
            theadRow.innerHTML = `<th class="sticky-col">Concepto</th>`;
            sucursales.forEach(s => {
                theadRow.innerHTML += `<th class="col-sucursal">${escapeHtml(s.nombre)}</th>`;
            });
            theadRow.innerHTML += `<th class="col-total-red">TOTAL RED</th>`;

            const tbody = document.getElementById('pnl-tbody');
            let html = '';

            function makeRow(label, key, isSubtotal = false, isMajor = false, isSpecial = false, isPct = false) {
                let trClass = '';
                if (isSpecial) trClass = 'tr-utilidad-neta';
                else if (isMajor) trClass = 'tr-total-major';
                else if (isSubtotal) trClass = 'tr-subtotal';

                let rowHtml = `<tr class="${trClass}"><td class="sticky-col">${label}</td>`;

                sucursales.forEach(s => {
                    const rowData = dataMap[s.id_valora_mas] || {};
                    const val = isPct ? fmtPct(rowData[key]) : fmt(rowData[key]);
                    rowHtml += `<td class="col-sucursal">${val}</td>`;
                });

                const redVal = isPct ? fmtPct(totalRed[key]) : fmt(totalRed[key]);
                rowHtml += `<td class="col-total-red">${redVal}</td></tr>`;
                return rowHtml;
            }

            function makeSectionHeader(title) {
                const totalCols = sucursales.length + 2;
                return `<tr class="tr-section-header"><td colspan="${totalCols}">${title}</td></tr>`;
            }

            // SECCIÓN: INGRESOS POR INTERESES
            html += makeSectionHeader('INGRESOS POR INTERESES SOBRE PRÉSTAMO PRENDARIO');
            html += makeRow('Intereses Ordinarios (Refrendos)', 'int_refrendos');
            html += makeRow('Intereses Ordinarios (Desempeños)', 'int_desempenos');
            html += makeRow('Intereses Moratorios', 'int_moratorios');
            html += makeRow('(-) IVA Trasladado sobre Intereses (16%)', 'iva_intereses');
            html += makeRow('► Ingresos Netos por Intereses', 'ing_netos_intereses', true);

            // SECCIÓN: BAZAR
            html += makeSectionHeader('INGRESOS POR VENTA DE BAZAR (PRENDA NO RECUPERADA)');
            html += makeRow('Precio de Venta', 'precio_venta_bazar');
            html += makeRow('(-) Costo de Venta (monto originalmente prestado)', 'costo_venta_bazar');
            html += makeRow('► Utilidad Bruta en Venta de Bazar', 'utilidad_bazar', true);
            html += makeRow('(+) Otros Ingresos Operativos (comisiones, garantías)', 'otros_ingresos');
            html += makeRow('► UTILIDAD BRUTA TOTAL', 'utilidad_bruta_total', false, true);

            // SECCIÓN: GASTOS OPERACIÓN
            html += makeSectionHeader('GASTOS DE OPERACIÓN (SUCURSAL)');
            html += makeRow('Sueldos y Salarios', 'gasto_nomina');
            html += makeRow('Bonos y Comisiones', 'gasto_bonos');
            html += makeRow('IMSS / Prestaciones', 'gasto_imss');
            html += makeRow('Renta', 'gasto_renta');
            html += makeRow('Servicios (Luz, Agua, Teléfono / Internet)', 'gasto_servicios');
            html += makeRow('Mantenimiento (sucursal, transporte, cómputo)', 'gasto_mantenimiento');
            html += makeRow('Papelería e Insumos', 'gasto_papeleria');
            html += makeRow('Publicidad', 'gasto_publicidad');
            html += makeRow('Seguridad / Protección Civil / Monitoreo', 'gasto_seguridad');
            html += makeRow('Gastos Varios y Menores', 'gasto_varios');
            html += makeRow('► Total Gastos de Operación', 'total_gastos_operacion', true);

            // SECCIÓN: ADMINISTRACIÓN CORPORATIVA
            html += makeSectionHeader('GASTOS DE ADMINISTRACIÓN (OFICINA CORPORATIVA, PRORRATEADA)');
            html += makeRow('Gasto de Oficina Asignado a la Sucursal', 'gastos_admin');
            html += makeRow('► Total Gastos de Administración', 'gastos_admin', true);

            // SECCIÓN: NO MONETARIAS Y RESERVAS
            html += makeSectionHeader('PARTIDAS NO MONETARIAS Y RESERVAS');
            html += makeRow('(-) Depreciación y Amortización del Ejercicio', 'depreciacion');
            html += makeRow('(-) Estimación para Cuentas de Cobro Dudoso', 'reserva_cartera');
            html += makeRow('(-) Estimación por Deterioro de Inventario de Bazar', 'reserva_bazar');
            html += makeRow('► UTILIDAD DE OPERACIÓN (EBIT)', 'ebit', false, true);

            // SECCIÓN: RIF
            html += makeSectionHeader('RESULTADO INTEGRAL DE FINANCIAMIENTO');
            html += makeRow('(+) Ingresos Financieros (intereses bancarios ganados)', 'ingresos_financieros');
            html += makeRow('(-) Gastos Financieros (crédito revolvente / préstamos)', 'gastos_financieros');
            html += makeRow('(+/-) Fluctuación Cambiaria', 'fluctuacion');
            html += makeRow('► Total Resultado Integral de Financiamiento', 'rif', true);
            html += makeRow('► UTILIDAD ANTES DE IMPUESTOS', 'utilidad_antes_impuestos', false, true);

            // SECCIÓN: IMPUESTOS
            html += makeSectionHeader('IMPUESTOS');
            html += makeRow('(-) ISR', 'isr');
            html += makeRow('(-) PTU', 'ptu');
            html += makeRow('► UTILIDAD NETA DEL EJERCICIO', 'utilidad_neta', false, false, true);

            // SECCIÓN: MÁRGENES %
            html += makeSectionHeader('MÁRGENES (%)');
            html += makeRow('Margen de Intereses / Ingresos Totales', 'margen_intereses', false, false, false, true);
            html += makeRow('Margen Bruto en Venta de Bazar', 'margen_bazar', false, false, false, true);
            html += makeRow('Margen Operativo (EBIT / Utilidad Bruta Total)', 'margen_operativo', false, false, false, true);
            html += makeRow('Margen Neto (Utilidad Neta / Utilidad Bruta Total)', 'margen_neto', false, false, false, true);

            tbody.innerHTML = html;
        }

        // ==========================================
        // TABLA: BALANCE GENERAL NIF REAL
        // ==========================================
        function renderBalanceTable(sucursales, dataMap, totalRed) {
            const theadRow = document.getElementById('balance-thead-row');
            theadRow.innerHTML = `<th class="sticky-col">Concepto</th>`;
            sucursales.forEach(s => {
                theadRow.innerHTML += `<th class="col-sucursal">${escapeHtml(s.nombre)}</th>`;
            });
            theadRow.innerHTML += `<th class="col-total-red">CONSOLIDADO RED</th>`;

            const tbody = document.getElementById('balance-tbody');
            let html = '';

            function makeRow(label, key, isSubtotal = false, isMajor = false, isControl = false) {
                let trClass = '';
                if (isControl) {
                    const dif = Math.abs(totalRed[key] || 0);
                    trClass = dif < 0.05 ? 'tr-control-balanced' : 'tr-control-alert';
                } else if (isMajor) {
                    trClass = 'tr-total-major';
                } else if (isSubtotal) {
                    trClass = 'tr-subtotal';
                }

                let rowHtml = `<tr class="${trClass}"><td class="sticky-col">${label}</td>`;

                sucursales.forEach(s => {
                    const rowData = dataMap[s.id_valora_mas] || {};
                    rowHtml += `<td class="col-sucursal">${fmt(rowData[key])}</td>`;
                });

                rowHtml += `<td class="col-total-red">${fmt(totalRed[key])}</td></tr>`;
                return rowHtml;
            }

            function makeSectionHeader(title) {
                const totalCols = sucursales.length + 2;
                return `<tr class="tr-section-header"><td colspan="${totalCols}">${title}</td></tr>`;
            }

            // ACTIVO CIRCULANTE
            html += makeSectionHeader('ACTIVO CIRCULANTE');
            html += makeRow('Caja', 'caja');
            html += makeRow('Bancos (todas las cuentas: BBVA, Bancoppel, etc.)', 'bancos');
            html += makeRow('► Total Efectivo y Equivalentes', 'total_efectivo', true);
            html += makeRow('Cartera Vigente (préstamo prendario)', 'cartera_vigente');
            html += makeRow('(+) Intereses Devengados por Cobrar', 'intereses_devengados');
            html += makeRow('► Total Cartera de Préstamos', 'total_cartera', true);
            html += makeRow('(-) Estimación para Cuentas de Cobro Dudoso', 'estimacion_cobro_dudoso');
            html += makeRow('► Cartera Neta', 'cartera_neta', true);
            html += makeRow('Inventario de Bazar (a costo — valor de préstamo original)', 'inventario_bazar_costo');
            html += makeRow('(-) Estimación por Deterioro de Inventario de Bazar', 'estimacion_deterioro_bazar');
            html += makeRow('► Bazar Neto', 'bazar_neto', true);
            html += makeRow('IVA Acreditable', 'iva_acreditable');
            html += makeRow('TOTAL ACTIVO CIRCULANTE', 'total_activo_circulante', false, true);

            // ACTIVO NO CIRCULANTE
            html += makeSectionHeader('ACTIVO NO CIRCULANTE');
            html += makeRow('Equipo de Transporte (costo)', 'equipo_transporte');
            html += makeRow('Mobiliario y Equipo de Oficina (costo)', 'mobiliario_oficina');
            html += makeRow('Equipo de Cómputo (costo)', 'equipo_computo');
            html += makeRow('► Total Activo Fijo (costo)', 'total_activo_fijo_costo', true);
            html += makeRow('(-) Depreciación Acumulada', 'depreciacion_acumulada');
            html += makeRow('► Activo Fijo Neto', 'activo_fijo_neto', true);
            html += makeRow('Depósitos en Garantía (rentas)', 'depositos_garantia');
            html += makeRow('TOTAL ACTIVO NO CIRCULANTE', 'total_activo_no_circulante', false, true);
            html += makeRow('TOTAL ACTIVO', 'total_activo', false, true);

            // PASIVO A CORTO PLAZO
            html += makeSectionHeader('PASIVO A CORTO PLAZO');
            html += makeRow('Proveedores', 'proveedores');
            html += makeRow('Acreedores Diversos', 'acreedores');
            html += makeRow('IVA Trasladado por Pagar (sobre intereses, pendiente de enterar al SAT)', 'iva_trasladado_pagar');
            html += makeRow('ISR e Impuestos Retenidos por Pagar (salarios, IMSS)', 'retenciones_impuestos');
            html += makeRow('PTU por Pagar', 'ptu_pagar');
            html += makeRow('Anticipos de Clientes (Apartados Vigentes)', 'apartados_vigentes');
            html += makeRow('Porción Circulante de Crédito Revolvente / Préstamos Bancarios', 'credito_revolvente_cp');
            html += makeRow('TOTAL PASIVO A CORTO PLAZO', 'total_pasivo_cp', false, true);

            // PASIVO A LARGO PLAZO
            html += makeSectionHeader('PASIVO A LARGO PLAZO');
            html += makeRow('Crédito Revolvente / Préstamos Bancarios (porción largo plazo)', 'credito_bancario_lp');
            html += makeRow('TOTAL PASIVO A LARGO PLAZO', 'total_pasivo_lp', false, true);
            html += makeRow('TOTAL PASIVO', 'total_pasivo', false, true);

            // CAPITAL CONTABLE
            html += makeSectionHeader('CAPITAL CONTABLE');
            html += makeRow('Capital Social (aportaciones de socios)', 'capital_social');
            html += makeRow('Resultados de Ejercicios Anteriores', 'resultados_anteriores');
            html += makeRow('Utilidad del Ejercicio (vínculo automático al Edo. de Resultados)', 'utilidad_ejercicio');
            html += makeRow('TOTAL CAPITAL CONTABLE', 'total_capital', false, true);
            html += makeRow('TOTAL PASIVO + CAPITAL', 'total_pasivo_capital', false, true);

            // FILA DE CONTROL VITAL
            html += makeRow('► DIFERENCIA (Activo - Pasivo - Capital) · debe ser 0', 'diferencia_control', false, false, true);

            tbody.innerHTML = html;
        }

        // ==========================================
        // TABLA: FLUJO DE EFECTIVO NIF REAL
        // ==========================================
        function renderFlujoTable(sucursales, dataMap, totalRed) {
            const theadRow = document.getElementById('flujo-thead-row');
            theadRow.innerHTML = `<th class="sticky-col">Concepto</th>`;
            sucursales.forEach(s => {
                theadRow.innerHTML += `<th class="col-sucursal">${escapeHtml(s.nombre)}</th>`;
            });
            theadRow.innerHTML += `<th class="col-total-red">TOTAL RED</th>`;

            const tbody = document.getElementById('flujo-tbody');
            let html = '';

            function makeRow(label, key, isSubtotal = false, isMajor = false, isSpecial = false) {
                let trClass = '';
                if (isSpecial) trClass = 'tr-utilidad-neta';
                else if (isMajor) trClass = 'tr-total-major';
                else if (isSubtotal) trClass = 'tr-subtotal';

                let rowHtml = `<tr class="${trClass}"><td class="sticky-col">${label}</td>`;

                sucursales.forEach(s => {
                    const rowData = dataMap[s.id_valora_mas] || {};
                    rowHtml += `<td class="col-sucursal">${fmt(rowData[key])}</td>`;
                });

                rowHtml += `<td class="col-total-red">${fmt(totalRed[key])}</td></tr>`;
                return rowHtml;
            }

            function makeSectionHeader(title) {
                const totalCols = sucursales.length + 2;
                return `<tr class="tr-section-header"><td colspan="${totalCols}">${title}</td></tr>`;
            }

            html += makeRow('Efectivo Inicial Disponible del Período (Caja + Bancos)', 'efectivo_inicial', true);

            // ACTIVIDADES DE OPERACIÓN
            html += makeSectionHeader('ACTIVIDADES DE OPERACIÓN');
            html += makeRow('(+) Cobros por Refrendos — Capital', 'refrendos_capital');
            html += makeRow('(+) Cobros por Refrendos — Intereses', 'refrendos_intereses');
            html += makeRow('(+) Cobros por Desempeños — Capital', 'desempenos_capital');
            html += makeRow('(+) Cobros por Desempeños — Intereses', 'desempenos_intereses');
            html += makeRow('(+) Cobros por Venta de Bazar', 'cobros_bazar');
            html += makeRow('(+) Otros Ingresos Operativos Cobrados', 'otros_ingresos_cobrados');
            html += makeRow('(-) Nuevos Empeños Otorgados', 'nuevos_empenos_otorgados');
            html += makeRow('(-) Gastos de Operación Pagados', 'gastos_operacion_pagados');
            html += makeRow('(-) Gastos de Administración Pagados', 'gastos_admin_pagados');
            html += makeRow('(-) ISR y PTU Pagados', 'impuestos_pagados');
            html += makeRow('► Flujo Neto de Actividades de Operación', 'flujo_operacion', true);

            // ACTIVIDADES DE INVERSIÓN
            html += makeSectionHeader('ACTIVIDADES DE INVERSIÓN');
            html += makeRow('(-) Adquisición de Mobiliario, Transporte y Equipo', 'adquisicion_activo_fijo');
            html += makeRow('(+) Venta de Activo Fijo', 'venta_activo_fijo');
            html += makeRow('► Flujo Neto de Actividades de Inversión', 'flujo_inversion', true);

            // ACTIVIDADES DE FINANCIAMIENTO
            html += makeSectionHeader('ACTIVIDADES DE FINANCIAMIENTO');
            html += makeRow('(+) Aportaciones de Socios', 'aportaciones_socios');
            html += makeRow('(-) Retiros de Socios', 'retiros_socios');
            html += makeRow('(+) Disposición de Crédito Revolvente / Préstamos Bancarios', 'disposicion_creditos');
            html += makeRow('(-) Pago de Capital de Préstamos', 'pago_capital_prestamos');
            html += makeRow('(-) Pago de Intereses Bancarios', 'pago_intereses_bancarios');
            html += makeRow('► Flujo Neto de Actividades de Financiamiento', 'flujo_financiamiento', true);

            // RESUMEN FINAL
            html += makeRow('► INCREMENTO (DISMINUCIÓN) NETO DE EFECTIVO', 'incremento_neto_efectivo', false, true);
            html += makeRow('► EFECTIVO AL FINAL DEL PERÍODO', 'efectivo_final', false, false, true);

            tbody.innerHTML = html;
        }

        // ==========================================
        // EXPORTACIÓN A EXCEL (.xlsx)
        // ==========================================
        window.exportToExcel = function() {
            if (!globalData) {
                alert('No hay datos disponibles para exportar.');
                return;
            }

            const wb = XLSX.utils.book_new();

            // Hoja 1: Comparativa de Sucursales
            const wsRank = XLSX.utils.table_to_sheet(document.getElementById('ranking-table'));
            XLSX.utils.book_append_sheet(wb, wsRank, "Comparativa Sucursales");

            // Hoja 2: Estado de Resultados
            const wsPnl = XLSX.utils.table_to_sheet(document.getElementById('table-pnl'));
            XLSX.utils.book_append_sheet(wb, wsPnl, "Estado de Resultados");

            // Hoja 3: Balance General
            const wsBal = XLSX.utils.table_to_sheet(document.getElementById('table-balance'));
            XLSX.utils.book_append_sheet(wb, wsBal, "Balance General");

            // Hoja 4: Flujo de Efectivo
            const wsFlu = XLSX.utils.table_to_sheet(document.getElementById('table-flujo'));
            XLSX.utils.book_append_sheet(wb, wsFlu, "Flujo de Efectivo");

            const fi = document.getElementById('fecha_inicio').value;
            const ff = document.getElementById('fecha_fin').value;
            XLSX.writeFile(wb, `Gastos_y_Finanzas_Valora_Mas_${fi}_al_${ff}.xlsx`);
        };

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });
</script>
@endsection
