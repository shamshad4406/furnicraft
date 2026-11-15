<div class="row g-3">
    <x-admin.input name="low_stock_threshold" label="Warn me at or below" type="number" min="0" max="999999"
                   :value="settings('low_stock_threshold')" required
                   help="The shop-wide reorder level. A product can override it with its own on the inventory screen." />

    <x-admin.input name="stock_alert_email" label="Send low-stock warnings to" type="email"
                   :value="settings('stock_alert_email')"
                   help="Left blank, nothing is emailed — the low-stock list is still on the inventory screen." />
</div>

<hr class="my-4">

<h3 class="h6 fw-bold mb-3" style="color:var(--fc-navy)">Where stock stands</h3>

@php
    $lowCount = \App\Models\Product::lowStock()->count();
    $unpriced = \App\Models\Product::whereNull('cost_price')->where('status', 'active')->count();
    $ledgerRows = \App\Models\StockMovement::count();
@endphp

<div class="row g-3 small">
    <div class="col-md-4">
        <span class="text-muted d-block">At or below the reorder level</span>
        <strong class="{{ $lowCount > 0 ? 'text-warning' : 'text-success' }}">
            <a href="{{ route('admin.inventory.index', ['flag' => 'low']) }}">{{ $lowCount }} product(s)</a>
        </strong>
    </div>

    <div class="col-md-4">
        <span class="text-muted d-block">Without a cost price</span>
        <strong class="{{ $unpriced > 0 ? 'text-warning' : 'text-success' }}">
            <a href="{{ route('admin.inventory.index', ['flag' => 'uncosted']) }}">{{ $unpriced }} product(s)</a>
        </strong>
        <div class="text-muted" style="font-size:.72rem">They count as nothing in the stock valuation.</div>
    </div>

    <div class="col-md-4">
        <span class="text-muted d-block">Ledger entries</span>
        <strong>{{ number_format($ledgerRows) }}</strong>
        @if ($ledgerRows === 0)
            <div class="text-danger" style="font-size:.72rem">
                Run <code>php artisan inventory:open-balances</code> so the ledger starts from what
                is actually on the shelf.
            </div>
        @endif
    </div>
</div>
