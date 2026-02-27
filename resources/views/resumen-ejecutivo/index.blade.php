@extends('employees.layouts.main')

@section('title', 'Resumen Ejecutivo')

@section('styles')
    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
        .icon-shape {
            width: 4rem;
            height: 4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border-radius: 50%;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.1); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.1); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.1); }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.1); }

        .table-responsive {
            overflow-x: auto;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid p-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Resumen Ejecutivo Global</h4>
            <p class="text-muted">Vista rápida del desempeño global de Valora Más</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('resumen-ejecutivo.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}" {{ $sucursalId == $sucursal->id_valora_mas ? 'selected' : '' }}>
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tarjetas KPI Principales (Financieros) -->
    <div class="row mb-4">
        <!-- Ingresos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Ingresos Totales</h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0">$ {{ number_format($totalIngresos, 2) }}</h2>
                    <span class="text-muted small">Ventas + Intereses</span>
                </div>
            </div>
        </div>

        <!-- Gastos -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Gastos Operativos</h6>
                        <div class="icon-shape bg-light-danger text-danger">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0">$ {{ number_format($totalGastosGlobal, 2) }}</h2>
                    <span class="text-muted small">Total egresos registrados</span>
                </div>
            </div>
        </div>

        <!-- Utilidad Neta -->
        <div class="col-12 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Utilidad Neta</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold {{ $utilidadNeta >= 0 ? 'text-dark' : 'text-danger' }} mb-0">
                        $ {{ number_format($utilidadNeta, 2) }}
                    </h2>
                    <span class="badge {{ $utilidadNeta >= 0 ? 'bg-success' : 'bg-danger' }} mt-2">
                        {{ $totalIngresos > 0 ? number_format(($utilidadNeta / $totalIngresos) * 100, 1) : 0 }}% Margen
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y Desglose -->
    <div class="row mb-4">
        <!-- Gráfico de Comparativa Financiera -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Comparativa Financiera Global</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="financialChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- KPI Cards Secundarios -->
        <div class="col-md-4">
            <!-- Inventario -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-warning text-warning me-3">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">Inventario (Piso)</h6>
                            <h4 class="fw-bold text-dark mb-0">$ {{ number_format($inventarioPisoVentaTotal, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empeños -->
            <div class="card shadow-sm border-0 mb-3 rounded-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-light-primary text-primary me-3">
                            <i class="bi bi-tags-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-1">Empeños (Nuevos)</h6>
                            <h4 class="fw-bold text-dark mb-0">$ {{ number_format($empenosData['prestamo'], 2) }}</h4>
                            <small class="text-muted">{{ $empenosData['contratos'] }} Contratos</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Composición Inventario -->
            <div class="card shadow-sm border-0 rounded-3">
                 <div class="card-body">
                    <h6 class="text-muted text-uppercase fw-bold ls-1 mb-3">Composición Inventario</h6>
                    <canvas id="inventoryChart" height="200"></canvas>
                 </div>
            </div>
        </div>
    </div>

    <!-- Tabla Semáforo por Sucursal -->
    @if(empty($sucursalId) && count($branchKPIs) > 0)
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Desempeño por Sucursal</h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#branchPerformanceChart" aria-expanded="false">
                        <i class="bi bi-bar-chart-line"></i> Ver Gráfico
                    </button>
                </div>
                <div class="card-body p-0">
                    <!-- Collapse para gráfico comparativo -->
                    <div class="collapse p-4" id="branchPerformanceChart">
                         <canvas id="branchesChart" height="100"></canvas>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Ingresos</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Gastos</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Utilidad Neta</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Margen %</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Inv. Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($branchKPIs as $nombre => $kpi)
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">{{ $nombre }}</td>
                                    <td class="text-end">$ {{ number_format($kpi['ingresos'], 2) }}</td>
                                    <td class="text-end">$ {{ number_format($kpi['gastos'], 2) }}</td>
                                    <td class="text-end fw-bold {{ $kpi['utilidad_neta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        $ {{ number_format($kpi['utilidad_neta'], 2) }}
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $margen = $kpi['margen_bruto_pct'];
                                            $badgeClass = $margen > 30 ? 'bg-success' : ($margen > 15 ? 'bg-warning text-dark' : 'bg-danger');
                                        @endphp
                                        <span class="badge {{ $badgeClass }} rounded-pill">
                                            {{ number_format($margen, 1) }}%
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end">$ {{ number_format($kpi['inventario_total'], 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // --- Gráfico Financiero (Barras) ---
        const ctxFin = document.getElementById('financialChart');
        if (ctxFin) {
            new Chart(ctxFin, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($chartFinanciero['labels']) !!},
                    datasets: [{
                        label: 'Monto (MXN)',
                        data: {!! json_encode($chartFinanciero['data']) !!},
                        backgroundColor: [
                            'rgba(25, 135, 84, 0.7)', // Success (Ingresos)
                            'rgba(220, 53, 69, 0.7)', // Danger (Gastos)
                            'rgba(13, 202, 240, 0.7)'  // Info (Utilidad)
                        ],
                        borderColor: [
                            'rgba(25, 135, 84, 1)',
                            'rgba(220, 53, 69, 1)',
                            'rgba(13, 202, 240, 1)'
                        ],
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [2, 4] }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // --- Gráfico Inventario (Doughnut) ---
        const ctxInv = document.getElementById('inventoryChart');
        if (ctxInv) {
            new Chart(ctxInv, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($chartInventario['labels']) !!},
                    datasets: [{
                        data: {!! json_encode($chartInventario['data']) !!},
                        backgroundColor: [
                            '#FFD700', // Oro
                            '#C0C0C0', // Plata
                            '#fd7e14', // Varios
                            '#0dcaf0'  // Autos
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12 } }
                    },
                    cutout: '70%'
                }
            });
        }

        // --- Gráfico Sucursales (Comparativo) ---
        @if(!empty($chartSucursales))
        const ctxBranches = document.getElementById('branchesChart');
        if (ctxBranches) {
            new Chart(ctxBranches, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($chartSucursales['labels']) !!},
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: {!! json_encode($chartSucursales['ingresos']) !!},
                            backgroundColor: 'rgba(118, 75, 162, 0.6)',
                            borderRadius: 4
                        },
                        {
                            label: 'Utilidad Neta',
                            data: {!! json_encode($chartSucursales['utilidades']) !!},
                            backgroundColor: 'rgba(13, 202, 240, 0.6)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f0f0f0' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        @endif
    });
</script>
@endsection