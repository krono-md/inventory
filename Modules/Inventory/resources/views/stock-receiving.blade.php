@extends('inventory::layouts.dashboard')

@section('title', 'Receiving')

@push('styles')
<style>
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-intransit { background: #dbeafe; color: #1e40af; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    .tab-btn { transition: all 0.15s ease; }

    .shipment-row { cursor: pointer; transition: background 0.15s; }
    .shipment-row:hover { background: #f1f5f9; }

    .expand-arrow { transition: transform 0.2s ease; display: inline-flex; align-items: center; color: #94a3b8; }
    .expand-arrow.open { transform: rotate(90deg); color: #0b1e3d; }

    .items-detail { display: none; }
    .items-detail.open { display: table-row; }
    .items-detail td { padding: 0 !important; background: #f8fafc; }
    .items-detail-inner { border-top: 1px solid #e2e8f0; padding: 14px 16px 14px 52px; }
    .items-mini-table { width:100%; border-collapse:collapse; font-size:13px; }
    .items-mini-table th { text-align:left; padding:6px 10px; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; }
    .items-mini-table td { padding:6px 10px; color:#334155; border-bottom:1px solid #eef2f7; }
    .items-mini-table tr:last-child td { border-bottom:none; }

    #confirmModal, #rejectModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #confirmModal.open, #rejectModal.open { opacity: 1; pointer-events: auto; }

    .modal-items-list { margin: 0; padding: 0; list-style: none; font-size: 13px; }
    .modal-items-list li { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
    .modal-items-list li:last-child { border-bottom: none; }
    .modal-item-name { color: #1e293b; font-weight: 500; }
    .modal-item-meta { color: #64748b; font-size: 12px; }
    .modal-item-qty { background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 12px; color: #0b1e3d; }
</style>
@endpush

@section('content')
<div class="inv-page">
    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(34,197,94,0.12);color:#16a34a;border-radius:10px;font-weight:500;font-size:14px;">
            {{ session('success') }}
        </div>
    @endif

    <!-- KPI tiles -->
    <div class="kpi-row cols-3">
        <div class="kpi-tile" style="--accent:#f59e0b;">
            <div class="kpi-head">
                <span class="kpi-label">Pending Deliveries</span>
                <span class="kpi-icon" style="background:rgba(245,158,11,0.15);color:#f59e0b;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <p class="kpi-value">{{ number_format($pendingCount) }}</p>
        </div>
        <div class="kpi-tile" style="--accent:#22c55e;">
            <div class="kpi-head">
                <span class="kpi-label">Received Today</span>
                <span class="kpi-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
            </div>
            <p class="kpi-value">{{ number_format($receivedTodayCount) }}</p>
        </div>
        <div class="kpi-tile" style="--accent:#ef4444;">
            <div class="kpi-head">
                <span class="kpi-label">Rejected</span>
                <span class="kpi-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </span>
            </div>
            <p class="kpi-value">{{ number_format($rejectedCount) }}</p>
        </div>
    </div>

    <!-- Data panel -->
    <div class="data-panel">
        <div class="data-toolbar" style="border-bottom:1px solid #eef2f7;">
            <div style="display:flex;gap:4px;background:#e2e8f0;border-radius:8px;padding:3px;flex-shrink:0;">
                <button id="tabPending" class="tab-btn" onclick="switchTab('pending')" style="padding:6px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;background:#0b1e3d;color:#fff;">Pending</button>
                <button id="tabHistory" class="tab-btn" onclick="switchTab('history')" style="padding:6px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;background:transparent;color:#64748b;">History</button>
            </div>
            <div id="pendingFilters" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                <form method="GET" action="{{ route('inventory.stock-receiving') }}" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                    <div class="tb-search">
                        <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search shipment or supplier...">
                    </div>
                    <select name="status" class="tb-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="intransit" {{ ($filters['status'] ?? '') === 'intransit' ? 'selected' : '' }}>In Transit</option>
                    </select>
                    @if(array_filter($filters ?? []))
                        <a href="{{ route('inventory.stock-receiving') }}" class="tb-clear">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Clear
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <div class="responsive-table" style="min-width:0;">
            <table class="data-grid">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>SHIPMENT</th>
                        <th>SUPPLIER</th>
                        <th>ITEMS</th>
                        <th class="col-r">QTY</th>
                        <th>WAREHOUSE</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody id="tbodyPending">
                    @forelse ($deliveries as $delivery)
                        @php $isProcessed = $deliveryProcessed[$delivery->id] ?? false; @endphp
                        <tr class="shipment-row" data-po-id="{{ $delivery->purchase_order_id }}" onclick="toggleItems(this)" style="{{ $isProcessed ? 'opacity:0.4;' : '' }}">
                            <td>
                                <span class="expand-arrow" data-arrow="1">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                                </span>
                            </td>
                            <td class="cell-strong">{{ $delivery->shipment_number }}</td>
                            <td>{{ $delivery->supplier_name }}</td>
                            <td class="cell-muted">{{ $delivery->po_category ?? '—' }}</td>
                            <td class="col-r cell-strong">{{ $delivery->qty }}</td>
                            <td>{{ $delivery->destination_warehouse_name }}</td>
                            <td>
                                <span class="status-badge status-{{ str_replace(' ', '-', strtolower($delivery->status)) }}">{{ ucfirst($delivery->status) }}</span>
                            </td>
                            <td onclick="event.stopPropagation()">
                                @error("del_action_{$delivery->id}")
                                    <p style="color:#ef4444;font-size:11px;margin:0 0 6px 0;">{{ $message }}</p>
                                @enderror
                                @if(!$isProcessed)
                                    <button onclick="openConfirmModal({{ $delivery->id }}, '{{ $delivery->shipment_number }}', '{{ $delivery->supplier_name }}', '{{ $delivery->destination_warehouse_name }}', {{ $delivery->qty }})" style="background:#166534;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;">Approve</button>
                                    <button onclick="openRejectModal({{ $delivery->id }})" style="background:#991b1b;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;">Reject</button>
                                @else
                                    <span style="color:#94a3b8;font-size:12px;">Processed</span>
                                @endif
                            </td>
                        </tr>
                        <tr class="items-detail" data-po-id="{{ $delivery->purchase_order_id }}">
                            <td colspan="8">
                                <div class="items-detail-inner">
                                    <div style="max-width:600px;">
                                        <table class="items-mini-table">
                                            <thead><tr><th style="width:40%;">Item</th><th style="width:35%;">SKU</th><th style="width:25%;text-align:right;">Qty</th></tr></thead>
                                            <tbody class="items-tbody" data-po-id="{{ $delivery->purchase_order_id }}"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:48px 16px;color:#94a3b8;font-size:14px;">
                                <svg width="48" height="48" fill="none" stroke="#cbd5e1" viewBox="0 0 24 24" stroke-width="1.5" style="margin:0 auto 12px;display:block;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                No pending deliveries from Procurement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tbody id="tbodyHistory" style="display:none;">
                    @forelse ($history as $entry)
                        <tr>
                            <td></td>
                            <td class="cell-strong">{{ $entry->shipment_number }}</td>
                            <td class="cell-muted">{{ $historySuppliers[$entry->shipment_number] ?? '—' }}</td>
                            <td class="cell-muted">{{ $entry->item?->category?->name ?? '—' }}</td>
                            <td class="col-r cell-strong">{{ $entry->quantity }}</td>
                            <td>{{ $entry->warehouse?->name ?? '—' }}</td>
                            <td>
                                <span class="status-badge status-{{ $entry->status }}" style="{{ $entry->status === 'approved' ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;' }}">{{ ucfirst($entry->status) }}</span>
                            </td>
                            <td class="cell-muted" style="font-size:12px;">
                                <div>by {{ $entry->processor?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:#94a3b8;">{{ $entry->processed_at?->format('M d, Y h:i A') ?? '—' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:48px 16px;color:#94a3b8;font-size:14px;">No processed records yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="pendingPagination">
            @if($deliveries->hasPages())
                <div class="panel-foot">
                    {{ $deliveries->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

    <script>
        const deliveryItems = {!! $deliveryItemsJson !!};

        function toggleItems(el) {
            const row = el.closest('tr.shipment-row');
            const poId = row.dataset.poId;
            const detailRow = row.nextElementSibling;
            const isOpen = detailRow.classList.contains('open');
            const arrow = row.querySelector('.expand-arrow');

            arrow.classList.toggle('open');

            if (isOpen) {
                detailRow.classList.remove('open');
            } else {
                const tbody = detailRow.querySelector('.items-tbody');
                if (!tbody.hasChildNodes()) {
                    const items = deliveryItems[poId] || [];
                    tbody.innerHTML = items.length
                        ? items.map(i => `<tr><td style="font-weight:500;">${i.name}</td><td style="color:#64748b;font-family:monospace;font-size:12px;">${i.sku}</td><td style="text-align:right;font-weight:600;">${i.qty}</td></tr>`).join('')
                        : '<tr><td colspan="3" style="color:#94a3b8;text-align:center;padding:12px;">No items found for this purchase order.</td></tr>';
                }
                detailRow.classList.add('open');
            }
        }

        function switchTab(tab) {
            const tabPending = document.getElementById('tabPending');
            const tabHistory = document.getElementById('tabHistory');
            const tbodyPending = document.getElementById('tbodyPending');
            const tbodyHistory = document.getElementById('tbodyHistory');
            const pendingFilters = document.getElementById('pendingFilters');
            const pendingPagination = document.getElementById('pendingPagination');

            if (tab === 'pending') {
                tabPending.style.background = '#0b1e3d'; tabPending.style.color = '#fff';
                tabHistory.style.background = 'transparent'; tabHistory.style.color = '#64748b';
                tbodyPending.style.display = ''; tbodyHistory.style.display = 'none';
                pendingFilters.style.display = ''; pendingPagination.style.display = '';
            } else {
                tabHistory.style.background = '#0b1e3d'; tabHistory.style.color = '#fff';
                tabPending.style.background = 'transparent'; tabPending.style.color = '#64748b';
                tbodyHistory.style.display = ''; tbodyPending.style.display = 'none';
                pendingFilters.style.display = 'none'; pendingPagination.style.display = 'none';
            }
        }
    </script>

    <!-- Confirm Modal -->
    <div id="confirmModal" class="nexora-modal-overlay" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:20;align-items:center;justify-content:center;">
        <div class="nexora-modal" style="max-width:520px;">
            <div class="nexora-modal-logo"></div>
            <div class="nexora-modal-header">
                <h2 class="nexora-modal-title">Approve Receiving</h2>
                <button type="button" onclick="closeConfirmModal()" class="nexora-modal-close">&times;</button>
            </div>
            <form id="confirmForm" method="POST" action="">
                @csrf
                <div style="padding:6px 20px 16px 20px;font-size:13px;color:#475569;line-height:1.7;">
                    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #eef2f7;">
                        <div><strong style="color:#1e293b;">Shipment:</strong><br><span id="confirmShipment" style="font-size:14px;"></span></div>
                        <div><strong style="color:#1e293b;">Supplier:</strong><br><span id="confirmSupplier"></span></div>
                        <div><strong style="color:#1e293b;">Warehouse:</strong><br><span id="confirmWarehouse"></span></div>
                    </div>
                    <div style="margin-bottom:6px;"><strong style="color:#1e293b;font-size:13px;">Items to receive:</strong></div>
                    <ul class="modal-items-list" id="confirmItemsList"></ul>
                </div>
                <div class="nexora-modal-actions">
                    <button type="button" onclick="closeConfirmModal()" class="nexora-modal-btn-secondary">Cancel</button>
                    <button type="submit" class="nexora-modal-btn-primary" style="background:#166534;color:#fff;border-color:#166534;">Confirm & Receive</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="nexora-modal-overlay" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:20;align-items:center;justify-content:center;">
        <div class="nexora-modal" style="max-width:480px;">
            <div class="nexora-modal-logo"></div>
            <div class="nexora-modal-header">
                <h2 class="nexora-modal-title">Reject Delivery</h2>
                <button type="button" onclick="closeRejectModal()" class="nexora-modal-close">&times;</button>
            </div>
            <form id="rejectForm" method="POST" action="">
                @csrf
                <div class="nexora-modal-form" style="grid-template-columns:1fr;">
                    <div>
                        <label class="nexora-modal-label">Reason <span style="color:#ef4444;">*</span></label>
                        <textarea name="reject_reason" required rows="4" class="nexora-modal-input" style="resize:vertical;"></textarea>
                        @error('reject_reason')<p class="nexora-modal-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="nexora-modal-actions">
                    <button type="button" onclick="closeRejectModal()" class="nexora-modal-btn-secondary">Cancel</button>
                    <button type="submit" class="nexora-modal-btn-primary" style="background:#dc2626;color:#fff;border-color:#dc2626;">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const confirmModal = document.getElementById('confirmModal');
        const rejectModal = document.getElementById('rejectModal');

        function openConfirmModal(id, shipment, supplier, warehouse, qty) {
            document.getElementById('confirmShipment').textContent = shipment;
            document.getElementById('confirmSupplier').textContent = supplier;
            document.getElementById('confirmWarehouse').textContent = warehouse;

            const itemsList = document.getElementById('confirmItemsList');
            const row = document.querySelector(`tr.shipment-row[data-po-id]`);
            if (row) {
                const poId = row.dataset.poId;
                const items = deliveryItems[poId] || [];
                itemsList.innerHTML = items.length
                    ? items.map(i => `<li><span class="modal-item-name">${i.name}</span> <span class="modal-item-meta">${i.sku}</span> <span class="modal-item-qty">${i.qty}</span></li>`).join('')
                    : '<li style="color:#94a3b8;justify-content:center;padding:12px 0;">No items</li>';
            }

            document.getElementById('confirmForm').action = '{{ url("inventory/stock-receiving") }}' + '/' + id + '/approve';
            confirmModal.classList.add('open');
        }
        function closeConfirmModal() {
            confirmModal.classList.remove('open');
        }
        confirmModal.addEventListener('click', function(e) {
            if (e.target === this) closeConfirmModal();
        });

        function openRejectModal(deliveryId) {
            document.getElementById('rejectForm').action = '{{ url("inventory/stock-receiving") }}' + '/' + deliveryId + '/reject';
            rejectModal.classList.add('open');
        }
        function closeRejectModal() {
            rejectModal.classList.remove('open');
            document.getElementById('rejectForm').reset();
        }
        rejectModal.addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
@endsection
