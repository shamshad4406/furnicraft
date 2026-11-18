{{--
    The tax invoice. Its own document, not the admin layout: this is what gets
    printed or saved as PDF, so it carries only its own styles and is laid out
    for A4. The browser's print dialog is the download button.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice {{ $order->invoice_number }} — {{ settings('company_name', 'FurniCraft') }}</title>

    <style>
        /* Self-contained: an invoice must print identically years later,
           whatever has happened to the site's stylesheets since. */
        :root {
            --ink: #16263f;
            --muted: #64748b;
            --line: #d8dee7;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #eef1f5;
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: var(--ink);
            line-height: 1.5;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 14mm;
            background: #fff;
            box-shadow: 0 2px 16px rgba(0, 0, 0, .12);
        }

        .toolbar {
            width: 210mm;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .toolbar a, .toolbar button {
            font: inherit;
            padding: 7px 14px;
            border-radius: 5px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button { background: var(--ink); color: #fff; border-color: var(--ink); }

        header { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid var(--ink); padding-bottom: 12px; }
        header h1 { margin: 0 0 2px; font-size: 20px; letter-spacing: .02em; }
        .muted { color: var(--muted); }
        .right { text-align: right; }

        .doc-title { font-size: 15px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

        .parties { display: flex; gap: 20px; margin: 16px 0; }
        .parties > div { flex: 1; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); margin-bottom: 3px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 6px 7px; border: 1px solid var(--line); }
        th { background: #f4f6f9; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: 600; }

        .totals { margin-top: 12px; display: flex; justify-content: flex-end; }
        .totals table { width: 280px; }
        .grand td { background: #f4f6f9; font-size: 13px; font-weight: 700; }

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
    <a href="{{ route('admin.orders.show', $order) }}">&larr; Back to the order</a>
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
            @if ($order->seller_gst_number)
                <div style="margin-top:4px"><strong>GSTIN:</strong> {{ $order->seller_gst_number }}</div>
            @endif
            @if (settings('gst_pan'))
                <div><strong>PAN:</strong> {{ settings('gst_pan') }}</div>
            @endif
        </div>

        <div class="right">
            <div class="doc-title">Tax Invoice</div>
            <div style="margin-top:6px"><strong>{{ $order->invoice_number }}</strong></div>
            <div class="muted">Dated {{ $order->invoiced_at->format('d M Y') }}</div>
            <div class="muted">Order {{ $order->order_number }}</div>
            <div class="muted">Placed {{ ($order->placed_at ?? $order->created_at)->format('d M Y') }}</div>
        </div>
    </header>

    <div class="parties">
        <div>
            <div class="label">Billed to</div>
            <strong>{{ $order->billing_address['name'] ?? $order->customer_name }}</strong><br>
            {{ collect([
                $order->billing_address['line1'] ?? null,
                $order->billing_address['line2'] ?? null,
                $order->billing_address['landmark'] ?? null,
            ])->filter()->implode(', ') }}<br>
            {{ $order->billing_address['city'] ?? '' }}, {{ $order->billing_address['state'] ?? '' }}
            — {{ $order->billing_address['pincode'] ?? '' }}<br>
            {{ $order->billing_address['phone'] ?? $order->customer_phone }}
            @if ($order->gst_number)
                <br><strong>GSTIN:</strong> {{ $order->gst_number }}
            @endif
        </div>

        <div>
            <div class="label">Shipped to</div>
            <strong>{{ $order->shipping_address['name'] }}</strong><br>
            {{ collect([
                $order->shipping_address['line1'] ?? null,
                $order->shipping_address['line2'] ?? null,
                $order->shipping_address['landmark'] ?? null,
            ])->filter()->implode(', ') }}<br>
            {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }}
            — {{ $order->shipping_address['pincode'] }}<br>
            {{ $order->shipping_address['phone'] }}
        </div>

        <div>
            <div class="label">Place of supply</div>
            {{ $order->place_of_supply ?: '—' }}
            @if ($order->place_of_supply_code) ({{ $order->place_of_supply_code }}) @endif
            <div class="muted" style="margin-top:6px">
                {{ $order->is_interstate ? 'Inter-state supply — IGST' : 'Intra-state supply — CGST + SGST' }}
            </div>
            <div class="label" style="margin-top:10px">Payment</div>
            {{ $order->payment_method === 'cod' ? 'Cash on delivery' : 'Online' }}
            <div class="muted">{{ $order->payment_label }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:26px">#</th>
                <th>Description</th>
                <th style="width:62px">HSN</th>
                <th class="num" style="width:42px">Qty</th>
                <th class="num" style="width:74px">Rate</th>
                <th class="num" style="width:78px">Taxable</th>
                <th class="num" style="width:44px">GST</th>
                <th class="num" style="width:74px">Tax</th>
                <th class="num" style="width:84px">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $index => $item)
                @php
                    // Catalogue prices include GST, so the taxable value is the
                    // line less the tax it already contains.
                    $taxable = round((float) $item->line_subtotal - (float) $item->tax_amount, 2);
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->name }}
                        @if ($item->variant_label)
                            <div class="muted">{{ $item->variant_label }}</div>
                        @endif
                        <div class="muted">SKU {{ $item->sku }}</div>
                    </td>
                    <td>{{ $item->hsn_code ?: '—' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ indian_number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ indian_number_format($taxable, 2) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.') }}%</td>
                    <td class="num">{{ indian_number_format((float) $item->tax_amount, 2) }}</td>
                    <td class="num">{{ indian_number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- GST has to be summarised per rate, not per product. --}}
    <table style="margin-top:12px">
        <thead>
            <tr>
                <th>Tax rate</th>
                <th class="num">Taxable value</th>
                @if ($order->is_interstate)
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
                    <td class="num">{{ indian_number_format($row["taxable"], 2) }}</td>
                    @if ($order->is_interstate)
                        <td class="num">{{ indian_number_format($row["igst"], 2) }}</td>
                    @else
                        <td class="num">{{ indian_number_format($row["cgst"], 2) }}</td>
                        <td class="num">{{ indian_number_format($row["sgst"], 2) }}</td>
                    @endif
                    <td class="num">{{ indian_number_format($row["total"], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ indian_number_format($taxTotals["taxable"], 2) }}</td>
                @if ($order->is_interstate)
                    <td class="num">{{ indian_number_format($taxTotals["igst"], 2) }}</td>
                @else
                    <td class="num">{{ indian_number_format($taxTotals["cgst"], 2) }}</td>
                    <td class="num">{{ indian_number_format($taxTotals["sgst"], 2) }}</td>
                @endif
                <td class="num">{{ indian_number_format($taxTotals["tax"], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals">
        <table>
            <tbody>
                <tr>
                    <td>Taxable value</td>
                    <td class="num">{{ indian_number_format($taxTotals["taxable"], 2) }}</td>
                </tr>
                @if ($order->is_interstate)
                    <tr><td>IGST</td><td class="num">{{ indian_number_format($taxTotals["igst"], 2) }}</td></tr>
                @else
                    <tr><td>CGST</td><td class="num">{{ indian_number_format($taxTotals["cgst"], 2) }}</td></tr>
                    <tr><td>SGST</td><td class="num">{{ indian_number_format($taxTotals["sgst"], 2) }}</td></tr>
                @endif
                @if ($order->discount > 0)
                    <tr>
                        <td>Discount @if ($order->coupon_code) ({{ $order->coupon_code }}) @endif</td>
                        <td class="num">− {{ indian_number_format((float) $order->discount, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td>Delivery</td>
                    <td class="num">{{ $order->delivery_charge > 0 ? indian_number_format((float) $order->delivery_charge, 2) : 'Free' }}</td>
                </tr>
                <tr class="grand">
                    <td>Total</td>
                    <td class="num">{{ money($order->grand_total) }}</td>
                </tr>
                @if ($order->amount_due > 0)
                    <tr>
                        <td>Paid</td>
                        <td class="num">{{ indian_number_format((float) $order->amount_paid, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Payable on delivery</td>
                        <td class="num">{{ money($order->amount_due) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="words">
        <span class="muted">Amount in words:</span>
        <strong>{{ amount_in_words((float) $order->grand_total) }}</strong>
    </div>

    <footer>
        <div class="terms">{{ settings('invoice_terms') }}</div>
        <div class="sign">
            For {{ settings('gst_legal_name') ?: settings('company_name', 'FurniCraft') }}
            <div class="line">Authorised signatory</div>
        </div>
    </footer>

    <p class="muted" style="text-align:center; font-size:10px; margin-top:14px">
        This is a computer-generated invoice and is valid without a signature.
    </p>
</div>

<script src="{{ asset_v('assets/js/invoice.js') }}"></script>
</body>
</html>
