@extends('employees.layouts.main')

@section('title', 'Reporte de Pagos con Tarjeta')

@section('styles')
    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <style type="text/css">
        .cursor-pointer { cursor: pointer; }
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease;
        }
        .icon-shape {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
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
            background: rgba(255, 255, 255, 0.85);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }

        /* Spreadsheet-like interactive styling */
        .table-input {
            border: 1px solid transparent;
            background-color: transparent;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.875rem;
            text-align: right;
            width: 100%;
            transition: all 0.2s;
        }
        .table-input:hover {
            border-color: #dee2e6;
            background-color: #f8f9fa;
        }
        .table-input:focus {
            border-color: #0d6efd;
            background-color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            outline: none;
        }
        .table-select {
            border: 1px solid #ced4da;
            background-color: #fff;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.875rem;
            width: 100%;
            text-align: center;
            transition: all 0.2s;
        }
        .table-select:hover {
            border-color: #adb5bd;
        }
        .table-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            outline: none;
        }

        /* Overridden input styling */
        .input-overridden {
            border-color: #ffc107 !important;
            background-color: #fff3cd !important;
            color: #664d03;
            font-weight: 600;
        }

        /* Table Column Sizing */
        .w-compct { width: 75px; margin: 0 auto; }
        .w-months { width: 70px; margin: 0 auto; }
        .w-amount { width: 105px; display: inline-block; }

        .btn-xs {
            padding: 0.15rem 0.35rem;
            font-size: 0.75rem;
            border-radius: 0.2rem;
            line-height: 1;
        }

        .config-card {
            border: 1px solid rgba(13, 110, 253, 0.2);
            border-left: 4px solid #0d6efd;
            background-color: #f8faff;
        }

        /* DataTables Custom Styling */
        div.dataTables_wrapper div.dataTables_filter {
            text-align: right;
            margin-bottom: 0.75rem;
        }
        div.dataTables_wrapper div.dataTables_length {
            margin-bottom: 0.75rem;
        }
        div.dataTables_wrapper div.dataTables_filter input {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 4px 10px;
            margin-left: 6px;
        }
        div.dataTables_wrapper div.dataTables_length select {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 4px 8px;
            margin: 0 4px;
        }
        .dataTables_wrapper .pagination .page-item .page-link {
            border-radius: 4px;
            margin: 0 2px;
        }
        .dataTables_wrapper .pagination .page-item.active .page-link {
            background: #0d6efd;
            border-color: #0d6efd;
        }

        /* Print styling */
        @media print {
            body * { visibility: hidden; }
            #print-section, #print-section * { visibility: visible; }
            #print-section {
                position: absolute;
                left: 0; top: 0; width: 100%;
            }
            .table-input {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                text-align: right;
                box-shadow: none !important;
                outline: none !important;
                font-weight: normal !important;
                color: #000 !important;
            }
            .table-select {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                text-align: center;
                box-shadow: none !important;
                outline: none !important;
                color: #000 !important;
            }
            .actions-column, .btn-reset-row, th.actions-column, td.actions-column, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate {
                display: none !important;
            }
            #sidebar-wrapper, #menu-toggle, #filter-form, .btn, #loading-overlay, .navbar, .kpi-container, .config-accordion {
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
            <div class="d-flex gap-2">
                <button id="btn-export-excel" class="btn btn-success shadow-sm fw-bold">
                    <i class="bi bi-file-earmark-excel me-2"></i>Exportar a Excel
                </button>
                <button onclick="window.print();" class="btn btn-primary shadow-sm fw-bold">
                    <i class="bi bi-printer me-2"></i>Imprimir Reporte
                </button>
            </div>
        </div>
    </div>

    <!-- 1. Filtros Principales (Originales) -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <form id="filter-form" class="row g-3">
                <div class="col-md-3">
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
                    <label class="form-label fw-semibold">Transacción</label>
                    <select name="transaccion" id="transaccion" class="form-select">
                        <option value="">-- Todos --</option>
                        <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                        <option value="CLIP">CLIP</option>
                        <option value="DEPOSITO">DEPOSITO</option>
                        <option value="TERMINAL">TERMINAL</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Fecha Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $fechaInicio }}" class="form-control">
                </div>
                <div class="col-md-2">
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

    <!-- 2. Panel de Configuración de Tasas por Sucursal (Colapsable) -->
    <div class="card shadow-sm border-0 mb-4 rounded-3 config-card config-accordion">
        <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#configPanelBody">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="bi bi-sliders me-2"></i>Configuración de Tasas y Comisiones por Sucursal
            </h5>
            <span class="text-muted small"><i class="bi bi-chevron-down me-1"></i> Expandir / Contraer</span>
        </div>
        <div id="configPanelBody" class="collapse">
            <div class="card-body px-4 pb-4">
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                    <i class="bi bi-info-circle-fill me-3 fs-4 text-info"></i>
                    <div>
                        Configura las tasas predeterminadas para cada sucursal. Estos valores actúan como fórmulas automáticas en la tabla inferior. Las modificaciones en una fila específica anulan temporalmente la fórmula general para esa transacción.
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-center mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-3">Sucursal</th>
                                <th>Comisión Base %</th>
                                <th>3 MSI %</th>
                                <th>6 MSI %</th>
                                <th>9 MSI %</th>
                                <th>12 MSI %</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="sucursal-rates-body">
                            @foreach($sucursales ?? [] as $sucursal)
                                <tr data-sucursal-id="{{ $sucursal->id_valora_mas }}">
                                    <td class="text-start fw-bold ps-3">{{ $sucursal->nombre }}</td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm text-center rate-base" value="2.99" style="width: 80px; margin: 0 auto;">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm text-center rate-msi3" value="4.50" style="width: 80px; margin: 0 auto;">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm text-center rate-msi6" value="7.50" style="width: 80px; margin: 0 auto;">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm text-center rate-msi9" value="9.90" style="width: 80px; margin: 0 auto;">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm text-center rate-msi12" value="11.95" style="width: 80px; margin: 0 auto;">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-copy-rates py-1">
                                            <i class="bi bi-copy me-1"></i>Copiar a Todas
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" id="btn-reset-config" class="btn btn-outline-secondary fw-bold">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Restablecer Valores por Defecto
                    </button>
                    <button type="button" id="btn-save-config" class="btn btn-success fw-bold">
                        <i class="bi bi-check-circle me-2"></i>Guardar y Aplicar Fórmulas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. KPIs Principales (Originales) -->
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

    <!-- 4. Tabla Detalle Movimientos con los 3 Filtros justo arriba y diseño original -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3" id="print-section">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-0">PAGOS CON TARJETA</h5>
                            <p class="text-muted small mb-0 d-block d-print-inline">
                                Período: <span id="print-period">-</span> | Generado: {{ now()->format('d/m/Y H:i:s') }}
                            </p>
                        </div>
                    </div>

                    <!-- LOS 3 FILTROS EXACTAMENTE COMO EN TU IMAGEN -->
                    <div class="row g-3 align-items-end pt-3 pb-3 border-top">
                        <div class="col-12 col-md-4">
                            <label for="table_transaccion" class="form-label fw-bold text-muted small text-uppercase mb-1">TRANSACCIÓN</label>
                            <select id="table_transaccion" class="form-select">
                                <option value="">-- Todas --</option>
                                <option value="CLIP">CLIP</option>
                                <option value="TERMINAL">TERMINAL</option>
                                <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                                <option value="DEPOSITO">DEPOSITO</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="table_banco" class="form-label fw-bold text-muted small text-uppercase mb-1">BANCO (CUENTA DESTINO)</label>
                            <select id="table_banco" class="form-select">
                                <option value="">-- Todos los Bancos / Cuentas --</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="table_concepto" class="form-label fw-bold text-muted small text-uppercase mb-1">CONCEPTO</label>
                            <select id="table_concepto" class="form-select">
                                <option value="">-- Todos los Conceptos --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table table-hover align-middle mb-0 w-100" id="tabla-pagos-tarjeta">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold">SUCURSAL</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">FECHA</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">CONTRATO</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">CONCEPTO</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">VOUCHER</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">REFERENCIA</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">TIPO DE PAGO</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">TRANSACCIÓN</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold">CUENTA DESTINO</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">MONTO</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">MESES</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">COMISIÓN %</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">COMISIÓN MESES</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">COMISIÓN CLIP</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">IVA COMISIÓN</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">TOTAL</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center actions-column">FÓRMULA</th>
                                </tr>
                            </thead>
                            <tbody id="movimientos-body">
                                <tr><td colspan="17" class="text-center text-muted py-3">Cargando datos...</td></tr>
                            </tbody>
                            <tfoot class="bg-light fw-bold" id="table-footer">
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
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables Bootstrap 5 -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<!-- SheetJS CDN for high fidelity Excel Export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

        const overlay = document.getElementById('loading-overlay');
        const dashboard = document.getElementById('dashboard-content');
        const form = document.getElementById('filter-form');
        const sucursalSelect = document.getElementById('sucursal_id');

        // Global State variables
        let rawMovements = [];
        let sucursalRates = {};
        let rowOverrides = {};
        let dataTableInstance = null;

        // 1. Initialize Sucursal Rates
        initSucursalRates();

        // 2. Fetch initial data
        loadData();

        // Event listener for main filter form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
        });

        // 3. Listener para los 3 filtros rápidos arriba de la tabla
        $('#table_transaccion, #table_banco, #table_concepto').on('change', function() {
            recomputeAndRender();
        });

        // Event listener for config panel - SAVE
        document.getElementById('btn-save-config').addEventListener('click', function() {
            document.querySelectorAll('#sucursal-rates-body tr').forEach(row => {
                const sucId = row.dataset.sucursalId;
                sucursalRates[sucId] = {
                    base: parseFloat(row.querySelector('.rate-base').value) || 0,
                    msi3: parseFloat(row.querySelector('.rate-msi3').value) || 0,
                    msi6: parseFloat(row.querySelector('.rate-msi6').value) || 0,
                    msi9: parseFloat(row.querySelector('.rate-msi9').value) || 0,
                    msi12: parseFloat(row.querySelector('.rate-msi12').value) || 0
                };
            });
            localStorage.setItem('card_payments_sucursal_rates', JSON.stringify(sucursalRates));
            
            recomputeAndRender();
            
            const btn = document.getElementById('btn-save-config');
            const originalText = btn.innerHTML;
            btn.className = "btn btn-success fw-bold";
            btn.innerHTML = `<i class="bi bi-check-all me-2"></i>¡Fórmulas Aplicadas!`;
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        });

        // Event listener for config panel - RESET CONFIGS
        document.getElementById('btn-reset-config').addEventListener('click', function() {
            if (confirm('¿Estás seguro de restablecer todas las tasas a sus valores por defecto (2.99% base)?')) {
                localStorage.removeItem('card_payments_sucursal_rates');
                document.querySelectorAll('#sucursal-rates-body tr').forEach(row => {
                    row.querySelector('.rate-base').value = '2.99';
                    row.querySelector('.rate-msi3').value = '4.50';
                    row.querySelector('.rate-msi6').value = '7.50';
                    row.querySelector('.rate-msi9').value = '9.90';
                    row.querySelector('.rate-msi12').value = '11.95';
                });
                initSucursalRates();
                recomputeAndRender();
            }
        });

        // Copy rates from one sucursal row to all others in the settings table
        document.getElementById('sucursal-rates-body').addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-copy-rates');
            if (!btn) return;
            
            const row = btn.closest('tr');
            if (!row) return;
            
            const base = row.querySelector('.rate-base').value;
            const msi3 = row.querySelector('.rate-msi3').value;
            const msi6 = row.querySelector('.rate-msi6').value;
            const msi9 = row.querySelector('.rate-msi9').value;
            const msi12 = row.querySelector('.rate-msi12').value;
            
            if (confirm('¿Copiar estas tasas de comisión a todas las sucursales?')) {
                document.querySelectorAll('#sucursal-rates-body tr').forEach(r => {
                    if (r !== row) {
                        r.querySelector('.rate-base').value = base;
                        r.querySelector('.rate-msi3').value = msi3;
                        r.querySelector('.rate-msi6').value = msi6;
                        r.querySelector('.rate-msi9').value = msi9;
                        r.querySelector('.rate-msi12').value = msi12;
                    }
                });
                document.getElementById('btn-save-config').click();
            }
        });

        // Event delegation for table inline editing via jQuery so DataTables pagination doesn't drop listeners
        $(document).on('input', '#tabla-pagos-tarjeta tbody .table-input', function() {
            handleRowFieldChange($(this));
        });

        $(document).on('change', '#tabla-pagos-tarjeta tbody .table-select', function() {
            handleRowFieldChange($(this));
        });

        function handleRowFieldChange($elem) {
            const $row = $elem.closest('tr');
            const codMovimiento = $row.data('cod-movimiento');
            if (!codMovimiento) return;

            if (!rowOverrides[codMovimiento]) {
                rowOverrides[codMovimiento] = { overrides: {} };
            }

            const val = parseFloat($elem.val());
            const valInt = parseInt($elem.val(), 10);

            if ($elem.hasClass('row-comision-pct')) {
                rowOverrides[codMovimiento].comision_pct = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.comision_pct = true;
            } else if ($elem.hasClass('row-meses')) {
                rowOverrides[codMovimiento].meses = isNaN(valInt) ? 0 : valInt;
                rowOverrides[codMovimiento].overrides.meses = true;
            } else if ($elem.hasClass('row-comision-meses')) {
                rowOverrides[codMovimiento].comision_meses = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.comision_meses = true;
            } else if ($elem.hasClass('row-comision-clip')) {
                rowOverrides[codMovimiento].comision = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.comision = true;
            } else if ($elem.hasClass('row-iva-comision')) {
                rowOverrides[codMovimiento].iva = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.iva = true;
            }

            localStorage.setItem('card_payments_row_overrides', JSON.stringify(rowOverrides));

            // Recalculate row values in place
            const rawItem = rawMovements.find(item => item.cod_movimiento == codMovimiento);
            if (rawItem) {
                const m = calculateMovement(rawItem);
                $row.find('.row-comision-meses').val(m.comision_meses.toFixed(2));
                $row.find('.row-comision-clip').val(m.comision.toFixed(2));
                $row.find('.row-iva-comision').val(m.iva.toFixed(2));
                $row.find('.cell-total').text(formatter.format(m.total));
                $elem.addClass('input-overridden');

                $row.find('.actions-column').html(`
                    <button type="button" class="btn btn-xs btn-outline-warning btn-reset-row" title="Restablecer valores de la fórmula">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                `);
            }

            updateKpisAndFooter();
        }

        // Event listener for row formula reset
        $(document).on('click', '#tabla-pagos-tarjeta tbody .btn-reset-row', function() {
            const $row = $(this).closest('tr');
            const codMovimiento = $row.data('cod-movimiento');
            if (!codMovimiento) return;

            delete rowOverrides[codMovimiento];
            localStorage.setItem('card_payments_row_overrides', JSON.stringify(rowOverrides));

            recomputeAndRender();
        });

        // Event listener for Export to Excel
        document.getElementById('btn-export-excel').addEventListener('click', exportToExcel);

        function initSucursalRates() {
            const savedRates = localStorage.getItem('card_payments_sucursal_rates');
            if (savedRates) {
                sucursalRates = JSON.parse(savedRates);
                Object.keys(sucursalRates).forEach(sucId => {
                    const row = document.querySelector(`tr[data-sucursal-id="${sucId}"]`);
                    if (row) {
                        const rates = sucursalRates[sucId];
                        row.querySelector('.rate-base').value = rates.base;
                        row.querySelector('.rate-msi3').value = rates.msi3;
                        row.querySelector('.rate-msi6').value = rates.msi6;
                        row.querySelector('.rate-msi9').value = rates.msi9;
                        row.querySelector('.rate-msi12').value = rates.msi12;
                    }
                });
            } else {
                sucursalRates = {};
                document.querySelectorAll('#sucursal-rates-body tr').forEach(row => {
                    const sucId = row.dataset.sucursalId;
                    sucursalRates[sucId] = {
                        base: parseFloat(row.querySelector('.rate-base').value) || 2.99,
                        msi3: parseFloat(row.querySelector('.rate-msi3').value) || 4.50,
                        msi6: parseFloat(row.querySelector('.rate-msi6').value) || 7.50,
                        msi9: parseFloat(row.querySelector('.rate-msi9').value) || 9.90,
                        msi12: parseFloat(row.querySelector('.rate-msi12').value) || 11.95
                    };
                });
            }
        }

        function loadData() {
            overlay.style.display = 'flex';
            dashboard.style.opacity = '0.5';

            const formData = new FormData(form);
            const urlParams = new URLSearchParams(formData).toString();

            const fechaInicioVal = document.getElementById('fecha_inicio').value;
            const fechaFinVal = document.getElementById('fecha_fin').value;
            
            document.getElementById('print-period').innerText = `${fechaInicioVal} al ${fechaFinVal}`;

            fetch(`{{ route('reporte-pagos-tarjeta.data') }}?${urlParams}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error al obtener los datos');
                    return response.json();
                })
                .then(data => {
                    rawMovements = data.detalleMovimientos || [];
                    
                    const savedOverrides = localStorage.getItem('card_payments_row_overrides');
                    rowOverrides = savedOverrides ? JSON.parse(savedOverrides) : {};
                    
                    // Extraer valores únicos para poblar los 3 filtros rápidos de la tabla
                    const bancosSet = new Set();
                    const conceptosSet = new Set();
                    const transaccionesSet = new Set();

                    rawMovements.forEach(m => {
                        if (m.cuenta_destino && m.cuenta_destino !== '-') bancosSet.add(m.cuenta_destino);
                        if (m.concepto) conceptosSet.add(m.concepto.toUpperCase());
                        if (m.transaccion && m.transaccion !== 'NO DEFINIDO') transaccionesSet.add(m.transaccion.toUpperCase());
                    });

                    // Poblar selector de Bancos
                    const bancoSelect = document.getElementById('table_banco');
                    const currentBanco = bancoSelect.value;
                    bancoSelect.innerHTML = '<option value="">-- Todos los Bancos / Cuentas --</option>';
                    Array.from(bancosSet).sort().forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b;
                        opt.textContent = b;
                        if (b === currentBanco) opt.selected = true;
                        bancoSelect.appendChild(opt);
                    });

                    // Poblar selector de Conceptos
                    const conceptoSelect = document.getElementById('table_concepto');
                    const currentConcepto = conceptoSelect.value;
                    conceptoSelect.innerHTML = '<option value="">-- Todos los Conceptos --</option>';
                    Array.from(conceptosSet).sort().forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c;
                        opt.textContent = c;
                        if (c === currentConcepto) opt.selected = true;
                        conceptoSelect.appendChild(opt);
                    });

                    // Poblar selector de Transacciones
                    const transSelect = document.getElementById('table_transaccion');
                    const currentTrans = transSelect.value;
                    transSelect.innerHTML = '<option value="">-- Todas --</option>';
                    const defaultTrans = ['CLIP', 'TERMINAL', 'TRANSFERENCIA', 'DEPOSITO'];
                    const allTrans = Array.from(new Set([...defaultTrans, ...Array.from(transaccionesSet)])).sort();
                    allTrans.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t;
                        opt.textContent = t;
                        if (t === currentTrans) opt.selected = true;
                        transSelect.appendChild(opt);
                    });

                    recomputeAndRender();
                })
                .catch(error => {
                    console.error("Error:", error);
                    const tbody = document.getElementById('movimientos-body');
                    tbody.innerHTML = '<tr><td colspan="17" class="text-center text-danger py-3">Ocurrió un error al cargar el reporte.</td></tr>';
                })
                .finally(() => {
                    overlay.style.display = 'none';
                    dashboard.style.display = 'block';
                    dashboard.style.opacity = '1';
                });
        }

        function calculateMovement(m) {
            const isCanceled = m.status === 'CANCELADO';
            const aplicaComision = (m.transaccion === 'CLIP' || m.transaccion === 'TERMINAL' || m.tipo_pago === 'TARJETA');
            
            const rates = sucursalRates[m.sucursal_id] || { base: 2.99, msi3: 4.5, msi6: 7.5, msi9: 9.9, msi12: 11.95 };
            const rowOverride = rowOverrides[m.cod_movimiento] || { overrides: {} };
            
            // 1. Commission Base %
            let comision_pct = 0;
            let isPctOverridden = false;
            if (rowOverride.overrides && rowOverride.overrides.comision_pct) {
                comision_pct = parseFloat(rowOverride.comision_pct) || 0;
                isPctOverridden = true;
            } else if (aplicaComision) {
                comision_pct = parseFloat(rates.base) || 0;
            }
            
            // 2. Meses (MSI)
            let meses = 0;
            let isMesesOverridden = false;
            if (rowOverride.overrides && rowOverride.overrides.meses) {
                meses = parseInt(rowOverride.meses) || 0;
                isMesesOverridden = true;
            }
            
            let surcharge_pct = 0;
            if (meses === 3) surcharge_pct = parseFloat(rates.msi3) || 0;
            else if (meses === 6) surcharge_pct = parseFloat(rates.msi6) || 0;
            else if (meses === 9) surcharge_pct = parseFloat(rates.msi9) || 0;
            else if (meses === 12) surcharge_pct = parseFloat(rates.msi12) || 0;
            
            // 3. Comisión Meses ($)
            let comision_meses = 0;
            let isComisionMesesOverridden = false;
            if (rowOverride.overrides && rowOverride.overrides.comision_meses) {
                comision_meses = parseFloat(rowOverride.comision_meses) || 0;
                isComisionMesesOverridden = true;
            } else if (aplicaComision && m.monto > 0) {
                comision_meses = Math.round(m.monto * (surcharge_pct / 100) * 100) / 100;
            }
            
            // 4. Comisión Clip ($)
            let comision_clip = 0;
            let isComisionClipOverridden = false;
            if (rowOverride.overrides && rowOverride.overrides.comision) {
                comision_clip = parseFloat(rowOverride.comision) || 0;
                isComisionClipOverridden = true;
            } else if (aplicaComision && m.monto > 0) {
                comision_clip = Math.round(((m.monto * (comision_pct / 100)) + comision_meses + 1) * 100) / 100;
            }
            
            // 5. IVA Comisión ($)
            let iva = 0;
            let isIvaOverridden = false;
            if (rowOverride.overrides && rowOverride.overrides.iva) {
                iva = parseFloat(rowOverride.iva) || 0;
                isIvaOverridden = true;
            } else if (aplicaComision && m.monto > 0) {
                iva = Math.round(comision_clip * 0.16 * 100) / 100;
            }
            
            // 6. Total ($)
            let total = m.monto;
            if (aplicaComision) {
                total = Math.round((m.monto - comision_clip - iva) * 100) / 100;
            }
            
            return {
                ...m,
                comision_pct,
                meses,
                comision_meses,
                comision: comision_clip,
                iva,
                total,
                aplicaComision,
                overrides: {
                    comision_pct: isPctOverridden,
                    meses: isMesesOverridden,
                    comision_meses: isComisionMesesOverridden,
                    comision: isComisionClipOverridden,
                    iva: isIvaOverridden
                }
            };
        }

        function recomputeAndRender() {
            // Destroy DataTable if already initialized
            if ($.fn.DataTable.isDataTable('#tabla-pagos-tarjeta')) {
                $('#tabla-pagos-tarjeta').DataTable().destroy();
            }

            const tbody = document.getElementById('movimientos-body');
            tbody.innerHTML = '';

            // Obtener valores seleccionados en los 3 filtros rápidos arriba de la tabla
            const selTrans = $('#table_transaccion').val();
            const selBanco = $('#table_banco').val();
            const selConc = $('#table_concepto').val();

            let filteredMovements = rawMovements;
            if (selTrans) {
                filteredMovements = filteredMovements.filter(m => (m.transaccion || '').toUpperCase() === selTrans.toUpperCase());
            }
            if (selBanco) {
                filteredMovements = filteredMovements.filter(m => (m.cuenta_destino || '').toUpperCase() === selBanco.toUpperCase());
            }
            if (selConc) {
                filteredMovements = filteredMovements.filter(m => (m.concepto || '').toUpperCase() === selConc.toUpperCase());
            }

            if (filteredMovements && filteredMovements.length > 0) {
                let rowsHtml = '';

                filteredMovements.forEach(item => {
                    const m = calculateMovement(item);
                    const isCanceled = m.status === 'CANCELADO';

                    const rowClass = isCanceled ? 'table-warning text-muted text-decoration-line-through' : '';
                    const statusBadge = isCanceled 
                        ? `<span class="badge bg-danger rounded-pill">CANCELADA</span>` 
                        : `<span class="badge bg-success rounded-pill">ACTIVA</span>`;

                    const isOverridden = Object.values(m.overrides).some(o => o === true);

                    // Exactly matching original layout & styling from user's screenshot
                    let html = `
                        <tr class="${rowClass}" data-cod-movimiento="${m.cod_movimiento}">
                            <td class="ps-4 fw-semibold">${m.sucursal}</td>
                            <td>${m.fecha}</td>
                            <td>${m.contrato}</td>
                            <td>${m.concepto}</td>
                            <td class="text-center">${m.voucher ? '<span class="badge bg-danger fw-bold">' + m.voucher + '</span>' : statusBadge}</td>
                            <td>${m.referencia || '-'}</td>
                            <td class="fw-semibold text-secondary">${m.tipo_pago}</td>
                            <td class="fw-semibold text-primary">${m.transaccion}</td>
                            <td>${m.cuenta_destino || '-'}</td>
                            <td class="text-end fw-semibold">${formatter.format(m.monto)}</td>
                    `;

                    // Editable/Calculated cells
                    if (m.aplicaComision && !isCanceled) {
                        html += `
                            <td>
                                <select class="table-select w-months row-meses ${m.overrides.meses ? 'input-overridden' : ''}">
                                    <option value="0" ${m.meses === 0 ? 'selected' : ''}>0</option>
                                    <option value="3" ${m.meses === 3 ? 'selected' : ''}>3</option>
                                    <option value="6" ${m.meses === 6 ? 'selected' : ''}>6</option>
                                    <option value="9" ${m.meses === 9 ? 'selected' : ''}>9</option>
                                    <option value="12" ${m.meses === 12 ? 'selected' : ''}>12</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" class="table-input w-compct row-comision-pct ${m.overrides.comision_pct ? 'input-overridden' : ''}" value="${m.comision_pct.toFixed(2)}">
                            </td>
                            <td>
                                <input type="number" step="0.01" class="table-input w-amount row-comision-meses ${m.overrides.comision_meses ? 'input-overridden' : ''}" value="${m.comision_meses.toFixed(2)}">
                            </td>
                            <td>
                                <input type="number" step="0.01" class="table-input w-amount row-comision-clip ${m.overrides.comision ? 'input-overridden' : ''}" value="${m.comision.toFixed(2)}">
                            </td>
                            <td>
                                <input type="number" step="0.01" class="table-input w-amount row-iva-comision ${m.overrides.iva ? 'input-overridden' : ''}" value="${m.iva.toFixed(2)}">
                            </td>
                        `;
                    } else {
                        html += `
                            <td class="text-center text-muted">-</td>
                            <td class="text-center text-muted">-</td>
                            <td class="text-end text-muted">${formatter.format(m.comision_meses)}</td>
                            <td class="text-end text-muted">${formatter.format(m.comision)}</td>
                            <td class="text-end text-muted">${formatter.format(m.iva)}</td>
                        `;
                    }

                    // Total and Actions column
                    html += `
                            <td class="pe-4 text-end fw-bold text-success cell-total">${formatter.format(m.total)}</td>
                            <td class="text-center actions-column">
                                ${(isOverridden && !isCanceled) ? `
                                    <button type="button" class="btn btn-xs btn-outline-warning btn-reset-row" title="Restablecer valores de la fórmula">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                ` : `
                                    <span class="text-muted small"><i class="bi bi-calculator-fill text-black-50"></i></span>
                                `}
                            </td>
                        </tr>
                    `;

                    rowsHtml += html;
                });

                tbody.innerHTML = rowsHtml;

                // Initialize DataTable
                dataTableInstance = $('#tabla-pagos-tarjeta').DataTable({
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    order: [[1, 'desc']], // ordenar por Fecha descendente
                    autoWidth: false,
                    language: {
                        processing: "Procesando...",
                        search: "Buscar en tabla:",
                        lengthMenu: "Mostrar _MENU_ registros",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        infoEmpty: "Mostrando 0 a 0 de 0 registros",
                        infoFiltered: "(filtrado de _MAX_ registros totales)",
                        zeroRecords: "No se encontraron resultados",
                        emptyTable: "No hay datos disponibles en la tabla",
                        paginate: {
                            first: "Primero",
                            previous: "Anterior",
                            next: "Siguiente",
                            last: "Último"
                        }
                    }
                });

                updateKpisAndFooter(filteredMovements);

            } else {
                tbody.innerHTML = '<tr><td colspan="17" class="text-center text-muted py-4">No se encontraron movimientos con los filtros seleccionados.</td></tr>';
                document.getElementById('table-footer').innerHTML = '';
                document.getElementById('kpi-total-tarjeta').innerText = '$ 0.00';
                document.getElementById('kpi-transacciones').innerText = '0';
                document.getElementById('kpi-ticket-promedio').innerText = '$ 0.00';
            }
        }

        function updateKpisAndFooter(activeSubset) {
            let totalMonto = 0;
            let totalComisionMeses = 0;
            let totalComision = 0;
            let totalIva = 0;
            let totalGeneral = 0;
            let activeCount = 0;

            const list = activeSubset || rawMovements;

            if (list && list.length > 0) {
                list.forEach(item => {
                    const m = calculateMovement(item);
                    if (m.status !== 'CANCELADO') {
                        totalMonto += m.monto;
                        totalComisionMeses += m.comision_meses;
                        totalComision += m.comision;
                        totalIva += m.iva;
                        totalGeneral += m.total;
                        activeCount++;
                    }
                });
            }

            document.getElementById('kpi-total-tarjeta').innerText = formatter.format(totalGeneral);
            document.getElementById('kpi-transacciones').innerText = activeCount;
            const ticketPromedio = activeCount > 0 ? (totalGeneral / activeCount) : 0;
            document.getElementById('kpi-ticket-promedio').innerText = formatter.format(ticketPromedio);

            const tfoot = document.getElementById('table-footer');
            tfoot.innerHTML = `
                <tr>
                    <td colspan="9" class="ps-4 py-3 text-end">TOTAL REPORTADO (ACTIVOS):</td>
                    <td class="text-end py-3">${formatter.format(totalMonto)}</td>
                    <td class="text-center py-3 text-muted">-</td>
                    <td class="text-center py-3 text-muted">-</td>
                    <td class="text-end py-3 text-muted">${formatter.format(totalComisionMeses)}</td>
                    <td class="text-end py-3 text-muted">${formatter.format(totalComision)}</td>
                    <td class="text-end py-3 text-muted">${formatter.format(totalIva)}</td>
                    <td class="text-end py-3 text-success font-monospace" style="font-size: 1.15rem;">
                        ${formatter.format(totalGeneral)}
                    </td>
                    <td class="actions-column"></td>
                </tr>
            `;
        }

        function exportToExcel() {
            if (!rawMovements || rawMovements.length === 0) {
                alert("No hay información en el reporte para exportar.");
                return;
            }

            const dataToExport = [];
            const activeMovements = rawMovements.map(item => calculateMovement(item));

            const headers = [
                "Sucursal", "Fecha", "Contrato", "Concepto", "Estatus", "Referencia", 
                "Tipo de Pago", "Transacción", "Cuenta Destino", "Monto", "Meses (MSI)", "Comisión %", 
                "Comisión Meses", "Comisión Clip", "IVA Comisión", "Total"
            ];

            activeMovements.forEach(m => {
                dataToExport.push({
                    "Sucursal": m.sucursal,
                    "Fecha": m.fecha,
                    "Contrato": m.contrato,
                    "Concepto": m.concepto,
                    "Estatus": m.status,
                    "Referencia": m.referencia || "-",
                    "Tipo de Pago": m.tipo_pago,
                    "Transacción": m.transaccion,
                    "Cuenta Destino": m.cuenta_destino || "-",
                    "Monto": Number(m.monto),
                    "Meses (MSI)": m.aplicaComision && m.status !== 'CANCELADO' ? Number(m.meses) : 0,
                    "Comisión %": m.aplicaComision && m.status !== 'CANCELADO' ? Number(m.comision_pct) : 0,
                    "Comisión Meses": Number(m.comision_meses),
                    "Comisión Clip": Number(m.comision),
                    "IVA Comisión": Number(m.iva),
                    "Total": Number(m.total)
                });
            });

            const worksheet = XLSX.utils.json_to_sheet(dataToExport);

            const range = XLSX.utils.decode_range(worksheet['!ref']);
            for (let r = 1; r <= range.e.r; r++) {
                const rowNum = r + 1;
                const m = activeMovements[r - 1];
                if (!m) continue;

                if (m.aplicaComision && m.status !== 'CANCELADO') {
                    // Comisión Meses ($) (Column 12 -> M)
                    const cellM = worksheet[XLSX.utils.encode_cell({ r: r, c: 12 })];
                    if (cellM) {
                        if (m.overrides.comision_meses) {
                            cellM.v = m.comision_meses;
                            cellM.t = 'n';
                        } else {
                            const rates = sucursalRates[m.sucursal_id] || { base: 2.99, msi3: 4.5, msi6: 7.5, msi9: 9.9, msi12: 11.95 };
                            let surcharge_pct = 0;
                            if (m.meses === 3) surcharge_pct = rates.msi3;
                            else if (m.meses === 6) surcharge_pct = rates.msi6;
                            else if (m.meses === 9) surcharge_pct = rates.msi9;
                            else if (m.meses === 12) surcharge_pct = rates.msi12;

                            cellM.f = `J${rowNum} * (${surcharge_pct} / 100)`;
                            cellM.v = m.comision_meses;
                            cellM.t = 'n';
                        }
                    }

                    // Comisión Clip ($) (Column 13 -> N)
                    const cellN = worksheet[XLSX.utils.encode_cell({ r: r, c: 13 })];
                    if (cellN) {
                        if (m.overrides.comision) {
                            cellN.v = m.comision;
                            cellN.t = 'n';
                        } else {
                            cellN.f = `(J${rowNum} * (L${rowNum} / 100)) + M${rowNum} + 1`;
                            cellN.v = m.comision;
                            cellN.t = 'n';
                        }
                    }

                    // IVA Comisión ($) (Column 14 -> O)
                    const cellO = worksheet[XLSX.utils.encode_cell({ r: r, c: 14 })];
                    if (cellO) {
                        if (m.overrides.iva) {
                            cellO.v = m.iva;
                            cellO.t = 'n';
                        } else {
                            cellO.f = `N${rowNum} * 0.16`;
                            cellO.v = m.iva;
                            cellO.t = 'n';
                        }
                    }

                    // Total ($) (Column 15 -> P)
                    const cellP = worksheet[XLSX.utils.encode_cell({ r: r, c: 15 })];
                    if (cellP) {
                        cellP.f = `J${rowNum} - N${rowNum} - O${rowNum}`;
                        cellP.v = m.total;
                        cellP.t = 'n';
                    }
                }
            }

            const lastRowIndex = range.e.r + 1;
            const lastDataRow = lastRowIndex;

            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 8 })] = { v: "TOTAL REPORTADO (ACTIVOS):", t: 's' };

            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 9 })] = { f: `SUM(J2:J${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 12 })] = { f: `SUM(M2:M${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 13 })] = { f: `SUM(N2:N${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 14 })] = { f: `SUM(O2:O${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 15 })] = { f: `SUM(P2:P${lastDataRow})`, t: 'n' };

            range.e.r = lastRowIndex;
            worksheet['!ref'] = XLSX.utils.encode_range(range);

            const maxCols = headers.map(h => h.length);
            dataToExport.forEach(row => {
                Object.keys(row).forEach((key, colIdx) => {
                    const val = row[key];
                    const valLen = val ? val.toString().length : 0;
                    if (valLen > maxCols[colIdx]) {
                        maxCols[colIdx] = valLen;
                    }
                });
            });
            worksheet['!cols'] = maxCols.map(len => ({ wch: len + 3 }));

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Pagos con Tarjeta");
            
            const fechaInicioVal = document.getElementById('fecha_inicio').value;
            const fechaFinVal = document.getElementById('fecha_fin').value;
            const sucursalName = sucursalSelect.options[sucursalSelect.selectedIndex].text.replace(/[^a-zA-Z0-9]/g, "_");
            const transaccionSelect = document.getElementById('transaccion');
            const transaccionName = transaccionSelect && transaccionSelect.value ? `_${transaccionSelect.value}` : '';
            
            const filename = `Reporte_Pagos_Tarjeta_${sucursalName}${transaccionName}_${fechaInicioVal}_al_${fechaFinVal}.xlsx`;
            XLSX.writeFile(workbook, filename);
        }
    });
</script>
@endsection
