@extends('employees.layouts.main')
@section('styles')
	<style type="text/css">
		


  </style>
@endsection
@section('content')

<div class="container-fluid p-4">
	<div class="row mb-4">
		<div class="col-12 d-flex justify-content-between align-items-center">
			<h4 class="title">Bienvenido, {{ Auth::user()->nombre_completo ?? Auth::user()->nombre ?? '' }}</h4>
		</div>
	</div>

	<div class="row">
		<div class="col-12 col-md-6 col-lg-4">
			<div class="card shadow-sm border-0">
				<div class="card-body">
					<h5 class="card-title text-muted mb-3">Total Empeño (Mes Actual)</h5>
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
@endsection
@section('scripts')


	<script type="text/javascript">
	
	</script>

@endsection