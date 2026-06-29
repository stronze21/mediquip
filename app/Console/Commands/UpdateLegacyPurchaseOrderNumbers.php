<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateLegacyPurchaseOrderNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-orders:update-legacy-numbers
                            {--dry-run : Preview changes without updating purchase orders}
                            {--year= : Only update purchase orders for this order year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update legacy purchase order numbers to the PO-YYYYxxx format';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $year = $this->option('year');

        if ($year !== null && !preg_match('/^\d{4}$/', (string) $year)) {
            $this->error('The --year option must be a 4-digit year.');
            return self::FAILURE;
        }

        $query = PurchaseOrder::query()
            ->when($year, fn($query) => $query->whereYear('order_date', $year))
            ->orderBy('order_date')
            ->orderBy('created_at')
            ->orderBy('id');

        $purchaseOrders = $query->get()
            ->filter(fn(PurchaseOrder $purchaseOrder) => !$this->hasUpdatedFormat($purchaseOrder->po_number))
            ->values();

        if ($purchaseOrders->isEmpty()) {
            $this->info('No legacy purchase order numbers found.');
            return self::SUCCESS;
        }

        $this->info("Found {$purchaseOrders->count()} legacy purchase order number(s).");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made.');
        }

        $updated = 0;
        $nextSequencesByYear = $this->nextSequencesByYear();

        DB::transaction(function () use ($purchaseOrders, $dryRun, &$updated, &$nextSequencesByYear) {
            foreach ($purchaseOrders as $purchaseOrder) {
                $newNumber = $this->nextPONumber($purchaseOrder, $nextSequencesByYear);

                $this->line("PO ID {$purchaseOrder->id}: {$purchaseOrder->po_number} -> {$newNumber}");

                if (!$dryRun) {
                    $purchaseOrder->update(['po_number' => $newNumber]);
                }

                $updated++;
            }
        });

        $this->newLine();
        $this->info(($dryRun ? 'Previewed' : 'Updated') . " {$updated} purchase order number(s).");

        return self::SUCCESS;
    }

    private function hasUpdatedFormat(?string $poNumber): bool
    {
        return is_string($poNumber) && preg_match('/^PO-\d{7}$/', $poNumber) === 1;
    }

    private function nextSequencesByYear(): array
    {
        $sequences = [];

        PurchaseOrder::query()
            ->get(['po_number'])
            ->each(function (PurchaseOrder $purchaseOrder) use (&$sequences) {
                if (!is_string($purchaseOrder->po_number)) {
                    return;
                }

                if (preg_match('/^PO-(\d{4})(\d{3,})$/', $purchaseOrder->po_number, $matches) !== 1) {
                    return;
                }

                $year = $matches[1];
                $sequence = (int) $matches[2];
                $sequences[$year] = max($sequences[$year] ?? 0, $sequence);
            });

        return $sequences;
    }

    private function nextPONumber(PurchaseOrder $purchaseOrder, array &$nextSequencesByYear): string
    {
        $year = $purchaseOrder->order_date?->format('Y') ?? now()->format('Y');
        $nextSequencesByYear[$year] = ($nextSequencesByYear[$year] ?? 0) + 1;

        return 'PO-' . $year . str_pad((string) $nextSequencesByYear[$year], 3, '0', STR_PAD_LEFT);
    }
}
