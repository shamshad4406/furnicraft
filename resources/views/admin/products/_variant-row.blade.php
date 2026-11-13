<tr data-repeat-row>
    <td>
        @if (! empty($variant['id']))
            <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $variant['id'] }}">
        @endif
        <input type="text" name="variants[{{ $i }}][sku]" value="{{ $variant['sku'] ?? '' }}"
               class="form-control form-control-sm @error('variants.'.$i.'.sku') is-invalid @enderror"
               placeholder="SOF-001-BLU" aria-label="Variant SKU">
        @error('variants.'.$i.'.sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </td>
    <td>
        <input type="text" name="variants[{{ $i }}][size]" value="{{ $variant['size'] ?? '' }}"
               class="form-control form-control-sm @error('variants.'.$i.'.size') is-invalid @enderror"
               placeholder="3 Seater" aria-label="Variant size">
        @error('variants.'.$i.'.size') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </td>
    <td>
        <input type="text" name="variants[{{ $i }}][color]" value="{{ $variant['color'] ?? '' }}"
               class="form-control form-control-sm" placeholder="Blue" aria-label="Variant colour">
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="variants[{{ $i }}][price]" value="{{ $variant['price'] ?? '' }}"
               class="form-control form-control-sm" placeholder="Base" aria-label="Variant price">
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="variants[{{ $i }}][sale_price]"
               value="{{ $variant['sale_price'] ?? '' }}"
               class="form-control form-control-sm @error('variants.'.$i.'.sale_price') is-invalid @enderror"
               placeholder="—" aria-label="Variant sale price">
        @error('variants.'.$i.'.sale_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </td>
    <td>
        <input type="number" min="0" name="variants[{{ $i }}][stock_quantity]"
               value="{{ $variant['stock_quantity'] ?? 0 }}"
               class="form-control form-control-sm" aria-label="Variant stock">
    </td>
    <td class="text-center">
        <input type="hidden" name="variants[{{ $i }}][is_active]" value="0">
        <input type="checkbox" class="form-check-input mt-0" name="variants[{{ $i }}][is_active]" value="1"
               @checked($variant['is_active'] ?? true) aria-label="Variant active">
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove
                aria-label="Remove variant"><i class="bi bi-x-lg"></i></button>
    </td>
</tr>
