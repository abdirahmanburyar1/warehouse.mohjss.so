<?php

namespace App\Observers;

use App\Models\Liquidate;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Notifications\LiquidationActionRequired;

class LiquidationObserver
{
    /**
     * Handle the Liquidate "created" event.
     */
    public function created(Liquidate $liquidate): void
    {
        if ($liquidate->status !== 'pending') {
            return;
        }

        $reviewers = $this->getEligibleReviewers($liquidate);

        foreach ($reviewers as $user) {
            $user->notify(new LiquidationActionRequired($liquidate, LiquidationActionRequired::ACTION_NEEDS_REVIEW));
        }
    }

    /**
     * Handle the Liquidate "updated" event.
     */
    public function updated(Liquidate $liquidate): void
    {
        if ($liquidate->wasChanged('status') && $liquidate->status === 'reviewed') {
            $approvers = User::withPermission('liquidation-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', $liquidate->reviewed_by)
                ->whereHas('warehouse', function($q) {
                    $q->where('type', 'central');
                })
                ->get();

            foreach ($approvers as $user) {
                $user->notify(new LiquidationActionRequired($liquidate, LiquidationActionRequired::ACTION_READY_FOR_APPROVAL));
            }
        }
    }

    /**
     * Get eligible reviewers based on record origin.
     */
    private function getEligibleReviewers(Liquidate $liquidate)
    {
        $query = User::withPermission('liquidation-review')
            ->where('is_active', true)
            ->whereNotNull('email');
            
        if ($liquidate->liquidated_by) {
            $query->where('id', '!=', $liquidate->liquidated_by);
        }

        // Logic to narrow down by warehouse/region
        if (!empty($liquidate->facility)) {
            // Origin is a Facility, notify Regional Warehouse users in same region
            $facility = Facility::where('name', $liquidate->facility)->first();
            if ($facility && $facility->region) {
                $query->whereHas('warehouse', function($q) use ($facility) {
                    $q->where('region', $facility->region)
                      ->where('type', 'regional');
                });
            }
        } elseif (!empty($liquidate->warehouse)) {
            // Origin is a Warehouse, notify users in that same warehouse
            $warehouse = Warehouse::where('name', $liquidate->warehouse)->first();
            if ($warehouse) {
                $query->where('warehouse_id', $warehouse->id);
            }
        } elseif ($liquidate->warehouse_id) {
            $query->where('warehouse_id', $liquidate->warehouse_id);
        }

        return $query->get();
    }
}
