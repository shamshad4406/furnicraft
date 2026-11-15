{{--
    The date-range picker, shared by every report.

    $period, $periods, $from, $to, $action
--}}
<form method="GET" action="{{ $action }}" class="row g-2 p-3 border-bottom bg-light">
    <div class="col-md-3">
        <label for="period" class="visually-hidden">Period</label>
        <select id="period" name="period" class="form-select form-select-sm">
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label for="from" class="visually-hidden">From</label>
        <input type="date" id="from" name="from" value="{{ request('from', $from->toDateString()) }}"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-3">
        <label for="to" class="visually-hidden">To</label>
        <input type="date" id="to" name="to" value="{{ request('to', $to->toDateString()) }}"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-3 d-flex gap-1">
        <button class="btn btn-sm btn-primary flex-grow-1">Show</button>
        <a href="{{ $action }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>

    <div class="col-12">
        <span class="small text-muted">
            Dates only apply when "Pick dates" is chosen.
            Showing <strong>{{ $from->format('d M Y') }}</strong> to <strong>{{ $to->format('d M Y') }}</strong>.
        </span>
    </div>
</form>
