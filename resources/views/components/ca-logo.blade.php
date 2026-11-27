@props(['size' => 36, 'showWordmark' => true, 'subtitle' => ''])

<div {{ $attributes->merge(['class' => 'd-inline-flex align-items-center gap-2']) }}>
    @if($showWordmark)
        <img src="{{ asset('images/uptoware-logo.png') }}" alt="Uptoware Audit" style="height: {{ $size }}px; width: auto; max-width: 100%; object-fit: contain;">
        @if($subtitle)
            <span class="badge bg-light text-muted border fw-normal" style="font-size: 0.7rem;">{{ $subtitle }}</span>
        @endif
    @else
        <img src="{{ asset('images/uptoware-icon.png') }}" alt="Uptoware" style="height: {{ $size }}px; width: {{ $size }}px; border-radius: 8px; object-fit: cover;">
    @endif
</div>
