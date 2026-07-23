@extends('inventory::layouts.dashboard')

@section('title', 'Requests')

@push('styles')
<style>
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-processing { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-cancelled { background: #e2e8f0; color: #64748b; }

    .type-restock { background: #dbeafe; color: #1e40af; }
    .type-replacement { background: #fef9c3; color: #854d0e; }
    .type-pill { display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }

    .stock-status { font-size: 11px; font-weight: 600; margin-left: 6px; padding: 2px 6px; border-radius: 4px; }
    .stock-out { color: #991b1b; background: #fee2e2; }
    .stock-low { color: #854d0e; background: #fef9c3; }

    .type-toggle { display: flex; gap: 0; background: #e2e8f0; border-radius: 8px; padding: 3px; }
    .type-toggle button { padding: 6px 16px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; background: transparent; color: #64748b; transition: all 0.15s; flex: 1; }
    .type-toggle button.active { background: #0b1e3d; color: #fff; }

    #requestModal { opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
    #requestModal.open { opacity: 1; pointer-events: auto; }

    .restock-fields, .replacement-fields { display: none; }
    .restock-fields.active, .replacement-fields.active { display: block; }
</style>
@endpush

@section('content')
<div class="inv-page">
    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(34,197,94,0.12);color:#16a34a;border-radius:10px;font-weight:500;font-size:14px;">
            {{ session('success') }}
        </div>
    @endif

    <div class="kpi-row cols-3">
        <div class="kpi-tile" style="--accent:#f59e0b;">
            <div class="kpi-head">
                <span class="kpi-label">Total Requests</span>
                <span class="kpi-icon" style="background:rgba(245,158,11,0.15);color:#f59e0b;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </span>
            </div>
            <p class="kpi-value">{{ number_format($totalCount) }}</p>
        </div>
        <div class="kpi-tile" style="--accent:#22c55e;">
            <div class="kpi-head">
                <span class="kpi-label">Pending</span>
                <span class="kpi-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <p class="kpi-value">{{ number_format($pendingCount) }}</p>
        </div>
        <div class="kpi-tile" style="--accent:#3b82f6;">
            <div class="kpi-head">
                <span class="kpi-label">Department</span>
                <span class="kpi-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </span>
            </div>
            <p class="kpi-value">{{ session('employee_department', 'Inventory') }}</p>
        </div>
    </div>

    <div class="data-panel">
        <div class="data-toolbar" style="border-bottom:1px solid #eef2f7;">
            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                <div class="tb-search">
                    <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" id="searchInput" placeholder="Search by item or req #..." oninput="filterRequests()">
                </div>
                <select id="statusFilter" class="tb-select" onchange="filterRequests()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <button onclick="openRequestModal()" style="margin-left:auto;background:#0b1e3d;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:12px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;gap:6px;flex-shrink:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                New Request
            </button>
        </div>

        <div class="responsive-table" style="min-width:0;">
            <table class="data-grid">
                <thead>
                    <tr>
                        <th>REQ #</th>
                        <th>TYPE</th>
                        <th>ITEM</th>
                        <th class="col-r">QTY</th>
                        <th>STATUS</th>
                        <th>DATE</th>
                    </tr>
                </thead>
                <tbody id="requestsBody">
                    @php
                        $reqType = fn($notes) => str_contains($notes ?? '', '[type:restock]') ? 'Restock' : (str_contains($notes ?? '', '[type:replacement]') ? 'Replacement' : '—');
                    @endphp
                    @forelse ($requests as $req)
                        <tr class="req-row" data-status="{{ strtolower($req->status ?? 'pending') }}" data-search="{{ strtolower($req->req_id . ' ' . $req->part_name) }}">
                            <td class="cell-strong" style="font-size:11px;">{{ $req->req_id }}</td>
                            <td>
                                @php $t = $reqType($req->notes); @endphp
                                @if($t !== '—')
                                    <span class="type-pill type-{{ strtolower($t) }}">{{ $t }}</span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td>{{ $req->part_name }}</td>
                            <td class="col-r cell-strong">{{ $req->quantity }}</td>
                            <td>
                                @php
                                    $s = strtolower($req->status ?? 'pending');
                                    $bc = match($s) {
                                        'pending' => 'status-pending',
                                        'processing' => 'status-processing',
                                        'completed' => 'status-completed',
                                        'rejected' => 'status-rejected',
                                        'cancelled' => 'status-cancelled',
                                        default => 'status-pending',
                                    };
                                @endphp
                                <span class="status-badge {{ $bc }}">{{ $req->status ?? 'Pending' }}</span>
                            </td>
                            <td class="cell-muted" style="font-size:12px;">{{ \Carbon\Carbon::parse($req->date_requested)->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr id="emptyRow">
                            <td colspan="6" style="text-align:center;padding:48px 16px;color:#94a3b8;font-size:14px;">
                                <svg width="40" height="40" fill="none" stroke="#cbd5e1" viewBox="0 0 24 24" stroke-width="1.5" style="margin:0 auto 8px;display:block;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                No requests yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="requestModal" class="nexora-modal-overlay" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:20;align-items:center;justify-content:center;">
    <div class="nexora-modal" style="max-width:520px;">
        <div class="nexora-modal-logo"></div>
        <div class="nexora-modal-header">
            <h2 class="nexora-modal-title">New Request</h2>
            <button type="button" onclick="closeRequestModal()" class="nexora-modal-close">&times;</button>
        </div>
        <form method="POST" action="{{ route('inventory.requests.store') }}">
            @csrf
            <input type="hidden" name="type" id="requestType" value="restock">
            <input type="hidden" name="item_id" id="selectedItemId" value="">
            <input type="hidden" name="defect_id" id="selectedDefectId" value="">

            <div style="padding:16px 20px 0 20px;">
                <div class="type-toggle">
                    <button type="button" class="active" onclick="setRequestType('restock')" id="typeRestockBtn">Restock</button>
                    <button type="button" onclick="setRequestType('replacement')" id="typeReplacementBtn">Replacement</button>
                </div>
            </div>

            <div class="nexora-modal-form" style="margin-top:0;">

                <div style="grid-column:1/-1;">
                    <label class="nexora-modal-label">Item</label>

                    {{-- Restock fields --}}
                    <div class="restock-fields active" id="restockFields">
                        <select class="nexora-modal-input" id="restockItemSelect" onchange="onRestockSelect(this)">
                            <option value="">Select an item...</option>
                            <optgroup label="— Out of Stock —">
                                @foreach ($lowStockItems as $item)
                                    @if($item['status'] === 'Out of Stock')
                                        <option value="{{ $item['id'] }}" data-name="{{ $item['name'] }}">{{ $item['name'] }} ({{ $item['sku'] }}) <span class="stock-status stock-out">0 available</span></option>
                                    @endif
                                @endforeach
                            </optgroup>
                            <optgroup label="— Low Stock —">
                                @foreach ($lowStockItems as $item)
                                    @if($item['status'] === 'Low Stock')
                                        <option value="{{ $item['id'] }}" data-name="{{ $item['name'] }}">{{ $item['name'] }} ({{ $item['sku'] }}) — {{ $item['total_available'] }} available</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        </select>
                        @error('part_name')<p class="nexora-modal-error">{{ $message }}</p>@enderror
                    </div>

                    {{-- Replacement fields --}}
                    <div class="replacement-fields" id="replacementFields">
                        <select class="nexora-modal-input" id="defectItemSelect" onchange="onDefectSelect(this)">
                            <option value="">Select a defect item...</option>
                            @foreach ($defectItems as $item)
                                <option value="{{ $item->id }}" data-name="{{ $item->part_name }}">{{ $item->part_name }}@if($item->quantity > 1) (x{{ $item->quantity }})@endif@if($item->source) — {{ $item->source }}@endif</option>
                            @endforeach
                            <option value="__other__">Other (type manually)</option>
                        </select>
                        <input type="text" name="part_name" id="modalPartName" value="{{ old('part_name') }}" placeholder="Type part name..." style="display:none;margin-top:8px;width:100%;padding:10px 12px;border:1px solid #d1d9e6;border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;outline:none;color:#0f172a;box-sizing:border-box;">
                    </div>
                </div>

                <div>
                    <label class="nexora-modal-label">Quantity</label>
                    <input type="number" name="quantity" id="requestQty" value="{{ old('quantity', 1) }}" required min="1" class="nexora-modal-input">
                    @error('quantity')<p class="nexora-modal-error">{{ $message }}</p>@enderror
                </div>
                <div style="grid-column:1/-1;">
                    <label class="nexora-modal-label">Notes</label>
                    <textarea name="notes" rows="3" maxlength="1000" placeholder="Reason for request, specifications..." class="nexora-modal-input" style="resize:vertical;">{{ old('notes') }}</textarea>
                    @error('notes')<p class="nexora-modal-error">{{ $message }}</p>@enderror
                </div>
            </div>
            @error('submit')
                <p style="color:#ef4444;font-size:13px;margin:12px 0 0;padding:10px;background:#fef2f2;border-radius:6px;">{{ $message }}</p>
            @enderror
            <div class="nexora-modal-actions">
                <button type="button" onclick="closeRequestModal()" class="nexora-modal-btn-secondary">Cancel</button>
                <button type="submit" class="nexora-modal-btn-primary" style="background:#0b1e3d;color:#fff;border-color:#0b1e3d;">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
    var lowStockItems = @json($lowStockItems);

    function setRequestType(type) {
        document.getElementById('requestType').value = type;

        document.getElementById('typeRestockBtn').classList.toggle('active', type === 'restock');
        document.getElementById('typeReplacementBtn').classList.toggle('active', type === 'replacement');

        document.getElementById('restockFields').classList.toggle('active', type === 'restock');
        document.getElementById('replacementFields').classList.toggle('active', type === 'replacement');

        document.getElementById('selectedItemId').value = '';
        document.getElementById('selectedDefectId').value = '';
    }

    function onRestockSelect(select) {
        var option = select.options[select.selectedIndex];
        if (option && option.value) {
            document.getElementById('selectedItemId').value = option.value;
            document.getElementById('selectedDefectId').value = '';
            var qty = document.getElementById('requestQty');
            if (!qty.value || qty.value == 1) qty.value = 1;
        }
    }

    function onDefectSelect(select) {
        var textInput = document.getElementById('modalPartName');
        var selectedId = document.getElementById('selectedDefectId');

        if (select.value === '__other__') {
            textInput.style.display = 'block';
            textInput.value = '';
            textInput.focus();
            selectedId.value = '';
        } else if (select.value !== '') {
            textInput.style.display = 'none';
            selectedId.value = select.value;
            document.getElementById('selectedItemId').value = '';

            var option = select.options[select.selectedIndex];
            var qty = document.getElementById('requestQty');
            if (!qty.value || qty.value == 1) qty.value = 1;
        } else {
            textInput.style.display = 'none';
            selectedId.value = '';
        }
    }

    @if(old('part_name') && !$defectItems->pluck('part_name')->contains(old('part_name')))
        (function() {
            setRequestType('replacement');
            var s = document.getElementById('defectItemSelect');
            s.value = '__other__';
            onDefectSelect(s);
            document.getElementById('modalPartName').value = '{{ old('part_name') }}';
        })();
    @endif

    function openRequestModal() {
        document.getElementById('requestModal').classList.add('open');
    }

    function closeRequestModal() {
        document.getElementById('requestModal').classList.remove('open');
        document.getElementById('requestModal').querySelector('form').reset();
        document.getElementById('restockFields').classList.add('active');
        document.getElementById('replacementFields').classList.remove('active');
        document.getElementById('typeRestockBtn').classList.add('active');
        document.getElementById('typeReplacementBtn').classList.remove('active');
    }

    document.getElementById('requestModal').addEventListener('click', function (e) {
        if (e.target === this) closeRequestModal();
    });

    function filterRequests() {
        var q = document.getElementById('searchInput').value.toLowerCase();
        var st = document.getElementById('statusFilter').value.toLowerCase();
        var rows = document.querySelectorAll('.req-row');
        var visible = 0;
        rows.forEach(function(r) {
            var matchSearch = !q || r.getAttribute('data-search').includes(q);
            var matchStatus = !st || r.getAttribute('data-status') === st;
            r.style.display = (matchSearch && matchStatus) ? '' : 'none';
            if (matchSearch && matchStatus) visible++;
        });
        var empty = document.getElementById('emptyRow');
        if (empty) empty.style.display = visible > 0 ? 'none' : '';
    }
</script>
@endsection
