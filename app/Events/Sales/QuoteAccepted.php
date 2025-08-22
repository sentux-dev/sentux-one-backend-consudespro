<?php
namespace App\Events\Sales;
use App\Models\Sales\Quote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteAccepted {
    use Dispatchable, SerializesModels;
    public Quote $quote;
    public function __construct(Quote $quote) { $this->quote = $quote; }
}