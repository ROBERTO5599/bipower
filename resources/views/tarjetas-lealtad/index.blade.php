@extends('employees.layouts.main')

@section('title', 'Tarjetas de Lealtad')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover {
            transition: all 0.25s ease;
        }
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.08) !important;
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

        .badge-subtle-primary { background-color: rgba(13, 110, 253, 0.12); color: #0d6efd; }
        .badge-subtle-success { background-color: rgba(25, 135, 84, 0.12); color: #198754; }
        .badge-subtle-danger { background-color: rgba(220, 53, 69, 0.12); color: #dc3545; }
        .badge-subtle-warning { background-color: rgba(255, 193, 7, 0.18); color: #997404; }
        .badge-subtle-info { background-color: rgba(13, 202, 240, 0.15); color: #087990; }
        .badge-subtle-purple { background-color: rgba(111, 66, 193, 0.12); color: #6f42c1; }

        .rank-medal {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .rank-1 { background-color: #ffd700; color: #856404; box-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
        .rank-2 { background-color: #e0e0e0; color: #495057; }
        .rank-3 { background-color: #cd7f32; color: #ffffff; }
        .rank-other { background-color: #f1f5f9; color: #64748b; }

        .banner-conversion {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
        }
    </style>
@endsection

@section('content')
    <div id="loading-overlay">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <h5 class="fw-bold text-dark">Cargando métricas de Tarjetas de Lealtad...</h5>
        <p class="text-muted small">Consultando puntos acumulados, canjes, campañas y saldos activos...</p>
    </div>

    <div class="container-fluid px-4 py-4">
        {{-- ENCABEZADO Y ACCIONES --}}
        <div class="row align-items-center mb-4">
            <div class="col-md-7">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none">Inicio</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tarjetas de Lealtad</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h3 fw-bold text-dark mb-0">Tarjetas de Lealtad (Monedero Electrónico)</h2>
                    <span class="badge badge-subtle-purple px-2 py-1 fs-7">
                        <i class="bi bi-star-fill text-warning me-1"></i> Power BI
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    Análisis de acumulación de puntos, canjes en transacciones, saldo activo en pesos, campañas y comportamiento de clientes.
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-success btn-sm shadow-sm" id="btn-exportar-csv">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar Clientes (CSV)
                </button>
                <button type="button" class="btn btn-primary btn-sm shadow-sm" id="btn-actualizar">
                    <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                </button>
            </div>
        </div>

        {{-- BANNER DE CONVERSIÓN Y OPERACIÓN DESTACADA --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="banner-conversion shadow-sm">
                    <div class="row align-items-center g-3">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-white bg-opacity-25 p-3 rounded-circle d-none d-sm-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                                    <i class="bi bi-currency-exchange fs-3 text-white"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-warning text-dark fw-bold px-2 py-1">Tasa Oficial</span>
                                        <span class="fw-bold fs-6">Equivalencia Monedero:</span>
                                    </div>
                                    <h4 class="mb-0 fw-bold mt-1 text-white" id="banner-tasa">
                                        3 Puntos = $1.00 Peso MXN <span class="fs-6 fw-normal text-white-50">(1 Punto = $0.333 MXN)</span>
                                    </h4>
                                    <small class="text-white-50">Configurada en el catálogo maestro del sistema de monedero.</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 text-md-end border-start border-white border-opacity-25 ps-md-4">
                            <span class="badge bg-white bg-opacity-20 text-white mb-1 px-2 py-1">
                                <i class="bi bi-trophy-fill text-warning me-1"></i> Movimiento Líder en Canje
                            </span>
                            <div class="fs-5 fw-bold text-white text-truncate" id="banner-top-operacion">
                                Venta de Contado
                            </div>
                            <div class="small text-white-50" id="banner-top-operacion-sub">
                                0 canjes (0 pts canjeados)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- BARRA DE FILTROS --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form id="form-filtros" class="row g-2 align-items-center">
                    <div class="col-12 col-md-auto d-flex align-items-center gap-1 flex-wrap">
                        <span class="text-muted small fw-semibold me-1"><i class="bi bi-funnel-fill text-primary"></i> Periodo:</span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-preset" data-preset="mes-actual">Mes Actual</button>
                            <button type="button" class="btn btn-outline-secondary btn-preset" data-preset="mes-anterior">Mes Anterior</button>
                            <button type="button" class="btn btn-outline-secondary btn-preset" data-preset="anio-actual">Año Actual</button>
                            <button type="button" class="btn btn-outline-secondary btn-preset" data-preset="historico">Todo</button>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="{{ $fechaInicio }}">
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-check"></i></span>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="{{ $fechaFin }}">
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id">
                            <option value="">Todas las sucursales</option>
                            @foreach ($sucursales as $suc)
                                <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-search me-1"></i> Filtrar
                        </button>
                        <button type="button" class="btn btn-light btn-sm px-3 border" id="btn-limpiar-filtros">
                            Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- TARJETAS KPIS PRINCIPALES --}}
        <div class="row g-3 mb-4">
            {{-- KPI 1: PUNTOS ACUMULADOS --}}
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm card-hover h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted fw-bold small">Puntos Acumulados</span>
                            <div class="icon-shape bg-primary text-white shadow-sm">
                                <i class="bi bi-plus-circle-fill"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-1" id="kpi-puntos-acumulados">0</h3>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">Equivalente en Pesos:</span>
                            <span class="fw-bold text-primary fs-6" id="kpi-pesos-acumulados">$0.00 MXN</span>
                        </div>
                        <div class="text-muted small mt-2 pt-2 border-top">
                            <span class="text-secondary" id="kpi-sub-acumulados">Acumulados en el periodo</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 2: PUNTOS CANJEADOS --}}
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm card-hover h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted fw-bold small">Puntos Canjeados</span>
                            <div class="icon-shape bg-success text-white shadow-sm">
                                <i class="bi bi-bag-check-fill"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-success mb-1" id="kpi-puntos-canjeados">0</h3>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">Ahorro Aplicado:</span>
                            <span class="fw-bold text-success fs-6" id="kpi-pesos-canjeados">$0.00 MXN</span>
                        </div>
                        <div class="text-muted small mt-2 pt-2 border-top d-flex justify-content-between">
                            <span class="text-secondary">Total Operaciones:</span>
                            <span class="fw-bold text-dark" id="kpi-total-canjes">0 canjes</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 3: SALDO ACTIVO CIRCULANTE (GLOBAL) --}}
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm card-hover h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted fw-bold small">Saldo Activo en Puntos</span>
                            <div class="icon-shape bg-info text-white shadow-sm">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-info mb-1" id="kpi-saldo-activo-puntos">0</h3>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">Valor en Dinero:</span>
                            <span class="fw-bold text-dark fs-6" id="kpi-saldo-activo-pesos">$0.00 MXN</span>
                        </div>
                        <div class="text-muted small mt-2 pt-2 border-top d-flex justify-content-between">
                            <span class="text-secondary">Puntos Vivos:</span>
                            <span class="badge badge-subtle-info">En monederos activos</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 4: CAMPAÑAS ACTIVAS --}}
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm card-hover h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted fw-bold small">Campañas Activas</span>
                            <div class="icon-shape bg-warning text-dark shadow-sm">
                                <i class="bi bi-megaphone-fill"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-dark mb-1" id="kpi-campanas-activas">0</h3>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">En Rango de Fechas:</span>
                            <span class="fw-semibold text-secondary" id="kpi-campanas-total">0 campañas</span>
                        </div>
                        <div class="text-muted small mt-2 pt-2 border-top d-flex justify-content-between">
                            <span class="text-secondary">Clientes registrados:</span>
                            <span class="fw-bold text-dark" id="kpi-clientes-registrados">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- FILA DE GRÁFICAS: EVOLUCIÓN TEMPORAL & MOVIMIENTO DE CANJE --}}
        <div class="row g-4 mb-4">
            {{-- GRÁFICA 1: EVOLUCIÓN TEMPORAL --}}
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h5 class="card-title fw-bold text-dark mb-0">Evolución de Movimientos de Monedero</h5>
                            <small class="text-muted">Puntos acumulados vs puntos canjeados en el periodo</small>
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active btn-toggle-chart-unit" data-unit="puntos">Puntos</button>
                            <button type="button" class="btn btn-outline-primary btn-toggle-chart-unit" data-unit="pesos">Pesos ($)</button>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div style="height: 310px; position: relative;">
                            <canvas id="chartEvolucion"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- GRÁFICA 2: MOVIMIENTOS EN QUE SE CANJEAN PUNTOS --}}
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="card-title fw-bold text-dark mb-0">Canjes por Operación Comercial</h5>
                        <small class="text-muted">¿En qué tipo de movimiento se canjean más puntos?</small>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div style="height: 200px; position: relative;">
                            <canvas id="chartCanjesOperacion"></canvas>
                        </div>
                        <div class="mt-3 table-responsive" style="max-height: 140px; overflow-y: auto;">
                            <table class="table table-sm align-middle table-borderless mb-0 small">
                                <tbody id="lista-canjes-operacion">
                                    {{-- Llenado dinámico --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLA: TOP 10 CLIENTES QUE MÁS USAN PUNTOS --}}
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <span class="badge bg-warning text-dark me-2"><i class="bi bi-award-fill"></i> Ranking</span>
                            <h5 class="card-title fw-bold text-dark d-inline mb-0">Top 10 de Clientes que Más Usan Puntos</h5>
                            <div class="text-muted small mt-1">Clientes con mayor volumen de canjes de puntos y ahorro acumulado</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-muted small text-uppercase">
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Cliente</th>
                                    <th>No. Tarjeta</th>
                                    <th>Sucursal</th>
                                    <th class="text-center">Total Canjes</th>
                                    <th class="text-end">Puntos Canjeados</th>
                                    <th class="text-end">Ahorro en Pesos ($)</th>
                                    <th class="text-end">Saldo Activo</th>
                                    <th class="text-center">Última Vez de Canje</th>
                                    <th class="text-center" style="width: 90px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-top-clientes">
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">Cargando ranking de clientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECCIÓN INFORMATIVA: CAMPAÑAS ACTIVAS EN EL RANGO --}}
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <span class="badge bg-primary me-2"><i class="bi bi-tag-fill"></i> Promociones</span>
                            <h5 class="card-title fw-bold text-dark d-inline mb-0">Campañas y Promociones Activas en el Periodo</h5>
                            <div class="text-muted small mt-1">Estrategias de monedero aplicadas a acumulación o canje de puntos</div>
                        </div>
                        <span class="badge badge-subtle-primary px-3 py-2 fs-7" id="badge-campanas-summary">0 campañas encontradas</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-muted small text-uppercase">
                                    <th>ID</th>
                                    <th>Nombre de la Campaña</th>
                                    <th>Tipo</th>
                                    <th>Multiplicador / Monto</th>
                                    <th class="text-center">Fecha Inicio</th>
                                    <th class="text-center">Fecha Fin / Vencimiento</th>
                                    <th class="text-end">Puntos Otorgados</th>
                                    <th class="text-center">Estatus</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-campanas">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Cargando campañas...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLA GENERAL: SALDO ACTIVO Y ÚLTIMO CANJE POR CLIENTE --}}
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="row align-items-center g-2">
                            <div class="col-12 col-md-5">
                                <span class="badge bg-info text-white me-2"><i class="bi bi-people-fill"></i> Directorio</span>
                                <h5 class="card-title fw-bold text-dark d-inline mb-0">Saldo Activo y Último Canje por Cliente</h5>
                                <div class="text-muted small mt-1">Listado completo de clientes con saldo en monedero o historial de canjes</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="buscador-clientes" placeholder="Buscar por cliente, teléfono o tarjeta...">
                                </div>
                            </div>
                            <div class="col-12 col-md-3 d-flex gap-2 justify-content-md-end">
                                <select class="form-select form-select-sm" id="filtro-tipo-cliente" style="max-width: 180px;">
                                    <option value="todos">Todos los clientes</option>
                                    <option value="con-canje">Con canjes realizados</option>
                                    <option value="con-saldo">Con saldo positivo (&gt; 0)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-muted small text-uppercase">
                                    <th>Cliente</th>
                                    <th>Teléfono / Correo</th>
                                    <th>No. Tarjeta</th>
                                    <th>Sucursal Registro</th>
                                    <th class="text-end">Puntos Acum.</th>
                                    <th class="text-end">Puntos Canje</th>
                                    <th class="text-end text-primary">Saldo Activo (Pts)</th>
                                    <th class="text-end text-success">Saldo Activo ($)</th>
                                    <th class="text-center">Última Vez de Canje</th>
                                    <th class="text-center" style="width: 100px;">Historial</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-clientes-general">
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">Cargando directorio de clientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-0 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="text-muted small" id="info-paginacion-clientes">Mostrando 0 de 0 registros</span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-ant-pag" disabled>Anterior</button>
                            <span class="small fw-semibold px-2" id="indicador-pagina">Pág 1 de 1</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-sig-pag" disabled>Siguiente</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL DE HISTORIAL DEL CLIENTE --}}
    <div class="modal fade" id="modalHistorialCliente" tabindex="-1" aria-labelledby="modalHistorialClienteLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title fw-bold text-dark" id="modalHistorialClienteLabel">Historial de Movimientos de Monedero</h5>
                        <div class="text-muted small" id="modal-cliente-subtitulo">Detalle completo de transacciones</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Mini KPI resumen del cliente --}}
                    <div class="row g-2 mb-3">
                        <div class="col-sm-3">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Teléfono</small>
                                <span class="fw-bold text-dark" id="modal-tel">-</span>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">No. Tarjeta</small>
                                <span class="fw-bold text-primary" id="modal-tarjeta">-</span>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Total Movimientos</small>
                                <span class="fw-bold text-dark" id="modal-total-movs">0</span>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Saldo de Puntos</small>
                                <span class="fw-bold text-success" id="modal-saldo-pts">0 pts</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-muted small text-uppercase">
                                    <th>Fecha y Hora</th>
                                    <th>Operación</th>
                                    <th>Código / Folio</th>
                                    <th>Contrato</th>
                                    <th class="text-end">Monto Transacción</th>
                                    <th class="text-end">Puntos</th>
                                    <th>Sucursal</th>
                                    <th>Promoción</th>
                                </tr>
                            </thead>
                            <tbody id="modal-tabla-movimientos">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Cargando movimientos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Estado local de la vista
        let appData = {
            configuracion: { puntos_por_peso: 3.0 },
            kpis: {},
            campanas: [],
            canjes_por_operacion: [],
            top_clientes: [],
            evolucion_diaria: [],
            clientes: []
        };

        let chartEvolucionInstance = null;
        let chartCanjesOpInstance = null;
        let chartUnit = 'puntos'; // 'puntos' o 'pesos'

        // Paginación y filtrado de la tabla general
        let filteredClientes = [];
        let currentPage = 1;
        const pageSize = 15;

        // Modal Bootstrap
        const modalHistorialEl = document.getElementById('modalHistorialCliente');
        const modalHistorial = new bootstrap.Modal(modalHistorialEl);

        // Formateadores
        const fmtNum = (num) => new Intl.NumberFormat('es-MX').format(num || 0);
        const fmtPts = (num) => `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(num || 0)} pts`;
        const fmtMoney = (num) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(num || 0);

        // Fetch de Datos
        function fetchData() {
            document.getElementById('loading-overlay').style.display = 'flex';

            const form = document.getElementById('form-filtros');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            fetch(`{{ route('tarjetas-lealtad.data') }}?${params}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP error ${res.status}`);
                return res.json();
            })
            .then(data => {
                appData = data;
                renderDashboard();
            })
            .catch(err => {
                console.error('Error al cargar datos:', err);
                alert('Ocurrió un error al cargar la información de Tarjetas de Lealtad. Verifique su conexión y permisos.');
            })
            .finally(() => {
                document.getElementById('loading-overlay').style.display = 'none';
            });
        }

        // Renderizado Completo
        function renderDashboard() {
            renderBanner();
            renderKPIs();
            renderChartEvolucion();
            renderChartCanjesOperacion();
            renderTopClientes();
            renderCampanas();
            applyClientesFilter();
        }

        // 1. Banner Superior
        function renderBanner() {
            const tasa = appData.configuracion?.puntos_por_peso || 3.0;
            const pesosPorPunto = (1 / tasa).toFixed(4);
            document.getElementById('banner-tasa').innerHTML = 
                `${fmtNum(tasa)} Puntos = $1.00 Peso MXN <span class="fs-6 fw-normal text-white-50">(1 Pto = $${pesosPorPunto} MXN)</span>`;

            const topOp = appData.kpis?.top_operacion_canje;
            if (topOp) {
                document.getElementById('banner-top-operacion').textContent = topOp.operacion_origen;
                document.getElementById('banner-top-operacion-sub').textContent = 
                    `${fmtNum(topOp.total_canjes)} canjes (${fmtNum(topOp.puntos_canjeados)} pts / ${fmtMoney(topOp.pesos_descuento)}) • ${topOp.porcentaje}% del total`;
            } else {
                document.getElementById('banner-top-operacion').textContent = 'Sin canjes en el periodo';
                document.getElementById('banner-top-operacion-sub').textContent = 'No se registraron movimientos';
            }
        }

        // 2. KPIs Ejecutivos
        function renderKPIs() {
            const k = appData.kpis || {};
            document.getElementById('kpi-puntos-acumulados').textContent = fmtPts(k.puntos_acumulados_periodo);
            document.getElementById('kpi-pesos-acumulados').textContent = `${fmtMoney(k.pesos_acumulados_periodo)} MXN`;

            document.getElementById('kpi-puntos-canjeados').textContent = fmtPts(k.puntos_canjeados_periodo);
            document.getElementById('kpi-pesos-canjeados').textContent = `${fmtMoney(k.pesos_canjeados_periodo)} MXN`;
            document.getElementById('kpi-total-canjes').textContent = `${fmtNum(k.total_canjes_periodo)} canjes`;

            document.getElementById('kpi-saldo-activo-puntos').textContent = fmtPts(k.saldo_activo_puntos);
            document.getElementById('kpi-saldo-activo-pesos').textContent = `${fmtMoney(k.saldo_activo_pesos)} MXN`;

            document.getElementById('kpi-campanas-activas').textContent = fmtNum(k.campanas_activas_count);
            document.getElementById('kpi-campanas-total').textContent = `${fmtNum(k.campanas_total_rango)} en periodo`;
            document.getElementById('kpi-clientes-registrados').textContent = fmtNum(k.total_clientes_registrados);
        }

        // 3. Gráfica de Evolución Temporal
        function renderChartEvolucion() {
            const ctx = document.getElementById('chartEvolucion').getContext('2d');
            const dataEvol = appData.evolucion_diaria || [];

            const labels = dataEvol.map(d => `${d.dia_semana} ${d.fecha_formato}`);
            const dataGanados = dataEvol.map(d => chartUnit === 'puntos' ? d.puntos_ganados : (d.puntos_ganados / appData.configuracion.puntos_por_peso));
            const dataCanje = dataEvol.map(d => chartUnit === 'puntos' ? d.puntos_canje : (d.pesos_canje || (d.puntos_canje / appData.configuracion.puntos_por_peso)));

            if (chartEvolucionInstance) {
                chartEvolucionInstance.destroy();
            }

            chartEvolucionInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: chartUnit === 'puntos' ? 'Puntos Acumulados' : 'Equivalente Acumulado ($)',
                            data: dataGanados,
                            backgroundColor: 'rgba(79, 70, 229, 0.75)',
                            borderColor: '#4f46e5',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: chartUnit === 'puntos' ? 'Puntos Canjeados' : 'Descuento Canjeado ($)',
                            data: dataCanje,
                            backgroundColor: 'rgba(16, 185, 129, 0.75)',
                            borderColor: '#10b981',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', align: 'end' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const val = context.parsed.y;
                                    return chartUnit === 'puntos' ? `${context.dataset.label}: ${fmtPts(val)}` : `${context.dataset.label}: ${fmtMoney(val)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return chartUnit === 'puntos' ? fmtNum(value) : `$${fmtNum(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Toggle unidad de gráfica (Puntos / Pesos)
        document.querySelectorAll('.btn-toggle-chart-unit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.btn-toggle-chart-unit').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                chartUnit = this.getAttribute('data-unit');
                renderChartEvolucion();
            });
        });

        // 4. Gráfica Donut de Canjes por Operación
        function renderChartCanjesOperacion() {
            const ctx = document.getElementById('chartCanjesOperacion').getContext('2d');
            const ops = appData.canjes_por_operacion || [];

            const labels = ops.map(o => o.operacion_origen);
            const dataPts = ops.map(o => o.puntos_canjeados);

            const colors = [
                '#4f46e5', '#10b981', '#f59e0b', '#06b6d4', '#ec4899', '#8b5cf6', '#64748b'
            ];

            if (chartCanjesOpInstance) {
                chartCanjesOpInstance.destroy();
            }

            chartCanjesOpInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataPts,
                        backgroundColor: colors.slice(0, labels.length),
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
                                label: function(ctx) {
                                    const item = ops[ctx.dataIndex];
                                    return ` ${item.operacion_origen}: ${fmtPts(item.puntos_canjeados)} (${item.porcentaje}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Llenado de lista de operaciones
            const tbody = document.getElementById('lista-canjes-operacion');
            if (ops.length === 0) {
                tbody.innerHTML = '<tr><td class="text-center text-muted py-2">Sin canjes en el periodo</td></tr>';
                return;
            }

            let html = '';
            ops.forEach((op, idx) => {
                const color = colors[idx % colors.length];
                html += `
                    <tr>
                        <td style="width: 14px;"><span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: ${color};"></span></td>
                        <td class="fw-semibold text-truncate" style="max-width: 130px;">${op.operacion_origen}</td>
                        <td class="text-end text-muted">${fmtNum(op.total_canjes)} canjes</td>
                        <td class="text-end fw-bold">${fmtMoney(op.pesos_descuento)}</td>
                        <td class="text-end text-secondary fw-semibold">${op.porcentaje}%</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        // 5. Top 10 Clientes que más usan puntos
        function renderTopClientes() {
            const tbody = document.getElementById('tabla-top-clientes');
            const top = appData.top_clientes || [];

            if (top.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No se registraron canjes de puntos en el periodo seleccionado.</td></tr>';
                return;
            }

            let html = '';
            top.forEach((c, idx) => {
                const rank = idx + 1;
                let rankClass = 'rank-other';
                if (rank === 1) rankClass = 'rank-1';
                else if (rank === 2) rankClass = 'rank-2';
                else if (rank === 3) rankClass = 'rank-3';

                html += `
                    <tr>
                        <td class="text-center">
                            <span class="rank-medal ${rankClass}">${rank}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">${c.cliente_nombre}</div>
                            <div class="small text-muted"><i class="bi bi-telephone me-1"></i>${c.telefono || 'Sin teléfono'}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace">${c.codigo_tarjeta || 'Sin Tarjeta'}</span>
                        </td>
                        <td>${c.sucursal_nombre || 'General'}</td>
                        <td class="text-center fw-semibold">${fmtNum(c.total_canjes)}</td>
                        <td class="text-end fw-bold text-success">${fmtPts(c.puntos_canjeados)}</td>
                        <td class="text-end fw-bold text-dark">${fmtMoney(c.pesos_canjeados)}</td>
                        <td class="text-end">
                            <div class="fw-semibold text-primary">${fmtPts(c.saldo_activo_puntos)}</div>
                            <small class="text-muted">${fmtMoney(c.saldo_activo_pesos)}</small>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-subtle-info">${c.ultimo_canje_formato}</span>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-ver-historial" data-id="${c.cliente_id}" title="Ver historial">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

            attachHistorialButtons();
        }

        // 6. Campañas Activas en el Periodo
        function renderCampanas() {
            const tbody = document.getElementById('tabla-campanas');
            const campanas = appData.campanas || [];

            document.getElementById('badge-campanas-summary').textContent = `${campanas.length} campañas en rango`;

            if (campanas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron campañas o promociones registradas para las fechas seleccionadas.</td></tr>';
                return;
            }

            let html = '';
            campanas.forEach(camp => {
                const statusBadge = camp.status === 'Activa' 
                    ? '<span class="badge badge-subtle-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>Activa</span>'
                    : '<span class="badge badge-subtle-danger px-2 py-1">Inactiva</span>';

                let beneficio = '-';
                if (camp.factor_multiplicador > 1) {
                    beneficio = `<span class="badge bg-warning text-dark">x${camp.factor_multiplicador} Puntos</span>`;
                } else if (camp.monto_promocional > 0) {
                    beneficio = `<span class="badge bg-info text-white">+${fmtPts(camp.monto_promocional)} Fijos</span>`;
                } else {
                    beneficio = '<span class="text-muted">Estándar</span>';
                }

                html += `
                    <tr>
                        <td class="fw-semibold text-muted">#${camp.id}</td>
                        <td class="fw-bold text-dark">${camp.nombre}</td>
                        <td><span class="badge bg-light text-secondary border">${camp.tipo_campana || 'General'}</span></td>
                        <td>${beneficio}</td>
                        <td class="text-center">${camp.fecha_inicio}</td>
                        <td class="text-center">${camp.fecha_vencimiento}</td>
                        <td class="text-end fw-bold text-primary">${fmtPts(camp.puntos_generados)}</td>
                        <td class="text-center">${statusBadge}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        // 7. Directorio General de Clientes (Filtro y Paginación)
        function applyClientesFilter() {
            const q = (document.getElementById('buscador-clientes').value || '').toLowerCase().trim();
            const tipoFiltro = document.getElementById('filtro-tipo-cliente').value;
            const todos = appData.clientes || [];

            filteredClientes = todos.filter(c => {
                // Filtro texto
                const matchText = !q || 
                    (c.nombre && c.nombre.toLowerCase().includes(q)) ||
                    (c.telefono && c.telefono.toLowerCase().includes(q)) ||
                    (c.codigo_tarjeta && c.codigo_tarjeta.toLowerCase().includes(q));

                if (!matchText) return false;

                // Filtro por comportamiento
                if (tipoFiltro === 'con-canje') {
                    return c.has_canje;
                } else if (tipoFiltro === 'con-saldo') {
                    return c.saldo_activo_puntos > 0;
                }

                return true;
            });

            currentPage = 1;
            renderClientesTable();
        }

        function renderClientesTable() {
            const tbody = document.getElementById('tabla-clientes-general');
            const total = filteredClientes.length;
            const totalPages = Math.ceil(total / pageSize) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = startIdx + pageSize;
            const pageData = filteredClientes.slice(startIdx, endIdx);

            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No se encontraron clientes con los filtros aplicados.</td></tr>';
            } else {
                let html = '';
                pageData.forEach(c => {
                    const badgeCanje = c.has_canje 
                        ? `<span class="badge badge-subtle-success"><i class="bi bi-check-circle me-1"></i>${c.ultima_fecha_canje}</span>`
                        : `<span class="badge bg-light text-muted border">Sin canjes</span>`;

                    html += `
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">${c.nombre}</div>
                                <small class="text-muted">ID: ${c.id}</small>
                            </td>
                            <td>
                                <div><i class="bi bi-telephone text-muted me-1"></i>${c.telefono}</div>
                                <small class="text-muted"><i class="bi bi-envelope text-muted me-1"></i>${c.correo}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">${c.codigo_tarjeta}</span>
                            </td>
                            <td>${c.sucursal_registro}</td>
                            <td class="text-end text-muted">${fmtPts(c.puntos_acumulados)}</td>
                            <td class="text-end text-muted">${fmtPts(c.puntos_canjeados)}</td>
                            <td class="text-end fw-bold text-primary">${fmtPts(c.saldo_activo_puntos)}</td>
                            <td class="text-end fw-bold text-success">${fmtMoney(c.saldo_activo_pesos)}</td>
                            <td class="text-center">${badgeCanje}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-ver-historial" data-id="${c.id}" title="Ver historial">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            }

            // Paginación UI
            document.getElementById('info-paginacion-clientes').textContent = 
                total > 0 ? `Mostrando ${startIdx + 1} a ${Math.min(endIdx, total)} de ${fmtNum(total)} clientes` : '0 clientes';
            document.getElementById('indicador-pagina').textContent = `Pág ${currentPage} de ${totalPages}`;
            document.getElementById('btn-ant-pag').disabled = currentPage <= 1;
            document.getElementById('btn-sig-pag').disabled = currentPage >= totalPages;

            attachHistorialButtons();
        }

        // 8. Botones de Historial / Modal
        function attachHistorialButtons() {
            document.querySelectorAll('.btn-ver-historial').forEach(btn => {
                btn.onclick = function() {
                    const clienteId = this.getAttribute('data-id');
                    cargarHistorialCliente(clienteId);
                };
            });
        }

        function cargarHistorialCliente(clienteId) {
            document.getElementById('modal-tabla-movimientos').innerHTML = 
                '<tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando historial de movimientos...</td></tr>';
            
            modalHistorial.show();

            fetch(`{{ url('/tarjetas-lealtad/cliente') }}/${clienteId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                const cli = data.cliente || {};
                const movs = data.movimientos || [];

                document.getElementById('modal-cliente-subtitulo').textContent = `${cli.nombre} • ${cli.codigo_tarjeta}`;
                document.getElementById('modal-tel').textContent = cli.telefono || 'Sin teléfono';
                document.getElementById('modal-tarjeta').textContent = cli.codigo_tarjeta;
                document.getElementById('modal-total-movs').textContent = `${movs.length} operaciones`;

                let saldoPts = 0;
                movs.forEach(m => { saldoPts += m.puntos; });
                document.getElementById('modal-saldo-pts').textContent = fmtPts(saldoPts);

                const tbody = document.getElementById('modal-tabla-movimientos');
                if (movs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No existen movimientos registrados para este cliente.</td></tr>';
                    return;
                }

                let html = '';
                movs.forEach(m => {
                    const badgePts = m.es_canje
                        ? `<span class="badge badge-subtle-success fw-bold">${fmtPts(m.puntos)} (Canje)</span>`
                        : `<span class="badge badge-subtle-primary fw-bold">+${fmtPts(m.puntos)}</span>`;

                    html += `
                        <tr>
                            <td>${m.fecha}</td>
                            <td><span class="badge bg-light text-dark border">${m.tipo_accion}</span></td>
                            <td class="font-monospace small">${m.codigo_movimiento}</td>
                            <td class="font-monospace small">${m.contrato}</td>
                            <td class="text-end fw-semibold">${fmtMoney(m.monto)}</td>
                            <td class="text-end">${badgePts}</td>
                            <td>${m.sucursal}</td>
                            <td><small class="text-muted">${m.promocion}</small></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                document.getElementById('modal-tabla-movimientos').innerHTML = 
                    '<tr><td colspan="8" class="text-center py-4 text-danger">Error al cargar movimientos del cliente.</td></tr>';
            });
        }

        // Paginación botones
        document.getElementById('btn-ant-pag').addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderClientesTable();
            }
        });
        document.getElementById('btn-sig-pag').addEventListener('click', () => {
            currentPage++;
            renderClientesTable();
        });

        // Buscador y filtro en vivo de clientes
        document.getElementById('buscador-clientes').addEventListener('input', applyClientesFilter);
        document.getElementById('filtro-tipo-cliente').addEventListener('change', applyClientesFilter);

        // Presets de Fechas
        document.querySelectorAll('.btn-preset').forEach(btn => {
            btn.addEventListener('click', function() {
                const preset = this.getAttribute('data-preset');
                const hoy = new Date();
                let fInicio = '';
                let fFin = hoy.toISOString().split('T')[0];

                if (preset === 'mes-actual') {
                    const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                    fInicio = primero.toISOString().split('T')[0];
                } else if (preset === 'mes-anterior') {
                    const primeroAnt = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
                    const ultimoAnt = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
                    fInicio = primeroAnt.toISOString().split('T')[0];
                    fFin = ultimoAnt.toISOString().split('T')[0];
                } else if (preset === 'anio-actual') {
                    fInicio = `${hoy.getFullYear()}-01-01`;
                } else if (preset === 'historico') {
                    fInicio = '2023-01-01';
                }

                document.getElementById('fecha_inicio').value = fInicio;
                document.getElementById('fecha_fin').value = fFin;
                fetchData();
            });
        });

        // Submit de Filtros
        document.getElementById('form-filtros').addEventListener('submit', function (e) {
            e.preventDefault();
            fetchData();
        });

        // Limpiar Filtros
        document.getElementById('btn-limpiar-filtros').addEventListener('click', function () {
            const hoy = new Date();
            const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            document.getElementById('fecha_inicio').value = primero.toISOString().split('T')[0];
            document.getElementById('fecha_fin').value = hoy.toISOString().split('T')[0];
            document.getElementById('sucursal_id').value = '';
            document.getElementById('buscador-clientes').value = '';
            document.getElementById('filtro-tipo-cliente').value = 'todos';
            fetchData();
        });

        // Botón Actualizar
        document.getElementById('btn-actualizar').addEventListener('click', fetchData);

        // Exportar Clientes CSV
        document.getElementById('btn-exportar-csv').addEventListener('click', function () {
            const clientes = filteredClientes.length > 0 ? filteredClientes : (appData.clientes || []);
            if (clientes.length === 0) {
                alert('No hay datos para exportar.');
                return;
            }

            const headers = [
                'ID_Cliente',
                'Nombre_Cliente',
                'Telefono',
                'Correo',
                'Codigo_Tarjeta',
                'Sucursal_Registro',
                'Puntos_Acumulados',
                'Puntos_Canjeados',
                'Saldo_Activo_Puntos',
                'Saldo_Activo_Pesos_MXN',
                'Ultima_Fecha_Canje',
                'Total_Canjes_Realizados'
            ];

            const rows = clientes.map(c => [
                c.id || '',
                `"${(c.nombre || '').replace(/"/g, '""')}"`,
                `"${(c.telefono || '').replace(/"/g, '""')}"`,
                `"${(c.correo || '').replace(/"/g, '""')}"`,
                `"${(c.codigo_tarjeta || '').replace(/"/g, '""')}"`,
                `"${(c.sucursal_registro || '').replace(/"/g, '""')}"`,
                c.puntos_acumulados || 0,
                c.puntos_canjeados || 0,
                c.saldo_activo_puntos || 0,
                c.saldo_activo_pesos || 0,
                `"${(c.ultima_fecha_canje || '').replace(/"/g, '""')}"`,
                c.total_canjes_num || 0
            ]);

            const csvContent = '\uFEFF' + [
                headers.join(','),
                ...rows.map(r => r.join(','))
            ].join('\r\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', `Tarjetas_Lealtad_Clientes_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        // Carga inicial
        fetchData();
    });
</script>
@endsection
