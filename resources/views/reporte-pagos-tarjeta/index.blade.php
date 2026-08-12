@extends('employees.layouts.main')

@section('title', 'Reporte de Pagos con Tarjeta')

@section('styles')
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
            background: rgba(255, 255, 255, 0.8);
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
            border: 1px solid transparent;
            background-color: transparent;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.875rem;
            width: 100%;
            text-align: center;
            transition: all 0.2s;
        }
        .table-select:hover {
            border-color: #dee2e6;
            background-color: #f8f9fa;
        }
        .table-select:focus {
            border-color: #0d6efd;
            background-color: #fff;
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
        .w-compct { width: 70px; margin: 0 auto; }
        .w-months { width: 70px; margin: 0 auto; }
        .w-amount { width: 100px; display: inline-block; }

        .btn-xs {
            padding: 0.15rem 0.3rem;
            font-size: 0.75rem;
            border-radius: 0.2rem;
            line-height: 1;
        }

        .config-card {
            border: 1px solid rgba(13, 110, 253, 0.2);
            border-left: 4px solid #0d6efd;
            background-color: #f8faff;
        }

        .table-hover tbody tr:hover td {
            background-color: rgba(13, 110, 253, 0.02) !important;
        }

        /* Print styling */
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
            /* Style inputs and selects to look like text when printed */
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
            .actions-column, .btn-reset-row, th.actions-column, td.actions-column {
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

    <!-- Filtros -->
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

    <!-- Panel de Configuración de Tasas por Sucursal -->
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
                        <i class="bi bi-check-circle me-2"></i>Aplicar Tasas
                    </button>
                </div>
            </div>
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
                                    <th class="py-3 text-uppercase text-muted small fw-bold">Cuenta Destino</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Monto</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Meses</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-center">Comisión %</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Comisión Meses</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Comisión Clip</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">IVA Comisión</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold text-end">Total</th>
                                    <th class="pe-4 py-3 text-uppercase text-muted small fw-bold text-center actions-column">Fórmula</th>
                                </tr>
                            </thead>
                            <tbody id="movimientos-body">
                                <tr><td colspan="16" class="text-center text-muted py-3">Cargando datos...</td></tr>
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
<!-- Import SheetJS CDN for high fidelity Excel Export -->
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

        // 1. Initialize Sucursal Rates
        initSucursalRates();

        // 2. Fetch initial data
        loadData();

        // Event listener for filter submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadData();
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
            
            // Render spreadsheet updates and show feedback
            recomputeAndRender();
            
            // Inline notification inside panel or simple alert
            const btn = document.getElementById('btn-save-config');
            const originalText = btn.innerHTML;
            btn.className = "btn btn-success fw-bold";
            btn.innerHTML = `<i class="bi bi-check-all me-2"></i>¡Aplicado!`;
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        });

        // Event listener for config panel - RESET CONFIGS
        document.getElementById('btn-reset-config').addEventListener('click', function() {
            if (confirm('¿Estás seguro de restablecer todas las tasas a sus valores por defecto (2.99% base)?')) {
                localStorage.removeItem('card_payments_sucursal_rates');
                // Reset inputs to default values
                document.querySelectorAll('#sucursal-rates-body tr').forEach(row => {
                    row.querySelector('.rate-base').value = '2.99';
                    row.querySelector('.rate-msi3').value = '4.50';
                    row.querySelector('.rate-msi6').value = '7.50';
                    row.querySelector('.rate-msi9').value = '9.90';
                    row.querySelector('.rate-msi12').value = '11.95';
                });
                // Re-init memory state
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
                // Auto-trigger save
                document.getElementById('btn-save-config').click();
            }
        });

        // Event listener for inline editing in the table body (Input change delegation)
        const tbody = document.getElementById('movimientos-body');
        
        tbody.addEventListener('input', function(e) {
            const target = e.target;
            const row = target.closest('tr');
            if (!row) return;
            
            const codMovimiento = row.dataset.codMovimiento;
            if (!codMovimiento) return;
            
            // Find movement
            const m = rawMovements.find(item => item.cod_movimiento == codMovimiento);
            if (!m) return;
            
            // Init override structure if missing
            if (!rowOverrides[codMovimiento]) {
                rowOverrides[codMovimiento] = { overrides: {} };
            }
            
            const val = parseFloat(target.value);
            const valInt = parseInt(target.value);
            
            if (target.classList.contains('row-comision-pct')) {
                rowOverrides[codMovimiento].comision_pct = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.comision_pct = true;
            } else if (target.classList.contains('row-meses')) {
                rowOverrides[codMovimiento].meses = isNaN(valInt) ? 0 : valInt;
                rowOverrides[codMovimiento].overrides.meses = true;
            } else if (target.classList.contains('row-comision-meses')) {
                rowOverrides[codMovimiento].comision_meses = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.comision_meses = true;
            } else if (target.classList.contains('row-comision-clip')) {
                rowOverrides[codMovimiento].comision = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.comision = true;
            } else if (target.classList.contains('row-iva-comision')) {
                rowOverrides[codMovimiento].iva = isNaN(val) ? 0 : val;
                rowOverrides[codMovimiento].overrides.iva = true;
            }
            
            localStorage.setItem('card_payments_row_overrides', JSON.stringify(rowOverrides));
            recomputeAndRender();
        });

        // Handle dropdown selection (selects fire 'change' not 'input')
        tbody.addEventListener('change', function(e) {
            const target = e.target;
            if (target.classList.contains('row-meses')) {
                const row = target.closest('tr');
                if (!row) return;
                
                const codMovimiento = row.dataset.codMovimiento;
                if (!codMovimiento) return;
                
                if (!rowOverrides[codMovimiento]) {
                    rowOverrides[codMovimiento] = { overrides: {} };
                }
                
                rowOverrides[codMovimiento].meses = parseInt(target.value) || 0;
                rowOverrides[codMovimiento].overrides.meses = true;
                
                localStorage.setItem('card_payments_row_overrides', JSON.stringify(rowOverrides));
                recomputeAndRender();
            }
        });

        // Event listener for row formula reset
        tbody.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-reset-row');
            if (!btn) return;
            
            const row = btn.closest('tr');
            if (!row) return;
            
            const codMovimiento = row.dataset.codMovimiento;
            if (!codMovimiento) return;
            
            // Delete override for this movement
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
                // Pre-fill input fields in configuration panel with loaded rates
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
                // Read base inputs values
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
                    
                    // Retrieve stored row overrides
                    const savedOverrides = localStorage.getItem('card_payments_row_overrides');
                    rowOverrides = savedOverrides ? JSON.parse(savedOverrides) : {};
                    
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
            
            // Get sucursal rates configuration
            const rates = sucursalRates[m.sucursal_id] || { base: 2.99, msi3: 4.5, msi6: 7.5, msi9: 9.9, msi12: 11.95 };
            
            // Check if overrides exist
            const rowOverride = rowOverrides[m.cod_movimiento] || { overrides: {} };
            
            // 1. Commission Base %
            let comision_pct = 0;
            let isPctOverridden = false;
            if (rowOverride.overrides.comision_pct) {
                comision_pct = parseFloat(rowOverride.comision_pct) || 0;
                isPctOverridden = true;
            } else if (aplicaComision) {
                comision_pct = parseFloat(rates.base) || 0;
            }
            
            // 2. Meses (MSI)
            let meses = 0;
            let isMesesOverridden = false;
            if (rowOverride.overrides.meses) {
                meses = parseInt(rowOverride.meses) || 0;
                isMesesOverridden = true;
            } else {
                meses = 0; // default to 0
            }
            
            // Surcharge percent lookup
            let surcharge_pct = 0;
            if (meses === 3) surcharge_pct = parseFloat(rates.msi3) || 0;
            else if (meses === 6) surcharge_pct = parseFloat(rates.msi6) || 0;
            else if (meses === 9) surcharge_pct = parseFloat(rates.msi9) || 0;
            else if (meses === 12) surcharge_pct = parseFloat(rates.msi12) || 0;
            
            // 3. Comisión Meses ($)
            let comision_meses = 0;
            let isComisionMesesOverridden = false;
            if (rowOverride.overrides.comision_meses) {
                comision_meses = parseFloat(rowOverride.comision_meses) || 0;
                isComisionMesesOverridden = true;
            } else if (aplicaComision && m.monto > 0) {
                comision_meses = Math.round(m.monto * (surcharge_pct / 100) * 100) / 100;
            }
            
            // 4. Comisión Clip ($)
            let comision_clip = 0;
            let isComisionClipOverridden = false;
            if (rowOverride.overrides.comision) {
                comision_clip = parseFloat(rowOverride.comision) || 0;
                isComisionClipOverridden = true;
            } else if (aplicaComision && m.monto > 0) {
                comision_clip = Math.round(((m.monto * (comision_pct / 100)) + comision_meses + 1) * 100) / 100;
            }
            
            // 5. IVA Comisión ($)
            let iva = 0;
            let isIvaOverridden = false;
            if (rowOverride.overrides.iva) {
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
                comision: comision_clip, // map to 'comision' structure
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
            let totalMonto = 0;
            let totalComisionMeses = 0;
            let totalComision = 0;
            let totalIva = 0;
            let totalGeneral = 0;
            let activeCount = 0;

            tbody.innerHTML = '';

            if (rawMovements && rawMovements.length > 0) {
                rawMovements.forEach(item => {
                    const m = calculateMovement(item);
                    const isCanceled = m.status === 'CANCELADO';
                    
                    if (!isCanceled) {
                        totalMonto += m.monto;
                        totalComisionMeses += m.comision_meses;
                        totalComision += m.comision;
                        totalIva += m.iva;
                        totalGeneral += m.total;
                        activeCount++;
                    }

                    const rowClass = isCanceled ? 'table-warning text-muted text-decoration-line-through' : '';
                    const statusBadge = isCanceled 
                        ? `<span class="badge bg-danger rounded-pill">CANCELADA</span>` 
                        : `<span class="badge bg-success rounded-pill">ACTIVA</span>`;

                    const isOverridden = Object.values(m.overrides).some(o => o === true);
                    
                    const tr = document.createElement('tr');
                    tr.className = rowClass;
                    tr.dataset.codMovimiento = m.cod_movimiento;

                    // Base read-only cells
                    let html = `
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
                        <td class="pe-4 text-end fw-bold text-success">${formatter.format(m.total)}</td>
                        <td class="text-center actions-column">
                            ${(isOverridden && !isCanceled) ? `
                                <button type="button" class="btn btn-xs btn-outline-warning btn-reset-row" title="Restablecer valores de la fórmula">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            ` : `
                                <span class="text-muted small"><i class="bi bi-calculator-fill text-black-50"></i></span>
                            `}
                        </td>
                    `;

                    tr.innerHTML = html;
                    tbody.appendChild(tr);
                });

                // Update KPIs
                document.getElementById('kpi-total-tarjeta').innerText = formatter.format(totalGeneral);
                document.getElementById('kpi-transacciones').innerText = activeCount;
                const ticketPromedio = activeCount > 0 ? (totalGeneral / activeCount) : 0;
                document.getElementById('kpi-ticket-promedio').innerText = formatter.format(ticketPromedio);

                // Update Table Footer
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
            } else {
                tbody.innerHTML = '<tr><td colspan="17" class="text-center text-muted py-4">No se encontraron movimientos con tarjeta en el período seleccionado.</td></tr>';
                document.getElementById('table-footer').innerHTML = '';
                document.getElementById('kpi-total-tarjeta').innerText = '$ 0.00';
                document.getElementById('kpi-transacciones').innerText = '0';
                document.getElementById('kpi-ticket-promedio').innerText = '$ 0.00';
            }
        }

        function exportToExcel() {
            if (!rawMovements || rawMovements.length === 0) {
                alert("No hay información en el reporte para exportar.");
                return;
            }

            const dataToExport = [];
            const activeMovements = rawMovements.map(item => calculateMovement(item));

            // Define column headers
            const headers = [
                "Sucursal", "Fecha", "Contrato", "Concepto", "Estatus", "Referencia", 
                "Tipo de Pago", "Transacción", "Cuenta Destino", "Monto", "Meses (MSI)", "Comisión %", 
                "Comisión Meses", "Comisión Clip", "IVA Comisión", "Total"
            ];

            // 1. Map data rows for SheetJS
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

            // 2. Create Sheet from JSON array
            const worksheet = XLSX.utils.json_to_sheet(dataToExport);

            // 3. Insert real Excel Formulas for calculations in each row
            const range = XLSX.utils.decode_range(worksheet['!ref']);
            for (let r = 1; r <= range.e.r; r++) { // skip header row (index 0)
                const rowNum = r + 1; // Excel row numbering is 1-indexed, so row index 1 is Excel row 2
                const m = activeMovements[r - 1];
                if (!m) continue;

                if (m.aplicaComision && m.status !== 'CANCELADO') {
                    // Check if there are overrides to write static values, otherwise write Excel Formulas

                    // Comisión Meses ($) (Column 12 -> M)
                    const cellM = worksheet[XLSX.utils.encode_cell({ r: r, c: 12 })];
                    if (cellM) {
                        if (m.overrides.comision_meses) {
                            cellM.v = m.comision_meses;
                            cellM.t = 'n';
                        } else {
                            // Find months surcharge rate % from config
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

            // 4. Append Sum Totals Row in Excel at the bottom using SUM formulas
            const lastRowIndex = range.e.r + 1;
            const lastDataRow = lastRowIndex; // row number in Excel for last data row

            // Label 'TOTAL REPORTADO (ACTIVOS):' in cell I (Column 8)
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 8 })] = { v: "TOTAL REPORTADO (ACTIVOS):", t: 's' };

            // SUM Formulas for columns: Monto (J), Comisión Meses (M), Comisión Clip (N), IVA Comisión (O), Total (P)
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 9 })] = { f: `SUM(J2:J${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 12 })] = { f: `SUM(M2:M${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 13 })] = { f: `SUM(N2:N${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 14 })] = { f: `SUM(O2:O${lastDataRow})`, t: 'n' };
            worksheet[XLSX.utils.encode_cell({ r: lastRowIndex, c: 15 })] = { f: `SUM(P2:P${lastDataRow})`, t: 'n' };

            // Update range reference
            range.e.r = lastRowIndex;
            worksheet['!ref'] = XLSX.utils.encode_range(range);

            // 5. Adjust column widths dynamically for readability
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

            // 6. Build and save workbook
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
