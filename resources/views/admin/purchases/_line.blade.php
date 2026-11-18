{{--
    One editable purchase order line.

    $item (nullable), $index — an integer for a saved line, or __INDEX__
    inside the <template> the editor clones and renumbers.
--}}
<tr>
    <td>
        {{-- A line must name a catalogue product to be stockable when it
             arrives; "something else" is for freight, packing and the like. --}}
        <select class="form-select form-select-sm" data-product-picker aria-label="Pick a product">
            <option value="">Something else</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected($item?->product_id === $product->id)>
                    {{ Str::limit($product->name, 38) }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item?->product_id }}">
        <input type="hidden" name="items[{{ $index }}][sku]" value="{{ $item?->sku }}">
    </td>

    <td>
        <input type="text" name="items[{{ $index }}][name]" required maxlength="255"
               value="{{ $item?->name }}" class="form-control form-control-sm"
               placeholder="What you are buying" aria-label="Line description">
    </td>

    <td>
        <input type="number" name="items[{{ $index }}][quantity]" required min="1" max="100000"
               value="{{ $item?->quantity ?? 1 }}" class="form-control form-control-sm text-end"
               data-qty aria-label="Quantity">
    </td>

    <td>
        <input type="number" name="items[{{ $index }}][unit_cost]" required step="0.01" min="0"
               value="{{ $item ? (float) $item->unit_cost : '' }}"
               class="form-control form-control-sm text-end" data-cost aria-label="Unit cost">
    </td>

    <td>
        <input type="number" name="items[{{ $index }}][gst_rate]" required step="0.01" min="0" max="100"
               value="{{ $item ? (float) $item->gst_rate : (float) settings('gst_default_rate', 18) }}"
               class="form-control form-control-sm text-end" data-rate aria-label="GST rate">
    </td>

    <td class="text-end align-middle fw-semibold" data-line-total>
        {{ $item ? indian_number_format((float) $item->line_total, 2) : '0.00' }}
    </td>

    <td class="text-end align-middle">
        <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-line
                aria-label="Remove this line"><i class="bi bi-x-lg"></i></button>
    </td>
</tr>
