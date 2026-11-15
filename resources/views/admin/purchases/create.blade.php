@extends('admin.layouts.app')

@section('title', 'New purchase order')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.purchases.index') }}">Purchases</a></li>
    <li class="breadcrumb-item active" aria-current="page">New</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-xl-7">
        <x-admin.card icon="bi-cart-plus" title="New purchase order"
                      subtitle="Lines are added once it exists">

            @if ($suppliers->isEmpty())
                <div class="alert alert-warning small mb-0">
                    There are no active suppliers yet.
                    <a href="{{ route('admin.suppliers.create') }}">Add one first</a>.
                </div>
            @else
                <form method="POST" action="{{ route('admin.purchases.store') }}">
                    @csrf

                    <div class="row g-2">
                        <div class="col-md-7">
                            <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select id="supplier_id" name="supplier_id" required
                                    class="form-select form-select-sm @error('supplier_id') is-invalid @enderror">
                                <option value="">Choose a supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $selected) == $supplier->id)>
                                        {{ $supplier->name }} ({{ $supplier->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-5">
                            <label for="expected_on" class="form-label">Expected</label>
                            <input type="date" id="expected_on" name="expected_on" value="{{ old('expected_on') }}"
                                   class="form-control form-control-sm @error('expected_on') is-invalid @enderror">
                            <div class="form-text" style="font-size:.72rem">
                                Their usual lead time is shown next to each supplier's name on their page.
                            </div>
                            @error('expected_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                      class="form-control form-control-sm">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <button class="btn btn-sm btn-primary mt-3">Create and add lines</button>
                    <a href="{{ route('admin.purchases.index') }}" class="btn btn-sm btn-outline-secondary mt-3">Cancel</a>
                </form>
            @endif
        </x-admin.card>
    </div>
</div>

@endsection
