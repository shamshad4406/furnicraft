@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'actions' => null,
    'flush' => false,
])

<div {{ $attributes->merge(['class' => 'ad-card']) }}>
    @if ($title || $actions)
        <div class="ad-card__header">
            <div>
                <h2>
                    @if ($icon) <i class="bi {{ $icon }} me-1"></i> @endif
                    {{ $title }}
                </h2>
                @if ($subtitle)
                    <div class="text-muted" style="font-size:.78rem">{{ $subtitle }}</div>
                @endif
            </div>
            @if ($actions)
                <div class="ad-card__actions">{{ $actions }}</div>
            @endif
        </div>
    @endif

    <div class="{{ $flush ? '' : 'ad-card__body' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="ad-card__footer">{{ $footer }}</div>
    @endisset
</div>
