@extends('employees.layouts.main')

@section('title', 'Colaboradores y Productividad')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease;
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
        .bg-light-success { background-color: rgba(25, 135, 84, 0.12); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.12); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.12); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.15); }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.12); }
        .bg-light-secondary { background-color: rgba(108, 117, 125, 0.12); }

        .table-responsive { overflow-x: auto; }
        .ls-1 { letter-spacing: 0.5px; }

        .metric-tooltip {
            cursor: pointer;
            border-bottom: 1px dashed #6c757d;
            display: inline-block;
        }

        /* Nav Pills Styling */
        .nav-pills .nav-link {
            border-radius: 50rem;
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease-in-out;
        }
        .nav-pills .nav-link:hover {
            background-color: #e9ecef;
            color: #0d6efd;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
            color: #ffffff;
            border-color: #0d6efd;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
        }

        /* Quick Range Buttons */
        .quick-range-btn {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
        }
        .quick-range-btn.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        /* Category Chips por Ramos Oficiales */
        .cat-chip {
            display: inline-block;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 50rem;
            background-color: #f1f3f5;
            color: #343a40;
            border: 1px solid #dee2e6;
            margin-right: 0.3rem;
            margin-bottom: 0.2rem;
            white-space: nowrap;
        }
        .chip-oro {
            background-color: #fff9db !important;
            color: #856404 !important;
            border-color: #ffe066 !important;
        }
        .chip-plata {
            background-color: #f1f3f5 !important;
            color: #495057 !important;
            border-color: #ced4da !important;
        }
        .chip-varios {
            background-color: #e7f5ff !important;
            color: #1971c2 !important;
            border-color: #a5d8ff !important;
        }
        .chip-autos {
            background-color: #fff5f5 !important;
            color: #c92a2a !important;
            border-color: #ffc9c9 !important;
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
        .spinner-border { width: 3rem; height: 3rem; }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold" id="loading-text">Calculando productividad del personal y valuadores...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <!-- Encabezado y Filtros Rápidos -->
    <div class="row mb-4">
        <div class="col-12 col-md-6">
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-people-fill text-primary me-2"></i>Colaboradores y Productividad
            </h3>
            <p class="text-muted mb-0">Análisis ejecutivo de nómina, rendimiento individual y calidad de valuación</p>
        </div>
        <div class="col-12 col-md-6 d-flex justify-content-md-end align-items-center mt-3 mt-md-0 flex-wrap gap-2">
            <span class="text-muted small fw-semibold me-1">Atajos rápidos:</span>
            <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn active" data-range="este-mes">Este Mes</button>
            <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="mes-anterior">Mes Anterior</button>
            <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="este-anio">Este Año</button>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-3 p-md-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales Consolidadas --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}" class="form-control">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Badge Informativo de Selección -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge bg-light text-dark border px-3 py-2" id="active-range-badge">
            <i class="bi bi-calendar3 me-1"></i> Cargando periodo...
        </span>
    </div>

    <!-- ============================================================= -->
    <!-- 4 KPIS PRINCIPALES DE PRODUCTIVIDAD (REQS 85, 86, 87, 88)      -->
    <!-- ============================================================= -->
    <div class="row mb-4">
        <!-- 85. Nómina Total -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-danger border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-nomina" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de nómina...">
                                Nómina Total <i class="bi bi-info-circle ms-1 small text-danger"></i>
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-nomina-total">$ 0.00</h2>
                    <span class="text-muted small">Σ Sueldo de Colaboradores Activos</span>
                    <div class="mt-2 text-muted small pt-2 border-top">
                        Costo Promedio: <span class="fw-bold text-danger" id="kpi-costo-promedio">$ 0.00</span> (<span class="fw-bold text-dark" id="kpi-num-empleados">0</span> colab.)
                    </div>
                </div>
            </div>
        </div>

        <!-- 86. Venta por Empleado -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-primary border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-venta-empleado" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de venta...">
                                Venta por Empleado <i class="bi bi-info-circle ms-1 small text-primary"></i>
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-cart-check-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-venta-promedio">$ 0.00</h2>
                    <span class="text-muted small">Ventas Totales ÷ Empleados</span>
                    <div class="mt-2 text-muted small pt-2 border-top">
                        Tickets Promedio: <span class="fw-bold text-dark" id="kpi-tickets-venta-promedio">0</span> tickets / colaborador
                    </div>
                </div>
            </div>
        </div>

        <!-- 87. Utilidad Bruta por Empleado -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-success border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-utilidad-empleado" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose de utilidad...">
                                Utilidad por Empleado <i class="bi bi-info-circle ms-1 small text-success"></i>
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ut-bruta-promedio">$ 0.00</h2>
                    <span class="text-muted small">Utilidad Bruta ÷ Empleados</span>
                    <div class="mt-2 text-muted small pt-2 border-top">
                        Margen Total: <span class="fw-bold text-success" id="kpi-ut-bruta-global">$ 0.00</span> (Ventas - Costos)
                    </div>
                </div>
            </div>
        </div>

        <!-- 88. Ratio Utilidad Bruta / Costo por Empleado (Meta 3-4x) -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3 border-start border-warning border-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">
                            <span class="metric-tooltip" id="tooltip-ratio-ub" data-bs-toggle="tooltip" data-bs-html="true" title="Cargando desglose del multiplicador...">
                                Retorno UB / Costo <i class="bi bi-info-circle ms-1 small text-warning"></i>
                            </span>
                        </h6>
                        <div class="icon-shape bg-light-warning text-warning">
                            <i class="bi bi-trophy-fill"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ratio-ub">0.00x</h2>
                        <span class="badge bg-success rounded-pill px-2 py-1 small" id="badge-meta">Meta: 3x-4x</span>
                    </div>
                    <span class="text-muted small">Utilidad Bruta ÷ Nómina Total</span>
                    <div class="mt-2 text-muted small pt-2 border-top" id="kpi-ratio-status">
                        Multiplicador sobre gasto en nómina
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- 89 Y 91. GRÁFICOS: BARRAS TRIPLES Y EFICIENCIA POR SUCURSAL    -->
    <!-- ============================================================= -->
    <div class="row mb-4">
        <!-- 89. Gráfico de barras: venta, utilidad y costo por empleado -->
        <div class="col-12 col-xl-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-bar-chart-fill text-primary me-2"></i>Venta, Utilidad y Costo por Colaborador
                        </h5>
                        <small class="text-muted">Comparativa individual de los colaboradores más productivos</small>
                    </div>
                </div>
                <div class="card-body p-4 d-flex justify-content-center align-items-center">
                    <canvas id="barraTripleChart" height="280"></canvas>
                </div>
            </div>
        </div>

        <!-- 91. Indicadores: Ratios de Nómina vs Utilidad por Sucursal -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-buildings-fill text-success me-2"></i>Eficiencia por Sucursal
                    </h5>
                    <small class="text-muted">Retorno de utilidad frente al gasto de nómina</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold text-end">Nómina</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold text-end">Utilidad</th>
                                    <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-center">Retorno</th>
                                </tr>
                            </thead>
                            <tbody id="ratios-sucursal-body">
                                <tr><td colspan="4" class="text-center text-muted py-3">Cargando sucursales...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i> Retorno mayor a 1.0x indica que la utilidad cubre la nómina.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- RESUMEN CONSOLIDADO: AVALÚOS POR CATEGORÍA (CANTIDAD Y MONTO)  -->
    <!-- ============================================================= -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-pie-chart-fill text-info me-2"></i>Distribución de Avalúos por Ramos (Oro, Plata, Varios, Autos)
                        </h5>
                        <small class="text-muted">Desglose integral de avalúos y montos prestados clasificados en los 4 ramos oficiales</small>
                    </div>
                    <span class="badge bg-primary text-white px-3 py-2" id="badge-total-categorias">
                        4 Ramos Oficiales
                    </span>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="row g-3" id="contenedor-cards-categorias">
                        <div class="col-12 text-center text-muted py-3">Cargando categorías...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- PESTAÑAS: CALIDAD DE VALUACIÓN (92), ALERTAS (93) Y RANKING    -->
    <!-- ============================================================= -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills mb-3" id="colaboradoresTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-calidad-btn" data-bs-toggle="pill" data-bs-target="#tab-calidad-pane" type="button" role="tab" aria-selected="true">
                        <i class="bi bi-award-fill me-1 text-warning"></i> 92. Calidad del Valuador (Remate / Préstamo)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-alertas-btn" data-bs-toggle="pill" data-bs-target="#tab-alertas-pane" type="button" role="tab" aria-selected="false">
                        <i class="bi bi-shield-exclamation me-1 text-danger"></i> 93. Alertas de Avalúos Fuera de Rango
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-ranking-btn" data-bs-toggle="pill" data-bs-target="#tab-ranking-pane" type="button" role="tab" aria-selected="false">
                        <i class="bi bi-person-lines-fill me-1 text-primary"></i> Ranking Integral de Colaboradores
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="colaboradoresTabsContent">
                
                <!-- PESTAÑA 1: 92. TABLA CALIDAD DEL VALUADOR CON CATEGORÍAS -->
                <div class="tab-pane fade show active" id="tab-calidad-pane" role="tabpanel" aria-labelledby="tab-calidad-btn">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h5 class="fw-bold mb-0">
                                    <i class="bi bi-gem text-warning me-2"></i>Calidad del Valuador: Ratio Precio Remate / Monto Prestado
                                </h5>
                                <small class="text-muted">Efectividad de recuperación y categorías valuadas con desglose de montos</small>
                            </div>
                            <div style="max-width: 280px;" class="w-100">
                                <input type="text" id="filtro-valuador-input" class="form-control form-control-sm rounded-pill px-3" placeholder="🔍 Buscar valuador o sucursal...">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Valuador</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Avalúos Realizados</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Monto Prestado Total</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Avalúo Promedio</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center" style="min-width: 220px;">Categorías Valuadas (Monto y Piezas)</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center" title="Precio remate / Monto prestado">Ratio Calidad</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla-calidad-body">
                                        <tr><td colspan="8" class="text-center text-muted py-4">Cargando métricas de valuadores...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 2: 93. TABLA ALERTAS DE AVALÚOS FUERA DE RANGO -->
                <div class="tab-pane fade" id="tab-alertas-pane" role="tabpanel" aria-labelledby="tab-alertas-btn">
                    <div class="card shadow-sm border-0 rounded-3 border-start border-danger border-4">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold mb-0 text-danger">
                                    <i class="bi bi-exclamation-octagon-fill me-2"></i>Valuadores con Avalúos Fuera del Rango de Sucursal
                                </h5>
                                <small class="text-muted">Desviación sistemática de valuadores frente al promedio de su punto de venta</small>
                            </div>
                            <span class="badge bg-danger rounded-pill px-3 py-2" id="badge-total-alertas">0 Alertas detectadas</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Valuador</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Contratos Evaluados</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Avalúo Promedio</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Ratio Préstamo/Avalúo</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center">Desviación vs Sucursal</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Nivel de Alerta</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla-alertas-body">
                                        <tr><td colspan="7" class="text-center text-muted py-4">Analizando desviaciones de avalúos...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 3: RANKING INTEGRAL DE COLABORADORES -->
                <div class="tab-pane fade" id="tab-ranking-pane" role="tabpanel" aria-labelledby="tab-ranking-btn">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h5 class="fw-bold mb-0">
                                    <i class="bi bi-trophy text-primary me-2"></i>Productividad y Retorno por Colaborador
                                </h5>
                                <small class="text-muted">Ventas, margen de utilidad y retorno de inversión laboral individual</small>
                            </div>
                            <div style="max-width: 260px;" class="w-100">
                                <input type="text" id="filtro-ranking-input" class="form-control form-control-sm rounded-pill px-3" placeholder="🔍 Buscar colaborador...">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Colaborador</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-center text-primary">Operaciones</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ventas ($)</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end text-success">Utilidad Bruta</th>
                                            <th class="py-3 text-uppercase text-muted small fw-bold text-end text-danger">Costo Est.</th>
                                            <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center">Multiplicador ROI</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla-ranking-body">
                                        <tr><td colspan="7" class="text-center text-muted py-4">Cargando colaboradores...</td></tr>
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

