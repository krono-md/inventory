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

    #confirmModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #confirmModal.open { opacity: 1; pointer-events: auto; }
    #rejectModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #rejectModal.open { opacity: 1; pointer-events: auto; }
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
                        <th>SHIPMENT</th>
                        <th>SUPPLIER</th>
                        <th>CATEGORY</th>
                        <th class="col-r">QTY</th>
                        <th>WAREHOUSE</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody id="tbodyPending">
                    @forelse ($deliveries as $delivery)
                        @php $isProcessed = $deliveryProcessed[$delivery->id] ?? false; @endphp
                        <tr style="{{ $isProcessed ? 'opacity:0.4;' : '' }}">
                            <td class="cell-strong">{{ $delivery->shipment_number }}</td>
                            <td>{{ $delivery->supplier_name }}</td>
                            <td class="cell-muted">{{ $delivery->po_category ?? '—' }}</td>
                            <td class="col-r cell-strong">{{ $delivery->qty }}</td>
                            <td>{{ $delivery->destination_warehouse_name }}</td>
                            <td>
                                <span class="status-badge status-{{ str_replace(' ', '-', strtolower($delivery->status)) }}">{{ ucfirst($delivery->status) }}</span>
                            </td>
                            <td>
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
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;padding:48px 16px;color:#94a3b8;font-size:14px;">
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
                            <td colspan="7" style="text-align:center;padding:48px 16px;color:#94a3b8;font-size:14px;">No processed records yet.</td>
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
        <div class="nexora-modal" style="max-width:480px;">
            <div class="nexora-modal-logo"></div>
            <div class="nexora-modal-header">
                <h2 class="nexora-modal-title">Confirm Receiving</h2>
                <button type="button" onclick="closeConfirmModal()" class="nexora-modal-close">&times;</button>
            </div>
            <div style="padding:20px;font-size:14px;color:#333;line-height:1.8;">
                <p style="margin:0 0 8px 0;"><strong>Shipment:</strong> <span id="confirmShipment"></span></p>
                <p style="margin:0 0 8px 0;"><strong>Supplier:</strong> <span id="confirmSupplier"></span></p>
                <p style="margin:0 0 8px 0;"><strong>Warehouse:</strong> <span id="confirmWarehouse"></span></p>
                <p style="margin:0 0 8px 0;"><strong>Quantity:</strong> <span id="confirmQty"></span></p>
            </div>
            <form id="confirmForm" method="POST" action="">
                @csrf
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
        function openConfirmModal(id, shipment, supplier, warehouse, qty) {
            document.getElementById('confirmShipment').textContent = shipment;
            document.getElementById('confirmSupplier').textContent = supplier;
            document.getElementById('confirmWarehouse').textContent = warehouse;
            document.getElementById('confirmQty').textContent = qty;
            document.getElementById('confirmForm').action = '{{ url("inventory/stock-receiving") }}' + '/' + id + '/approve';
            document.getElementById('confirmModal').classList.add('open');
        }
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('open');
        }
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmModal();
        });

        function openRejectModal(deliveryId) {
            document.getElementById('rejectForm').action = '{{ url("inventory/stock-receiving") }}' + '/' + deliveryId + '/reject';
            document.getElementById('rejectModal').classList.add('open');
        }
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('open');
            document.getElementById('rejectForm').reset();
        }
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
@endsection
