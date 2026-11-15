{{--
    One editable quotation line.

    $item (nullable), $index — an integer for a saved line, or the literal
    __INDEX__ inside the <template> the line editor clones, which it
    renumbers as soon as the row is in the page.
--}}
<tr>
    <td>
        {{-- Picking a product fills the rest of the row in. Left empty for
             something being made to order. --}}
        <select class="form-select form-select-sm" data-product-picker aria-label="Pick a catalogue product">
            <option value="">Made to order</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected($item?->product_id === $product->id)>
                    {{ Str::limit($product->name, 34) }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item?->product_id }}">
        <input type="hidden" name="items[{{ $index }}][sku]" value="{{ $item?->sku }}">
        <input type="hidden" name="items[{{ $index }}][hsn_code]" value="{{ $item?->hsn_code }}">
    </td>

    <td>
        <input type="text" name="items[{{ $index }}][name]" required maxlength="255"
               value="{{ $item?->name }}" class="form-control form-control-sm mb-1"
               placeholder="What it is" aria-label="Item name">
        <input type="text" name="items[{{ $index }}][description]" maxlength="1000"
               value="{{ $item?->description }}" class="form-control form-control-sm"
               placeholder="Sizes, finish, anything worth writing down" aria-label="Item description">
    </td>

    <td>
        <input type="number" name="items[{{ $index }}][quantity]" required min="1" max="100000"
               value="{{ $item?->quantity ?? 1 }}" class="form-control form-control-sm text-end"
               data-qty aria-label="Quantity">
    </td>

    <td>
        <input type="number" name="items[{{ $index }}][unit_price]" required step="0.01" min="0"
               value="{{ $item ? (float) $item->unit_price : '' }}"
               class="form-control form-control-sm text-end" data-price aria-label="Rate">
    </td>

    <td>
        <input type="number" name="items[{{ $index }}][gst_rate]" required step="0.01" min="0" max="100"
               value="{{ $item ? (float) $item->gst_rate : (float) settings('gst_default_rate', 18) }}"
               class="form-control form-control-sm text-end" data-rate aria-label="GST rate">
    </td>

    <td class="text-end align-middle fw-semibold" data-line-total>
        {{ $item ? number_format((float) $item->line_total, 2) : '0.00' }}
    </td>

    <td class="text-end align-middle">
        <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-line
                aria-label="Remove this line"><i class="bi bi-x-lg"></i></button>
    </td>
</tr>
