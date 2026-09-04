@extends('employees.layouts.main')

@section('title', 'Metas, Estacionalidad y Forecast')

@section('styles')
    <style type="text/css">
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }
        .text-verde { color: #198754; }
        .text-amarillo { color: #ffc107; }
        .text-rojo { color: #dc3545; }
        .bg-verde { background-color: #198754; color: white; }
        .bg-amarillo { background-color: #ffc107; color: black; }
        .bg-rojo { background-color: #dc3545; color: white; }
        
        /* Tarjetas de Indicadores Separadas y Elegantes */
        .gauge-card {
            border: 1px solid #edf2f7;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            background: #ffffff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .gauge-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
        }
        .gauge-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .card-ventas::before { background: #3b82f6; }
        .card-empenos::before { background: #10b981; }
        .card-intereses::before { background: #f59e0b; }
        .card-utilidad::before { background: #6366f1; }

        .gauge-container {
            width: 100%;
            height: 180px;
        }
        .badge-kpi {
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            padding: 5px 12px;
            border-radius: 20px;
        }
        .quick-range-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-color: transparent !important;
            color: #fff !important;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.4);
        }
        .sidebar-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .bg-verde {
            background-color: #dcfce7 !important;
            color: #15803d !important;
            border: 1px solid #bbf7d0 !important;
            font-weight: 700;
        }
        .bg-amarillo {
            background-color: #fef9c3 !important;
            color: #a16207 !important;
            border: 1px solid #fef08a !important;
            font-weight: 700;
        }
        .bg-rojo {
            background-color: #fee2e2 !important;
            color: #b91c1c !important;
            border: 1px solid #fecaca !important;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Calculando Proyecciones...</span>
    </div>
    <h5 class="text-muted fw-bold">Compilando e infiriendo modelos históricos...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Metas Predictivas y Forecast Estacional</h4>
            <p class="text-muted">Proyección científica combinando tendencia lineal y estacionalidad corporativa</p>
        </div>
    </div>

    <!-- Filtros Inteligentes con Rango de Fecha -->
    <div class="card shadow-sm border-0 mb-4 rounded-3 bg-white">     
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="sucursal_id" class="form-label fw-bold text-muted small text-uppercase">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas las Sucursales --</option>
                        @foreach($sucursales ?? [] as $sucursal)
                            <option value="{{ $sucursal->id_valora_mas }}">
                                {{ $sucursal->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label fw-bold text-muted small text-uppercase">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="{{ $fechaInicio }}">
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label fw-bold text-muted small text-uppercase">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="{{ $fechaFin }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary fw-bold sidebar-gradient border-0 py-2">
                        <i class="bi bi-search me-2"></i>Consultar
                    </button>
                </div>

                <!-- Botonera de Rangos Rápidos y Ajustes de Modelo -->
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="today">Hoy</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn active" data-range="month">Este Mes</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="prev-month">Mes Anterior</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="year">Este Año</button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text small fw-semibold">Profundidad</span>
                        <select name="meses_historico" id="meses_historico" class="form-select form-select-sm">
                            <option value="12" {{ ($mesesHistorico ?? 12) == 12 ? 'selected' : '' }}>12 Meses</option>
                            <option value="18" {{ ($mesesHistorico ?? 12) == 18 ? 'selected' : '' }}>18 Meses</option>
                            <option value="24" {{ ($mesesHistorico ?? 12) == 24 ? 'selected' : '' }}>24 Meses</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text small fw-semibold">Crecimiento</span>
                        <input type="number" step="0.1" name="crecimiento" id="crecimiento" value="{{ $crecimiento ?? 5 }}" class="form-control form-control-sm">
                        <span class="input-group-text small">%</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Indicadores Velocímetros Principales Separados -->
    <div class="row g-3 mb-4">
        <!-- Ventas -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card gauge-card card-ventas h-100 text-center p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-center mb-1">
                        <span class="badge bg-light text-primary border badge-kpi fw-bold text-uppercase">
                            <i class="bi bi-cart-check-fill me-1"></i> Ventas Reales vs Meta
                        </span>
                    </div>
                    <div id="gaugeVentas" class="gauge-container"></div>
                </div>
                <div class="bg-light rounded-3 p-2 mt-2 border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-start">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">REAL</small>
                            <span class="fw-bold text-dark font-monospace-custom" id="txtRevVentas" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                        <div class="text-end border-start ps-2">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">META</small>
                            <span class="fw-semibold text-secondary font-monospace-custom" id="txtMetaVentas" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empeños -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card gauge-card card-empenos h-100 text-center p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-center mb-1">
                        <span class="badge bg-light text-success border badge-kpi fw-bold text-uppercase">
                            <i class="bi bi-journal-plus me-1"></i> Empeños Reales vs Meta
                        </span>
                    </div>
                    <div id="gaugeEmpenos" class="gauge-container"></div>
                </div>
                <div class="bg-light rounded-3 p-2 mt-2 border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-start">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">REAL</small>
                            <span class="fw-bold text-dark font-monospace-custom" id="txtRevEmpenos" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                        <div class="text-end border-start ps-2">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">META</small>
                            <span class="fw-semibold text-secondary font-monospace-custom" id="txtMetaEmpenos" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Intereses -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card gauge-card card-intereses h-100 text-center p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-center mb-1">
                        <span class="badge bg-light text-warning text-dark border badge-kpi fw-bold text-uppercase">
                            <i class="bi bi-cash-coin me-1"></i> Intereses Cobrados
                        </span>
                    </div>
                    <div id="gaugeIntereses" class="gauge-container"></div>
                </div>
                <div class="bg-light rounded-3 p-2 mt-2 border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-start">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">REAL</small>
                            <span class="fw-bold text-dark font-monospace-custom" id="txtRevIntereses" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                        <div class="text-end border-start ps-2">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">META</small>
                            <span class="fw-semibold text-secondary font-monospace-custom" id="txtMetaIntereses" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Utilidad -->
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card gauge-card card-utilidad h-100 text-center p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-center mb-1">
                        <span class="badge bg-light text-indigo border badge-kpi fw-bold text-uppercase" style="color: #6366f1;">
                            <i class="bi bi-wallet2 me-1"></i> Utilidad Operativa
                        </span>
                    </div>
                    <div id="gaugeUtilidad" class="gauge-container"></div>
                </div>
                <div class="bg-light rounded-3 p-2 mt-2 border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-start">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">REAL</small>
                            <span class="fw-bold text-dark font-monospace-custom" id="txtRevUtilidad" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                        <div class="text-end border-start ps-2">
                            <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600;">META</small>
                            <span class="fw-semibold text-secondary font-monospace-custom" id="txtMetaUtilidad" style="font-size: 0.95rem;">$ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos de Proyección y Estacionalidad -->
    <div class="row mb-4">
        <!-- Tendencia Histórica -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Comportamiento Predictivo de Ventas Globales</h5>
                    <span class="badge bg-light text-primary">Tendencia + Crecimiento</span>
                </div>
                <div class="card-body p-4">
                    <div style="height: 300px; position: relative;">
                        <canvas id="ventasTimelineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Índice de Estacionalidad Mensual -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Índice de Estacionalidad Mensual (12 Meses)</h5>
                    <span class="badge bg-light text-info">Baseline = 1.0 (Sin Estacionalidad)</span>
                </div>
                <div class="card-body p-4">
                    <div style="height: 300px; position: relative;">
                        <canvas id="estacionalidadChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparativa de Metas (Automática vs Manual) -->
    <div class="row mb-4">
        <div class="col-12 col-lg-7 mb-4">
            <div class="card shadow-sm border-0 h-100 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-shuffle text-primary me-2"></i> Comparativa de Metas: Automática vs Manual</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold text-start">Indicador</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Meta Automática</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Meta Manual</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Meta Aplicada</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Diferencia</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold">Origen (tipo_meta)</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-comparativa-body">
                                <tr><td colspan="6" class="text-center text-muted py-4">Cargando comparativa...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5 mb-4">
            <div class="card shadow-sm border-0 h-100 rounded-3 text-dark" style="background-color: #f0f5ff; border-left: 5px solid #0d6efd !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle-fill text-primary"></i> Lógica del Motor Predictivo</h5>
                    <p class="small mb-3">
                        El sistema infiere las metas mensuales del período objetivo combinando dos capas analíticas:
                    </p>
                    <ul class="small ps-3 mb-3">
                        <li class="mb-2"><strong>Regresión Lineal Dinámica:</strong> Detecta la tendencia de crecimiento o desaceleración según la profundidad histórica elegida.</li>
                        <li class="mb-2"><strong>Índice de Estacionalidad:</strong> Multiplica el valor por la desviación típica histórica del mes (por ejemplo, picos de empeño en la cuesta de enero o picos de ventas en diciembre).</li>
                    </ul>
                    <p class="small mb-0">
                        <span class="badge bg-primary">Override Manual:</span> Si hay metas manuales cargadas en la base de datos de la sucursal (tabla <code>metas</code>), éstas sobrescriben automáticamente el modelo estadístico y se reflejan como origen <strong>Manual</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Sucursales -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Desglose de Objetivos por Sucursal</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th rowspan="2" class="ps-4 py-3 text-uppercase text-muted small fw-bold text-start align-middle">Sucursal</th>
                                    <th colspan="3" class="py-2 text-uppercase text-muted small fw-bold border-start border-end">Ventas</th>
                                    <th colspan="3" class="py-2 text-uppercase text-muted small fw-bold border-end">Empeños</th>
                                    <th colspan="3" class="py-2 text-uppercase text-muted small fw-bold border-end">Intereses</th>
                                    <th colspan="3" class="py-2 text-uppercase text-muted small fw-bold pe-4">Utilidad Op.</th>
                                </tr>
                                <tr>
                                    <th class="py-2 text-uppercase text-muted small fw-bold border-start">Real</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold">Meta</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold border-end">%</th>
                                    
                                    <th class="py-2 text-uppercase text-muted small fw-bold">Real</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold">Meta</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold border-end">%</th>

                                    <th class="py-2 text-uppercase text-muted small fw-bold">Real</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold">Meta</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold border-end">%</th>

                                    <th class="py-2 text-uppercase text-muted small fw-bold">Real</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold">Meta</th>
                                    <th class="py-2 text-uppercase text-muted small fw-bold pe-4">%</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-sucursales-body">
                                <tr><td colspan="13" class="text-center text-muted py-4">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<!-- ECharts para Gauges Profesionales -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        let ventasTimelineChart = null;
        let estacionalidadChart = null;
        let gauges = {};

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');

        // ECharts Configuración Limpia y Espaciosa (sin colisiones)
        function createGaugeOption(initialVal) {
            return {
                series: [{
                    type: 'gauge',
                    startAngle: 180,
                    endAngle: 0,
                    center: ['50%', '75%'],
                    radius: '95%',
                    min: 0,
                    max: 100,
                    splitNumber: 4,
                    axisLine: {
                        lineStyle: {
                            width: 12,
                            color: [
                                [0.7, '#ffc107'], // 0-70% amarillo
                                [0.9, '#fd7e14'], // 70-90% naranja/precaución
                                [1, '#198754']    // >90% verde
                            ]
                        }
                    },
                    pointer: {
                        icon: 'path://M12.8,0.7l12,40.1H0.7L12.8,0.7z',
                        length: '14%',
                        width: 12,
                        offsetCenter: [0, '-50%'],
                        itemStyle: {
                            color: 'auto'
                        }
                    },
                    axisTick: {
                        show: false
                    },
                    splitLine: {
                        distance: -12,
                        length: 12,
                        lineStyle: {
                            color: '#fff',
                            width: 2
                        }
                    },
                    axisLabel: {
                        color: '#6c757d',
                        distance: -34,
                        fontSize: 10,
                        formatter: function (val) {
                            if (val === 0) return '0%';
                            if (val === 50) return '50%';
                            if (val === 100) return '100%';
                            return '';
                        }
                    },
                    detail: {
                        valueAnimation: true,
                        formatter: '{value}%',
                        color: 'auto',
                        fontSize: 24,
                        fontWeight: 'bold',
                        offsetCenter: [0, '-15%']
                    },
                    data: [{ value: initialVal || 0 }]
                }]
            };
        }

        gauges['ventas'] = echarts.init(document.getElementById('gaugeVentas'));
        gauges['empenos'] = echarts.init(document.getElementById('gaugeEmpenos'));
        gauges['intereses'] = echarts.init(document.getElementById('gaugeIntereses'));
        gauges['utilidad'] = echarts.init(document.getElementById('gaugeUtilidad'));

        for (let g in gauges) {
            gauges[g].setOption(createGaugeOption(0));
        }

        window.addEventListener('resize', function() {
            for (let g in gauges) {
                if (gauges[g]) gauges[g].resize();
            }
        });

        // Botonera de Rangos Rápidos (Hoy, Este Mes, Mes Anterior, Este Año)
        document.querySelectorAll('.quick-range-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.quick-range-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const range = this.dataset.range;
                const startInput = document.getElementById('fecha_inicio');
                const endInput = document.getElementById('fecha_fin');

                const today = new Date();
                let start, end;

                function formatDate(d) {
                    const year = d.getFullYear();
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                }

                if (range === 'today') {
                    start = end = today;
                } else if (range === 'month') {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = today;
                } else if (range === 'prev-month') {
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                } else if (range === 'year') {
                    start = new Date(today.getFullYear(), 0, 1);
                    end = today;
                }

                startInput.value = formatDate(start);
                endInput.value = formatDate(end);

                loadData();
            });
        });

        loadData();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            fetch(`{{ route('metas-forecast.data') }}?${new URLSearchParams(new FormData(form)).toString()}`)
                .then(r => r.json())
                .then(data => {
                    updateDashboard(data);
                })
                .catch(err => console.error(err))
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                    for (let g in gauges) {
                        if (gauges[g]) gauges[g].resize();
                    }
                });
        }

        function updateGauge(gaugeId, real, meta) {
            let pct = meta > 0 ? (real / meta) * 100 : 0;
            let maxVal = pct > 100 ? Math.ceil(pct / 10) * 10 : 100;
            if (gauges[gaugeId]) {
                gauges[gaugeId].setOption({
                    series: [{
                        max: maxVal,
                        data: [{ value: parseFloat(pct.toFixed(1)) }],
                        axisLabel: {
                            formatter: function (v) {
                                if (v === 0) return '0%';
                                if (v === Math.round(maxVal / 2)) return Math.round(maxVal / 2) + '%';
                                if (v === maxVal) return maxVal + '%';
                                return '';
                            }
                        }
                    }]
                });
            }
        }

        function updateDashboard(data) {
            // Textos Globales
            document.getElementById('txtRevVentas').innerText = formatter.format(data.globals.ventas.real);
            document.getElementById('txtMetaVentas').innerText = formatter.format(data.globals.ventas.meta);
            updateGauge('ventas', data.globals.ventas.real, data.globals.ventas.meta);

            document.getElementById('txtRevEmpenos').innerText = formatter.format(data.globals.empenos.real);
            document.getElementById('txtMetaEmpenos').innerText = formatter.format(data.globals.empenos.meta);
            updateGauge('empenos', data.globals.empenos.real, data.globals.empenos.meta);

            document.getElementById('txtRevIntereses').innerText = formatter.format(data.globals.intereses.real);
            document.getElementById('txtMetaIntereses').innerText = formatter.format(data.globals.intereses.meta);
            updateGauge('intereses', data.globals.intereses.real, data.globals.intereses.meta);

            document.getElementById('txtRevUtilidad').innerText = formatter.format(data.globals.utilidad.real);
            document.getElementById('txtMetaUtilidad').innerText = formatter.format(data.globals.utilidad.meta);
            updateGauge('utilidad', data.globals.utilidad.real, data.globals.utilidad.meta);

            // Chart Timeline
            updateTimelineChart(data.chartTimeline);

            // Chart Estacionalidad
            updateEstacionalidadChart(data.estacionalidad);

            // Render Comparativa Table
            renderComparativa(data.comparativaMetas);

            // Render Table
            renderTable(data.branchKPIs);
        }

        function renderComparativa(comparativa) {
            const tbody = document.getElementById('tabla-comparativa-body');
            tbody.innerHTML = '';
            
            comparativa.forEach(item => {
                const diffVal = item.meta_aplicada - item.meta_automatica;
                const diffPct = item.meta_automatica !== 0 ? (diffVal / Math.abs(item.meta_automatica)) * 100 : 0;
                
                let diffText = '';
                let diffClass = '';
                if (diffVal > 0.01) {
                    diffText = `+${formatter.format(diffVal)} (+${diffPct.toFixed(1)}%)`;
                    diffClass = 'text-success fw-bold';
                } else if (diffVal < -0.01) {
                    diffText = `${formatter.format(diffVal)} (${diffPct.toFixed(1)}%)`;
                    diffClass = 'text-danger fw-bold';
                } else {
                    diffText = 'Sin cambios';
                    diffClass = 'text-muted';
                }
                
                let badgeClass = item.tipo_meta === 'Manual' ? 'bg-primary' : 'bg-secondary';
                
                tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark text-start">${item.indicador}</td>
                        <td class="py-3 text-muted">${formatter.format(item.meta_automatica)}</td>
                        <td class="py-3 text-muted">${item.meta_manual > 0 ? formatter.format(item.meta_manual) : 'N/D'}</td>
                        <td class="py-3 fw-bold text-dark">${formatter.format(item.meta_aplicada)}</td>
                        <td class="py-3 ${diffClass}">${diffText}</td>
                        <td class="pe-4 py-3">
                            <span class="badge ${badgeClass} px-3 py-2">${item.tipo_meta}</span>
                        </td>
                    </tr>
                `;
            });
        }

        function renderTable(kpis) {
            const tbody = document.getElementById('tabla-sucursales-body');
            tbody.innerHTML = '';
            
            kpis.forEach(kpi => {
                let badgeV = kpi.semaforo_ventas === 'verde' ? 'bg-verde' : (kpi.semaforo_ventas === 'amarillo' ? 'bg-amarillo' : 'bg-rojo');
                let badgeE = kpi.semaforo_empenos === 'verde' ? 'bg-verde' : (kpi.semaforo_empenos === 'amarillo' ? 'bg-amarillo' : 'bg-rojo');
                let badgeI = kpi.semaforo_intereses === 'verde' ? 'bg-verde' : (kpi.semaforo_intereses === 'amarillo' ? 'bg-amarillo' : 'bg-rojo');
                let badgeU = kpi.semaforo_utilidad === 'verde' ? 'bg-verde' : (kpi.semaforo_utilidad === 'amarillo' ? 'bg-amarillo' : 'bg-rojo');
                
                tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark text-start align-middle border-end">
                            ${kpi.id} 
                            ${kpi.is_manual ? '<i class="bi bi-person-fill ms-1 text-primary" title="Meta Manual"></i>' : '<i class="bi bi-robot ms-1 text-muted" title="Meta Automática"></i>'}
                        </td>
                        
                        <td class="py-3 text-primary fw-semibold">${formatter.format(kpi.real_ventas)}</td>
                        <td class="py-3 text-muted">${formatter.format(kpi.meta_ventas)}</td>
                        <td class="py-3 border-end">
                            <span class="badge ${badgeV} px-2 py-1" style="font-size: 0.8rem;">${kpi.pct_ventas.toFixed(1)}%</span>
                        </td>
                        
                        <td class="py-3 text-dark fw-semibold">${formatter.format(kpi.real_empenos)}</td>
                        <td class="py-3 text-muted">${formatter.format(kpi.meta_empenos)}</td>
                        <td class="py-3 border-end">
                            <span class="badge ${badgeE} px-2 py-1" style="font-size: 0.8rem;">${kpi.pct_empenos.toFixed(1)}%</span>
                        </td>

                        <td class="py-3 fw-semibold" style="color: #b25e00;">${formatter.format(kpi.real_intereses)}</td>
                        <td class="py-3 text-muted">${formatter.format(kpi.meta_intereses)}</td>
                        <td class="py-3 border-end">
                            <span class="badge ${badgeI} px-2 py-1" style="font-size: 0.8rem;">${kpi.pct_intereses.toFixed(1)}%</span>
                        </td>

                        <td class="py-3 text-success fw-semibold">${formatter.format(kpi.real_utilidad)}</td>
                        <td class="py-3 text-muted">${formatter.format(kpi.meta_utilidad)}</td>
                        <td class="pe-4 py-3">
                            <span class="badge ${badgeU} px-2 py-1" style="font-size: 0.8rem;">${kpi.pct_utilidad.toFixed(1)}%</span>
                        </td>
                    </tr>
                `;
            });

            if (kpis.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center py-4">Sin datos de sucursales</td></tr>';
            }
        }

        function updateTimelineChart(chartData) {
            const ctx = document.getElementById('ventasTimelineChart');
            if (ventasTimelineChart) ventasTimelineChart.destroy();

            ventasTimelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ventas Reales',
                            data: chartData.real,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Meta / Tendencia Ajustada',
                            data: chartData.tendencia,
                            borderColor: '#ffc107',
                            borderDash: [5, 5],
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: 'Año Anterior',
                            data: chartData.ly,
                            borderColor: '#6c757d',
                            borderWidth: 2,
                            opacity: 0.5,
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) { return context.dataset.label + ': ' + formatter.format(context.raw); }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => formatter.format(value) }
                        }
                    }
                }
            });
        }

        function updateEstacionalidadChart(chartData) {
            const ctx = document.getElementById('estacionalidadChart');
            if (estacionalidadChart) estacionalidadChart.destroy();

            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            estacionalidadChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: meses,
                    datasets: [
                        {
                            label: 'Ventas',
                            data: chartData.ventas,
                            borderColor: '#0d6efd',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3
                        },
                        {
                            label: 'Empeños',
                            data: chartData.empenos,
                            borderColor: '#fd7e14',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3
                        },
                        {
                            label: 'Intereses',
                            data: chartData.intereses,
                            borderColor: '#ffc107',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3
                        },
                        {
                            label: 'Utilidad',
                            data: chartData.utilidad,
                            borderColor: '#198754',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) { return context.dataset.label + ': ' + context.raw.toFixed(2); }
                            }
                        }
                    },
                    scales: {
                        y: {
                            suggestedMin: 0.5,
                            suggestedMax: 1.5,
                            ticks: { callback: value => value.toFixed(1) },
                            title: {
                                display: true,
                                text: 'Índice de Estacionalidad'
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
