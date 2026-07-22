@extends('inventory::layouts.dashboard')

@section('title', 'Stock Receiving')

@push('styles')
<style>
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-intransit { background: #dbeafe; color: #1e40af; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    .kpi-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    .table-row { transition: background 0.12s ease; }
    .table-row:hover { background: #f8fafc; }
    .tab-btn { transition: all 0.15s ease; }
    .fade-in { animation: fadeIn 0.2s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

    #confirmModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #confirmModal.open { opacity: 1; pointer-events: auto; }
    #rejectModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #rejectModal.open { opacity: 1; pointer-events: auto; }
</style>
@endpush

@section('content')
    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(34,197,94,0.12);color:#16a34a;border-radius:10px;font-weight:500;font-size:14px;">
            {{ session('success') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
        <div class="kpi-card" style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:13px;color:#94a3b8;margin:0 0 4px 0;">Pending Deliveries</p>
            <p style="font-size:36px;font-weight:700;color:#fff;margin:0;">{{ $pendingCount }}</p>
        </div>
        <div class="kpi-card" style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:13px;color:#94a3b8;margin:0 0 4px 0;">Received Today</p>
            <p style="font-size:36px;font-weight:700;color:#fff;margin:0;">{{ $receivedTodayCount }}</p>
        </div>
        <div class="kpi-card" style="background:#0b1e3d;padding:20px;border-radius:20px;">
            <p style="font-size:13px;color:#94a3b8;margin:0 0 4px 0;">Rejected</p>
            <p style="font-size:36px;font-weight:700;color:#fff;margin:0;">{{ $rejectedCount }}</p>
        </div>
    </div>

    <div style="background:#fff;border-radius:20px;overflow:hidden;min-width:0;">
        <div style="padding:16px 20px;display:flex;align-items:center;gap:12px;flex-wrap:nowrap;min-width:0;border-bottom:1px solid #e2e8f0;">
            <div style="display:flex;gap:4px;background:#e2e8f0;border-radius:8px;padding:3px;flex-shrink:0;">
                <button id="tabPending" class="tab-btn" onclick="switchTab('pending')" style="padding:6px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;background:#0b1e3d;color:#fff;">Pending</button>
                <button id="tabHistory" class="tab-btn" onclick="switchTab('history')" style="padding:6px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;background:transparent;color:#64748b;">History</button>
            </div>
            <div id="pendingFilters" style="display:flex;align-items:center;gap:12px;flex:1;">
                <form method="GET" action="{{ route('inventory.stock-receiving') }}" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;background:#f1f5f9;border-radius:8px;padding:8px 14px;gap:8px;flex:1;min-width:150px;border:1px solid #e2e8f0;">
                        <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search shipment or supplier..." style="border:none;outline:none;background:transparent;font-size:13px;font-family:'Inter',sans-serif;color:#333;width:100%;">
                    </div>
                    <select name="status" onchange="this.form.submit()" style="background:#f1f5f9;color:#333;border:1px solid #e2e8f0;border-radius:8px;padding:8px 14px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;outline:none;flex-shrink:0;">
                        <option value="">All Status</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="intransit" {{ ($filters['status'] ?? '') === 'intransit' ? 'selected' : '' }}>In Transit</option>
                    </select>
                    @if(array_filter($filters ?? []))
                        <a href="{{ route('inventory.stock-receiving') }}" style="color:#64748b;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:12px;font-family:'Inter',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:4px;flex-shrink:0;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Clear
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#1b3a6b;">
                        <th style="text-align:left;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">SHIPMENT</th>
                        <th style="text-align:left;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">SUPPLIER</th>
                        <th style="text-align:left;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">CATEGORY</th>
                        <th style="text-align:center;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">QTY</th>
                        <th style="text-align:left;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">WAREHOUSE</th>
                        <th style="text-align:center;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">STATUS</th>
                        <th style="text-align:center;padding:10px 16px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;">ACTION</th>
                    </tr>
                </thead>
                <tbody id="tbodyPending">
                    @forelse ($deliveries as $delivery)
                        @php $isProcessed = $deliveryProcessed[$delivery->id] ?? false; @endphp
                        <tr class="table-row" style="border-bottom:1px solid #e2e8f0;{{ $isProcessed ? 'opacity:0.4;' : '' }}">
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;font-weight:500;">{{ $delivery->shipment_number }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;">{{ $delivery->supplier_name }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#5B7A9D;">{{ $delivery->po_category ?? '—' }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;font-weight:600;text-align:center;">{{ $delivery->qty }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;">{{ $delivery->destination_warehouse_name }}</td>
                            <td style="padding:12px 16px;text-align:center;">
                                <span class="status-badge status-{{ str_replace(' ', '-', strtolower($delivery->status)) }}">{{ ucfirst($delivery->status) }}</span>
                            </td>
                            <td style="padding:12px 16px;text-align:center;">
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
                        <tr class="table-row" style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;font-weight:500;">{{ $entry->shipment_number }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#5B7A9D;">{{ $historySuppliers[$entry->shipment_number] ?? '—' }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#5B7A9D;">{{ $entry->item?->category?->name ?? '—' }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;font-weight:600;text-align:center;">{{ $entry->quantity }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#132B52;">{{ $entry->warehouse?->name ?? '—' }}</td>
                            <td style="padding:12px 16px;text-align:center;">
                                <span class="status-badge status-{{ $entry->status }}" style="{{ $entry->status === 'approved' ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;' }}">{{ ucfirst($entry->status) }}</span>
                            </td>
                            <td style="padding:12px 16px;font-size:12px;color:#5B7A9D;text-align:center;">
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
                <div style="padding:16px;border-top:1px solid #e2e8f0;">
                    {{ $deliveries->links() }}
                </div>
            @endif
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