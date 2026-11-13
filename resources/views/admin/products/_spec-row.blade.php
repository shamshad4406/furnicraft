<div class="row g-2 mb-2 align-items-start" data-repeat-row>
    <div class="col-md-4">
        <input type="text" name="specifications[{{ $i }}][name]" value="{{ $spec['name'] ?? '' }}"
               class="form-control form-control-sm @error('specifications.'.$i.'.name') is-invalid @enderror"
               placeholder="Seating Capacity" aria-label="Specification name">
        @error('specifications.'.$i.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-7">
        <input type="text" name="specifications[{{ $i }}][value]" value="{{ $spec['value'] ?? '' }}"
               class="form-control form-control-sm @error('specifications.'.$i.'.value') is-invalid @enderror"
               placeholder="3 Seater" aria-label="Specification value">
        @error('specifications.'.$i.'.value') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-1 d-grid">
        <button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove
                aria-label="Remove specification"><i class="bi bi-x-lg"></i></button>
    </div>
</div>
