@extends('employees.layouts.main')

@section('styles')
	<style type="text/css">
		.cursor-pointer {
			cursor: pointer;
		}
		.card-hover:hover {
			transform: translateY(-2px);
			box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12) !important;
			transition: all 0.25s ease;
		}
		.btn-periodo {
			border-radius: 6px;
			font-weight: 600;
			transition: all 0.2s ease;
		}
		.btn-periodo.active {
			background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%) !important;
			color: #fff !important;
			border-color: #4f46e5 !important;
			box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3) !important;
		}
		.btn-view-toggle {
			border-radius: 6px;
			font-weight: 600;
			transition: all 0.2s ease;
		}
		.btn-view-toggle.active {
			background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%) !important;
			color: #fff !important;
			border-color: #4f46e5 !important;
			box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25) !important;
		}
		.chart-container-movs {
			position: relative;
			height: 330px;
			width: 100%;
		}
		.table-scrollable {
			max-height: 480px;
			overflow-y: auto;
		}
		.badge-time {
			font-family: monospace;
			font-size: 0.85rem;
			letter-spacing: 0.5px;
		}
		.badge-type-pill {
			font-size: 0.75rem;
			font-weight: 600;
			padding: 0.35rem 0.65rem;
			border-radius: 50rem;
		}
		.type-legend-item {
			transition: background-color 0.2s ease;
		}
		.type-legend-item:hover {
			background-color: rgba(0,0,0,0.03);
		}
		.bg-indigo { background-color: #6366f1 !important; color: #ffffff !important; }
		.bg-purple { background-color: #8b5cf6 !important; color: #ffffff !important; }
		.bg-teal { background-color: #059669 !important; color: #ffffff !important; }
		.bg-orange { background-color: #fd7e14 !important; color: #ffffff !important; }
	</style>
@endsection

@section('content')

<div class="container-fluid p-4">
	<!-- Encabezado de Bienvenida -->
	<div class="row mb-4">
		<div class="col-12 d-flex justify-content-between align-items-center">
			<h4 class="title mb-0">Bienvenido, {{ Auth::user()->nombre_completo ?? Auth::user()->nombre ?? '' }} <small class="text-muted fs-6">(del {{ \Carbon\Carbon::parse($fechaDel)->format('d/m/Y H:i') }} al {{ \Carbon\Carbon::parse($fechaAl)->format('d/m/Y H:i') }})</small></h4>
		</div>
	</div>

	@if(auth()->check() && auth()->user()->getPbiRoleType() === 'super_admin')
	<div class="row mb-4">
		<div class="col-md-4">
			<label class="form-label fw-semibold">Filtro por Regional (Super Admin)</label>
			<select name="regional_user_id" id="regional_user_id" class="form-select shadow-sm" onchange="const url = new URL(window.location.href); if(this.value){ url.searchParams.set('regional_user_id', this.value); } else { url.searchParams.delete('regional_user_id'); } window.location.href = url.toString();">
				<option value="">-- Todos los Regionales --</option>
				@foreach($regionales ?? [] as $reg)
					<option value="{{ $reg->id }}" {{ (isset($regionalUserId) && $regionalUserId == $reg->id) ? 'selected' : '' }}>
						{{ $reg->nombre_completo }}
					</option>
				@endforeach
			</select>
		</div>
	</div>
	@endif

	<!-- Fila de Tarjetas Resumen Mensual General -->
	<div class="row mb-4">
		<!-- Card Total Empeño -->
		<div class="col-12 col-md-6 col-lg-4 mb-3">
			<div class="card shadow-sm border-0 card-hover cursor-pointer" data-bs-toggle="modal" data-bs-target="#empenoModal" title="Click para ver detalles por sucursal">
				<div class="card-body">
					<h5 class="card-title text-muted mb-3">Total Empeño (Mes)</h5>
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<h2 class="mb-0 font-weight-bold text-primary">$ {{ number_format($totalEmpeno ?? 0, 2) }}</h2>
						</div>
						<div class="icon-shape bg-light text-primary rounded-circle p-3">
							<i class="bi bi-cash-coin fs-1"></i>
						</div>
					</div>
					<p class="mt-3 mb-0 text-muted text-sm">
						<span class="text-nowrap">Acumulado de todas las sucursales</span>
					</p>
				</div>
			</div>
		</div>

		<!-- Card Total Refrendo -->
		<div class="col-12 col-md-6 col-lg-4 mb-3">
			<div class="card shadow-sm border-0 card-hover cursor-pointer" data-bs-toggle="modal" data-bs-target="#refrendoModal" title="Click para ver detalles por sucursal">
				<div class="card-body">
					<h5 class="card-title text-muted mb-3">Total Refrendo (Mes)</h5>
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<h2 class="mb-0 font-weight-bold text-success">$ {{ number_format($totalRefrendo ?? 0, 2) }}</h2>
						</div>
						<div class="icon-shape bg-light text-success rounded-circle p-3">
							<i class="bi bi-arrow-repeat fs-1"></i>
						</div>
					</div>
					<p class="mt-3 mb-0 text-muted text-sm">
						<span class="text-nowrap">Acumulado de todas las sucursales</span>
					</p>
				</div>
			</div>
		</div>

		<!-- Card Total Desempeño -->
		<div class="col-12 col-md-6 col-lg-4 mb-3">
			<div class="card shadow-sm border-0 card-hover cursor-pointer" data-bs-toggle="modal" data-bs-target="#desempenoModal" title="Click para ver detalles por sucursal">
				<div class="card-body">
					<h5 class="card-title text-muted mb-3">Total Desempeño (Mes)</h5>
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<h2 class="mb-0 font-weight-bold text-info">$ {{ number_format($totalDesempeno ?? 0, 2) }}</h2>
						</div>
						<div class="icon-shape bg-light text-info rounded-circle p-3">
							<i class="bi bi-box-arrow-right fs-1"></i>
						</div>
					</div>
					<p class="mt-3 mb-0 text-muted text-sm">
						<span class="text-nowrap">Acumulado de todas las sucursales</span>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- ===================================================================== -->
	<!-- NUEVA SECCIÓN: CONSULTA DE MOVIMIENTOS POR HORA Y PERIODO EN TIEMPO REAL -->
	<!-- ===================================================================== -->
	<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden">
		<!-- Barra Superior de Filtros y Control -->
		<div class="card-body p-4 bg-white border-bottom">
			<div class="row align-items-center g-3">
				<div class="col-lg-5 col-12">
					<h5 class="fw-bold mb-1 text-dark d-flex align-items-center">
						<span class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 me-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
							<i class="bi bi-clock-history fs-5"></i>
						</span>
						Monitoreo de Movimientos y Flujo Horario
					</h5>
					<p class="text-muted small mb-0">
						Consulta cuántos movimientos se realizaron por hora y filtra por Día, Semana o Mes en tiempo real.
					</p>
				</div>
				<div class="col-lg-7 col-12">
					<div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
						<!-- Selector de Sucursal -->
						<div style="min-width: 190px;">
							<label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-shop me-1 text-primary"></i>Sucursal</label>
							<select id="mov-sucursal" class="form-select form-select-sm fw-semibold shadow-none">
								<option value="all" selected>-- Todas las Sucursales --</option>
								@if(isset($sucursalesList))
									@foreach($sucursalesList as $s)
										<option value="{{ $s->id_valora_mas }}">
											{{ $s->nombre }}
										</option>
									@endforeach
								@endif
							</select>
						</div>

						<!-- Selector de Periodo: Hoy / Ayer / Semana / Mes / Rango -->
						<div>
							<label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-calendar-range me-1 text-primary"></i>Periodo</label>
							<div class="btn-group btn-group-sm w-100" role="group">
								<button type="button" class="btn btn-outline-primary btn-periodo" data-periodo="hoy" title="Consultar operaciones de hoy">
									Hoy
								</button>
								<button type="button" class="btn btn-outline-primary btn-periodo" data-periodo="ayer" title="Consultar operaciones de ayer">
									Ayer
								</button>
								<button type="button" class="btn btn-outline-primary btn-periodo" data-periodo="dia" title="Consultar un día específico">
									Día
								</button>
								<button type="button" class="btn btn-outline-primary btn-periodo" data-periodo="semana" title="Consultar semana">
									Semana
								</button>
								<button type="button" class="btn btn-outline-primary btn-periodo active" data-periodo="mes" title="Consultar mes">
									Mes
								</button>
								<button type="button" class="btn btn-outline-primary btn-periodo" data-periodo="rango" title="Filtrar por rango de fechas">
									Rango
								</button>
							</div>
						</div>

						<!-- Selector de Fecha Única (para Hoy, Ayer, Semana, Mes) -->
						<div id="container-fecha-unica" style="min-width: 130px;">
							<label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-calendar3 me-1 text-primary"></i>Fecha</label>
							<input type="date" id="mov-fecha" class="form-control form-control-sm shadow-none" value="{{ now()->toDateString() }}">
						</div>

						<!-- Selector de Rango de Fechas (para Rango personalizado) -->
						<div id="container-fecha-rango" class="d-none" style="min-width: 250px;">
							<div class="row g-1">
								<div class="col-6">
									<label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-calendar-event me-1 text-primary"></i>Desde</label>
									<input type="date" id="mov-fecha-inicio" class="form-control form-control-sm shadow-none" value="{{ now()->startOfMonth()->toDateString() }}">
								</div>
								<div class="col-6">
									<label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1 text-primary"></i>Hasta</label>
									<input type="date" id="mov-fecha-fin" class="form-control form-control-sm shadow-none" value="{{ now()->toDateString() }}">
								</div>
							</div>
						</div>

						<!-- Selector de Tipo de Movimiento -->
						<div style="min-width: 155px;">
							<label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-tag me-1 text-primary"></i>Tipo Mov.</label>
							<select id="mov-tipo" class="form-select form-select-sm shadow-none">
								<option value="todos">Todos los movimientos</option>
								<option value="1">Empeño</option>
								<option value="2">Refrendo</option>
								<option value="3">Abono a Capital</option>
								<option value="4">Desempeño</option>
								<option value="5">Venta Menudeo</option>
								<option value="6">Venta Mayoreo</option>
								<option value="7">Anticipo Apartado</option>
								<option value="8">Abono Apartado</option>
								<option value="12">Liquidación Apartado</option>
								<option value="19">Enganche Crédito</option>
								<option value="20">Pago Crédito</option>
								<option value="21">Liquidación Crédito</option>
								<option value="23">Devolución Crédito</option>
								<option value="18">Gastos</option>
							</select>
						</div>

						<!-- Botón Consultar -->
						<div class="align-self-end">
							<button id="btn-recargar-movs" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center" title="Recargar datos">
								<i class="bi bi-arrow-clockwise me-1" id="icon-reload"></i>Consultar
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Badges de contexto de filtro y Spinner -->
			<div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
				<div class="d-flex align-items-center flex-wrap gap-2">
					<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold" id="label-periodo-activo">
						<i class="bi bi-clock me-1"></i>Consultando...
					</span>
					<span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-semibold" id="label-sucursal-activa">
						<i class="bi bi-geo-alt-fill text-danger me-1"></i>Todas las Sucursales
					</span>
				</div>
				<div id="mov-loading-spinner" class="d-none align-items-center text-primary">
					<div class="spinner-border spinner-border-sm me-2" role="status"></div>
					<small class="fw-semibold">Consultando base de datos...</small>
				</div>
			</div>
		</div>

		<!-- Tarjetas KPIs de Rendimiento Operativo -->
		<div class="card-body p-4 bg-light bg-opacity-50 border-bottom">
			<div class="row g-3">
				<!-- KPI Total Movimientos -->
				<div class="col-6 col-lg-3">
					<div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-primary">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<small class="text-muted fw-bold text-uppercase d-block mb-1">Total Movimientos</small>
								<h3 class="mb-0 fw-bold text-primary" id="kpi-total-movs">0</h3>
							</div>
							<div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
								<i class="bi bi-arrow-left-right fs-4"></i>
							</div>
						</div>
						<small class="text-muted mt-2 d-block" id="kpi-sub-total">Operaciones realizadas</small>
					</div>
				</div>

				<!-- KPI Monto Total -->
				<div class="col-6 col-lg-3">
					<div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-success">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<small class="text-muted fw-bold text-uppercase d-block mb-1">Monto Operado</small>
								<h3 class="mb-0 fw-bold text-success" id="kpi-total-monto">$0.00</h3>
							</div>
							<div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
								<i class="bi bi-cash-stack fs-4"></i>
							</div>
						</div>
						<small class="text-muted mt-2 d-block">Monto total acumulado</small>
					</div>
				</div>

				<!-- KPI Hora Pico -->
				<div class="col-6 col-lg-3">
					<div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-warning">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<small class="text-muted fw-bold text-uppercase d-block mb-1">Hora Pico Operativa</small>
								<h4 class="mb-0 fw-bold text-dark fs-5" id="kpi-hora-pico">--:--</h4>
							</div>
							<div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle">
								<i class="bi bi-alarm fs-4"></i>
							</div>
						</div>
						<small class="text-muted mt-2 d-block">Mayor afluencia de clientes</small>
					</div>
				</div>

				<!-- KPI Promedio Horario -->
				<div class="col-6 col-lg-3">
					<div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-info">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<small class="text-muted fw-bold text-uppercase d-block mb-1">Promedio por Hora</small>
								<h3 class="mb-0 fw-bold text-info" id="kpi-promedio-hora">0</h3>
							</div>
							<div class="p-3 bg-info bg-opacity-10 text-info rounded-circle">
								<i class="bi bi-speedometer2 fs-4"></i>
							</div>
						</div>
						<small class="text-muted mt-2 d-block">Horas con actividad registrada</small>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección de Gráficas -->
		<div class="card-body p-4 bg-white border-bottom">
			<div class="row g-4">
				<!-- Gráfica Principal: Por Hora / Evolución del Periodo -->
				<div class="col-12 col-xl-8">
					<div class="card border-0 shadow-sm rounded-3 p-3 h-100">
						<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">
							<div>
								<h6 class="fw-bold mb-0 text-dark">
									<i class="bi bi-bar-chart-fill text-primary me-2"></i>
									<span id="chart-main-title">Afluencia de Movimientos por Hora</span>
								</h6>
								<small class="text-muted" id="chart-main-subtitle">Conteo de transacciones por cada hora</small>
							</div>
							<div class="d-flex flex-wrap align-items-center gap-2">
								<!-- Selector de Vista: Por Horas vs Por Días (Visible en Mes y Semana) -->
								<div class="btn-group btn-group-sm" role="group" id="view-toggle-container" style="display: none;">
									<button type="button" class="btn btn-outline-primary active btn-view-toggle" data-view="horas" id="btn-view-horas" title="Ver movimientos agrupados por hora (07:00 a 20:00 hrs)">
										<i class="bi bi-clock-history me-1"></i>Por Horas
									</button>
									<button type="button" class="btn btn-outline-primary btn-view-toggle" data-view="dias" id="btn-view-dias" title="Ver evolución día a día">
										<i class="bi bi-calendar-date me-1"></i>Por Días
									</button>
								</div>

								<!-- Selector de Métrica: Conteo (#) vs Monto ($) -->
								<div class="btn-group btn-group-sm" role="group">
									<button type="button" class="btn btn-outline-secondary active btn-metric-toggle" data-metric="counts">
										<i class="bi bi-hash me-1"></i>Conteo (#)
									</button>
									<button type="button" class="btn btn-outline-secondary btn-metric-toggle" data-metric="montos">
										<i class="bi bi-currency-dollar me-1"></i>Monto ($)
									</button>
								</div>
							</div>
						</div>
						<div class="chart-container-movs">
							<canvas id="chartMovimientos"></canvas>
						</div>
					</div>
				</div>

				<!-- Gráfica Secundaria: Desglose por Tipo de Movimiento -->
				<div class="col-12 col-xl-4">
					<div class="card border-0 shadow-sm rounded-3 p-3 h-100 d-flex flex-column">
						<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
							<div>
								<h6 class="fw-bold mb-0 text-dark">
									<i class="bi bi-pie-chart-fill text-success me-2"></i>
									Por Tipo de Movimiento
								</h6>
								<small class="text-muted" style="font-size: 0.72rem;">Sin dotaciones ni inicios de caja</small>
							</div>
						</div>
						<div style="position: relative; height: 180px;" class="mb-3">
							<canvas id="chartTipos"></canvas>
						</div>
						<!-- Lista scrolleable de desglose por tipo -->
						<div class="flex-grow-1 overflow-auto pe-1" style="max-height: 140px;" id="tipos-breakdown-list">
							<!-- Llenado dinámicamente con JS -->
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Tabla Detallada: Todos los Movimientos y con Tiempo -->
		<div class="card-body p-4 bg-white">
			<div class="row align-items-center g-2 mb-3">
				<div class="col-12 col-md-5">
					<h6 class="fw-bold mb-1 text-dark">
						<i class="bi bi-table text-primary me-2"></i>
						Todos los Movimientos con Registro de Tiempo
					</h6>
					<p class="text-muted small mb-0">Detalle cronológico con hora exacta (hh:mm:ss), tipo, caja, usuario y monto</p>
				</div>
				<div class="col-12 col-md-4">
					<div class="input-group input-group-sm shadow-none">
						<span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
						<input type="text" id="mov-search-input" class="form-control border-start-0" placeholder="Buscar folio, tipo, contrato, monto, caja...">
					</div>
				</div>
				<div class="col-12 col-md-3 text-md-end">
					<span id="movs-badge-total" class="badge bg-secondary me-2">0 registros</span>
					<button id="btn-export-csv" class="btn btn-sm btn-outline-success shadow-none">
						<i class="bi bi-file-earmark-excel me-1"></i>Descargar CSV
					</button>
				</div>
			</div>

			<div class="table-responsive table-scrollable border rounded-3">
				<table class="table table-hover align-middle mb-0 text-sm">
					<thead class="table-light sticky-top">
						<tr>
							<th class="text-center" style="width: 80px;">Folio</th>
							<th style="min-width: 140px;"><i class="bi bi-clock-history me-1 text-primary"></i>Tiempo Exacto</th>
							<th>Sucursal</th>
							<th>Tipo de Movimiento</th>
							<th class="text-center">Contrato</th>
							<th class="text-center">Caja</th>
							<th class="text-center">Usuario</th>
							<th class="text-end">Monto Total</th>
							<th>Referencia / Obs.</th>
						</tr>
					</thead>
					<tbody id="movs-table-body">
						<tr>
							<td colspan="9" class="text-center py-4 text-muted">
								<div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>
								Cargando movimientos...
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Paginación de la Tabla -->
			<div class="d-flex justify-content-between align-items-center mt-3 pt-2">
				<small class="text-muted" id="pagination-info">Mostrando 0 de 0 movimientos</small>
				<div class="btn-group btn-group-sm">
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
</div>

<!-- Modal Empeño -->
<div class="modal fade" id="empenoModal" tabindex="-1" aria-labelledby="empenoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="empenoModalLabel">Detalle Empeño por Sucursal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
		<div class="alert alert-info py-2 mb-3">
			<small>Periodo: <strong>{{ \Carbon\Carbon::parse($fechaDel)->format('d/m/Y H:i') }}</strong> al <strong>{{ \Carbon\Carbon::parse($fechaAl)->format('d/m/Y H:i') }}</strong></small>
		</div>
        <ul class="list-group list-group-flush">
			@if(isset($sucursalesDetalleEmpeno) && count($sucursalesDetalleEmpeno) > 0)
				@foreach($sucursalesDetalleEmpeno as $detalle)
				<li class="list-group-item d-flex justify-content-between align-items-center px-0">
					<strong class="text-dark">{{ $detalle['nombre'] }}</strong>
					<span class="badge bg-primary rounded-pill">$ {{ number_format($detalle['total'], 2) }}</span>
				</li>
				@endforeach
			@endif
		</ul>
		<div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
			<strong>Total General:</strong>
			<h5 class="mb-0 text-primary font-weight-bold">$ {{ number_format($totalEmpeno ?? 0, 2) }}</h5>
		</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Refrendo -->
<div class="modal fade" id="refrendoModal" tabindex="-1" aria-labelledby="refrendoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="refrendoModalLabel">Detalle Refrendo por Sucursal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
		<div class="alert alert-info py-2 mb-3">
			<small>Periodo: <strong>{{ \Carbon\Carbon::parse($fechaDel)->format('d/m/Y H:i') }}</strong> al <strong>{{ \Carbon\Carbon::parse($fechaAl)->format('d/m/Y H:i') }}</strong></small>
		</div>
        <ul class="list-group list-group-flush">
			@if(isset($sucursalesDetalleRefrendo) && count($sucursalesDetalleRefrendo) > 0)
				@foreach($sucursalesDetalleRefrendo as $detalle)
				<li class="list-group-item d-flex justify-content-between align-items-center px-0">
					<strong class="text-dark">{{ $detalle['nombre'] }}</strong>
					<span class="badge bg-success rounded-pill">$ {{ number_format($detalle['total'], 2) }}</span>
				</li>
				@endforeach
			@endif
		</ul>
		<div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
			<strong>Total General:</strong>
			<h5 class="mb-0 text-success font-weight-bold">$ {{ number_format($totalRefrendo ?? 0, 2) }}</h5>
		</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Desempeño -->
<div class="modal fade" id="desempenoModal" tabindex="-1" aria-labelledby="desempenoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="desempenoModalLabel">Detalle Desempeño por Sucursal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
		<div class="alert alert-info py-2 mb-3">
			<small>Periodo: <strong>{{ \Carbon\Carbon::parse($fechaDel)->format('d/m/Y H:i') }}</strong> al <strong>{{ \Carbon\Carbon::parse($fechaAl)->format('d/m/Y H:i') }}</strong></small>
		</div>
        <ul class="list-group list-group-flush">
			@if(isset($sucursalesDetalleDesempeno) && count($sucursalesDetalleDesempeno) > 0)
				@foreach($sucursalesDetalleDesempeno as $detalle)
				<li class="list-group-item d-flex justify-content-between align-items-center px-0">
					<strong class="text-dark">{{ $detalle['nombre'] }}</strong>
					<span class="badge bg-info text-dark rounded-pill">$ {{ number_format($detalle['total'], 2) }}</span>
				</li>
				@endforeach
			@endif
		</ul>
		<div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
			<strong>Total General:</strong>
			<h5 class="mb-0 text-info font-weight-bold">$ {{ number_format($totalDesempeno ?? 0, 2) }}</h5>
		</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('scripts')
	<!-- Chart.js CDN -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

	<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function () {
			// Tooltips de bootstrap
			var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
			tooltipTriggerList.map(function (tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl);
			});

			// ==========================================
			// LÓGICA DE MOVIMIENTOS POR HORA Y PERIODO
			// ==========================================
			let chartMovimientosInstance = null;
			let chartTiposInstance = null;
			let currentMetric = 'counts'; // 'counts' o 'montos'
			let currentPeriodo = 'mes';   // Por defecto 'mes' con horas y todas las sucursales
			let currentView = 'horas';    // 'horas' o 'dias'
			let cachedData = null;
			let allMovements = [];
			let filteredMovements = [];
			let currentPage = 1;
			const pageSize = 25;

			const currencyFormatter = new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN'
			});

			const numberFormatter = new Intl.NumberFormat('es-MX');

			// Colores temáticos por tipo de movimiento (fondo y texto legibles)
			const typeBadgeStyles = {
				1: { bg: '#0d6efd', color: '#ffffff' },  // Empeño (Azul)
				2: { bg: '#198754', color: '#ffffff' },  // Refrendo (Verde)
				3: { bg: '#ffc107', color: '#111827' },  // Abono Capital (Amarillo, texto oscuro)
				4: { bg: '#0dcaf0', color: '#111827' },  // Desempeño (Cian, texto oscuro)
				5: { bg: '#fd7e14', color: '#ffffff' },  // Venta Menudeo (Naranja)
				6: { bg: '#dc3545', color: '#ffffff' },  // Venta Mayoreo (Rojo)
				7: { bg: '#8b5cf6', color: '#ffffff' },  // Anticipo Apartado (Morado)
				8: { bg: '#059669', color: '#ffffff' },  // Abono Apartado (Esmeralda)
				10: { bg: '#475569', color: '#ffffff' }, // Retiro de Bóveda (Pizarra)
				12: { bg: '#6366f1', color: '#ffffff' }, // Liquidación Apartado (Índigo vivo)
				14: { bg: '#64748b', color: '#ffffff' }, // Retiro Caja a Bóveda
				16: { bg: '#94a3b8', color: '#ffffff' }, // Cierre Caja
				18: { bg: '#e11d48', color: '#ffffff' }, // Gastos
				19: { bg: '#0284c7', color: '#ffffff' }, // Enganche Crédito (Sky)
				20: { bg: '#15803d', color: '#ffffff' }, // Pago Crédito (Verde bosque)
				21: { bg: '#374151', color: '#ffffff' }, // Liquidación Crédito
				22: { bg: '#d97706', color: '#ffffff' }, // Devolución Garantía
				23: { bg: '#78716c', color: '#ffffff' }, // Devolución Crédito
				24: { bg: '#0891b2', color: '#ffffff' }  // Retiro Bancos
			};

			// Elementos del DOM
			const sucursalSelect = document.getElementById('mov-sucursal');
			const fechaInput = document.getElementById('mov-fecha');
			const containerFechaUnica = document.getElementById('container-fecha-unica');
			const containerFechaRango = document.getElementById('container-fecha-rango');
			const fechaInicioInput = document.getElementById('mov-fecha-inicio');
			const fechaFinInput = document.getElementById('mov-fecha-fin');
			const tipoSelect = document.getElementById('mov-tipo');
			const btnRecargar = document.getElementById('btn-recargar-movs');
			const iconReload = document.getElementById('icon-reload');
			const periodoButtons = document.querySelectorAll('.btn-periodo');
			const metricButtons = document.querySelectorAll('.btn-metric-toggle');
			const viewToggleContainer = document.getElementById('view-toggle-container');
			const viewButtons = document.querySelectorAll('.btn-view-toggle');
			const searchInput = document.getElementById('mov-search-input');
			const loadingSpinner = document.getElementById('mov-loading-spinner');
			const tableBody = document.getElementById('movs-table-body');
			const badgeTotal = document.getElementById('movs-badge-total');
			const paginationInfo = document.getElementById('pagination-info');
			const btnPrevPage = document.getElementById('btn-prev-page');
			const btnNextPage = document.getElementById('btn-next-page');
			const btnExportCsv = document.getElementById('btn-export-csv');

			// Actualiza visibilidad y estado de botones Por Horas / Por Días
			function updateViewButtons() {
				if (!viewToggleContainer) return;
				if (currentPeriodo === 'dia' || currentPeriodo === 'hoy' || currentPeriodo === 'ayer') {
					viewToggleContainer.style.display = 'none';
				} else {
					viewToggleContainer.style.display = 'inline-flex';
					viewButtons.forEach(b => {
						if (b.getAttribute('data-view') === currentView) {
							b.classList.add('active');
						} else {
							b.classList.remove('active');
						}
					});
				}
			}

			// Manejador de cambio de periodo (Hoy / Ayer / Semana / Mes / Rango)
			periodoButtons.forEach(btn => {
				btn.addEventListener('click', function () {
					periodoButtons.forEach(b => b.classList.remove('active'));
					this.classList.add('active');
					currentPeriodo = this.getAttribute('data-periodo');

					if (currentPeriodo === 'rango') {
						if (containerFechaUnica) containerFechaUnica.classList.add('d-none');
						if (containerFechaRango) containerFechaRango.classList.remove('d-none');
					} else {
						if (containerFechaUnica) containerFechaUnica.classList.remove('d-none');
						if (containerFechaRango) containerFechaRango.classList.add('d-none');
						if (currentPeriodo === 'hoy') {
							fechaInput.value = new Date().toISOString().slice(0, 10);
						} else if (currentPeriodo === 'ayer') {
							const d = new Date();
							d.setDate(d.getDate() - 1);
							fechaInput.value = d.toISOString().slice(0, 10);
						}
					}

					// Al cambiar a Mes, Semana o Rango, la vista predeterminada es por horas
					currentView = 'horas';
					updateViewButtons();
					fetchMovimientos();
				});
			});

			// Eventos de cambio en inputs de fecha rango
			if (fechaInicioInput) {
				fechaInicioInput.addEventListener('change', function () {
					if (currentPeriodo === 'rango') fetchMovimientos();
				});
			}
			if (fechaFinInput) {
				fechaFinInput.addEventListener('change', function () {
					if (currentPeriodo === 'rango') fetchMovimientos();
				});
			}

			// Manejador de cambio de vista (Por Horas vs Por Días)
			viewButtons.forEach(btn => {
				btn.addEventListener('click', function () {
					viewButtons.forEach(b => b.classList.remove('active'));
					this.classList.add('active');
					currentView = this.getAttribute('data-view');
					if (cachedData) {
						updateKPIs(cachedData);
						renderMainChart(cachedData);
					}
				});
			});

			// Manejador de cambio de métrica (# Conteo vs $ Monto)
			metricButtons.forEach(btn => {
				btn.addEventListener('click', function () {
					metricButtons.forEach(b => b.classList.remove('active'));
					this.classList.add('active');
					currentMetric = this.getAttribute('data-metric');
					if (cachedData) {
						renderMainChart(cachedData);
						renderTiposChart(cachedData);
					}
				});
			});

			// Cambios en selectores
			sucursalSelect.addEventListener('change', fetchMovimientos);
			fechaInput.addEventListener('change', fetchMovimientos);
			tipoSelect.addEventListener('change', fetchMovimientos);
			btnRecargar.addEventListener('click', fetchMovimientos);

			// Buscador en vivo de movimientos
			searchInput.addEventListener('input', function () {
				const query = this.value.toLowerCase().trim();
				if (!query) {
					filteredMovements = [...allMovements];
				} else {
					filteredMovements = allMovements.filter(m => {
						return (m.cod_movimiento && String(m.cod_movimiento).toLowerCase().includes(query)) ||
							(m.cod_contrato && String(m.cod_contrato).toLowerCase().includes(query)) ||
							(m.tipo_nombre && m.tipo_nombre.toLowerCase().includes(query)) ||
							(m.sucursal && m.sucursal.toLowerCase().includes(query)) ||
							(m.hora && m.hora.toLowerCase().includes(query)) ||
							(m.cod_caja && String(m.cod_caja).toLowerCase().includes(query)) ||
							(m.cod_usuario && String(m.cod_usuario).toLowerCase().includes(query)) ||
							(m.monto && String(m.monto).includes(query));
					});
				}
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
				const totalPages = Math.ceil(filteredMovements.length / pageSize);
				if (currentPage < totalPages) {
					currentPage++;
					renderTable();
				}
			});

			// Exportar CSV
			btnExportCsv.addEventListener('click', exportToCsv);

			// Función para consultar endpoint AJAX
			function fetchMovimientos() {
				const sucursalId = sucursalSelect.value;
				const fecha = fechaInput.value;
				const fechaInicio = fechaInicioInput ? fechaInicioInput.value : '';
				const fechaFin = fechaFinInput ? fechaFinInput.value : '';
				const tipo = tipoSelect.value;

				// Mostrar spinner
				loadingSpinner.classList.remove('d-none');
				loadingSpinner.classList.add('d-flex');
				if (iconReload) iconReload.classList.add('spin-animation');
				btnRecargar.disabled = true;

				let url = `/home/movimientos-data?sucursal_id=${encodeURIComponent(sucursalId)}&periodo=${encodeURIComponent(currentPeriodo)}&fecha=${encodeURIComponent(fecha)}&tipo_movimiento=${encodeURIComponent(tipo)}`;
				if (currentPeriodo === 'rango') {
					url += `&fecha_inicio=${encodeURIComponent(fechaInicio)}&fecha_fin=${encodeURIComponent(fechaFin)}`;
				}

				fetch(url)
					.then(response => {
						if (!response.ok) throw new Error('Error al consultar movimientos');
						return response.json();
					})
					.then(data => {
						if (!data.success) {
							throw new Error(data.error || 'Respuesta no exitosa');
						}
						cachedData = data;
						updateKPIs(data);
						renderMainChart(data);
						renderTiposChart(data);

						allMovements = data.movimientos || [];
						filteredMovements = [...allMovements];
						currentPage = 1;
						searchInput.value = '';
						renderTable();
					})
					.catch(err => {
						console.error(err);
						tableBody.innerHTML = `
							<tr>
								<td colspan="9" class="text-center py-4 text-danger">
									<i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-1"></i>
									Error al cargar los movimientos: ${err.message}
								</td>
							</tr>
						`;
					})
					.finally(() => {
						loadingSpinner.classList.add('d-none');
						loadingSpinner.classList.remove('d-flex');
						if (iconReload) iconReload.classList.remove('spin-animation');
						btnRecargar.disabled = false;
					});
			}

			// Actualizar tarjetas de KPI e indicadores
			function updateKPIs(data) {
				document.getElementById('kpi-total-movs').textContent = numberFormatter.format(data.total_movimientos || 0);
				document.getElementById('kpi-total-monto').textContent = currencyFormatter.format(data.total_monto || 0);
				document.getElementById('kpi-hora-pico').textContent = data.hora_pico || 'Sin actividad';
				document.getElementById('kpi-promedio-hora').textContent = data.promedio_por_hora ? (data.promedio_por_hora + ' movs/hr') : '0';

				// Etiquetas de periodo y sucursal
				const labelPeriodo = document.getElementById('label-periodo-activo');
				if (labelPeriodo) {
					let infoHoras = (currentPeriodo === 'mes' || currentPeriodo === 'semana' || currentPeriodo === 'rango')
						? (currentView === 'horas' ? ' • <i class="bi bi-clock me-1"></i>07:00 a 20:00 hrs' : ' • <i class="bi bi-calendar-date me-1"></i>Vista Diaria')
						: ' • <i class="bi bi-clock me-1"></i>07:00 a 20:00 hrs';
					labelPeriodo.innerHTML = `<i class="bi bi-calendar3 me-1"></i>${data.periodoLabel || ''} <span class="badge bg-white text-primary border ms-1 py-1 px-2 fw-semibold">${infoHoras}</span>`;
				}

				const labelSucursal = document.getElementById('label-sucursal-activa');
				if (labelSucursal) {
					const selectedOption = sucursalSelect.options[sucursalSelect.selectedIndex];
					labelSucursal.innerHTML = `<i class="bi bi-geo-alt-fill text-danger me-1"></i>${selectedOption ? selectedOption.text : ''}`;
				}

				const subTotal = document.getElementById('kpi-sub-total');
				if (subTotal) {
					subTotal.textContent = `En ${data.periodoLabel || 'el periodo'}`;
				}
			}

			// Renderizar Gráfica Principal (Por hora o por periodo)
			function renderMainChart(data) {
				const ctx = document.getElementById('chartMovimientos').getContext('2d');
				const titleEl = document.getElementById('chart-main-title');
				const subtitleEl = document.getElementById('chart-main-subtitle');

				updateViewButtons();

				let labels = [];
				let datasetValues = [];
				let labelMetric = currentMetric === 'counts' ? 'Cantidad de Movimientos' : 'Monto Operado ($)';

				if (currentPeriodo === 'dia' || currentPeriodo === 'hoy' || currentPeriodo === 'ayer') {
					labels = data.por_hora.labels;
					datasetValues = currentMetric === 'counts' ? data.por_hora.counts : data.por_hora.montos;
					if (titleEl) {
						if (currentPeriodo === 'hoy') titleEl.textContent = `Afluencia de Movimientos de Hoy (${data.fecha})`;
						else if (currentPeriodo === 'ayer') titleEl.textContent = `Afluencia de Movimientos de Ayer (${data.fecha})`;
						else titleEl.textContent = `Afluencia de Movimientos por Hora (${data.fecha})`;
					}
					if (subtitleEl) subtitleEl.textContent = 'Distribución horaria detallada (07:00 a 20:00 hrs)';
				} else if (currentPeriodo === 'semana') {
					if (currentView === 'horas') {
						labels = data.por_hora.labels;
						datasetValues = currentMetric === 'counts' ? data.por_hora.counts : data.por_hora.montos;
						if (titleEl) titleEl.textContent = `Movimientos por Hora en la Semana (${data.periodoLabel})`;
						if (subtitleEl) subtitleEl.textContent = 'Distribución horaria acumulada de Lunes a Domingo (07:00 a 20:00 hrs)';
					} else {
						labels = data.por_periodo.labels;
						datasetValues = currentMetric === 'counts' ? data.por_periodo.counts : data.por_periodo.montos;
						if (titleEl) titleEl.textContent = `Movimientos por Día de la Semana (${data.periodoLabel})`;
						if (subtitleEl) subtitleEl.textContent = 'Evolución de operaciones de Lunes a Domingo';
					}
				} else if (currentPeriodo === 'mes') {
					if (currentView === 'horas') {
						labels = data.por_hora.labels;
						datasetValues = currentMetric === 'counts' ? data.por_hora.counts : data.por_hora.montos;
						if (titleEl) titleEl.textContent = `Afluencia de Movimientos por Hora en el Mes (${data.periodoLabel})`;
						if (subtitleEl) subtitleEl.textContent = 'Distribución horaria acumulada de las operaciones del mes (07:00 a 20:00 hrs)';
					} else {
						labels = data.por_periodo.labels;
						datasetValues = currentMetric === 'counts' ? data.por_periodo.counts : data.por_periodo.montos;
						if (titleEl) titleEl.textContent = `Evolución Diaria del Mes (${data.periodoLabel})`;
						if (subtitleEl) subtitleEl.textContent = 'Distribución día a día a lo largo del mes (haz clic en un día para ver sus horas)';
					}
				} else if (currentPeriodo === 'rango') {
					if (currentView === 'horas') {
						labels = data.por_hora.labels;
						datasetValues = currentMetric === 'counts' ? data.por_hora.counts : data.por_hora.montos;
						if (titleEl) titleEl.textContent = `Afluencia de Movimientos por Hora en el Rango (${data.periodoLabel})`;
						if (subtitleEl) subtitleEl.textContent = 'Distribución horaria acumulada en el rango seleccionado (07:00 a 20:00 hrs)';
					} else {
						labels = data.por_periodo.labels;
						datasetValues = currentMetric === 'counts' ? data.por_periodo.counts : data.por_periodo.montos;
						if (titleEl) titleEl.textContent = `Evolución Diaria en el Rango (${data.periodoLabel})`;
						if (subtitleEl) subtitleEl.textContent = 'Distribución día a día del rango seleccionado (haz clic en un día para ver sus horas)';
					}
				}

				if (chartMovimientosInstance) {
					chartMovimientosInstance.destroy();
				}

				// Crear gradiente de color para las barras
				const gradient = ctx.createLinearGradient(0, 0, 0, 300);
				if (currentMetric === 'counts') {
					gradient.addColorStop(0, 'rgba(79, 70, 229, 0.85)');
					gradient.addColorStop(1, 'rgba(59, 130, 246, 0.45)');
				} else {
					gradient.addColorStop(0, 'rgba(16, 185, 129, 0.85)');
					gradient.addColorStop(1, 'rgba(5, 150, 105, 0.45)');
				}

				chartMovimientosInstance = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [{
							label: labelMetric,
							data: datasetValues,
							backgroundColor: gradient,
							borderColor: currentMetric === 'counts' ? '#4f46e5' : '#059669',
							borderWidth: 1.5,
							borderRadius: 6,
							hoverBackgroundColor: currentMetric === 'counts' ? '#4338ca' : '#047857'
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						onClick: function (evt, elements) {
							// Si está en vista por días en el mes o rango, al hacer clic en un día, abre sus horas
							if (elements && elements.length > 0 && currentView === 'dias' && (currentPeriodo === 'mes' || currentPeriodo === 'rango')) {
								const idx = elements[0].index;
								let selectedDateStr = null;
								if (currentPeriodo === 'mes') {
									const dayNum = idx + 1;
									const curDate = new Date(fechaInput.value + 'T00:00:00');
									curDate.setDate(dayNum);
									const yyyy = curDate.getFullYear();
									const mm = String(curDate.getMonth() + 1).padStart(2, '0');
									const dd = String(dayNum).padStart(2, '0');
									selectedDateStr = `${yyyy}-${mm}-${dd}`;
								} else if (currentPeriodo === 'rango' && fechaInicioInput && fechaInicioInput.value) {
									const curDate = new Date(fechaInicioInput.value + 'T00:00:00');
									curDate.setDate(curDate.getDate() + idx);
									const yyyy = curDate.getFullYear();
									const mm = String(curDate.getMonth() + 1).padStart(2, '0');
									const dd = String(curDate.getDate()).padStart(2, '0');
									selectedDateStr = `${yyyy}-${mm}-${dd}`;
								}

								if (selectedDateStr) {
									fechaInput.value = selectedDateStr;
									if (containerFechaUnica) containerFechaUnica.classList.remove('d-none');
									if (containerFechaRango) containerFechaRango.classList.add('d-none');

									periodoButtons.forEach(b => {
										if (b.getAttribute('data-periodo') === 'dia') {
											b.classList.add('active');
										} else {
											b.classList.remove('active');
										}
									});
									currentPeriodo = 'dia';
									currentView = 'horas';
									fetchMovimientos();
								}
							}
						},
						plugins: {
							legend: {
								display: false
							},
							tooltip: {
								backgroundColor: 'rgba(17, 24, 39, 0.95)',
								titleFont: { size: 13, weight: 'bold' },
								bodyFont: { size: 12 },
								padding: 10,
								callbacks: {
									title: function (items) {
										const item = items[0];
										const lbl = item.label || '';
										const isHourly = (currentView === 'horas' || currentPeriodo === 'dia' || currentPeriodo === 'hoy' || currentPeriodo === 'ayer');
										if (isHourly) {
											return `${lbl} hrs (${data.periodoLabel || data.fecha})`;
										}
										return `${lbl} (${data.periodoLabel || data.fecha})`;
									},
									label: function (context) {
										const idx = context.dataIndex;
										const isHourly = (currentView === 'horas' || currentPeriodo === 'dia' || currentPeriodo === 'hoy' || currentPeriodo === 'ayer');
										const c = isHourly ? (data.por_hora.counts[idx] || 0) : (data.por_periodo.counts[idx] || 0);
										const m = isHourly ? (data.por_hora.montos[idx] || 0) : (data.por_periodo.montos[idx] || 0);

										if (currentMetric === 'counts') {
											return ` Movimientos: ${numberFormatter.format(c)} (${currencyFormatter.format(m)})`;
										} else {
											return ` Monto: ${currencyFormatter.format(m)} (${numberFormatter.format(c)} movs)`;
										}
									}
								}
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								grid: {
									color: 'rgba(0, 0, 0, 0.05)'
								},
								ticks: {
									callback: function (value) {
										if (currentMetric === 'counts') {
											return numberFormatter.format(value);
										} else {
											return '$' + numberFormatter.format(value);
										}
									}
								}
							},
							x: {
								grid: {
									display: false
								}
							}
						}
					}
				});
			}

			// Renderizar Gráfica de Dona por Tipos
			function renderTiposChart(data) {
				const ctx = document.getElementById('chartTipos').getContext('2d');
				const tipos = data.por_tipo || [];

				const labels = tipos.map(t => t.nombre);
				const counts = tipos.map(t => currentMetric === 'counts' ? t.conteo : t.monto);
				const colors = tipos.map(t => t.color || '#6c757d');

				if (chartTiposInstance) {
					chartTiposInstance.destroy();
				}

				chartTiposInstance = new Chart(ctx, {
					type: 'doughnut',
					data: {
						labels: labels,
						datasets: [{
							data: counts,
							backgroundColor: colors,
							borderWidth: 2,
							borderColor: '#ffffff',
							hoverOffset: 6
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								display: false
							},
							tooltip: {
								callbacks: {
									label: function (context) {
										const idx = context.dataIndex;
										const t = tipos[idx];
										if (!t) return '';
										if (currentMetric === 'counts') {
											return ` ${t.nombre}: ${numberFormatter.format(t.conteo)} movs (${currencyFormatter.format(t.monto)})`;
										} else {
											return ` ${t.nombre}: ${currencyFormatter.format(t.monto)} (${numberFormatter.format(t.conteo)} movs)`;
										}
									}
								}
							}
						},
						cutout: '68%'
					}
				});

				// Llenar lista scrolleable de desglose
				const listContainer = document.getElementById('tipos-breakdown-list');
				if (!tipos.length) {
					listContainer.innerHTML = '<div class="text-center text-muted py-2">Sin movimientos en este periodo</div>';
					return;
				}

				let listHtml = '<div class="list-group list-group-flush">';
				tipos.forEach(t => {
					listHtml += `
						<div class="list-group-item d-flex justify-content-between align-items-center px-1 py-1 border-0 type-legend-item rounded-2">
							<div class="d-flex align-items-center text-truncate me-2">
								<span class="d-inline-block rounded-circle me-2 flex-shrink-0" style="width: 10px; height: 10px; background-color: ${t.color};"></span>
								<small class="fw-semibold text-truncate text-dark" title="${t.nombre}">${t.nombre}</small>
							</div>
							<div class="text-end flex-shrink-0">
								<span class="badge bg-light text-dark border me-1">${numberFormatter.format(t.conteo)}</span>
								<small class="text-muted fw-bold">${currencyFormatter.format(t.monto)}</small>
							</div>
						</div>
					`;
				});
				listHtml += '</div>';
				listContainer.innerHTML = listHtml;
			}

			// Renderizar Tabla Detallada con Paginación
			function renderTable() {
				const total = filteredMovements.length;
				badgeTotal.textContent = `${numberFormatter.format(total)} registros`;

				if (total === 0) {
					tableBody.innerHTML = `
						<tr>
							<td colspan="9" class="text-center py-4 text-muted">
								<i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
								No se encontraron movimientos para los filtros seleccionados.
							</td>
						</tr>
					`;
					paginationInfo.textContent = 'Mostrando 0 de 0 movimientos';
					btnPrevPage.disabled = true;
					btnNextPage.disabled = true;
					return;
				}

				const totalPages = Math.ceil(total / pageSize);
				if (currentPage > totalPages) currentPage = totalPages;
				if (currentPage < 1) currentPage = 1;

				const startIndex = (currentPage - 1) * pageSize;
				const endIndex = Math.min(startIndex + pageSize, total);
				const pageRows = filteredMovements.slice(startIndex, endIndex);

				let html = '';
				pageRows.forEach(item => {
					const badgeStyle = typeBadgeStyles[item.cod_tipo_movimiento] || { bg: '#4b5563', color: '#ffffff' };
					html += `
						<tr>
							<td class="text-center fw-bold text-muted">#${item.cod_movimiento || '-'}</td>
							<td>
								<div class="d-flex align-items-center">
									<i class="bi bi-clock me-1 text-primary"></i>
									<span class="badge bg-light text-dark border badge-time fw-bold">${item.hora || '--:--'}</span>
									<small class="text-muted ms-2">${item.fecha || ''}</small>
								</div>
							</td>
							<td><span class="fw-semibold text-dark">${item.sucursal || '-'}</span></td>
							<td>
								<span class="badge badge-type-pill shadow-xs" style="background-color: ${badgeStyle.bg} !important; color: ${badgeStyle.color} !important;">
									${item.tipo_nombre || 'OTRO'}
								</span>
							</td>
							<td class="text-center font-monospace">${item.cod_contrato ? ('#' + item.cod_contrato) : '<span class="text-muted">-</span>'}</td>
							<td class="text-center"><span class="badge bg-light text-secondary border">Caja ${item.cod_caja ?? '-'}</span></td>
							<td class="text-center"><small class="text-muted">U:${item.cod_usuario ?? '-'}</small></td>
							<td class="text-end fw-bold text-dark font-monospace">${currencyFormatter.format(item.monto || 0)}</td>
							<td><small class="text-muted text-truncate d-inline-block" style="max-width: 160px;" title="${item.referencia || ''}">${item.referencia || '-'}</small></td>
						</tr>
					`;
				});

				tableBody.innerHTML = html;

				paginationInfo.textContent = `Mostrando ${startIndex + 1} a ${endIndex} de ${numberFormatter.format(total)} movimientos (Pág. ${currentPage} de ${totalPages})`;
				btnPrevPage.disabled = currentPage === 1;
				btnNextPage.disabled = currentPage === totalPages;
			}

			// Exportar a CSV
			function exportToCsv() {
				if (!filteredMovements || !filteredMovements.length) {
					alert('No hay movimientos disponibles para exportar.');
					return;
				}

				const headers = ['Folio Movimiento', 'Fecha', 'Hora', 'Sucursal', 'Tipo Movimiento', 'Contrato', 'Caja', 'Usuario', 'Monto Total', 'Monto Efectivo', 'Monto Tarjeta', 'Referencia'];
				const rows = filteredMovements.map(m => [
					m.cod_movimiento || '',
					m.fecha || '',
					m.hora || '',
					`"${(m.sucursal || '').replace(/"/g, '""')}"`,
					`"${(m.tipo_nombre || '').replace(/"/g, '""')}"`,
					m.cod_contrato || '',
					m.cod_caja || '',
					m.cod_usuario || '',
					m.monto || 0,
					m.monto_efectivo || 0,
					m.monto_tarjeta || 0,
					`"${(m.referencia || '').replace(/"/g, '""')}"`
				]);

				let csvContent = '\uFEFF' + headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
				const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				const sucursalNombre = sucursalSelect.options[sucursalSelect.selectedIndex].text.replace(/\s+/g, '_');
				a.href = url;
				a.download = `movimientos_${sucursalNombre}_${currentPeriodo}_${fechaInput.value}.csv`;
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				URL.revokeObjectURL(url);
			}

			// Consulta inicial automática al cargar la página
			fetchMovimientos();
		});
	</script>
@endsection
