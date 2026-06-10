<?php

namespace App\Console\Commands;

use App\Models\SaleItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeReturnTotals extends Command
{
    protected $signature = 'returns:recompute
                            {--items : Recompute sale item returned quantities}
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Recompute returned quantities on sale items';

    public function handle(): int
    {
        $this->info('Starting return quantity recomputation...');
        $this->newLine();

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $fixed = $this->recomputeSaleItemReturnedQuantities($dryRun);

        $this->newLine();
        $this->info('Recomputation completed.');
        $this->info('Total records that '.($dryRun ? 'would be' : 'were')." updated: {$fixed}");

        if ($dryRun) {
            $this->warn('Run without --dry-run to apply these changes');
        }

        return 0;
    }

    private function recomputeSaleItemReturnedQuantities(bool $dryRun = false): int
    {
        $this->info('Recomputing sale item returned quantities...');

        $saleItems = SaleItem::all();
        $fixed = 0;

        foreach ($saleItems as $item) {
            $actualReturned = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->where('sale_return_items.sale_item_id', $item->id)
                ->where('sale_returns.status', 'processed')
                ->sum('sale_return_items.quantity');

            if ((int) $item->returned_quantity !== (int) $actualReturned) {
                $this->line("  Sale Item #{$item->id}: {$item->returned_quantity} -> {$actualReturned}");

                if (!$dryRun) {
                    $item->update(['returned_quantity' => $actualReturned]);
                }

                $fixed++;
            }
        }

        $this->info("  Sale items checked: {$saleItems->count()}, fixed: {$fixed}");

        return $fixed;
    }
}
