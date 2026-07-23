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

        return view('inventory::requests', [
            'requests' => $requests,
            'defectItems' => $defectItems,
            'pendingCount' => $requests->where('status', 'Pending')->count(),
            'totalCount' => $requests->count(),
            'activePage' => 'requests',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:150',
            'quantity' => 'required|integer|min:1',
            'priority' => 'required|in:Low,Medium,High',
            'notes' => 'nullable|string|max:1000',
        ]);

        $reqId = 'REQ-INV-' . now()->format('ymd') . '-' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        try {
            DB::connection('manufacturing')->table('requisitions')->insert([
                'req_id' => $reqId,
                'part_name' => $validated['part_name'],
                'quantity' => $validated['quantity'],
                'department' => session('employee_department', 'Inventory'),
                'requested_by' => session('employee_name', 'Unknown'),
                'priority' => $validated['priority'],
                'notes' => $validated['notes'] ?? null,
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
