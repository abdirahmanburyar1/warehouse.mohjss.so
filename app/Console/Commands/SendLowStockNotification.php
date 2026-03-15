<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\LowStockNotification;
use App\Events\LowStockAlert;

class SendLowStockNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:notify-low-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notification for low stock items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $setting = \App\Models\EmailNotificationSetting::lowStockItems();
        if (!$setting || !$setting->enabled) {
            $this->info('Reorder level notifications are disabled.');
            return Command::SUCCESS;
        }

        $config = $setting->config ?? [];
        $sendTime = $config['send_time'] ?? '09:00';
        $currentTime = now()->format('H:i');

        if ($currentTime !== $sendTime) {
            return Command::SUCCESS;
        }

        Log::info('Reorder level notification running: sending at configured time.', [
            'send_time' => $sendTime,
            'current_time' => $currentTime
        ]);

        $roleIds = $config['role_ids'] ?? [];
        if (empty($roleIds)) {
            $this->warn('No roles configured for reorder level notifications.');
            return Command::SUCCESS;
        }

        // Get all products and calculate their metrics
        $allProducts = Product::with(['items', 'category', 'dosage', 'items.warehouse'])->get();
        $itemsToNotify = new \Illuminate\Support\Collection();

        foreach ($allProducts as $product) {
            $metrics = $product->calculateInventoryMetrics();
            $reorderLevel = $metrics['reorder_level'] ?? 0;
            $totalQty = $product->items->sum('quantity');

            // Find items at or below reorder level (formula according to InventoryController/Product)
            if ($reorderLevel > 0 && $totalQty <= $reorderLevel) {
                 foreach ($product->items as $item) {
                     // Attach reorder_level to item for the blade view
                     $item->current_reorder_level = $reorderLevel;
                     $itemsToNotify->push($item);
                }
            }
        }

        if ($itemsToNotify->count() > 0) {
            $recipients = \App\Models\User::whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('roles.id', $roleIds);
            })->where('is_active', true)->get();

            if ($recipients->isEmpty()) {
                $this->warn('No active users found with the configured roles.');
                return Command::SUCCESS;
            }

            foreach ($recipients as $user) {
                Mail::to($user->email)->send(new LowStockNotification($itemsToNotify));
                Log::info('Reorder level notification sent.', ['to' => $user->email]);
            }
            
            // Broadcast real-time events for each matching item
            event(new LowStockAlert());
            
            $this->info('Reorder level notification emails sent successfully to ' . $recipients->count() . ' users.');
            Log::info('Reorder level notifications completed.', ['recipients' => $recipients->count()]);
        } else {
            $this->info('No items at or below reorder level found.');
            Log::info('Reorder level notification: no items at or below reorder level.');
        }

        return Command::SUCCESS;
    }
}
