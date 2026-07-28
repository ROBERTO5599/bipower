@extends('employees.layouts.main')

@section('title', 'Reporte de Pagos con Tarjeta')

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
        .bg-light-primary { background-color: rgba(13, 110, 253, 0.1); }
        
        .table-responsive { overflow-x: auto; }

        /* Spinner Overlay */
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

        /* Print styling to match a clean receipt look */
        @media print {
            body * {
                visibility: hidden;
            }
            #print-section, #print-section * {
                visibility: visible;
            }
            #print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            #sidebar-wrapper, #menu-toggle, #filter-form, .btn, #loading-overlay, .navbar, .kpi-container {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table th {
                background-color: #f8f9fa !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <h5 class="text-muted fw-bold">Obteniendo reporte de pagos con tarjeta...</h5>
</div>

<div class="container-fluid p-4" id="dashboard-content" style="display: none;">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="title fw-bold text-dark">Reporte de Pagos con Tarjeta</h4>
                <p class="text-muted mb-0">Listado consolidado de cobros con terminal, clip, transferencia y depósito</p>
            </div>
            <div>
                <button onclick="window.print();" class="btn btn-primary shadow-sm fw-bold">
                    <i class="bi bi-printer me-2"></i>Imprimir Reporte
                </button>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sucursal</label>
                    <select name="sucursal_id" id="sucursal_id" class="form-select">
                        <option value="">-- Todas Consolidado --</option>
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
                    <label class="form-label fw-semibold">Fecha Hasta (Corte)</label>
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

    <!-- KPIs Principales -->
    <div class="row mb-4 kpi-container">
        <!-- Total Ingresos Tarjeta -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Total Recaudado</h6>
                        <div class="icon-shape bg-light-success text-success">
                            <i class="bi bi-credit-card-2-back"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-total-tarjeta">$ 0.00</h2>
                    <span class="text-muted small">Monto neto sin cancelados</span>
                </div>
            </div>
        </div>

        <!-- Total Transacciones -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Transacciones</h6>
                        <div class="icon-shape bg-light-info text-info">
                            <i class="bi bi-calculator"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-transacciones">0</h2>
                    <span class="text-muted small">Pagos registrados activos</span>
                </div>
            </div>
        </div>

        <!-- Promedio Transacción -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0 card-hover h-100 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-muted text-uppercase fw-bold ls-1 mb-0">Ticket Promedio</h6>
                        <div class="icon-shape bg-light-primary text-primary">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <h2 class="display-6 fw-bold text-dark mb-0" id="kpi-ticket-promedio">$ 0.00</h2>
                    <span class="text-muted small">Monto promedio por cobro</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Detalle Movimientos -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3" id="print-section">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0">PAGOS CON TARJETA</h5>
                        <p class="text-muted small mb-0 d-block d-print-inline">
                            Período: <span id="print-period">-</span> | Generado: {{ now()->format('d/m/Y H:i:s') }}
                        </p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">Sucursal</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Fecha</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Contrato</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Concepto</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Voucher</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Referencia</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Tipo de Pago</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Transacción</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Monto</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Comisión</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="movimientos-body">
                                <tr><td colspan="11" class="text-center text-muted py-3">Cargando datos...</td></tr>
                            </tbody>
                            <tfoot class="bg-light fw-bold" id="table-footer">
                                <!-- Will be updated by JS -->
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const sucursalSelect = document.getElementById('sucursal_id');

        loadData();

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        function loadData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            const formData = new FormData(form);
            const urlParams = new URLSearchParams(formData).toString();

            const fechaInicioVal = document.getElementById('fecha_inicio').value;
            const fechaFinVal = document.getElementById('fecha_fin').value;
            
            // Format dates for printing
            document.getElementById('print-period').innerText = `${fechaInicioVal} al ${fechaFinVal}`;

            fetch(`{{ route('reporte-pagos-tarjeta.data') }}?${urlParams}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error al obtener los datos');
                    return response.json();
                })
                .then(data => {
                    updateDashboard(data);
                })
                .catch(error => {
                    console.error("Error:", error);
                    const tbody = document.getElementById('movimientos-body');
                    tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-3">Ocurrió un error al cargar el reporte.</td></tr>';
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function updateDashboard(data) {
            // Update KPIs
            document.getElementById('kpi-total-tarjeta').innerText = formatter.format(data.totalGeneral);
            
            const activeTransactionsCount = data.detalleMovimientos.filter(m => m.status === 'CONFIRMADO').length;
            document.getElementById('kpi-transacciones').innerText = activeTransactionsCount;

            const ticketPromedio = activeTransactionsCount > 0 ? (data.totalGeneral / activeTransactionsCount) : 0;
            document.getElementById('kpi-ticket-promedio').innerText = formatter.format(ticketPromedio);

            // Populate Table Body
            const tbody = document.getElementById('movimientos-body');
            tbody.innerHTML = '';
            
            if (data.detalleMovimientos && data.detalleMovimientos.length > 0) {
                data.detalleMovimientos.forEach(row => {
                    const isCanceled = row.status === 'CANCELADO';
                    const rowClass = isCanceled ? 'table-warning text-muted text-decoration-line-through' : '';
                    
                    const statusBadge = isCanceled 
                        ? `<span class="badge bg-danger rounded-pill">CANCELADA</span>` 
                        : `<span class="badge bg-success rounded-pill">ACTIVA</span>`;

                    tbody.innerHTML += `
                        <tr class="${rowClass}">
                            <td class="ps-4 fw-semibold">${row.sucursal}</td>
                            <td>${row.fecha}</td>
                            <td>${row.contrato}</td>
                            <td>${row.concepto}</td>
                            <td class="text-center">${row.voucher ? '<span class="badge bg-danger fw-bold">' + row.voucher + '</span>' : statusBadge}</td>
                            <td>${row.referencia || '-'}</td>
                            <td class="fw-semibold text-secondary">${row.tipo_pago}</td>
                            <td class="fw-semibold text-primary">${row.transaccion}</td>
                            <td class="text-end fw-semibold">${formatter.format(row.monto)}</td>
                            <td class="text-end text-muted">${formatter.format(row.comision)}</td>
                            <td class="pe-4 text-end fw-bold text-success">${formatter.format(row.total)}</td>
                        </tr>
                    `;
                });

                // Update Table Footer with totals
                const tfoot = document.getElementById('table-footer');
                tfoot.innerHTML = `
                    <tr>
                        <td colspan="8" class="ps-4 py-3 text-end">TOTAL REPORTADO (ACTIVOS):</td>
                        <td class="text-end py-3">${formatter.format(data.totalMonto)}</td>
                        <td class="text-end py-3 text-muted">${formatter.format(data.totalComision)}</td>
                        <td class="pe-4 text-end py-3 text-success font-monospace" style="font-size: 1.15rem;">
                            ${formatter.format(data.totalGeneral)}
                        </td>
                    </tr>
                `;

            } else {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No se encontraron movimientos con tarjeta en el período seleccionado.</td></tr>';
                document.getElementById('table-footer').innerHTML = '';
            }
        }
    });
</script>
@endsection
