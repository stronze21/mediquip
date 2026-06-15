<?php

namespace App\Livewire\Admin;

use App\Support\PrintDocumentSettings;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class PrintSettings extends Component
{
    use Toast;
    use WithFileUploads;

    public string $documentType = 'purchase_order';
    public array $settings = [];
    public $logoUpload;

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function updatedDocumentType(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $this->settings = PrintDocumentSettings::for($this->documentType);
        $this->logoUpload = null;
    }

    public function addLogo(): void
    {
        $this->validate([
            'logoUpload' => 'required|image|max:2048',
        ]);

        $path = $this->logoUpload->store('print-logos', 'public');

        $this->settings['header']['logos'][] = [
            'id' => uniqid('logo_', true),
            'path' => $path,
            'label' => 'Logo ' . (count($this->settings['header']['logos']) + 1),
            'x' => 4,
            'y' => 12,
            'width' => 90,
        ];

        $this->logoUpload = null;
        $this->save(false);
        $this->success('Logo added.');
    }

    public function removeLogo(int $index): void
    {
        unset($this->settings['header']['logos'][$index]);
        $this->settings['header']['logos'] = array_values($this->settings['header']['logos']);
    }

    public function updateLogoPosition(int $index, float $x, float $y): void
    {
        if (!isset($this->settings['header']['logos'][$index])) {
            return;
        }

        $this->settings['header']['logos'][$index]['x'] = max(0, min(100, round($x, 2)));
        $this->settings['header']['logos'][$index]['y'] = max(0, min(100, round($y, 2)));
    }

    public function addSignatory(): void
    {
        $this->settings['footer']['signatories'][] = [
            'label' => 'Signatory',
            'name' => '',
            'title' => '',
        ];
    }

    public function removeSignatory(int $index): void
    {
        unset($this->settings['footer']['signatories'][$index]);
        $this->settings['footer']['signatories'] = array_values($this->settings['footer']['signatories']);
    }

    public function save(bool $toast = true): void
    {
        $this->validate([
            'settings.header.height' => 'required|integer|min:80|max:260',
            'settings.header.title' => 'nullable|string|max:120',
            'settings.header.subtitle' => 'nullable|string|max:180',
            'settings.footer.text' => 'nullable|string|max:255',
            'settings.footer.layout' => 'required|in:horizontal,vertical',
            'settings.footer.columns' => 'required|integer|min:1|max:6',
            'settings.footer.signatories.*.label' => 'nullable|string|max:80',
            'settings.footer.signatories.*.name' => 'nullable|string|max:120',
            'settings.footer.signatories.*.title' => 'nullable|string|max:120',
            'settings.header.logos.*.label' => 'nullable|string|max:80',
            'settings.header.logos.*.x' => 'required|numeric|min:0|max:100',
            'settings.header.logos.*.y' => 'required|numeric|min:0|max:100',
            'settings.header.logos.*.width' => 'required|integer|min:30|max:240',
        ]);

        PrintDocumentSettings::save($this->documentType, $this->settings);
        $this->settings = PrintDocumentSettings::for($this->documentType);

        if ($toast) {
            $this->success('Print settings saved.');
        }
    }

    public function resetDefaults(): void
    {
        $this->settings = PrintDocumentSettings::defaults($this->documentType);
        $this->save();
    }

    public function render()
    {
        return view('livewire.admin.print-settings')
            ->layout('layouts.app', ['title' => 'Print Settings']);
    }
}
