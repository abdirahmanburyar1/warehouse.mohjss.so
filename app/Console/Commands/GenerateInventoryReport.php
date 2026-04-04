<?php

namespace App\Console\Commands;

use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\IssueQuantityReport;
use App\Models\IssueQuantityItem;
use App\Models\MonthlyQuantityReceived;
use App\Models\ReceivedQuantityItem;
use App\Models\Warehouse;
use App\Services\InventoryReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonthlyInventoryReportGenerated;

class GenerateInventoryReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:generate-report {month? : The month to generate report for (YYYY-MM format)} {--warehouse= : Specific warehouse ID} {--force : Force regeneration of existing reports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly inventory report (Batch-level & Warehouse-based)';

    /**
     * Execute the console command.
     */
    public function handle(InventoryReportService $service)
    {
        $monthArg = $this->argument('month');
        $force = $this->option('force');
        $today = now();

        if (!$monthArg && !$force) {
            $setting = \App\Models\EmailNotificationSetting::inventoryMonthlyReportSchedule();
            if (!$setting || !$setting->enabled) {
                return 0;
            }
            $config = $setting->config ?? [];
            $dayOfMonth = (int) ($config['day_of_month'] ?? 1);
            $time = $this->normalizeTime($config['time'] ?? '02:00');
            $currentTime = $today->format('H:i');
            if ($today->day != $dayOfMonth || $currentTime !== $time) {
                return 0;
            }
        }

        try {
            $monthYear = $monthArg ?? now()->subMonth()->format('Y-m');
            $warehouseId = $this->option('warehouse');

            $this->info("Starting inventory report generation for {$monthYear}...");
            if ($warehouseId) {
                $this->info("Filtering for Warehouse ID: {$warehouseId}");
            }

            $results = $service->generate($monthYear, $warehouseId, $force);
            
            $this->info("Processed {$results['processed']} warehouses.");
            $this->info("Total items generated: {$results['items_generated']}.");
            
            if ($results['processed'] > 0) {
                $this->info("Report generation completed successfully.");
            } else {
                $this->warn("No reports were generated. Check logs or verify warehouses exist.");
            }

            if ($results['skipped'] > 0) {
                $this->warn("Skipped {$results['skipped']} warehouses (report already exists).");
            }

            if (!empty($results['errors'])) {
                $this->error("Errors encountered:");
                foreach ($results['errors'] as $error) {
                    $this->error("- $error");
                }
            }

            // Send Email Notification
            Mail::to('abdirahman.buryar@gmail.com')->send(new MonthlyInventoryReportGenerated(
                $results['first_report'] ?? null,
                $results['items_generated'],
                !empty($results['errors']) ? implode(', ', $results['errors']) : null
            ));

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return '02:00';
    }
}
