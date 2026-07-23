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

    .type-toggle { display: flex; gap: 0; background: rgba(255,255,255,0.06); border-radius: 10px; padding: 4px; border: 1px solid rgba(255,255,255,0.06); }

    .item-list { max-height: 220px; overflow-y: auto; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); padding: 4px; }
    .item-list .il-group-header { padding: 8px 12px 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.35); }
    .item-list .il-item { display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; border-radius: 8px; cursor: pointer; transition: all 0.12s ease; }
    .item-list .il-item:hover { background: rgba(255,255,255,0.06); }
    .item-list .il-item.selected { background: rgba(27,111,200,0.2); }
    .item-list .il-item .il-name { font-size: 13px; font-weight: 600; color: #fff; }
    .item-list .il-item .il-sku { font-size: 11px; color: rgba(255,255,255,0.4); margin-left: 8px; }
    .item-list .il-item .il-stock { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; flex-shrink: 0; }
    .item-list .il-item .il-stock.il-out { color: #fca5a5; background: rgba(239,68,68,0.15); }
    .item-list .il-item .il-stock.il-low { color: #fcd34d; background: rgba(245,158,11,0.15); }
    .item-list .il-empty { padding: 20px 12px; text-align: center; color: rgba(255,255,255,0.3); font-size: 13px; }
    .item-list::-webkit-scrollbar { width: 4px; }
    .item-list::-webkit-scrollbar-track { background: transparent; }
    .item-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }
    .type-toggle button { padding: 8px 20px; border: none; border-radius: 7px; font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; background: transparent; color: rgba(255,255,255,0.45); transition: all 0.2s ease; flex: 1; letter-spacing: 0.2px; }
    .type-toggle button:hover { color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.04); }
    .type-toggle button.active { background: rgba(27,111,200,0.3); color: #90c8ff; box-shadow: 0 0 20px -6px rgba(27,111,200,0.25); }
    .type-toggle button.active::after { content: ''; display: block; height: 2px; width: 20px; background: #4a9ee8; margin: 4px auto 0; border-radius: 2px; }

    #requestModal { opacity: 0; pointer-events: none; }
    #requestModal.open { opacity: 1; pointer-events: auto; }

    .restock-fields, .replacement-fields { display: none; }
    .restock-fields.active, .replacement-fields.active { display: block; }

    .req-type-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; margin-right: 8px; flex-shrink: 0; }
    .req-type-icon.restock { background: rgba(27,111,200,0.2); }
    .req-type-icon.replacement { background: rgba(245,158,11,0.2); }

    .inv-page .kpi-row.cols-2 { grid-template-columns:1fr 1fr; }
    @media (max-width:520px){ .inv-page .kpi-row.cols-2 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="inv-page">
    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 16px;background:rgba(34,197,94,0.12);color:#16a34a;border-radius:10px;font-weight:500;font-size:14px;">
            {{ session('success') }}
        </div>
    @endif

    <div class="kpi-row cols-2">
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
            <button onclick="openRequestModal()" style="margin-left:auto;background:#1b6fc8;color:#fff;border:none;border-radius:10px;padding:10px 20px;font-size:14px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;gap:8px;flex-shrink:0;">
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
        <div class="panel-foot">
            {{ $requests->links() }}
        </div>
    </div>
</div>

<div id="requestModal" class="nexora-modal-overlay" style="display:flex;">
    <div class="nexora-modal" style="max-width:520px;">
        <div class="nexora-modal-logo"></div>
        <div class="nexora-modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="req-type-icon restock" id="headerIconRestock">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4a9ee8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </span>
                <span class="req-type-icon replacement" id="headerIconReplacement" style="display:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                <h2 class="nexora-modal-title">New Request</h2>
            </div>
            <button type="button" onclick="closeRequestModal()" class="nexora-modal-close">&times;</button>
        </div>
        <form method="POST" action="{{ route('inventory.requests.store') }}">
            @csrf
            <input type="hidden" name="type" id="requestType" value="restock">
            <input type="hidden" name="item_id" id="selectedItemId" value="">
            <input type="hidden" name="defect_id" id="selectedDefectId" value="">

            <div style="padding:0 0 18px 0;">
                <div class="type-toggle">
                    <button type="button" class="active" onclick="setRequestType('restock')" id="typeRestockBtn">Restock</button>
                    <button type="button" onclick="setRequestType('replacement')" id="typeReplacementBtn">Replacement</button>
                </div>
            </div>

            <div class="nexora-modal-form">

                <div style="grid-column:1/-1;">
                    <label class="nexora-modal-label">Item</label>

                    <div class="restock-fields active" id="restockFields">
                        <div class="item-list" id="itemList">
                            @php
                                $oos = $lowStockItems->where('status','Out of Stock');
                                $ls = $lowStockItems->where('status','Low Stock');
                            @endphp
                            @if($oos->count())
                                <div class="il-group-header">Out of Stock</div>
                                @foreach ($oos as $item)
                                    <div class="il-item" data-id="{{ $item['id'] }}" onclick="selectRestockItem({{ $item['id'] }})">
                                        <div><span class="il-name">{{ $item['name'] }}</span><span class="il-sku">{{ $item['sku'] }}</span></div>
                                        <span class="il-stock il-out">0 avail.</span>
                                    </div>
                                @endforeach
                            @endif
                            @if($ls->count())
                                <div class="il-group-header">Low Stock</div>
                                @foreach ($ls as $item)
                                    <div class="il-item" data-id="{{ $item['id'] }}" onclick="selectRestockItem({{ $item['id'] }})">
                                        <div><span class="il-name">{{ $item['name'] }}</span><span class="il-sku">{{ $item['sku'] }}</span></div>
                                        <span class="il-stock il-low">{{ $item['total_available'] }} avail.</span>
                                    </div>
                                @endforeach
                            @endif
                            @if(!$oos->count() && !$ls->count())
                                <div class="il-empty">No items need restocking</div>
                            @endif
                        </div>
                        <div id="restockStockHint" style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:6px;min-height:16px;"></div>
                        @error('part_name')<p class="nexora-modal-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="replacement-fields" id="replacementFields">
                        <div class="item-list" id="defectList">
                            @forelse ($defectItems as $item)
                                <div class="il-item" data-id="{{ $item->id }}" onclick="selectDefectItem({{ $item->id }}, {{ $item->quantity }})">
                                    <div>
                                        <span class="il-name">{{ $item->part_name }}</span>
                                        @if($item->quantity > 1)<span class="il-sku">x{{ $item->quantity }}</span>@endif
                                        @if($item->source)<span class="il-sku">{{ $item->source }}</span>@endif
                                    </div>
                                </div>
                            @empty
                                <div class="il-empty">No open defects</div>
                            @endforelse
                        </div>
                        <input type="hidden" name="part_name" id="modalPartName" value="">
                    </div>
                </div>

                <div style="grid-column:1/-1;">
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
                <p style="color:#ef4444;font-size:13px;margin:12px 0 0;padding:10px;background:rgba(239,68,68,0.12);border-radius:8px;">{{ $message }}</p>
            @enderror
            <div class="nexora-modal-actions">
                <button type="button" onclick="closeRequestModal()" class="nexora-modal-btn-secondary">Cancel</button>
                <button type="submit" class="nexora-modal-btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    var lowStockItems = @json($lowStockItems);
    var defectItems = @json($defectItems);
    var selectedRestockId = null;

    function setRequestType(type) {
        document.getElementById('requestType').value = type;

        document.getElementById('typeRestockBtn').classList.toggle('active', type === 'restock');
        document.getElementById('typeReplacementBtn').classList.toggle('active', type === 'replacement');

        document.getElementById('restockFields').classList.toggle('active', type === 'restock');
        document.getElementById('replacementFields').classList.toggle('active', type === 'replacement');

        document.getElementById('headerIconRestock').style.display = type === 'restock' ? '' : 'none';
        document.getElementById('headerIconReplacement').style.display = type === 'replacement' ? '' : 'none';

        clearRestockSelection();
        clearDefectSelection();
    }

    function clearDefectSelection() {
        document.getElementById('selectedDefectId').value = '';
        document.querySelectorAll('#defectList .il-item').forEach(function(el) { el.classList.remove('selected'); });
        enableQtyInput();
    }

    function enableQtyInput() {
        var qtyInput = document.getElementById('requestQty');
        qtyInput.readOnly = false;
        qtyInput.style.opacity = '';
    }

    function clearRestockSelection() {
        document.getElementById('selectedItemId').value = '';
        document.getElementById('selectedDefectId').value = '';
        document.getElementById('restockStockHint').textContent = '';
        document.querySelectorAll('#itemList .il-item').forEach(function(el) { el.classList.remove('selected'); });
        selectedRestockId = null;
        enableQtyInput();
    }

    function selectRestockItem(id) {
        var item = lowStockItems.find(function(i) { return i.id === id; });
        if (!item) return;
        document.getElementById('selectedItemId').value = item.id;
        document.getElementById('selectedDefectId').value = '';
        document.querySelectorAll('#itemList .il-item').forEach(function(el) {
            el.classList.toggle('selected', parseInt(el.getAttribute('data-id')) === id);
        });
        selectedRestockId = id;
        var hint = document.getElementById('restockStockHint');
        hint.textContent = item.status === 'Out of Stock' ? 'Currently out of stock' : item.total_available + ' available across all warehouses';
        var qty = document.getElementById('requestQty');
        if (!qty.value || qty.value == 1) qty.value = 1;
    }

    function selectDefectItem(id, qty) {
        document.getElementById('selectedDefectId').value = id;
        document.getElementById('selectedItemId').value = '';
        clearRestockSelection();
        document.querySelectorAll('#defectList .il-item').forEach(function(el) {
            el.classList.toggle('selected', parseInt(el.getAttribute('data-id')) === id);
        });
        var qtyInput = document.getElementById('requestQty');
        qtyInput.value = qty;
        qtyInput.readOnly = true;
        qtyInput.style.opacity = '0.6';
    }

    @if(old('defect_id') && $defectItems->contains('id', old('defect_id')))
        document.addEventListener('DOMContentLoaded', function() {
            var defect = defectItems.find(function(d) { return d.id === {{ (int) old('defect_id') }}; });
            if (defect) {
                setRequestType('replacement');
                selectDefectItem(defect.id, defect.quantity);
            }
        });
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
        document.getElementById('headerIconRestock').style.display = '';
        document.getElementById('headerIconReplacement').style.display = 'none';
        document.getElementById('restockStockHint').textContent = '';
        clearRestockSelection();
        clearDefectSelection();
    }

    document.getElementById('requestModal').addEventListener('click', function (e) {
        if (e.target === this) closeRequestModal();
    });

    @if($errors->any())
        document.addEventListener('DOMContentLoaded', function() { openRequestModal(); });
    @endif

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
