<?php
namespace App\Services\Inventory;
use App\Models\Sales\Quote;

class InventoryService {
    public function deductStockForQuote(Quote $quote): void {
        foreach ($quote->items as $item) {
            if ($item->product && $item->product->track_inventory) {
                $item->product->inventoryMovements()->create([
                    'quantity_change' => -$item->quantity,
                    'reason' => "Venta - Cotización #{$quote->quote_number}",
                    'sourceable_id' => $quote->id,
                    'sourceable_type' => Quote::class,
                ]);
                // Actualizamos el stock total en el producto
                $item->product->decrement('stock_quantity', $item->quantity);
            }
        }
    }
}