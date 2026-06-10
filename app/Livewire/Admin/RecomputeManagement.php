<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Artisan;

class RecomputeManagement extends Component
{
    use Toast;

    public $output = '';
    public $isRunning = false;
    public $lastCommand = '';

    public function render()
    {
        return view('livewire.admin.recompute-management')
            ->layout('layouts.app', ['title' => 'Recompute Return Totals']);
    }

    public function runRecompute($dryRun = true)
    {
        if ($this->isRunning) {
            $this->warning('A recompute operation is already running...');
            return;
        }

        $this->isRunning = true;
        $this->output = "🔄 Starting recomputation...\n";

        try {
            $command = 'returns:recompute';
            $command .= ' --items';

            if ($dryRun) {
                $command .= ' --dry-run';
            }

            $this->lastCommand = $command;

            // Capture output
            $exitCode = Artisan::call($command);
            $this->output = Artisan::output();

            if ($exitCode === 0) {
                if ($dryRun) {
                    $this->info('Preview completed successfully!');
                } else {
                    $this->success('Recomputation completed successfully!');
                }
            } else {
                $this->error('Command failed with exit code: ' . $exitCode);
            }
        } catch (\Exception $e) {
            $this->output .= "\n❌ Error: " . $e->getMessage();
            $this->error('An error occurred during recomputation.');
        } finally {
            $this->isRunning = false;
        }
    }

    public function clearOutput()
    {
        $this->output = '';
        $this->lastCommand = '';
    }
}
