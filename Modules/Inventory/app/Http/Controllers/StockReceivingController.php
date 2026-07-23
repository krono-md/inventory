<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;

use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Procurement;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockReceiving;
use Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockReceivingController extends Controller
{
    private function procurementDeliveriesQuery()
    {
        $schema = Schema::connection('procurement');
        $hasDeliverToWarehouse = $schema->hasColumn('deliveries', 'deliver_to_warehouse');

        $destinationWarehouse = $hasDeliverToWarehouse
            ? DB::raw('deliveries.deliver_to_warehouse as destination_warehouse_id')
            : DB::raw('NULL as destination_warehouse_id');

        $query = Procurement::query()
            ->leftJoin('suppliers', 'deliveries.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_orders', 'deliveries.purchase_order_id', '=', 'purchase_orders.id')
            ->select(
                'deliveries.*',
                'suppliers.name as supplier_name',
                $destinationWarehouse,
                DB::raw('NULL as po_category')
            );

        if (! (config('nexora.root_admin_module_testing') && auth()->user()?->role === 'root_admin')) {
            $query->where('purchase_orders.client_id', (int) session('employee_client_id'));
        }

        return $query;
    }

    private function findDeliveryForCurrentClient(int $deliveryId): Procurement
    {
        return $this->procurementDeliveriesQuery()
            ->where('deliveries.id', $deliveryId)
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        // Incoming stock is sourced from this client's Procurement deliveries.
        $baseQuery = $this->procurementDeliveriesQuery();
        $query = (clone $baseQuery)
            ->whereIn('deliveries.status', ['pending', 'intransit'])
            ->orderByDesc('deliveries.created_at');

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(deliveries.shipment_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(suppliers.name) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($status = $request->input('status')) {
            $query->where('deliveries.status', $status);
        }

        $deliveries = $query->paginate(10)->appends($request->query());

        $processedShipments = StockReceiving::query()->pluck('shipment_number')->all();

        // For kpi cards
        $pendingCount = (clone $baseQuery)->whereIn('deliveries.status', ['pending', 'intransit'])->count();
        $receivedTodayCount = StockReceiving::whereDate('processed_at', today())
            ->where('status', 'approved')
            ->count();
        $rejectedCount = StockReceiving::where('status', 'rejected')->count();

        $deliveryProcessed = [];
        $warehouseNames = Warehouse::withTrashed()
            ->whereIn('name', $deliveries->pluck('destination_warehouse_id')->filter()->unique())
            ->pluck('name', 'name');

        $deliveries->getCollection()->transform(function ($delivery) use ($processedShipments, $warehouseNames) {
            $delivery->destination_warehouse_name = $warehouseNames[$delivery->destination_warehouse_id] ?? 'No warehouse assigned';
            $delivery->supplier_name = $delivery->supplier_name ?? 'Unknown supplier';

            return $delivery;
        });

        $poIds = $deliveries->pluck('purchase_order_id')->filter()->unique()->values()->all();
        $deliveryItems = Procurement::itemsForPurchaseOrders($poIds);

        foreach ($deliveries as $delivery) {
            $deliveryProcessed[$delivery->id] = in_array($delivery->shipment_number, $processedShipments, true);

            $items = $deliveryItems[$delivery->purchase_order_id] ?? [];
            $delivery->po_category = !empty($items) ? ($items[0]->categories ?? null) : null;
        }

        $deliveryItemsJson = json_encode(collect($deliveryItems)->map(function ($items) {
            return collect($items)->map(fn ($i) => [
                'name' => $i->item_name,
                'qty' => $i->qty,
                'sku' => $i->sku,
            ]);
        })->toArray());

        // Audit trail â€” past approved/rejected records
        $history = StockReceiving::with(['item', 'warehouse', 'processor'])
            ->orderByDesc('processed_at')
            ->limit(50)
            ->get();

        $historySuppliers = $this->procurementDeliveriesQuery()
            ->whereIn('deliveries.shipment_number', $history->pluck('shipment_number')->filter()->unique())
            ->pluck('supplier_name', 'deliveries.shipment_number');

        return view('inventory::stock-receiving', [
            'deliveries' => $deliveries,
            'deliveryProcessed' => $deliveryProcessed,
            'pendingCount' => $pendingCount,
            'receivedTodayCount' => $receivedTodayCount,
            'rejectedCount' => $rejectedCount,
            'history' => $history,
            'historySuppliers' => $historySuppliers,
            'filters' => $request->only(['search', 'status']),
            'activePage' => 'stock-receiving',
            'deliveryItemsJson' => $deliveryItemsJson,
        ]);
    }

    public function approve(Request $request, $deliveryId)
    {
        $delivery = $this->findDeliveryForCurrentClient((int) $deliveryId);
        $warehouse = Warehouse::query()
            ->where('name', $delivery->destination_warehouse_id)
            ->where('status', 'active')
            ->first();

        if (! $warehouse) {
            return back()->withErrors(["del_action_{$delivery->id}" => 'This purchase order has no active destination warehouse.']);
        }

        $result = $this->executeApproval($delivery, ['warehouse_id' => $warehouse->id]);

        if ($result === true) {
            return back()->with('success', 'Delivery approved and stock updated.');
        }

        return back()->withErrors(["del_action_{$delivery->id}" => $result]);
    }

    private function executeApproval(Procurement $delivery, array $validated): true|string
    {
        $clientId = (int) session('employee_client_id');
        $employeeId = session('employee_id');
        $inv = DB::connection('inventory');

        $poItems = $delivery->getPurchaseOrderItems();

        if (empty($poItems)) {
            return 'Could not fetch delivery from procurement.';
        }

        foreach ($poItems as $product) {
            $categoryName = $product->categories ?? 'Uncategorized Incoming Goods';
            $categoryId = $inv->table('categories')
                ->where('name', $categoryName)
                ->where('client_id', $clientId)
                ->value('id');

            if (!$categoryId) {
                try {
                    $categoryId = $inv->table('categories')->insertGetId([
                        'name' => $categoryName,
                        'client_id' => $clientId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // A concurrent receipt created this client's category first;
                    // re-fetch its id instead of failing the whole approval.
                    $categoryId = $inv->table('categories')
                        ->where('name', $categoryName)
                        ->where('client_id', $clientId)
                        ->value('id');
                }
            }

            $itemId = $inv->table('items')
                ->where('sku', $product->sku)
                ->where('client_id', $clientId)
                ->value('id');

            if (!$itemId) {
                $itemId = $inv->table('items')->insertGetId([
                    'sku' => $product->sku,
                    'name' => $product->item_name,
                    'category_id' => $categoryId,
                    'unit_cost' => $product->unit_price,
                    'client_id' => $clientId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($categoryId) {
                $inv->table('items')
                    ->where('id', $itemId)
                    ->update(['category_id' => $categoryId, 'updated_at' => now()]);
            }

            $stockLevel = $inv->table('stock_levels')
                ->where('item_id', $itemId)
                ->where('warehouse_id', $validated['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if ($stockLevel) {
                $alreadyProcessed = $inv->table('stock_receivings')
                    ->where('shipment_number', $delivery->shipment_number)
                    ->where('item_id', $itemId)
                    ->exists();

                if ($alreadyProcessed) {
                    continue;
                }

                $inv->table('stock_levels')
                    ->where('id', $stockLevel->id)
                    ->increment('stock', $product->qty);
            } else {
                try {
                    $inv->table('stock_levels')->insert([
                        'item_id' => $itemId,
                        'warehouse_id' => $validated['warehouse_id'],
                        'stock' => $product->qty,
                        'reorder_threshold' => 10,
                        'client_id' => $clientId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    $existing = $inv->table('stock_levels')
                        ->where('item_id', $itemId)
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->lockForUpdate()
                        ->first();

                    $inv->table('stock_levels')
                        ->where('id', $existing->id)
                        ->increment('stock', $product->qty);
                }
            }

            $inv->table('stock_movements')->insert([
                'type' => 'inbound',
                'item_id' => $itemId,
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $product->qty,
                'reference' => $delivery->shipment_number,
                'notes' => "From delivery - Shipment: {$delivery->shipment_number}",
                'performed_by' => $employeeId,
                'client_id' => $clientId,
                'created_at' => now(),
            ]);

            $inv->table('stock_receivings')->insert([
                'shipment_number' => $delivery->shipment_number,
                'item_id' => $itemId,
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $product->qty,
                'status' => 'approved',
                'processed_by' => $employeeId,
                'remarks' => $delivery->remarks,
                'client_id' => $clientId,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $inv->table('warehouses')
            ->where('id', $validated['warehouse_id'])
            ->update(['last_activity_at' => now()]);

        DB::connection('procurement')
            ->table('deliveries')
            ->where('id', $delivery->id)
            ->update(['status' => 'delivered']);

        return true;
    }

    public function reject(Request $request, $deliveryId)
    {
        $validated = $request->validate([
            'reject_reason' => 'required|string',
        ]);

        $delivery = $this->findDeliveryForCurrentClient((int) $deliveryId);

        $clientId = (int) session('employee_client_id');

        $inv = DB::connection('inventory');

        $exists = $inv->table('stock_receivings')
            ->where('shipment_number', $delivery->shipment_number)
            ->where('status', 'rejected')
            ->exists();

        if ($exists) {
            return back()->with('error', 'This delivery has already been processed.');
        }

        $inv->table('stock_receivings')->insert([
            'shipment_number' => $delivery->shipment_number,
            'item_id' => null,
            'warehouse_id' => null,
            'quantity' => $delivery->qty,
            'status' => 'rejected',
            'processed_by' => session('employee_id'),
            'remarks' => $validated['reject_reason'],
            'client_id' => $clientId,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Delivery rejected.');
    }
}
