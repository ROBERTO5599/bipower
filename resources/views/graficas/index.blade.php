@extends('employees.layouts.main')

@section('title', 'Centro de Gráficas Globales')

@section('styles')
    <style type="text/css">
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease;
        }
        .icon-shape {
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            border-radius: 50%;
        }
        .bg-light-success { background-color: rgba(25, 135, 84, 0.1); }
        .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); }
        .bg-light-info { background-color: rgba(13, 202, 240, 0.1); }
        .bg-light-warning { background-color: rgba(255, 193, 7, 0.1); }
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.1); }
        .bg-light-secondary { background-color: rgba(108, 117, 125, 0.1); }

        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

        .nav-pills .nav-link {
            color: #667eea;
            font-weight: 600;
            border-radius: 20px;
            padding: 8px 20px;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link.active, .nav-pills .show>.nav-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        /* Spinner y loaders para cada tarjeta */
        .chart-loader {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 10;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-radius: 0.5rem;
        }
    </style>
@endsection

@section('content')

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="title fw-bold text-dark">Centro de Gráficas Globales</h4>
            <p class="text-muted">Análisis visual consolidado del desempeño de Valora Más</p>
        </div>
    </div>

    <!-- Filtros Globales -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
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
                    <label class="form-label fw-semibold">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ $fechaFin }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Navigation Pills / Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills" id="chartsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="resumen-tab" data-bs-toggle="pill" data-bs-target="#resumen-pane" type="button" role="tab">
                        <i class="bi bi-bar-chart-line-fill me-1"></i> Resumen Operativo
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ventas-tab" data-bs-toggle="pill" data-bs-target="#ventas-pane" type="button" role="tab">
                        <i class="bi bi-cart-fill me-1"></i> Ventas y Certificados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="inventarios-tab" data-bs-toggle="pill" data-bs-target="#inventarios-pane" type="button" role="tab">
                        <i class="bi bi-box-seam-fill me-1"></i> Cartera e Inventarios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="clientes-tab" data-bs-toggle="pill" data-bs-target="#clientes-pane" type="button" role="tab">
                        <i class="bi bi-people-fill me-1"></i> Clientes y Personal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="gastos-tab" data-bs-toggle="pill" data-bs-target="#gastos-pane" type="button" role="tab">
                        <i class="bi bi-bank me-1"></i> Gastos y Bancos
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tabs Content -->
    <div class="tab-content" id="chartsTabContent">
        <!-- 1. RESUMEN OPERATIVO -->
        <div class="tab-pane fade show active" id="resumen-pane" role="tabpanel">
            <div class="row">
                <!-- Comparativa Financiera -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Comparativa Financiera Global (Ingresos/Gastos/Utilidad)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-financial">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="financialChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Composición Cartera -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Composición de Cartera Depositaria (Vigente vs Vencida)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-cartera">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="carteraChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tendencia Mensual -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Tendencia Histórica Mensual</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-timeline">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="timelineChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distribución de Inventario -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Distribución de Prenda en Inventario</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-inventory">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="inventoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. VENTAS Y CERTIFICADOS -->
        <div class="tab-pane fade" id="ventas-pane" role="tabpanel">
            <div class="row">
                <!-- Ventas vs Utilidad por Familia -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Ventas vs Utilidad por Tipo de Prenda</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-ventasFamilia">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="ventasFamiliaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Métodos de Pago -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Métodos de Pago (Ventas)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-metodosPago">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="metodosPagoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colocación Certificados -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Colocación de Certificados de Confianza</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-colocacionCertificados">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="colocacionCertificadosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plazos Certificados -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Distribución de Plazos (Certificados)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-plazosCertificados">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="plazosCertificadosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. CARTERA E INVENTARIOS -->
        <div class="tab-pane fade" id="inventarios-pane" role="tabpanel">
            <div class="row">
                <!-- Distribución Antigüedad Piso -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Antigüedad de Inventario en Piso</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-distribucionAntiguedadPiso">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="distribucionAntiguedadPisoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Valor Piso por Sucursal -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Valor de Inventario en Piso por Sucursal</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-valorSucursalPiso">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="valorSucursalPisoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tipo de Cartera (Monto) -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Composición Cartera Depositaria (Operaciones)</h6>
                        </div>
                        <div class="col-body p-4 position-relative">
                            <div class="chart-loader" id="loader-carteraTipo">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container p-4">
                                <canvas id="carteraTipoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasa de Mora -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Tasa de Morosidad por Categoría</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-mora">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="moraChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Saldo Colocación Créditos -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Saldo Colocación de Crédito por Sucursal</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-saldoColocacion">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="saldoColocacionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Morosidad en Créditos -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Morosidad en Créditos por Sucursal</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-morosidadCreditos">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="morosidadCreditosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. CLIENTES Y PERSONAL -->
        <div class="tab-pane fade" id="clientes-pane" role="tabpanel">
            <div class="row">
                <!-- Frecuencia de Clientes -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Segmentación por Frecuencia de Empeño</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-segmentacionFrecuencia">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="segmentacionFrecuenciaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LTV Clientes -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">LTV de Clientes (Préstamos vs Intereses)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-ltv">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="ltvChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Segmentos RFM -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Distribución de Segmentos RFM</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-rfm">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="rfmChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ratios de Productividad (Colaboradores) -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Ratios de Operación por Sucursal</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-ratiosSucursal">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="ratiosSucursalChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. GASTOS Y BANCOS -->
        <div class="tab-pane fade" id="gastos-pane" role="tabpanel">
            <div class="row">
                <!-- Composición Gastos -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Distribución de Gastos Operativos</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-composicionGastos">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="composicionGastosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evolución de Resultados -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Evolución de Ingresos y Utilidad por Sucursal</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-evolucionResultados">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="evolucionResultadosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evolución de Saldos por Cuenta -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Flujo Libre Neto por Sucursal (Bancos)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-evolucionSaldos">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="evolucionSaldosChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flujos Mensuales (Entradas vs Salidas) -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Comparación Entradas vs Salidas (Flujo Bancos)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-flujosMensuales">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="flujosMensualesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Origen de Entradas (Bancos) -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Origen de Entradas Financieras (Bancos)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-origenEntradas">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="origenEntradasChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tipo de Salidas (Bancos) -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                        <div class="card-header bg-white border-0 pt-4 px-4">
                            <h6 class="fw-bold mb-0">Tipo de Salidas Financieras (Bancos)</h6>
                        </div>
                        <div class="card-body p-4 position-relative">
                            <div class="chart-loader" id="loader-tipoSalidas">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="chart-container">
                                <canvas id="tipoSalidasChart"></canvas>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        // Formatter de moneda
        const formatter = new Intl.NumberFormat('es-MX', {
            style: 'currency', currency: 'MXN', minimumFractionDigits: 0, maximumFractionDigits: 0
        });

        // Form form / filters
        const form = document.getElementById('filter-form');

        // Mapa de instancias de gráficos creadas para destruirlas antes de recrearlas
        const chartInstances = {};

        // Inicializar carga de datos
        loadAllCharts();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadAllCharts();
        });

        function showLoader(id) {
            const el = document.getElementById('loader-' + id);
            if (el) el.style.display = 'flex';
        }

        function hideLoader(id) {
            const el = document.getElementById('loader-' + id);
            if (el) el.style.display = 'none';
        }

        /**
         * Lanza solicitudes HTTP en paralelo a todos los módulos y dibuja las gráficas
         */
        function loadAllCharts() {
            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            // Mostrar todos los loaders al iniciar
            const activeLoaders = [
                'financial', 'cartera', 'timeline', 'inventory', 'ventasFamilia', 'metodosPago',
                'colocacionCertificados', 'plazosCertificados', 'distribucionAntiguedadPiso', 'valorSucursalPiso',
                'carteraTipo', 'mora', 'saldoColocacion', 'morosidadCreditos', 'segmentacionFrecuencia', 'ltv', 'rfm',
                'ratiosSucursal', 'composicionGastos', 'evolucionResultados', 'evolucionSaldos', 'flujosMensuales',
                'origenEntradas', 'tipoSalidas'
            ];
            activeLoaders.forEach(id => showLoader(id));

            // 1. Cargar datos del Resumen Ejecutivo (6 gráficas)
            fetch(`{{ route('resumen-ejecutivo.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderFinancial(data.chartFinanciero);
                    renderCartera(data.chartCartera);
                    renderTimeline(data.chartTimeline);
                    renderInventory(data.chartInventario);
                })
                .catch(e => console.error("Error resumen ejecutivo:", e))
                .finally(() => {
                    hideLoader('financial');
                    hideLoader('cartera');
                    hideLoader('timeline');
                    hideLoader('inventory');
                });

            // 2. Cargar datos de Ventas (2 gráficas)
            fetch(`{{ route('ventas.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderVentasFamilia(data.chartVentasFamilia);
                    renderMetodosPago(data.chartMetodosPago);
                })
                .catch(e => console.error("Error ventas:", e))
                .finally(() => {
                    hideLoader('ventasFamilia');
                    hideLoader('metodosPago');
                });

            // 3. Cargar datos de Certificados (2 gráficas)
            fetch(`{{ route('certificados.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderColocacionCertificados(data.chartColocacion);
                    renderPlazosCertificados(data.chartPlazos);
                })
                .catch(e => console.error("Error certificados:", e))
                .finally(() => {
                    hideLoader('colocacionCertificados');
                    hideLoader('plazosCertificados');
                });

            // 4. Cargar datos de Inventario Piso (2 gráficas)
            fetch(`{{ route('inventario-piso.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderDistribucionPiso(data.chartDistribucionAntiguedad);
                    renderValorPiso(data.chartValorAntiguedadSucursal);
                })
                .catch(e => console.error("Error inventario piso:", e))
                .finally(() => {
                    hideLoader('distribucionAntiguedadPiso');
                    hideLoader('valorSucursalPiso');
                });

            // 5. Cargar datos de Operaciones Cartera (2 gráficas)
            fetch(`{{ route('operaciones-cartera.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderCarteraTipo(data.chartCarteraTipo);
                    renderMora(data.chartMora);
                })
                .catch(e => console.error("Error operaciones cartera:", e))
                .finally(() => {
                    hideLoader('carteraTipo');
                    hideLoader('mora');
                });

            // 6. Cargar datos de Créditos (2 gráficas)
            fetch(`{{ route('creditos.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderSaldoColocacion(data.chartSaldoColocacion);
                    renderMorosidadCreditos(data.chartMorosidad);
                })
                .catch(e => console.error("Error creditos:", e))
                .finally(() => {
                    hideLoader('saldoColocacion');
                    hideLoader('morosidadCreditos');
                });

            // 7. Cargar datos de Clientes (3 gráficas)
            fetch(`{{ route('clientes.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderSegmentacionFrecuencia(data.chartSegmentacionFrecuencia);
                    renderLTV(data.chartLTV);
                    renderRFM(data.chartRFM);
                })
                .catch(e => console.error("Error clientes:", e))
                .finally(() => {
                    hideLoader('segmentacionFrecuencia');
                    hideLoader('ltv');
                    hideLoader('rfm');
                });

            // 8. Cargar datos de Colaboradores (1 gráfica)
            fetch(`{{ route('colaboradores.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderRatiosSucursal(data.chartRatiosSucursal);
                })
                .catch(e => console.error("Error colaboradores:", e))
                .finally(() => {
                    hideLoader('ratiosSucursal');
                });

            // 9. Cargar datos de Gastos y Finanzas (2 gráficas)
            fetch(`{{ route('gastos-finanzas.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderComposicionGastos(data.chartComposicionGastos);
                    renderEvolucionResultados(data.chartEvolucionIngresosUtilidad);
                })
                .catch(e => console.error("Error gastos finanzas:", e))
                .finally(() => {
                    hideLoader('composicionGastos');
                    hideLoader('evolucionResultados');
                });

            // 10. Cargar datos de Bancos y Flujos (4 gráficas)
            fetch(`{{ route('bancos.data') }}?${params}`)
                .then(r => r.json())
                .then(data => {
                    renderEvolucionSaldos(data.chartEvolucionSaldos);
                    renderFlujosMensuales(data.chartFlujosMensuales);
                    renderOrigenEntradas(data.chartEntradasPorOrigen);
                    renderTipoSalidas(data.chartSalidasPorTipo);
                })
                .catch(e => console.error("Error bancos:", e))
                .finally(() => {
                    hideLoader('evolucionSaldos');
                    hideLoader('flujosMensuales');
                    hideLoader('origenEntradas');
                    hideLoader('tipoSalidas');
                });
        }

        // ============================================
        // DIBUJADO DE GRÁFICOS (CHART.JS)
        // ============================================

        function drawChart(canvasId, config) {
            if (chartInstances[canvasId]) {
                chartInstances[canvasId].destroy();
            }
            const ctx = document.getElementById(canvasId);
            if (ctx) {
                chartInstances[canvasId] = new Chart(ctx, config);
            }
        }

        function renderFinancial(chartData) {
            if (!chartData) return;
            drawChart('financialChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto (MXN)',
                        data: chartData.data,
                        backgroundColor: ['rgba(25, 135, 84, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(13, 202, 240, 0.7)'],
                        borderColor: ['rgba(25, 135, 84, 1)', 'rgba(220, 53, 69, 1)', 'rgba(13, 202, 240, 1)'],
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderCartera(chartData) {
            if (!chartData) return;
            drawChart('carteraChart', {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '60%'
                }
            });
        }

        function renderTimeline(chartData) {
            if (!chartData) return;
            drawChart('timelineChart', {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: chartData.ingresos,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidades,
                            borderColor: '#0dcaf0',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Flujo Neto',
                            data: chartData.flujo,
                            borderColor: '#ffc107',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderInventory(chartData) {
            if (!chartData) return;
            drawChart('inventoryChart', {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#FFD700', '#C0C0C0', '#fd7e14', '#0dcaf0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right' } },
                    cutout: '60%'
                }
            });
        }

        function renderVentasFamilia(chartData) {
            if (!chartData) return;
            drawChart('ventasFamiliaChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ventas Brutas',
                            data: chartData.ventas,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderRadius: 4
                        },
                        {
                            label: 'Utilidad Generada',
                            data: chartData.utilidades,
                            backgroundColor: 'rgba(25, 135, 84, 0.7)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderMetodosPago(chartData) {
            if (!chartData) return;
            drawChart('metodosPagoChart', {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#198754', '#0d6efd', '#fd7e14']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function renderColocacionCertificados(chartData) {
            if (!chartData) return;
            drawChart('colocacionCertificadosChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto Colocado',
                        data: chartData.valores,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderPlazosCertificados(chartData) {
            if (!chartData) return;
            drawChart('plazosCertificadosChart', {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#0d6efd', '#ffc107', '#198754']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function renderDistribucionPiso(chartData) {
            if (!chartData) return;
            drawChart('distribucionAntiguedadPisoChart', {
                type: 'bar',
                data: {
                    labels: ['0-30 días', '31-60 días', '61-90 días', '90+ días'],
                    datasets: [
                        {
                            label: 'Oro (Kilatajes)',
                            data: chartData.data_oro,
                            backgroundColor: 'rgba(255, 215, 0, 0.7)',
                            borderRadius: 4
                        },
                        {
                            label: 'Varios (Electrónica/Otros)',
                            data: chartData.data_varios,
                            backgroundColor: 'rgba(108, 117, 125, 0.7)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function renderValorPiso(chartData) {
            if (!chartData) return;
            drawChart('valorSucursalPisoChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Valor Piso (MXN)',
                        data: chartData.valores,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderCarteraTipo(chartData) {
            if (!chartData) return;
            drawChart('carteraTipoChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto Cartera (MXN)',
                        data: chartData.data,
                        backgroundColor: 'rgba(13, 202, 240, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderMora(chartData) {
            if (!chartData) return;
            drawChart('moraChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Tasa de Mora %',
                        data: chartData.data,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } }
                }
            });
        }

        function renderSaldoColocacion(chartData) {
            if (!chartData) return;
            drawChart('saldoColocacionChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Saldo Colocación (MXN)',
                        data: chartData.valores,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderMorosidadCreditos(chartData) {
            if (!chartData) return;
            drawChart('morosidadCreditosChart', {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Tasa Morosidad',
                        data: chartData.antiguedad,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }

        function renderSegmentacionFrecuencia(chartData) {
            if (!chartData) return;
            drawChart('segmentacionFrecuenciaChart', {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#fd7e14', '#0d6efd', '#198754']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function renderLTV(chartData) {
            if (!chartData) return;
            drawChart('ltvChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto total',
                        data: chartData.data,
                        backgroundColor: ['rgba(13, 110, 253, 0.7)', 'rgba(25, 135, 84, 0.7)'],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderRFM(chartData) {
            if (!chartData) return;
            drawChart('rfmChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Clientes',
                        data: chartData.data,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function renderRatiosSucursal(chartData) {
            if (!chartData) return;
            drawChart('ratiosSucursalChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Ratios por Sucursal',
                        data: chartData.data,
                        backgroundColor: 'rgba(118, 75, 162, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }

        function renderComposicionGastos(chartData) {
            if (!chartData) return;
            drawChart('composicionGastosChart', {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#0d6efd', '#20c997', '#ffc107', '#fd7e14', '#198754', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right' } }
                }
            });
        }

        function renderEvolucionResultados(chartData) {
            if (!chartData) return;
            drawChart('evolucionResultadosChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: chartData.ingresos,
                            backgroundColor: 'rgba(25, 135, 84, 0.7)',
                            borderRadius: 4
                        },
                        {
                            label: 'Utilidad Neta',
                            data: chartData.utilidadNeta,
                            backgroundColor: 'rgba(13, 202, 240, 0.7)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderEvolucionSaldos(chartData) {
            if (!chartData) return;
            drawChart('evolucionSaldosChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Flujo Libre Cajas',
                            data: chartData.flujo_efectivo,
                            backgroundColor: 'rgba(25, 135, 84, 0.7)',
                            borderRadius: 4
                        },
                        {
                            label: 'TPV Bancos',
                            data: chartData.flujo_bancos,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderFlujosMensuales(chartData) {
            if (!chartData) return;
            drawChart('flujosMensualesChart', {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Monto (MXN)',
                        data: chartData.data,
                        backgroundColor: ['rgba(25, 135, 84, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(13, 202, 240, 0.7)'],
                        borderColor: ['#198754', '#dc3545', '#0dcaf0'],
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => formatter.format(v) } } }
                }
            });
        }

        function renderOrigenEntradas(chartData) {
            if (!chartData) return;
            drawChart('origenEntradasChart', {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#198754', '#0d6efd']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function renderTipoSalidas(chartData) {
            if (!chartData) return;
            drawChart('tipoSalidasChart', {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: ['#dc3545', '#fd7e14']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
</script>
@endsection
