<?php
namespace App\Listeners\Sales;
use App\Events\Sales\QuoteAccepted;
use App\Services\Inventory\InventoryService;

class DeductStockFromQuoteListener {
    protected InventoryService $inventoryService;
    public function __construct(InventoryService $inventoryService) { $this->inventoryService = $inventoryService; }
    public function handle(QuoteAccepted $event): void {
        $this->inventoryService->deductStockForQuote($event->quote);
    }
}