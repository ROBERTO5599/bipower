@extends('employees.layouts.main')

@section('styles')
    <style type="text/css">
        /* Mantén los estilos del primer index si los necesitas */
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
            transition: all 0.3s ease;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid p-4">
    <!-- Tu contenido actual, pero adaptado al estilo del layout employees -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title">Resumen Ejecutivo Global</h4>
        </div>
    </div>

    <!-- Filtros en estilo Bootstrap -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('resumen-ejecutivo.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sucursal</label>
                    <select name="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fila de Resumen Financiero: Tarjetas KPI Principales -->
    <div class="row">
        <!-- Ingresos -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-3">Ingresos Totales</h5>
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h2 class="mb-0 font-weight-bold text-success">$ {{ number_format($totalIngresos, 2) }}</h2>
                        </div>
                        <div class="icon-shape bg-light text-success rounded-circle p-3">
                            <i class="bi bi-cash-coin fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gastos -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-3">Gastos Totales</h5>
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h2 class="mb-0 font-weight-bold text-danger">$ {{ number_format($totalGastos, 2) }}</h2>
                        </div>
                        <div class="icon-shape bg-light text-danger rounded-circle p-3">
                            <i class="bi bi-arrow-down fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Utilidad Neta -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-3">Utilidad Neta</h5>
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h2 class="mb-0 font-weight-bold text-info">$ {{ number_format($utilidadNeta, 2) }}</h2>
                        </div>
                        <div class="icon-shape bg-light text-info rounded-circle p-3">
                            <i class="bi bi-graph-up-arrow fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Continúa con el resto de tu contenido adaptado a Bootstrap -->
    <!-- Aquí irían las secciones de inventario, operativa, etc. -->
    
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tus scripts de Chart.js aquí
        // Asegúrate de que los IDs de los canvas existan
    });
</script>
@endsection