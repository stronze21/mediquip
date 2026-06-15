@php
    $footer = $printSettings['footer'] ?? [];
    $signatories = array_values(array_filter($footer['signatories'] ?? [], fn ($item) => !empty($item['label']) || !empty($item['name']) || !empty($item['title'])));
    $columns = max(1, min(6, (int) ($footer['columns'] ?? 4)));
    $layout = $footer['layout'] ?? 'horizontal';
@endphp

@if (($footer['enabled'] ?? true) && (count($signatories) > 0 || !empty($footer['text'])))
    <div class="print-layout-footer" style="margin-top: 48px; padding-top: 18px; border-top: 1px solid #ddd;">
        @if (count($signatories) > 0)
            @if ($layout === 'vertical')
                <table style="width: 100%; border-collapse: separate; border-spacing: 0 18px;">
                    @foreach ($signatories as $signatory)
                        <tr>
                            <td style="width: 35%; padding-right: 18px; font-weight: 700; text-align: right;">
                                {{ $signatory['label'] }}
                            </td>
                            <td style="padding-top: 8px; border-top: 1px solid #333;">
                                <div style="font-weight: 700;">{!! $signatory['name'] ? e($signatory['name']) : '&nbsp;' !!}</div>
                                <div style="font-size: 11px; color: #666;">{!! $signatory['title'] ? e($signatory['title']) : '&nbsp;' !!}</div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                @foreach (array_chunk($signatories, $columns) as $row)
                    <table style="width: 100%; margin-top: 18px; table-layout: fixed; border-collapse: separate; border-spacing: 16px 0;">
                        <tr>
                            @foreach ($row as $signatory)
                                <td style="padding-top: 8px; border-top: 1px solid #333; text-align: center; vertical-align: top;">
                                    <div style="font-weight: 700;">{!! $signatory['name'] ? e($signatory['name']) : '&nbsp;' !!}</div>
                                    <div style="font-size: 11px; color: #666;">{!! $signatory['title'] ? e($signatory['title']) : '&nbsp;' !!}</div>
                                    <div style="margin-top: 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;">
                                        {{ $signatory['label'] }}
                                    </div>
                                </td>
                            @endforeach

                            @for ($i = count($row); $i < $columns; $i++)
                                <td></td>
                            @endfor
                        </tr>
                    </table>
                @endforeach
            @endif
        @endif

        @if (!empty($footer['text']))
            <div style="margin-top: 24px; font-size: 12px; text-align: center; color: #666;">
                {{ $footer['text'] }}
            </div>
        @endif
    </div>
@endif
