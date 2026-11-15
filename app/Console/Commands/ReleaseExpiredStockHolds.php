<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Frees stock held for checkouts that were never paid for.
 *
 * Placing an order holds its stock for 30 minutes so two people cannot buy the
 * last sofa at once. If the customer walks away, nothing releases that hold on
 * its own — this command does, and it is scheduled every five minutes.
 */
class ReleaseExpiredStockHolds extends Command
{
    protected $signature = 'stock:release-expired';

    protected $description = 'Release stock reservations whose hold has expired';

    public function handle(StockService $stock): int
    {
        $released = $stock->releaseExpired();

        if ($released > 0) {
            // Worth a log line: a steady stream of these means people are
            // reaching payment and then abandoning it.
            Log::info('Released expired stock holds', ['reservations' => $released]);
        }

        $this->info($released === 0
            ? 'No expired holds.'
            : 'Released '.$released.' expired hold(s).');

        return self::SUCCESS;
    }
}
