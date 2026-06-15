@php
    $header = $printSettings['header'] ?? [];
    $logos = $header['logos'] ?? [];
    $height = (int) ($header['height'] ?? 130);
@endphp

<div class="print-layout-header" style="position: relative; height: {{ $height }}px; margin-bottom: 24px; border-bottom: 2px solid #333; overflow: hidden;">
    @foreach ($logos as $logo)
        @if ($url = \App\Support\PrintDocumentSettings::logoUrl($logo))
            <img src="{{ $url }}" alt="{{ $logo['label'] ?? 'Logo' }}"
                style="position: absolute; left: {{ (float) ($logo['x'] ?? 0) }}%; top: {{ (float) ($logo['y'] ?? 0) }}%; width: {{ (int) ($logo['width'] ?? 90) }}px; max-height: {{ max(40, $height - 20) }}px; object-fit: contain;">
        @endif
    @endforeach

    <div style="position: absolute; inset: 0; display: table; width: 100%; height: 100%; text-align: center;">
        <div style="display: table-cell; vertical-align: middle;">
            <div style="font-size: 24px; font-weight: 700; color: #111;">
                {{ $header['title'] ?: $documentTitle }}
            </div>

            @if (!empty($header['subtitle']))
                <div style="margin-top: 4px; font-size: 13px; color: #555;">
                    {{ $header['subtitle'] }}
                </div>
            @endif

            <div style="margin-top: 4px; font-size: 12px; color: #666;">
                {{ $documentNumber }}
            </div>

            @if ($header['show_company_details'] ?? true)
                <div style="margin-top: 8px; font-size: 12px; line-height: 1.35; color: #666;">
                    {{ config('app.name') }}<br>
                    Computer Parts Inventory Management System<br>
                    Phone: Your Phone Number | Email: your@email.com
                </div>
            @endif
        </div>
    </div>
</div>
