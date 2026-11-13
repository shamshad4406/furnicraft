@extends('admin.layouts.app')

@section('title', 'Bulk Import')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Products</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bulk Import</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Import products from CSV" icon="bi-upload">
            <form method="POST" action="{{ route('admin.products.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="file" class="form-label required">CSV file</label>
                    <input type="file" id="file" name="file" accept=".csv,text/csv" required
                           class="form-control @error('file') is-invalid @enderror">
                    @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">
                        Up to {{ number_format($maxRows) }} rows, 10 MB. In Excel use
                        <em>Save As → CSV UTF-8</em>.
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="update_existing" id="update_existing"
                           value="1" @checked(old('update_existing', true))>
                    <label class="form-check-label small" for="update_existing">
                        Update existing products when the SKU already exists
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-upload me-1"></i> Upload &amp; Import
                </button>
            </form>

            <x-slot:footer>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.products.import.template') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Download template
                    </a>
                    <a href="{{ route('admin.products.export') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i> Export current catalog
                    </a>
                </div>
            </x-slot:footer>
        </x-admin.card>

        <x-admin.card title="Columns" icon="bi-list-ul" class="mt-3">
            <p class="small text-muted">
                Column order does not matter — the header row is matched by name.
                Required columns are marked.
            </p>
            <div class="d-flex flex-wrap gap-1">
                @foreach ($columns as $column)
                    <span class="badge {{ in_array($column, $required, true) ? 'text-bg-warning' : 'text-bg-light border' }}">
                        {{ $column }}@if (in_array($column, $required, true))*@endif
                    </span>
                @endforeach
            </div>
            <ul class="small text-muted mt-3 mb-0 ps-3">
                <li><code>category</code> and <code>brand</code> match on name or slug and must already exist.</li>
                <li><code>status</code> is draft, active or inactive. Blank means draft.</li>
                <li>Yes/no columns accept <code>yes</code>, <code>no</code>, <code>1</code> or <code>0</code>.</li>
                <li>Valid rows are imported even when other rows fail.</li>
            </ul>
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        @if ($report)
            <x-admin.card title="Import report" icon="bi-clipboard-check" :flush="true">
                <div class="row g-0 text-center border-bottom">
                    <div class="col-3 p-3">
                        <div class="fs-4 fw-bold text-success">{{ $report['imported'] }}</div>
                        <div class="text-muted" style="font-size:.76rem">Created</div>
                    </div>
                    <div class="col-3 p-3 border-start">
                        <div class="fs-4 fw-bold text-primary">{{ $report['updated'] }}</div>
                        <div class="text-muted" style="font-size:.76rem">Updated</div>
                    </div>
                    <div class="col-3 p-3 border-start">
                        <div class="fs-4 fw-bold {{ $report['failed'] ? 'text-danger' : 'text-muted' }}">{{ $report['failed'] }}</div>
                        <div class="text-muted" style="font-size:.76rem">Rejected</div>
                    </div>
                    <div class="col-3 p-3 border-start">
                        <div class="fs-4 fw-bold">{{ $report['total'] }}</div>
                        <div class="text-muted" style="font-size:.76rem">Rows read</div>
                    </div>
                </div>

                @if (empty($report['errors']))
                    <div class="ad-empty">
                        <i class="bi bi-check-circle text-success"></i>
                        Every row imported cleanly.
                    </div>
                @else
                    <x-admin.datatable :headers="['Line', 'SKU', 'Problem']">
                        @foreach ($report['errors'] as $error)
                            <tr>
                                <td class="text-muted">{{ $error['line'] }}</td>
                                <td><code>{{ $error['sku'] ?: '—' }}</code></td>
                                <td>
                                    <ul class="mb-0 ps-3 small text-danger">
                                        @foreach ($error['messages'] as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </x-admin.datatable>
                @endif
            </x-admin.card>
        @else
            <x-admin.card title="How it works" icon="bi-info-circle">
                <ol class="small text-muted mb-0 ps-3">
                    <li class="mb-2">Download the template, or export the current catalog to edit what is already there.</li>
                    <li class="mb-2">Fill in one product per row. Categories and brands must exist already — create them under Catalog first.</li>
                    <li class="mb-2">Upload the file. Each row is validated before anything is written.</li>
                    <li class="mb-2">Rows that pass are imported; rows that fail are listed here with the line number and the reason, so you can fix just those and upload again.</li>
                    <li>Images and variants are not part of the CSV — add those on the product screen.</li>
                </ol>
            </x-admin.card>
        @endif
    </div>
</div>

@endsection
