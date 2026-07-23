<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    public function index()
    {
        try {
            $requests = DB::connection('manufacturing')
                ->table('requisitions')
                ->where('requested_by', session('employee_name'))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        } catch (\Exception $e) {
            $requests = collect();
        }

        $defectItems = \Modules\Inventory\Models\Defect::where('status', 'Open')->get();

        $lowStockItems = \Modules\Inventory\Models\Item::with('stockLevels')
            ->where(function ($q) {
                $q->lowStock()->orWhere(function ($sq) {
                    $sq->whereIn('items.id', function ($sub) {
                        $sub->select('item_id')
                            ->from('stock_levels')
                            ->groupBy('item_id')
                            ->havingRaw('COALESCE(SUM(stock - reserved_quantity), 0) <= 0');
                    });
                });
            })
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'total_available' => $item->total_available,
                'status' => $item->status,
            ])
            ->sortBy('status')
            ->values();

        return view('inventory::requests', [
            'requests' => $requests,
            'defectItems' => $defectItems,
            'lowStockItems' => $lowStockItems,
            'pendingCount' => $requests->where('status', 'Pending')->count(),
            'totalCount' => $requests->count(),
            'activePage' => 'requests',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:restock,replacement',
            'part_name' => 'nullable|string|max:150',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validated['type'] === 'restock') {
            $item = \Modules\Inventory\Models\Item::find($request->input('item_id'));
            if (! $item) {
                return back()->withInput()->withErrors(['part_name' => 'Selected item not found.']);
            }
            $validated['part_name'] = $item->name;
        }

        if ($validated['type'] === 'replacement') {
            $defectId = $request->input('defect_id');
            if ($defectId) {
                $defect = \Modules\Inventory\Models\Defect::find($defectId);
                if (! $defect || $defect->status !== 'Open') {
                    return back()->withInput()->withErrors(['part_name' => 'Selected defect is no longer open.']);
                }
                $validated['part_name'] = $defect->part_name;
            }
        }

        if (empty($validated['part_name'])) {
            return back()->withInput()->withErrors(['part_name' => 'Part name is required.']);
        }

        $prefix = $validated['type'] === 'restock' ? 'RST' : 'RPL';
        $reqId = 'REQ-' . $prefix . '-' . now()->format('ymd') . '-' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $structuredNotes = $validated['notes'] ?? '';
        if ($validated['type'] === 'restock') {
            $structuredNotes = '[type:restock] [item_id:' . $request->input('item_id') . '] ' . $structuredNotes;
        } else {
            $structuredNotes = '[type:replacement] [defect_id:' . $request->input('defect_id') . '] ' . $structuredNotes;
        }

        try {
            DB::connection('manufacturing')->table('requisitions')->insert([
                'req_id' => $reqId,
                'part_name' => $validated['part_name'],
                'quantity' => $validated['quantity'],
                'department' => session('employee_department', 'Inventory'),
                'requested_by' => session('employee_name', 'Unknown'),
                'notes' => $structuredNotes,
                'destination' => session('employee_department', 'Inventory'),
                'date_requested' => now()->toDateString(),
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['submit' => 'Failed to submit request. The procurement system is currently unavailable. Please try again later.']);
        }

        return redirect()->route('inventory.requests')
            ->with('success', "Request '{$reqId}' for '{$validated['part_name']}' submitted to Procurement.");
    }
}
