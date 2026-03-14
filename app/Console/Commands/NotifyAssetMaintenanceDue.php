<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\User;
use App\Notifications\AssetMaintenanceDue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class NotifyAssetMaintenanceDue extends Command
{
    protected $signature = 'assets:notify-maintenance-due';
    protected $description = 'Notify asset managers of maintenance due today';

    public function handle(): int
    {
        $maintenanceTable = (new AssetMaintenance())->getTable();
        if (
            !Schema::hasTable($maintenanceTable) ||
            !Schema::hasColumn($maintenanceTable, 'completed_date') ||
            !Schema::hasColumn($maintenanceTable, 'maintenance_range') ||
            !Schema::hasColumn($maintenanceTable, 'asset_id')
        ) {
            $this->info("Maintenance table not available ({$maintenanceTable}). Skipping.");
            return 0;
        }

        $today = now()->toDateString();

        // Find maintenance records whose next due date is today (completed_date + maintenance_range months = today)
        $maintenanceDueToday = AssetMaintenance::query()
            ->whereNotNull('completed_date')
            ->where('maintenance_range', '>', 0)
            ->whereRaw('DATE_ADD(completed_date, INTERVAL maintenance_range MONTH) = ?', [$today])
            ->get();

        $assetIds = $maintenanceDueToday->pluck('asset_id')->unique()->values();
        $assets = Asset::whereIn('id', $assetIds)->get();

        if ($assets->isEmpty()) {
            $this->info('No assets due for maintenance today.');
            return 0;
        }

        $managers = User::permission('asset-manage')->get();
        foreach ($assets as $asset) {
            Notification::send($managers, new AssetMaintenanceDue($asset));
        }

        $this->info('Maintenance notifications sent: ' . $assets->count());
        return 0;
    }
}

