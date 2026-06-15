<div>
    <x-mary-header title="Print Settings" subtitle="Configure purchase order and sales invoice print headers and footers" separator>
        <x-slot:actions>
            <x-mary-button label="Reset Defaults" icon="o-arrow-path" wire:click="resetDefaults" class="btn-ghost" />
            <x-mary-button label="Save Settings" icon="o-check" wire:click="save" class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_1fr]">
        <div class="space-y-6">
            <x-mary-card title="Document">
                <x-mary-select label="Print Template" :options="[
                    ['value' => 'purchase_order', 'label' => 'Purchase Order'],
                    ['value' => 'invoice', 'label' => 'Sales Invoice'],
                ]" wire:model.live="documentType" option-value="value" option-label="label" />
            </x-mary-card>

            <x-mary-card title="Header">
                <div class="space-y-4">
                    <x-mary-input label="Header Title" wire:model.blur="settings.header.title" />
                    <x-mary-input label="Subtitle" wire:model.blur="settings.header.subtitle" />
                    <x-mary-input label="Header Height" type="number" min="80" max="260"
                        wire:model.blur="settings.header.height" suffix="px" />
                    <x-mary-checkbox label="Show company details" wire:model.live="settings.header.show_company_details" />

                    <div class="grid grid-cols-[1fr_auto] items-end gap-3">
                        <x-mary-file label="Add Logo" wire:model="logoUpload" accept="image/*" />
                        <x-mary-button label="Add" icon="o-plus" wire:click="addLogo" class="btn-primary"
                            spinner="addLogo" />
                    </div>
                </div>
            </x-mary-card>

            <x-mary-card title="Footer">
                <div class="space-y-4">
                    <x-mary-checkbox label="Show footer" wire:model.live="settings.footer.enabled" />
                    <x-mary-textarea label="Footer Text" rows="2" wire:model.blur="settings.footer.text" />
                    <x-mary-select label="Signatory Layout" :options="[
                        ['value' => 'horizontal', 'label' => 'Horizontal columns'],
                        ['value' => 'vertical', 'label' => 'Vertical columns'],
                    ]" wire:model.live="settings.footer.layout" option-value="value" option-label="label" />
                    <x-mary-input label="Number of Columns" type="number" min="1" max="6"
                        wire:model.blur="settings.footer.columns" />
                </div>
            </x-mary-card>
        </div>

        <div class="space-y-6">
            <x-mary-card title="Header Layout">
                <div class="mb-4 text-sm text-base-content/60">
                    Drag each logo inside the header preview, or select a logo and click the preview to place it.
                </div>

                <div
                    class="relative overflow-hidden bg-white border rounded-lg shadow-sm print-header-preview"
                    data-print-logo-canvas
                    style="height: {{ (int) ($settings['header']['height'] ?? 130) }}px;">
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ $settings['header']['title'] ?? '' }}
                        </div>
                        @if (!empty($settings['header']['subtitle']))
                            <div class="mt-1 text-sm text-gray-500">{{ $settings['header']['subtitle'] }}</div>
                        @endif
                        @if ($settings['header']['show_company_details'] ?? true)
                            <div class="mt-2 text-xs text-center text-gray-500">
                                {{ config('app.name') }}<br>
                                Business address, phone, and email
                            </div>
                        @endif
                    </div>

                    @foreach (($settings['header']['logos'] ?? []) as $index => $logo)
                        <div
                            class="absolute z-10 p-1 bg-white border rounded shadow cursor-move select-none touch-none"
                            wire:key="logo-preview-{{ $logo['id'] ?? $index }}"
                            data-print-logo
                            data-logo-index="{{ $index }}"
                            data-x="{{ (float) ($logo['x'] ?? 0) }}"
                            data-y="{{ (float) ($logo['y'] ?? 0) }}"
                            style="left: {{ (float) ($logo['x'] ?? 0) }}%; top: {{ (float) ($logo['y'] ?? 0) }}%;">
                            <img src="{{ \App\Support\PrintDocumentSettings::logoUrl($logo) }}"
                                alt="{{ $logo['label'] ?? 'Logo' }}"
                                draggable="false"
                                class="pointer-events-none"
                                style="width: {{ (int) ($logo['width'] ?? 90) }}px; max-height: 90px; object-fit: contain;">
                        </div>
                    @endforeach
                </div>
            </x-mary-card>

            <x-mary-card title="Logo Details">
                @if (count($settings['header']['logos'] ?? []) > 0)
                    <div class="space-y-4">
                        @foreach ($settings['header']['logos'] as $index => $logo)
                            <div class="grid grid-cols-1 gap-3 p-4 border rounded-lg md:grid-cols-[90px_1fr_auto] md:items-end"
                                wire:key="logo-row-{{ $logo['id'] ?? $index }}">
                                <img src="{{ \App\Support\PrintDocumentSettings::logoUrl($logo) }}"
                                    class="object-contain w-20 h-16 bg-white border rounded" alt="Logo preview">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                    <x-mary-input label="Label" wire:model.blur="settings.header.logos.{{ $index }}.label" />
                                    <x-mary-input label="X" type="number" step="0.1" min="0" max="100"
                                        wire:model.blur="settings.header.logos.{{ $index }}.x" suffix="%" />
                                    <x-mary-input label="Y" type="number" step="0.1" min="0" max="100"
                                        wire:model.blur="settings.header.logos.{{ $index }}.y" suffix="%" />
                                    <x-mary-input label="Width" type="number" min="30" max="240"
                                        wire:model.blur="settings.header.logos.{{ $index }}.width" suffix="px" />
                                </div>
                                <x-mary-button icon="o-trash" wire:click="removeLogo({{ $index }})"
                                    class="btn-ghost text-error" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center text-base-content/50">
                        No logos added yet.
                    </div>
                @endif
            </x-mary-card>

            <x-mary-card title="Signatories">
                <div class="mb-4 text-right">
                    <x-mary-button label="Add Signatory" icon="o-plus" wire:click="addSignatory" class="btn-outline" />
                </div>

                <div class="space-y-3">
                    @foreach (($settings['footer']['signatories'] ?? []) as $index => $signatory)
                        <div class="grid grid-cols-1 gap-3 p-4 border rounded-lg md:grid-cols-[1fr_1fr_1fr_auto]"
                            wire:key="signatory-row-{{ $index }}">
                            <x-mary-input label="Role Label" wire:model.blur="settings.footer.signatories.{{ $index }}.label" />
                            <x-mary-input label="Name" wire:model.blur="settings.footer.signatories.{{ $index }}.name" />
                            <x-mary-input label="Title" wire:model.blur="settings.footer.signatories.{{ $index }}.title" />
                            <x-mary-button icon="o-trash" wire:click="removeSignatory({{ $index }})"
                                class="self-end btn-ghost text-error" />
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    <div class="mb-2 text-sm font-semibold">Footer Preview</div>
                    <div class="{{ ($settings['footer']['layout'] ?? 'horizontal') === 'vertical' ? 'space-y-5' : 'grid gap-4' }}"
                        style="{{ ($settings['footer']['layout'] ?? 'horizontal') === 'horizontal' ? 'grid-template-columns: repeat(' . (int) ($settings['footer']['columns'] ?? 4) . ', minmax(0, 1fr));' : '' }}">
                        @foreach (($settings['footer']['signatories'] ?? []) as $signatory)
                            <div class="pt-4 text-center border-t">
                                <div class="font-semibold">{{ $signatory['name'] ?: 'Name' }}</div>
                                <div class="text-xs text-base-content/60">{{ $signatory['title'] ?: $signatory['label'] }}</div>
                                <div class="mt-1 text-xs uppercase tracking-wide">{{ $signatory['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-mary-card>
        </div>
    </div>
</div>