<!-- ============================================================= -->
<!-- MODAL: DESGLOSE COMPLETO DE AVALÚOS POR CATEGORÍA DEL VALUADOR -->
<!-- ============================================================= -->
<div class="modal fade" id="modalDesgloseCategorias" tabindex="-1" aria-labelledby="modalDesgloseCategoriasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <div>
                    <h5 class="modal-title fw-bold" id="modalDesgloseCategoriasLabel">
                        <i class="bi bi-diagram-3-fill me-2"></i>Detalle de Avalúos: Tipos de Movimiento y Ramos
                    </h5>
                    <small class="text-white-50" id="modal-valuador-subtitulo">Valuador: ...</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Mini KPIs en Modal -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="bg-light p-3 rounded-3 text-center border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.75rem;">Avalúos Realizados</small>
                            <h4 class="fw-bold text-dark mb-0 mt-1" id="modal-kpi-piezas">0</h4>
                            <small class="text-muted" style="font-size: 0.7rem;">piezas totales</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light p-3 rounded-3 text-center border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.75rem;">Monto Valuado</small>
                            <h4 class="fw-bold text-primary mb-0 mt-1" id="modal-kpi-avaluo">$ 0.00</h4>
                            <small class="text-muted" style="font-size: 0.7rem;">valor total asignado</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light p-3 rounded-3 text-center border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.75rem;">Monto Prestado</small>
                            <h4 class="fw-bold text-success mb-0 mt-1" id="modal-kpi-prestamo">$ 0.00</h4>
                            <small class="text-muted" style="font-size: 0.7rem;">dinero otorgado</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-light p-3 rounded-3 text-center border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.75rem;">Operación Principal</small>
                            <h5 class="fw-bold text-info mb-0 mt-1 text-truncate" id="modal-kpi-top-mov">-</h5>
                            <small class="text-muted" style="font-size: 0.7rem;" id="modal-kpi-top-cat-sub">Ramo: -</small>
                        </div>
                    </div>
                </div>

                <!-- Nav Tabs para alternar vistas del desglose -->
                <ul class="nav nav-pills mb-3 gap-2" id="modalDesgloseTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1 fw-semibold small" id="tab-movimientos-btn" data-bs-toggle="pill" data-bs-target="#tab-modal-movimientos" type="button" role="tab">
                            <i class="bi bi-arrow-left-right me-1"></i>Por Tipo de Movimiento
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 fw-semibold small" id="tab-categorias-btn" data-bs-toggle="pill" data-bs-target="#tab-modal-categorias" type="button" role="tab">
                            <i class="bi bi-grid-3x3-gap-fill me-1"></i>Por Ramo / Categoría
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 fw-semibold small" id="tab-cruzado-btn" data-bs-toggle="pill" data-bs-target="#tab-modal-cruzado" type="button" role="tab">
                            <i class="bi bi-table me-1"></i>Detalle Completo (Movimiento + Ramo)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="modalDesgloseTabContent">
                    <!-- Tab 1: Tipos de Movimiento -->
                    <div class="tab-pane fade show active" id="tab-modal-movimientos" role="tabpanel">
                        <div class="table-responsive rounded-3 border mb-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Tipo de Movimiento</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-center">Avalúos (Piezas)</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-center" style="min-width: 140px;">% del Total</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Monto Valuado ($)</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Monto Prestado ($)</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">Promedio / Pieza</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-movimientos-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando desglose de movimientos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Categorías / Ramos -->
                    <div class="tab-pane fade" id="tab-modal-categorias" role="tabpanel">
                        <div class="table-responsive rounded-3 border mb-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Ramo / Categoría</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-center">Avalúos (Piezas)</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-center" style="min-width: 140px;">% del Total</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Monto Valuado ($)</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Monto Prestado ($)</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">Promedio / Pieza</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-categorias-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando desglose de categorías...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 3: Detalle Cruzado -->
                    <div class="tab-pane fade" id="tab-modal-cruzado" role="tabpanel">
                        <div class="table-responsive rounded-3 border mb-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3 py-2 text-uppercase text-muted small fw-bold">Tipo Movimiento</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold">Ramo / Categoría</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-center">Avalúos</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Monto Valuado ($)</th>
                                        <th class="py-2 text-uppercase text-muted small fw-bold text-end">Monto Prestado ($)</th>
                                        <th class="pe-3 py-2 text-uppercase text-muted small fw-bold text-end">Promedio / Pieza</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-cruzado-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando detalle...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cerrar</button>
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

        // Formatters
        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2 });
        const compactFormatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 });
        const numberFormatter = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

        // DOM elements
        const overlay = document.getElementById('loading-overlay');
        const loadingText = document.getElementById('loading-text');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const inputInicio = document.getElementById('fecha_inicio');
        const inputFin = document.getElementById('fecha_fin');
        const sucursalSelect = document.getElementById('sucursal_id');
        const activeRangeBadge = document.getElementById('active-range-badge');

        // Charts
        let barraTripleChart = null;

        // Data cache for client-side search
        let rawValuadoresCalidad = [];
        let rawValuadoresAlerta = [];
        let rawRankingColaboradores = [];

        // Quick date ranges
        document.querySelectorAll('.quick-range-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const range = this.dataset.range;
                const now = new Date();

                if (range === 'este-mes') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                    inputInicio.value = formatDateString(firstDay);
                    inputFin.value = formatDateString(now);
                } else if (range === 'mes-anterior') {
                    const firstDayPrev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const lastDayPrev = new Date(now.getFullYear(), now.getMonth(), 0);
                    inputInicio.value = formatDateString(firstDayPrev);
                    inputFin.value = formatDateString(lastDayPrev);
                } else if (range === 'este-anio') {
                    const firstDayYear = new Date(now.getFullYear(), 0, 1);
                    inputInicio.value = formatDateString(firstDayYear);
                    inputFin.value = formatDateString(now);
                }

                loadDashboardData();
            });
        });

        function formatDateString(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
            loadDashboardData();
        });

        // Search inputs
        const filtroValuadorInput = document.getElementById('filtro-valuador-input');
        if (filtroValuadorInput) {
            filtroValuadorInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const filtered = rawValuadoresCalidad.filter(v => 
                    (v.valuador && v.valuador.toLowerCase().includes(query)) ||
                    (v.sucursal && v.sucursal.toLowerCase().includes(query))
                );
                renderValuadoresCalidadTable(filtered);
            });
        }

        const filtroRankingInput = document.getElementById('filtro-ranking-input');
        if (filtroRankingInput) {
            filtroRankingInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const filtered = rawRankingColaboradores.filter(c => 
                    (c.empleado && c.empleado.toLowerCase().includes(query)) ||
                    (c.sucursal && c.sucursal.toLowerCase().includes(query))
                );
                renderRankingTable(filtered);
            });
        }

        // Tab change resize charts
        document.querySelectorAll('#colaboradoresTabs button[data-bs-toggle="pill"]').forEach(tabBtn => {
            tabBtn.addEventListener('shown.bs.tab', function(e) {
                if (barraTripleChart) barraTripleChart.resize();
            });
        });

        // Initial Load
        loadDashboardData();

        function loadDashboardData() {
            overlay.style.display = 'flex';
            loadingText.innerText = 'Calculando productividad del personal y valuadores...';
            dashboard.style.opacity = '0.3';

            const sucursalText = sucursalSelect.options[sucursalSelect.selectedIndex].text;
            activeRangeBadge.innerHTML = `<i class="bi bi-calendar3 me-1"></i> Rango: ${inputInicio.value} al ${inputFin.value} | ${sucursalText}`;

            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            fetch(`{{ route('colaboradores.data') }}?${params}`)
                .then(res => {
                    if (!res.ok) throw new Error('Error al cargar datos de colaboradores');
                    return res.json();
                })
                .then(data => {
                    updateDashboardUI(data);
                })
                .catch(err => {
                    console.error("Error Colaboradores:", err);
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

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function updateDashboardUI(data) {
            // 85. Nómina Total y Plantilla
            const nominaTot = data.nominaTotal || 0;
            const plantilla = data.numEmpleados || 0;
            const costoProm = data.costoPromedioEmpleado || 0;
            const criterioLabel = data.criterioEmpleados || (data.esFiltroSucursal ? 'Colaboradores Operativos (≥ 5 mov.)' : 'Colaboradores Activos en Intranet');

            updateElementText('kpi-nomina-total', formatter.format(nominaTot));
            updateElementText('kpi-num-empleados', numberFormatter.format(plantilla));
            updateElementText('kpi-costo-promedio', formatter.format(costoProm));

            const empEl = document.getElementById('kpi-num-empleados');
            if (empEl && empEl.parentElement) {
                empEl.parentElement.setAttribute('title', criterioLabel);
            }

            setCustomTooltip('tooltip-nomina', `
                <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-danger"><i class="bi bi-cash-coin me-1"></i>Fórmula de Nómina y Costo:</strong>
                    <div class="d-flex justify-content-between"><span>(+) Total Nómina (Suma de Sueldos):</span> <span class="fw-bold text-danger">${formatter.format(nominaTot)}</span></div>
                    <div class="d-flex justify-content-between"><span>(÷) ${escapeHtml(criterioLabel)}:</span> <span class="fw-bold text-dark">${numberFormatter.format(plantilla)} personas</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>(=) COSTO PROM. / EMPLEADO:</span> <span class="text-danger">${formatter.format(costoProm)}</span></div>
                    <small class="text-muted d-block mt-1">${data.esFiltroSucursal ? 'Personal operativo con al menos 5 movimientos en la sucursal (excluye visitas de líderes).' : 'Total de colaboradores activos en Intranet sin duplicidad de líderes entre sucursales.'}</small>
                </div>
            `);

            // 86. Venta por Empleado
            const ventaProm = data.ventaPromedioEmpleado || 0;
            const ticketsProm = data.ticketsVentaPromedioEmpleado || 0;
            const ventaTot = data.ventaTotalMonto || 0;
            const ticketsTot = data.ventaTotalTickets || 0;
            const ticketPromedio = ticketsTot > 0 ? (ventaTot / ticketsTot) : 0;
            updateElementText('kpi-venta-promedio', formatter.format(ventaProm));
            updateElementText('kpi-tickets-venta-promedio', numberFormatter.format(ticketsProm));

            setCustomTooltip('tooltip-venta-empleado', `
                <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-primary"><i class="bi bi-cart-check-fill me-1"></i>Fórmula de Venta por Empleado:</strong>
                    <div class="d-flex justify-content-between"><span>(+) Ventas Totales Globales:</span> <span class="fw-bold text-success">${formatter.format(ventaTot)}</span></div>
                    <div class="d-flex justify-content-between"><span>(÷) ${escapeHtml(criterioLabel)}:</span> <span class="fw-bold text-dark">${numberFormatter.format(plantilla)} personas</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>(=) VENTA PROM. / EMPLEADO:</span> <span class="text-primary">${formatter.format(ventaProm)}</span></div>
                    <div class="d-flex justify-content-between text-muted small mt-1"><span>Total Tickets Venta:</span> <span>${numberFormatter.format(ticketsTot)} tickets</span></div>
                    <div class="d-flex justify-content-between text-muted small"><span>Ticket Promedio ($):</span> <span>${formatter.format(ticketPromedio)}</span></div>
                </div>
            `);

            // 87. Utilidad Bruta por Empleado
            const utProm = data.utilidadBrutaPromedioEmpleado || 0;
            const utGlobal = data.utilidadBrutaGlobal || 0;
            const costoInv = data.ventaTotalCosto || Math.max(0, ventaTot - utGlobal);
            const margenPct = ventaTot > 0 ? ((utGlobal / ventaTot) * 100) : 0;
            updateElementText('kpi-ut-bruta-promedio', formatter.format(utProm));
            updateElementText('kpi-ut-bruta-global', formatter.format(utGlobal));

            setCustomTooltip('tooltip-utilidad-empleado', `
                <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-success"><i class="bi bi-graph-up-arrow me-1"></i>Fórmula de Utilidad por Empleado:</strong>
                    <div class="d-flex justify-content-between"><span>(+) Venta Total Global:</span> <span class="fw-bold text-dark">${formatter.format(ventaTot)}</span></div>
                    <div class="d-flex justify-content-between"><span>(-) Costo de Inventario:</span> <span class="fw-bold text-danger">${formatter.format(costoInv)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between"><span>(=) Utilidad Bruta Global:</span> <span class="fw-bold text-success">${formatter.format(utGlobal)}</span></div>
                    <div class="d-flex justify-content-between"><span>(÷) ${escapeHtml(criterioLabel)}:</span> <span class="fw-bold text-dark">${numberFormatter.format(plantilla)} personas</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>(=) UTILIDAD PROM. / EMPLEADO:</span> <span class="text-success">${formatter.format(utProm)}</span></div>
                    <div class="d-flex justify-content-between text-muted small mt-1"><span>Margen Bruto Obtenido:</span> <span class="fw-semibold text-success">${margenPct.toFixed(1)}%</span></div>
                </div>
            `);

            // 88. Ratio Retorno UB / Costo
            const ratioUb = data.ratioUBvsCosto || 0;
            updateElementText('kpi-ratio-ub', ratioUb.toFixed(2) + 'x');
            
            const badgeMeta = document.getElementById('badge-meta');
            const ratioStatus = document.getElementById('kpi-ratio-status');
            if (ratioUb >= 3.0) {
                if (badgeMeta) { badgeMeta.className = 'badge bg-success rounded-pill px-2 py-1 small'; badgeMeta.innerText = 'Meta Cumplida (3x-4x)'; }
                if (ratioStatus) { ratioStatus.innerText = 'Excelente retorno sobre inversión laboral'; }
            } else if (ratioUb >= 1.5) {
                if (badgeMeta) { badgeMeta.className = 'badge bg-warning text-dark rounded-pill px-2 py-1 small'; badgeMeta.innerText = 'Retorno Moderado'; }
                if (ratioStatus) { ratioStatus.innerText = 'Rendimiento aceptable, meta sugerida 3.0x'; }
            } else {
                if (badgeMeta) { badgeMeta.className = 'badge bg-danger rounded-pill px-2 py-1 small'; badgeMeta.innerText = 'Bajo Retorno (<1.5x)'; }
                if (ratioStatus) { ratioStatus.innerText = 'Costo de nómina alto frente a utilidad generada'; }
            }

            setCustomTooltip('tooltip-ratio-ub', `
                <div class="custom-tooltip text-start" style="font-size:0.8rem; line-height: 1.5; min-width: 260px;">
                    <strong class="d-block mb-1 border-bottom pb-1 text-warning"><i class="bi bi-trophy-fill me-1"></i>Fórmula Retorno UB / Costo:</strong>
                    <div class="d-flex justify-content-between"><span>(+) Utilidad Bruta Global:</span> <span class="fw-bold text-success">${formatter.format(utGlobal)}</span></div>
                    <div class="d-flex justify-content-between"><span>(÷) Gasto en Nómina Total:</span> <span class="fw-bold text-danger">${formatter.format(nominaTot)}</span></div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold"><span>(=) MULTIPLICADOR DE RETORNO:</span> <span class="text-warning">${ratioUb.toFixed(2)}x</span></div>
                    <div class="d-flex justify-content-between text-muted small mt-1"><span>Meta Operativa:</span> <span class="fw-bold text-success">3.00x a 4.00x</span></div>
                    <small class="text-muted d-block mt-1 border-top pt-1">Por cada $1.00 invertido en nómina, la empresa genera $${ratioUb.toFixed(2)} de utilidad bruta.</small>
                </div>
            `);

            // 89. Gráfico de Barras Triple (Venta, Utilidad y Costo por Empleado)
            renderBarraTripleChart(data.chartBarraTriple);

            // 91. Ratios Nómina vs Utilidad por Sucursal
            renderRatiosSucursalTable(data.ratiosSucursales || []);

            // Resumen Consolidado de Categorías de Avalúos
            renderCategoriasGlobalesCards(data.categoriasResumen || []);

            // 92. Tabla de Calidad de Valuadores con Categorías
            rawValuadoresCalidad = data.valuadoresCalidad || [];
            renderValuadoresCalidadTable(rawValuadoresCalidad);

            // 93. Tabla de Alertas de Valuadores Fuera de Rango
            rawValuadoresAlerta = data.valuadoresAlerta || [];
            renderValuadoresAlertaTable(rawValuadoresAlerta);

            // Ranking Integral de Colaboradores
            rawRankingColaboradores = data.rankingColaboradores || [];
            renderRankingTable(rawRankingColaboradores);
        }

        // Render Cards de Categorías Globales (Estrictamente los 4 Ramos Oficiales)
        function renderCategoriasGlobalesCards(categorias) {
            const container = document.getElementById('contenedor-cards-categorias');
            const badgeTotal = document.getElementById('badge-total-categorias');
            if (!container) return;

            if (badgeTotal) {
                badgeTotal.innerText = `4 Ramos Oficiales`;
            }

            if (!categorias || categorias.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-muted py-3">No hay avalúos registrados en el periodo</div>';
                return;
            }

            const ramoConfigs = {
                'ORO': {
                    title: 'ORO',
                    subtitle: 'Alhajas y Joyería de Oro',
                    icon: 'bi-gem',
                    iconColor: 'text-warning',
                    borderClass: 'border-start border-4 border-warning',
                    bgBadge: 'bg-warning text-dark',
                    bgLight: '#fffdf5'
                },
                'PLATA': {
                    title: 'PLATA',
                    subtitle: 'Prendas y Joyería Ley .925',
                    icon: 'bi-circle-fill',
                    iconColor: 'text-secondary',
                    borderClass: 'border-start border-4 border-secondary',
                    bgBadge: 'bg-secondary text-white',
                    bgLight: '#f8f9fa'
                },
                'VARIOS': {
                    title: 'VARIOS',
                    subtitle: 'Electrónicos, Herramientas, etc.',
                    icon: 'bi-box-seam-fill',
                    iconColor: 'text-primary',
                    borderClass: 'border-start border-4 border-primary',
                    bgBadge: 'bg-primary text-white',
                    bgLight: '#f5f9ff'
                },
                'AUTOS': {
                    title: 'AUTOS',
                    subtitle: 'Vehículos Automotores',
                    icon: 'bi-car-front-fill',
                    iconColor: 'text-danger',
                    borderClass: 'border-start border-4 border-danger',
                    bgBadge: 'bg-danger text-white',
                    bgLight: '#fff5f5'
                }
            };

            let html = '';
            categorias.forEach(cat => {
                const cfg = ramoConfigs[cat.categoria] || {
                    title: cat.categoria,
                    subtitle: 'Categoría Operativa',
                    icon: 'bi-tag-fill',
                    iconColor: 'text-info',
                    borderClass: 'border-start border-4 border-info',
                    bgBadge: 'bg-info text-white',
                    bgLight: '#ffffff'
                };

                html += `
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="p-3 rounded-3 shadow-sm ${cfg.borderClass} h-100 card-hover" style="background-color: ${cfg.bgLight}; border-top: 1px solid #e9ecef; border-right: 1px solid #e9ecef; border-bottom: 1px solid #e9ecef;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi ${cfg.icon} ${cfg.iconColor} fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">${cfg.title}</h6>
                                        <small class="text-muted" style="font-size: 0.7rem;">${cfg.subtitle}</small>
                                    </div>
                                </div>
                                <span class="badge ${cfg.bgBadge} rounded-pill px-2 py-1" style="font-size: 0.75rem;">${cat.pct_monto || 0}%</span>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Monto Avalúo Total</small>
                                <h4 class="fw-bold text-dark mb-0">${formatter.format(cat.monto_avaluo || 0)}</h4>
                                <span class="text-muted small">Préstamo ÷ Avalúo: ${(cat.monto_avaluo > 0 ? (cat.monto_prestamo / cat.monto_avaluo * 100).toFixed(1) : '0.0')}%</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top text-muted" style="font-size: 0.78rem;">
                                <span><i class="bi bi-cash-stack text-success me-1"></i>Préstamo: <strong>${compactFormatter.format(cat.monto_prestamo || 0)}</strong></span>
                                <span><i class="bi bi-stack text-muted me-1"></i><strong>${numberFormatter.format(cat.cantidad || 0)}</strong> ${cat.categoria === 'AUTOS' ? 'autos' : 'prendas'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        // 89. Render Gráfico de Barras Triple
        function renderBarraTripleChart(chartData) {
            const ctx = document.getElementById('barraTripleChart');
            if (!ctx) return;
            if (barraTripleChart) barraTripleChart.destroy();
            if (!chartData || !chartData.labels || chartData.labels.length === 0) return;

            barraTripleChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ventas Realizadas ($)',
                            data: chartData.ventas || [],
                            backgroundColor: 'rgba(13, 110, 253, 0.85)',
                            borderRadius: 4
                        },
                        {
                            label: 'Utilidad Bruta ($)',
                            data: chartData.utilidad || [],
                            backgroundColor: 'rgba(25, 135, 84, 0.85)',
                            borderRadius: 4
                        },
                        {
                            label: 'Costo Est. Nómina ($)',
                            data: chartData.costo || [],
                            backgroundColor: 'rgba(220, 53, 69, 0.85)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.dataset.label}: ${formatter.format(ctx.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: { callback: v => formatter.format(v) },
                            title: { display: true, text: 'Importe en Pesos ($)' }
                        }
                    }
                }
            });
        }

        // 91. Render Ratios Nómina vs Utilidad por Sucursal
        function renderRatiosSucursalTable(sucursales) {
            const tbody = document.getElementById('ratios-sucursal-body');
            if (!tbody) return;

            if (!sucursales || sucursales.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No hay datos de sucursales disponibles</td></tr>';
                return;
            }

            let html = '';
            sucursales.forEach(s => {
                const ratio = s.ratio_utilidad_nomina || 0;
                let badgeClass = 'bg-danger text-white';
                if (ratio >= 2.0) badgeClass = 'bg-success text-white';
                else if (ratio >= 1.0) badgeClass = 'bg-warning text-dark';

                html += `
                    <tr>
                        <td class="ps-3 py-2 fw-semibold text-dark">
                            ${escapeHtml(s.sucursal)}
                            <span class="badge bg-light text-secondary border ms-1" style="font-size:0.72rem;" title="${s.empleados || 0} colaboradores operativos con ≥ 5 movimientos">${s.empleados || 0} colab.</span>
                        </td>
                        <td class="py-2 text-end text-danger">${compactFormatter.format(s.nomina || 0)}</td>
                        <td class="py-2 text-end text-success fw-bold">${compactFormatter.format(s.utilidad || 0)}</td>
                        <td class="pe-3 py-2 text-center">
                            <span class="badge ${badgeClass} rounded-pill px-2 py-1">${ratio.toFixed(2)}x</span>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        // 92. Render Tabla Calidad del Valuador con Categorías
        function renderValuadoresCalidadTable(items) {
            const tbody = document.getElementById('tabla-calidad-body');
            if (!tbody) return;

            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron valuadores</td></tr>';
                return;
            }

            let html = '';
            items.forEach((v, index) => {
                const ratioCalidad = v.ratio_calidad || 0;
                let badgeClass = 'bg-secondary';
                if (ratioCalidad >= 1.8) badgeClass = 'bg-success text-white';
                else if (ratioCalidad >= 1.4) badgeClass = 'bg-primary text-white';
                else if (ratioCalidad >= 1.0) badgeClass = 'bg-warning text-dark';
                else badgeClass = 'bg-danger text-white';

                // Categorías en formato de fichas de los 4 ramos
                let catBadges = '';
                if (v.top_categorias && v.top_categorias.length > 0) {
                    v.top_categorias.forEach(c => {
                        let chipClass = 'cat-chip';
                        let icon = 'bi-tag';
                        if (c.categoria === 'ORO') { chipClass += ' chip-oro'; icon = 'bi-gem text-warning'; }
                        else if (c.categoria === 'PLATA') { chipClass += ' chip-plata'; icon = 'bi-circle-fill text-secondary'; }
                        else if (c.categoria === 'VARIOS') { chipClass += ' chip-varios'; icon = 'bi-box-seam-fill text-primary'; }
                        else if (c.categoria === 'AUTOS') { chipClass += ' chip-autos'; icon = 'bi-car-front-fill text-danger'; }

                        catBadges += `<span class="${chipClass}" title="Avalúo: ${formatter.format(c.monto_avaluo)} | Préstamo: ${formatter.format(c.monto_prestamo)}"><i class="bi ${icon} me-1"></i><strong>${escapeHtml(c.categoria)}:</strong> ${compactFormatter.format(c.monto_avaluo)} <span class="text-muted">(${numberFormatter.format(c.cantidad)})</span></span>`;
                    });
                } else {
                    catBadges = '<span class="text-muted small">Sin avalúos registrados</span>';
                }

                html += `
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark">
                            <i class="bi bi-person-circle text-primary me-2"></i>${escapeHtml(v.valuador)}
                        </td>
                        <td class="py-3 text-muted">${escapeHtml(v.sucursal)}</td>
                        <td class="py-3 text-center fw-semibold">${numberFormatter.format(v.total_avaluos || 0)}</td>
                        <td class="py-3 text-end text-dark">${formatter.format(v.total_prestado || 0)}</td>
                        <td class="py-3 text-end text-muted">${formatter.format(v.prom_avaluo || 0)}</td>
                        <td class="py-3 text-center">${catBadges}</td>
                        <td class="py-3 text-center">
                            <span class="badge ${badgeClass} rounded-pill px-3 py-2 fw-bold" style="font-size: 0.85rem;">
                                ${ratioCalidad.toFixed(2)}x
                            </span>
                        </td>
                        <td class="pe-4 py-3 text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 btn-ver-categorias" data-index="${index}">
                                <i class="bi bi-diagram-3-fill me-1"></i>Detalle (${numberFormatter.format(v.total_avaluos || 0)})
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

            // Bind click to open modal
            document.querySelectorAll('.btn-ver-categorias').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.index);
                    const val = rawValuadoresCalidad[idx];
                    if (val) abrirModalCategorias(val);
                });
            });
        }

        // 93. Render Tabla Alertas Valuadores Fuera de Rango
        function renderValuadoresAlertaTable(items) {
            const tbody = document.getElementById('tabla-alertas-body');
            const badgeAlertas = document.getElementById('badge-total-alertas');
            if (!tbody) return;

            if (badgeAlertas) {
                badgeAlertas.innerText = `${items.length} ${items.length === 1 ? 'caso' : 'casos'}`;
            }

            if (items.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-success">
                            <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                            <strong>¡Excelente! Todos los valuadores se encuentran dentro de los rangos normales de operación.</strong>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            items.forEach(v => {
                let badgeClass = 'bg-danger';
                if (v.nivel_alerta === 'moderada') badgeClass = 'bg-warning text-dark';

                html += `
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark">
                            <i class="bi bi-person-exclamation text-danger me-2"></i>${escapeHtml(v.valuador)}
                        </td>
                        <td class="py-3 text-muted">${escapeHtml(v.sucursal)}</td>
                        <td class="py-3 text-center">
                            <span class="badge ${badgeClass} rounded-pill px-3 py-2 fw-bold text-uppercase">
                                ${escapeHtml(v.motivo)}
                            </span>
                        </td>
                        <td class="py-3 text-end fw-bold text-danger">${v.ratio_calidad ? v.ratio_calidad.toFixed(2) + 'x' : 'N/A'}</td>
                        <td class="py-3 text-center fw-semibold">${numberFormatter.format(v.total_avaluos || 0)}</td>
                        <td class="py-3 text-end text-dark">${formatter.format(v.total_prestado || 0)}</td>
                        <td class="pe-4 py-3 text-muted small">${escapeHtml(v.recomendacion || 'Revisar operación')}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        // 94. Render Tabla ROI Colaborador
        function renderColaboradoresRoiTable(items) {
            const tbody = document.getElementById('tabla-colaboradores-roi-body');
            if (!tbody) return;

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay datos disponibles para el período</td></tr>';
                return;
            }

            let html = '';
            items.forEach(c => {
                const roi = c.roi || 0;
                let badgeClass = 'bg-danger text-white';
                if (roi >= 3.0) badgeClass = 'bg-success text-white';
                else if (roi >= 1.5) badgeClass = 'bg-primary text-white';
                else if (roi >= 1.0) badgeClass = 'bg-warning text-dark';

                html += `
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark">
                            <i class="bi bi-person-badge text-primary me-2"></i>${escapeHtml(c.nombre)}
                        </td>
                        <td class="py-3 text-muted">${escapeHtml(c.sucursal)}</td>
                        <td class="py-3 text-center fw-semibold">${numberFormatter.format(c.total_movimientos || 0)}</td>
                        <td class="py-3 text-end text-dark">${formatter.format(c.total_generado || 0)}</td>
                        <td class="py-3 text-end text-success fw-bold">${formatter.format(c.utilidad_bruta || 0)}</td>
                        <td class="py-3 text-end text-danger">${formatter.format(c.costo_estimado || 0)}</td>
                        <td class="pe-4 py-3 text-center">
                            <span class="badge ${badgeClass} rounded-pill px-3 py-2 fw-bold">
                                ${roi.toFixed(2)}x
                            </span>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        // Función para abrir el Modal con el desglose por movimientos y categorías
        function abrirModalCategorias(valuador) {
            const modalEl = document.getElementById('modalDesgloseCategorias');
            if (!modalEl) return;

            const modalSubtitulo = document.getElementById('modal-valuador-subtitulo');
            const kpiPiezas = document.getElementById('modal-kpi-piezas');
            const kpiAvaluo = document.getElementById('modal-kpi-avaluo');
            const kpiPrestamo = document.getElementById('modal-kpi-prestamo');
            const kpiTopMov = document.getElementById('modal-kpi-top-mov');
            const kpiTopCatSub = document.getElementById('modal-kpi-top-cat-sub');
            const tbodyMovimientos = document.getElementById('modal-movimientos-tbody');
            const tbodyCategorias = document.getElementById('modal-categorias-tbody');
            const tbodyCruzado = document.getElementById('modal-cruzado-tbody');

            modalSubtitulo.innerText = `Valuador: ${valuador.valuador} | Sucursal: ${valuador.sucursal}`;
            kpiPiezas.innerText = numberFormatter.format(valuador.total_avaluos || 0);
            kpiAvaluo.innerText = formatter.format(valuador.total_avaluado || 0);
            kpiPrestamo.innerText = formatter.format(valuador.total_prestado || 0);

            const movs = valuador.tipos_movimiento || [];
            const cats = valuador.categorias || [];
            const cruzado = valuador.desglose_cruzado || [];

            if (kpiTopMov) {
                kpiTopMov.innerText = movs.length > 0 ? `${movs[0].tipo_movimiento} (${numberFormatter.format(movs[0].cantidad)})` : 'N/A';
            }
            if (kpiTopCatSub) {
                kpiTopCatSub.innerText = cats.length > 0 ? `Ramo ppal: ${cats[0].categoria} (${numberFormatter.format(cats[0].cantidad)})` : 'Ramo: -';
            }

            // 1. Tabla Tipos de Movimiento
            if (tbodyMovimientos) {
                if (movs.length === 0) {
                    tbodyMovimientos.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay desglose de movimientos registrado</td></tr>';
                } else {
                    let htmlMov = '';
                    movs.forEach(m => {
                        const pctMonto = m.pct_monto || 0;
                        let badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                        let icon = 'bi-file-earmark-plus';
                        let barColor = 'bg-primary';

                        if (m.tipo_movimiento.includes('REFRENDO')) {
                            badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                            icon = 'bi-arrow-repeat';
                            barColor = 'bg-success';
                        } else if (m.tipo_movimiento.includes('ABONO')) {
                            badgeClass = 'bg-info-subtle text-info border border-info-subtle';
                            icon = 'bi-cash-coin';
                            barColor = 'bg-info';
                        } else if (m.tipo_movimiento.includes('EMPEÑO')) {
                            badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                            icon = 'bi-shield-check';
                            barColor = 'bg-primary';
                        }

                        htmlMov += `
                            <tr>
                                <td class="ps-3 py-2 fw-bold text-dark">
                                    <span class="badge ${badgeClass} rounded-pill px-3 py-2 fw-bold">
                                        <i class="bi ${icon} me-1"></i>${escapeHtml(m.tipo_movimiento)}
                                    </span>
                                </td>
                                <td class="py-2 text-center fw-semibold fs-6">${numberFormatter.format(m.cantidad || 0)}</td>
                                <td class="py-2 text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar ${barColor}" role="progressbar" style="width: ${pctMonto}%;"></div>
                                        </div>
                                        <span class="small fw-semibold text-muted" style="width: 45px; text-align: right;">${pctMonto}%</span>
                                    </div>
                                </td>
                                <td class="py-2 text-end fw-bold text-dark">${formatter.format(m.monto_avaluo || 0)}</td>
                                <td class="py-2 text-end text-success fw-semibold">${formatter.format(m.monto_prestamo || 0)}</td>
                                <td class="pe-3 py-2 text-end text-muted">${formatter.format(m.promedio_avaluo || 0)}</td>
                            </tr>
                        `;
                    });
                    tbodyMovimientos.innerHTML = htmlMov;
                }
            }

            // 2. Tabla Categorías / Ramos
            if (tbodyCategorias) {
                if (cats.length === 0) {
                    tbodyCategorias.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay desglose por categorías disponible para este valuador</td></tr>';
                } else {
                    let htmlCat = '';
                    cats.forEach(c => {
                        const pctMonto = c.pct_monto || 0;
                        let icon = 'bi-tag-fill text-muted';
                        let barColor = 'bg-primary';
                        if (c.categoria === 'ORO') { icon = 'bi-gem text-warning'; barColor = 'bg-warning'; }
                        else if (c.categoria === 'PLATA') { icon = 'bi-circle-fill text-secondary'; barColor = 'bg-secondary'; }
                        else if (c.categoria === 'VARIOS') { icon = 'bi-box-seam-fill text-primary'; barColor = 'bg-primary'; }
                        else if (c.categoria === 'AUTOS') { icon = 'bi-car-front-fill text-danger'; barColor = 'bg-danger'; }

                        htmlCat += `
                            <tr>
                                <td class="ps-3 py-2 fw-bold text-dark">
                                    <i class="bi ${icon} me-2"></i>${escapeHtml(c.categoria)}
                                </td>
                                <td class="py-2 text-center fw-semibold fs-6">${numberFormatter.format(c.cantidad || 0)}</td>
                                <td class="py-2 text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar ${barColor}" role="progressbar" style="width: ${pctMonto}%;"></div>
                                        </div>
                                        <span class="small fw-semibold text-muted" style="width: 45px; text-align: right;">${pctMonto}%</span>
                                    </div>
                                </td>
                                <td class="py-2 text-end fw-bold text-dark">${formatter.format(c.monto_avaluo || 0)}</td>
                                <td class="py-2 text-end text-success fw-semibold">${formatter.format(c.monto_prestamo || 0)}</td>
                                <td class="pe-3 py-2 text-end text-muted">${formatter.format(c.promedio_avaluo || 0)}</td>
                            </tr>
                        `;
                    });
                    tbodyCategorias.innerHTML = htmlCat;
                }
            }

            // 3. Tabla Detalle Cruzado (Movimiento + Ramo)
            if (tbodyCruzado) {
                if (cruzado.length === 0) {
                    tbodyCruzado.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay desglose cruzado disponible</td></tr>';
                } else {
                    let htmlCruzado = '';
                    cruzado.forEach(cr => {
                        let catChip = 'cat-chip';
                        let catIcon = 'bi-tag';
                        if (cr.categoria === 'ORO') { catChip += ' chip-oro'; catIcon = 'bi-gem text-warning'; }
                        else if (cr.categoria === 'PLATA') { catChip += ' chip-plata'; catIcon = 'bi-circle-fill text-secondary'; }
                        else if (cr.categoria === 'VARIOS') { catChip += ' chip-varios'; catIcon = 'bi-box-seam-fill text-primary'; }
                        else if (cr.categoria === 'AUTOS') { catChip += ' chip-autos'; catIcon = 'bi-car-front-fill text-danger'; }

                        let movBadge = 'bg-primary-subtle text-primary border border-primary-subtle';
                        if (cr.tipo_movimiento.includes('REFRENDO')) movBadge = 'bg-success-subtle text-success border border-success-subtle';
                        else if (cr.tipo_movimiento.includes('ABONO')) movBadge = 'bg-info-subtle text-info border border-info-subtle';

                        htmlCruzado += `
                            <tr>
                                <td class="ps-3 py-2">
                                    <span class="badge ${movBadge} rounded-pill px-2 py-1 fw-bold">
                                        ${escapeHtml(cr.tipo_movimiento)}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <span class="${catChip}">
                                        <i class="bi ${catIcon} me-1"></i>${escapeHtml(cr.categoria)}
                                    </span>
                                </td>
                                <td class="py-2 text-center fw-semibold">${numberFormatter.format(cr.cantidad || 0)}</td>
                                <td class="py-2 text-end fw-bold text-dark">${formatter.format(cr.monto_avaluo || 0)}</td>
                                <td class="py-2 text-end text-success fw-semibold">${formatter.format(cr.monto_prestamo || 0)}</td>
                                <td class="pe-3 py-2 text-end text-muted">${formatter.format(cr.promedio_avaluo || 0)}</td>
                            </tr>
                        `;
                    });
                    tbodyCruzado.innerHTML = htmlCruzado;
                }
            }

            // Asegurar que la primera pestaña esté activa al abrir
            const firstTabBtn = document.getElementById('tab-movimientos-btn');
            if (firstTabBtn) {
                const tabInstance = bootstrap.Tab.getOrCreateInstance(firstTabBtn);
                tabInstance.show();
            }

            const modalInstance = new bootstrap.Modal(modalEl);
            modalInstance.show();
        }

    });
</script>
@endsection
