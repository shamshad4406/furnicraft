@props([
    'headers' => [],
    'empty' => 'Nothing to show yet.',
    'emptyIcon' => 'bi-inbox',
    'rows' => null,
])

<div class="table-responsive">
    <table {{ $attributes->merge(['class' => 'ad-table']) }}>
        @if (count($headers))
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th @if (is_array($header) && ($header['end'] ?? false)) class="text-end" @endif>
                            {{ is_array($header) ? $header['label'] : $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>

@if ($rows !== null && $rows->isEmpty())
    <div class="ad-empty">
        <i class="bi {{ $emptyIcon }}"></i>
        {{ $empty }}
    </div>
@endif

@if ($rows !== null && method_exists($rows, 'hasPages') && $rows->hasPages())
    <div class="ad-card__footer">
        {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
