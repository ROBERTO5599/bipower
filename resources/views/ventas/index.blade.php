@extends('employees.layouts.main')

@section('title', 'Ventas, Descuentos y Medios de Pago')

@section('styles')
    <style type="text/css">
        .cursor-pointer {
            cursor: pointer;
        }

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

        .bg-light-success {
            background-color: rgba(25, 135, 84, 0.1);
        }

        .bg-light-danger {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .bg-light-info {
            background-color: rgba(13, 202, 240, 0.1);
        }

        .bg-light-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }

        .bg-light-primary {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .bg-light-secondary {
            background-color: rgba(108, 117, 125, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        /* Spinner */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .metric-tooltip {
            position: relative;
            display: inline-block;
            border-bottom: 1px dotted #6c757d;
            cursor: help;
        }
    </style>
@endsection

@section('content')

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <h5 class="text-muted fw-bold">Analizando ventas y pagos...</h5>
    </div>

    <div class="container-fluid p-4" id="dashboard-content" style="display: none;">
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="title fw-bold text-dark">Análisis de Ventas, Descuentos y Medios de Pago</h4>
                <p class="text-muted">Desempeño de ventas de piso, rentabilidad y métodos de cobro</p>
            </div>
        </div>

        <!-- Filtros -->
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
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}"
                            class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Fecha Hasta</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="{{ substr($fechaFin, 0, 10) }}"
                            class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-funnel-fill me-2"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPIs Principales -->
        <div class="row mb-4 justify-content-center">
            <!-- Ventas Totales -->
            <div class="col-12 col-xl-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Ventas Totales</h6>
                            <div class="icon-shape bg-light-success text-success">
                                <i class="bi bi-cart-check"></i>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ventas-totales">$ 0.00</h2>
                        <span class="text-muted small">Oro, Varios, Autos</span>
                    </div>
                </div>
            </div>

            <!-- Contratos de ventas y apartados liquidados -->
            <div class="col-12 col-xl-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Contratos de ventas y apartados
                                liquidados</h6>
                            <div class="icon-shape bg-light-info text-info">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-tickets">0 Contratos</h2>
                        <span class="text-muted small" id="kpi-ticket-promedio">Monto promedio: $ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Utilidad Bruta de Ventas -->
            <div class="col-12 col-xl-4 mb-3">
                <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Utilidad Bruta</h6>
                            <div class="icon-shape bg-light-primary text-primary">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                        <h2 class="display-6 fw-bold mb-0 text-dark" id="kpi-utilidad-bruta">$ 0.00</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs Secundarios: Descuentos y Tarjetas -->
        <div class="row mb-4">
            <!-- Descuentos -->
            <div class="col-12 col-xl-6 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-shape bg-light-warning text-warning me-3">
                                <i class="bi bi-tags-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Total en Descuentos</h6>
                                <h3 class="fw-bold text-dark mb-0" id="kpi-descuento-total">$ 0.00</h3>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <small class="text-muted d-block">Número de contratos con descuento</small>
                                <span class="fw-bold" id="kpi-tickets-descuento">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagos con Efectivo / Tarjeta -->
            <div class="col-12 col-xl-6 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-shape bg-light-secondary text-secondary me-3">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div>
                                <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Efectivo / Tarjeta</h6>
                                <h3 class="fw-bold text-dark mb-0">
                                    <span id="kpi-pagos-efectivo" class="text-success">$ 0.00</span>
                                    <span class="text-muted fs-5 mx-1">|</span>
                                    <span id="kpi-pagos-tarjeta" class="text-primary">$ 0.00</span>
                                </h3>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <small class="text-muted d-block">% Ventas en Efectivo</small>
                                <span class="fw-bold text-success" id="kpi-porcentaje-efectivo">0%</span>
                            </div>
                            <div class="col-6 border-start">
                                <small class="text-muted d-block">% Ventas con Tarjeta</small>
                                <span class="fw-bold text-primary" id="kpi-porcentaje-tarjeta">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <!-- Ventas por Familia (Apilada Ventas vs Utilidad) -->
            <div class="col-md-8 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Ventas vs Utilidad por Tipo de Prenda</h5>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="ventasFamiliaChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Métodos de pago -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm border-0 h-100 rounded-3">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Métodos de Pago</h5>
                    </div>
                    <div class="card-body p-4 d-flex justify-content-center align-items-center">
                        <canvas id="metodosPagoChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablas Top N Artículos -->
        <div class="row mb-4">
            <!-- Top por Importe (Venta) -->
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Top 10 Artículos (Más Vendidos)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Artículo</th>
                                        <th class="py-3 text-uppercase text-muted small fw-bold text-end">Vendidos</th>
                                        <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Ingreso Bruto
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="top-articulos-importe">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Cargando datos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top por Margen -->
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Top 10 Artículos (Mayor Margen %)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Artículo</th>
                                        <th class="py-3 text-uppercase text-muted small fw-bold text-end">Utilidad Neta</th>
                                        <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">% Margen</th>
                                    </tr>
                                </thead>
                                <tbody id="top-articulos-margen">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Cargando datos...</td>
                                    </tr>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            let ventasFamiliaChart = null;
            let metodosPagoChart = null;

            const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
            const numberFormatter = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            const percentFormatter = new Intl.NumberFormat('es-MX', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 });

            const overlay = document.getElementById('loading-overlay');
            const dashboard = document.getElementById('dashboard-content');
            const form = document.getElementById('filter-form');

            loadData();

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                loadData();
            });

            function loadData() {
                overlay.style.display = 'flex';
                dashboard.style.opacity = '0.5';

                const formData = new FormData(form);
                const urlParams = new URLSearchParams(formData).toString();

                fetch(`{{ route('ventas.data') }}?${urlParams}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        updateDashboard(data);
                    })
                    .catch(error => {
                        console.error("Error:", error);
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

            function updateDashboard(data) {
                // KPIs Principales
                updateElementText('kpi-ventas-totales', formatter.format(data.ventasTotales || 0));
                updateElementText('kpi-ticket-promedio', `Monto promedio: ${formatter.format(data.ticketPromedio || 0)}`);
                updateElementText('kpi-total-tickets', `${numberFormatter.format(data.totalTickets || 0)} Contratos`);

                updateElementText('kpi-utilidad-bruta', formatter.format(data.utilidadBruta || 0));
                updateElementText('kpi-margen-venta', `${(data.margenVentaPorcentaje || 0).toFixed(1)}% Margen de Venta`);

                // Descuentos y Tarjetas
                updateElementText('kpi-descuento-total', formatter.format(data.montoDescuentoTotal || 0));
                updateElementText('kpi-tickets-descuento', numberFormatter.format(data.ticketsConDescuento || 0));

                updateElementText('kpi-pagos-efectivo', formatter.format(data.pagosEfectivo || 0));
                updateElementText('kpi-pagos-tarjeta', formatter.format(data.pagosTarjeta || 0));
                updateElementText('kpi-porcentaje-efectivo', `${(data.pagosEfectivoPorcentaje || 0).toFixed(1)}%`);
                updateElementText('kpi-porcentaje-tarjeta', `${(data.pagosTarjetaPorcentaje || 0).toFixed(1)}%`);

                // Tablas Top Artículos - Importe
                const tbodyImporte = document.getElementById('top-articulos-importe');
                if (data.topArticulosImporte && data.topArticulosImporte.length > 0) {
                    let tableHtml = '';
                    data.topArticulosImporte.forEach(item => {
                        tableHtml += `
                                        <tr>
                                            <td class="ps-4 py-3 fw-bold text-dark">${item.nombre}</td>
                                            <td class="py-3 text-end">${numberFormatter.format(item.cantidad)}</td>
                                            <td class="pe-4 py-3 text-end fw-bold text-primary">${formatter.format(item.ventas)}</td>
                                        </tr>
                                    `;
                    });
                    tbodyImporte.innerHTML = tableHtml;
                } else {
                    tbodyImporte.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Sin datos</td></tr>';
                }

                // Tablas Top Artículos - Margen
                const tbodyMargen = document.getElementById('top-articulos-margen');
                if (data.topArticulosMargen && data.topArticulosMargen.length > 0) {
                    let tableHtml = '';
                    data.topArticulosMargen.forEach(item => {
                        tableHtml += `
                                        <tr>
                                            <td class="ps-4 py-3 fw-bold text-dark">${item.nombre}</td>
                                            <td class="py-3 text-end text-success">${formatter.format(item.utilidad)}</td>
                                            <td class="pe-4 py-3 text-end fw-bold">${(item.margen_prc || 0).toFixed(1)}%</td>
                                        </tr>
                                    `;
                    });
                    tbodyMargen.innerHTML = tableHtml;
                } else {
                    tbodyMargen.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Sin datos</td></tr>';
                }

                // Gráficos
                updateBarChart(data.chartVentasFamilia);
                updateDoughnutChart(data.chartMetodosPago);
            }

            function updateBarChart(chartData) {
                const ctx = document.getElementById('ventasFamiliaChart');
                if (!ctx) return;

                if (ventasFamiliaChart) ventasFamiliaChart.destroy();

                if (!chartData) return;

                ventasFamiliaChart = new Chart(ctx, {
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
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: value => formatter.format(value) }
                            }
                        }
                    }
                });
            }

            function updateDoughnutChart(chartData) {
                const ctx = document.getElementById('metodosPagoChart');
                if (!ctx) return;

                if (metodosPagoChart) metodosPagoChart.destroy();
                if (!chartData) return;

                metodosPagoChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            data: chartData.data,
                            backgroundColor: [
                                '#198754', // Efectivo
                                '#0d6efd', // Tarjeta
                                '#fd7e14'  // Transferencia
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return ' ' + context.label + ': ' + formatter.format(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
@endsection