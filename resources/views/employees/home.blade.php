@extends('employees.layouts.main')
@section('styles')
	<style type="text/css">
		.cursor-pointer {
			cursor: pointer;
		}
		.card-hover:hover {
			transform: translateY(-2px);
			box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
			transition: all 0.3s ease;
		}
  </style>
@endsection
@section('content')

<div class="container-fluid p-4">
	<div class="row mb-4">
		<div class="col-12 d-flex justify-content-between align-items-center">
			<h4 class="title">Bienvenido, {{ Auth::user()->nombre_completo ?? Auth::user()->nombre ?? '' }} (del {{ \Carbon\Carbon::parse($fechaDel)->format('d/m/Y H:i') }} al {{ \Carbon\Carbon::parse($fechaAl)->format('d/m/Y H:i') }})</h4>
		</div>
	</div>

	<div class="row">
		<div class="col-12 col-md-6 col-lg-4">
			<!-- Added data-bs-toggle and data-bs-target for modal -->
			<div class="card shadow-sm border-0 card-hover cursor-pointer" data-bs-toggle="modal" data-bs-target="#empenoModal" title="Click para ver detalles por sucursal">
				<div class="card-body">
					<h5 class="card-title text-muted mb-3">Total Empeño</h5>
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
	</div>
</div>

<!-- Modal -->
<div class="modal fade" id="empenoModal" tabindex="-1" aria-labelledby="empenoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="empenoModalLabel">Detalle por Sucursal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
		<div class="alert alert-info py-2 mb-3">
			<small>Periodo: <strong>{{ \Carbon\Carbon::parse($fechaDel)->format('d/m/Y H:i') }}</strong> al <strong>{{ \Carbon\Carbon::parse($fechaAl)->format('d/m/Y H:i') }}</strong></small>
		</div>
        <ul class="list-group list-group-flush">
			@if(isset($sucursalesDetalle) && count($sucursalesDetalle) > 0)
				@foreach($sucursalesDetalle as $detalle)
				<li class="list-group-item d-flex justify-content-between align-items-center px-0">
					{{ $detalle['nombre'] }}
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

@endsection
@section('scripts')


	<script type="text/javascript">
		// Initialize tooltips if needed, though data-bs-toggle handles the modal
		var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
		var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		  return new bootstrap.Tooltip(tooltipTriggerEl)
		})
	</script>

@endsection
