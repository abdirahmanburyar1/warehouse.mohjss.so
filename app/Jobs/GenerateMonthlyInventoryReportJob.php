<?php

namespace App\Jobs;

use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\Product;
use App\Models\MonthlyQuantityReceived;
use App\Models\IssueQuantityReport;
use App\Models\ReceivedQuantity;
use App\Models\IssuedQuantity;
use App\Models\Warehouse;
use App\Services\InventoryReportService;
use App\Mail\MonthlyInventoryReportGenerated;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;

class GenerateMonthlyInventoryReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $monthYear;
    
    /**
     * Create a new job instance.
     */
    public function __construct($monthYear = null)
    {
        $this->monthYear = $monthYear ?? Carbon::now()->format('Y-m');
    }

    /**
     * Execute the job.
     */
    public function handle(InventoryReportService $service)
    {
        try {
            echo "Starting monthly inventory report generation for {$this->monthYear}\n";
            
            $results = $service->generate($this->monthYear, null, true);
            
            echo "Processed {$results['processed']} warehouses.\n";
            echo "Items generated: {$results['items_generated']}.\n";
            
            $errorMessage = !empty($results['errors']) ? implode("; ", $results['errors']) : null;
            
            // Send notification email
            $adminEmail = config('mail.admin_address') ?? 'abdirahman.buryar@gmail.com';
            Mail::to($adminEmail)->send(new MonthlyInventoryReportGenerated(
                $results['first_report'],
                $results['items_generated'],
                $errorMessage
            ));

            if (!empty($results['errors'])) {
                echo "Errors encountered:\n";
                foreach ($results['errors'] as $error) {
                    echo "- $error\n";
                }
            }

        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
