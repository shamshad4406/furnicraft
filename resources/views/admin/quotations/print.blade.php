{{--
    The printable quotation. Its own document, laid out for A4, carrying only
    its own styles — the same approach as the tax invoice, so a copy printed
    a year from now looks the way it does today.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Quotation {{ $quotation->number }} — {{ settings('company_name', 'FurniCraft') }}</title>

    <style>
        :root { --ink: #16263f; --muted: #64748b; --line: #d8dee7; --gold: #e0a83e; }

        * { box-sizing: border-box; }

        body {
            margin: 0; padding: 24px; background: #eef1f5;
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px; color: var(--ink); line-height: 1.5;
        }

        .sheet {
            width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm;
            background: #fff; box-shadow: 0 2px 16px rgba(0, 0, 0, .12);
        }

        .toolbar { width: 210mm; margin: 0 auto 12px; display: flex; justify-content: space-between; gap: 8px; }
        .toolbar a, .toolbar button {
            font: inherit; padding: 7px 14px; border-radius: 5px;
            border: 1px solid var(--line); background: #fff; color: var(--ink);
            text-decoration: none; cursor: pointer;
        }
        .toolbar button { background: var(--ink); color: #fff; border-color: var(--ink); }

        header { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid var(--ink); padding-bottom: 12px; }
        header h1 { margin: 0 0 2px; font-size: 20px; }

        .muted { color: var(--muted); }
        .right { text-align: right; }
        .doc-title { font-size: 15px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); margin-bottom: 3px; }

        .parties { display: flex; gap: 20px; margin: 16px 0; }
        .parties > div { flex: 1; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 6px 7px; border: 1px solid var(--line); }
        th { background: #f4f6f9; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }

        .totals { margin-top: 12px; display: flex; justify-content: flex-end; }
        .totals table { width: 280px; }
        .grand td { background: #f4f6f9; font-size: 13px; font-weight: 700; }

        .validity {
            margin-top: 14px; padding: 9px 12px;
            background: #fdf7ea; border-left: 3px solid var(--gold);
            font-size: 11px;
        }

        .words { margin-top: 10px; font-size: 11px; }

        footer { margin-top: 22px; display: flex; justify-content: space-between; gap: 20px; border-top: 1px solid var(--line); padding-top: 10px; }
        .terms { flex: 1; font-size: 10px; color: var(--muted); white-space: pre-line; }
        .sign { width: 190px; text-align: center; font-size: 10px; }
        .sign .line { margin-top: 42px; border-top: 1px solid var(--line); padding-top: 4px; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .toolbar { display: none; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ $backUrl }}">&larr; Back to the quotation</a>
    <button type="button" data-print>Print / save as PDF</button>
</div>

<div class="sheet">
    <header>
        <div>
            <h1>{{ settings('gst_legal_name') ?: settings('company_name', 'FurniCraft') }}</h1>
            <div class="muted" style="white-space: pre-line">{{ settings('company_address') }}</div>
            <div class="muted">
                {{ settings('company_phone') }}
                @if (settings('company_email')) · {{ settings('company_email') }} @endif
            </div>
            @if (settings('gst_number'))
                <div style="margin-top:4px"><strong>GSTIN:</strong> {{ settings('gst_number') }}</div>
            @endif
        </div>

        <div class="right">
            <div class="doc-title">Quotation</div>
            <div style="margin-top:6px"><strong>{{ $quotation->number }}</strong></div>
            <div class="muted">Dated {{ $quotation->created_at->format('d M Y') }}</div>
            @if ($quotation->enquiry)
                <div class="muted">Enquiry {{ $quotation->enquiry->reference }}</div>
            @endif
        </div>
    </header>

    <div class="parties">
        <div>
            <div class="label">Quotation for</div>
            <strong>{{ $quotation->customer_name }}</strong><br>
            {{ $quotation->customer_email }}<br>
            {{ $quotation->customer_phone }}
            @if ($quotation->gst_number)
                <br><strong>GSTIN:</strong> {{ $quotation->gst_number }}
            @endif
        </div>

        <div>
            <div class="label">Delivery to</div>
            @if (filled($quotation->shipping_address))
                {{ collect([
                    $quotation->shipping_address['line1'] ?? null,
                    $quotation->shipping_address['line2'] ?? null,
                ])->filter()->implode(', ') }}<br>
                {{ $quotation->shipping_address['city'] ?? '' }},
                {{ $quotation->shipping_address['state'] ?? '' }}
                — {{ $quotation->shipping_address['pincode'] ?? '' }}
            @else
                <span class="muted">To be confirmed</span>
            @endif
        </div>

        <div>
            <div class="label">Place of supply</div>
            {{ $quotation->place_of_supply ?: '—' }}
            @if ($quotation->place_of_supply_code) ({{ $quotation->place_of_supply_code }}) @endif
            <div class="muted" style="margin-top:6px">
                {{ $quotation->is_interstate ? 'Inter-state — IGST' : 'Intra-state — CGST + SGST' }}
            </div>
            @if ($quotation->delivery_days)
                <div class="label" style="margin-top:10px">Delivery</div>
                About {{ $quotation->delivery_days }} days from confirmation
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:26px">#</th>
                <th>Description</th>
                <th style="width:62px">HSN</th>
                <th class="num" style="width:42px">Qty</th>
                <th class="num" style="width:84px">Rate</th>
                <th class="num" style="width:50px">GST</th>
                <th class="num" style="width:94px">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->name }}
                        @if ($item->description)
                            <div class="muted">{{ $item->description }}</div>
                        @endif
                        @if ($item->sku)
                            <div class="muted">SKU {{ $item->sku }}</div>
                        @endif
                    </td>
                    <td>{{ $item->hsn_code ?: '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ indian_number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    <td class="num">{{ indian_number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($taxSummary->isNotEmpty())
        <table style="margin-top:12px">
            <thead>
                <tr>
                    <th>Tax rate</th>
                    <th class="num">Taxable value</th>
                    @if ($quotation->is_interstate)
                        <th class="num">IGST</th>
                    @else
                        <th class="num">CGST</th>
                        <th class="num">SGST</th>
                    @endif
                    <th class="num">Total tax</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($taxSummary as $row)
                    <tr>
                        <td>{{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}%</td>
                        <td class="num">{{ indian_number_format($row['taxable'], 2) }}</td>
                        @if ($quotation->is_interstate)
                            <td class="num">{{ indian_number_format($row['igst'], 2) }}</td>
                        @else
                            <td class="num">{{ indian_number_format($row['cgst'], 2) }}</td>
                            <td class="num">{{ indian_number_format($row['sgst'], 2) }}</td>
                        @endif
                        <td class="num">{{ indian_number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals">
        <table>
            <tbody>
                <tr><td>Subtotal</td><td class="num">{{ indian_number_format((float) $quotation->subtotal, 2) }}</td></tr>
                @if ($quotation->discount > 0)
                    <tr><td>Discount</td><td class="num">− {{ indian_number_format((float) $quotation->discount, 2) }}</td></tr>
                @endif
                <tr>
                    <td>Delivery</td>
                    <td class="num">{{ $quotation->delivery_charge > 0 ? indian_number_format((float) $quotation->delivery_charge, 2) : 'Free' }}</td>
                </tr>
                <tr class="grand"><td>Total</td><td class="num">{{ money($quotation->grand_total) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="words">
        <span class="muted">Amount in words:</span>
        <strong>{{ amount_in_words((float) $quotation->grand_total) }}</strong>
    </div>

    @if ($quotation->tax_total > 0)
        <p class="muted" style="font-size:11px; margin:6px 0 0">
            All prices are inclusive of {{ money($quotation->tax_total) }} GST.
        </p>
    @endif

    @if ($quotation->valid_until)
        <div class="validity">
            <strong>This quotation holds until {{ $quotation->valid_until->format('D, d M Y') }}.</strong>
            After that date prices may change and we would be glad to quote again.
        </div>
    @endif

    <footer>
        <div class="terms">{{ $quotation->terms ?: settings('quotation_terms') }}</div>
        <div class="sign">
            For {{ settings('gst_legal_name') ?: settings('company_name', 'FurniCraft') }}
            <div class="line">Authorised signatory</div>
        </div>
    </footer>

    <p class="muted" style="text-align:center; font-size:10px; margin-top:14px">
        This is a quotation, not an invoice. Nothing is charged until an order is confirmed.
    </p>
</div>

<script src="{{ asset_v('assets/js/invoice.js') }}"></script>
</body>
</html>
