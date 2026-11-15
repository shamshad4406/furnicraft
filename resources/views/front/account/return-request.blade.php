@extends('front.layouts.app')

@section('title', 'Return from order '.$order->order_number)
@section('robots', 'noindex, nofollow')

@section('content')

<section class="fc-page-hero">
    <div class="container-fc">
        <h1>Send something back</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('account.orders') }}">My Orders</a></li>
                <li class="breadcrumb-item"><a href="{{ route('account.order', $order->public_token) }}">{{ $order->order_number }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Return</li>
            </ol>
        </nav>
    </div>
</section>

<section class="fc-section">
    <div class="container-fc">
        <div class="row g-4">
            <div class="col-lg-3">@include('front.account._nav')</div>

            <div class="col-lg-9">
                <div class="fc-panel">
                    <h2 class="fc-panel__title">Order {{ $order->order_number }}</h2>

                    <p class="small text-muted">
                        Delivered {{ $order->delivered_at->format('d M Y') }}.
                        You can raise a return until
                        <strong>{{ $order->returnWindowEndsOn()->format('d M Y') }}</strong>.
                    </p>

                    <form method="POST" action="{{ route('account.order.return.store', $order->public_token) }}" novalidate>
                        @csrf

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Bought</th>
                                        <th class="text-end" style="width:120px">Returning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $itemId => $row)
                                        <tr @class(['text-muted' => $row['available'] === 0])>
                                            <td>
                                                <div class="small fw-semibold">{{ $row['item']->name }}</div>
                                                @if ($row['item']->variant_label)
                                                    <div class="small text-muted">{{ $row['item']->variant_label }}</div>
                                                @endif
                                                @if ($row['claimed'] > 0)
                                                    <div class="small text-muted">
                                                        {{ $row['claimed'] }} already in a return
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end small">{{ $row['ordered'] }}</td>
                                            <td>
                                                @if ($row['available'] > 0)
                                                    <input type="number" class="form-control form-control-sm text-end"
                                                           name="quantities[{{ $itemId }}]"
                                                           value="{{ old('quantities.'.$itemId, 0) }}"
                                                           min="0" max="{{ $row['available'] }}"
                                                           aria-label="Quantity returning of {{ $row['item']->name }}">
                                                @else
                                                    <span class="small">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @error('quantities') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="type" class="form-label">What would you like?</label>
                                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="reason" class="form-label">What went wrong? <span class="text-danger">*</span></label>
                                <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                                    <option value="">Choose a reason</option>
                                    @foreach ($reasons as $value => $label)
                                        <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="details" class="form-label">Anything else we should know?</label>
                                <textarea id="details" name="details" rows="3" maxlength="2000"
                                          class="form-control @error('details') is-invalid @enderror"
                                          placeholder="The more you tell us, the faster we can sort it out">{{ old('details') }}</textarea>
                                @error('details') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-navy">Send the request</button>
                            <a href="{{ route('account.order', $order->public_token) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>

                    @if (settings('return_policy_note'))
                        <hr>
                        <p class="small text-muted mb-0">{{ settings('return_policy_note') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
