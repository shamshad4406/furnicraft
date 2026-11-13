@php
    $preview = str_replace(
        ['{PREFIX}', '{YY}', '{MM}', '{YYYY}', '{SEQ}'],
        [
            settings('order_prefix', 'FC'),
            now()->format('y'),
            now()->format('m'),
            now()->format('Y'),
            str_pad((string) settings('order_next_sequence', 1), (int) settings('order_sequence_padding', 5), '0', STR_PAD_LEFT),
        ],
        (string) settings('order_number_format', '{PREFIX}/{YY}{MM}/{SEQ}')
    );
@endphp

<div class="alert alert-light border small d-flex gap-2">
    <i class="bi bi-hash text-primary"></i>
    <div>
        Next order number will look like <strong>{{ $preview }}</strong>.
        Placeholders: <code>{PREFIX}</code> <code>{YY}</code> <code>{YYYY}</code> <code>{MM}</code> <code>{SEQ}</code>.
        Order numbers start being issued in Phase 4.
    </div>
</div>

<div class="row g-3">
    <x-admin.input name="order_prefix" label="Order prefix" :value="settings('order_prefix')" required
                   help="Capital letters and digits only." />
    <x-admin.input name="order_number_format" label="Order number format" :value="settings('order_number_format')" required />
    <x-admin.input name="order_sequence_padding" label="Sequence length" type="number" min="1" max="10"
                   :value="settings('order_sequence_padding')" required help="5 gives 00001." />
    <x-admin.input name="order_next_sequence" label="Next sequence number" type="number" min="1"
                   :value="settings('order_next_sequence')" required />
    <x-admin.input name="invoice_prefix" label="Invoice prefix" :value="settings('invoice_prefix')" required />
    <x-admin.input name="quotation_prefix" label="Quotation prefix" :value="settings('quotation_prefix')" required />
</div>
