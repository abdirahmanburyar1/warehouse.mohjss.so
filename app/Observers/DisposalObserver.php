<?php

namespace App\Observers;

use App\Models\Disposal;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Notifications\DisposalActionRequired;

class DisposalObserver
{
    /**
     * Handle the Disposal "created" event.
     */
    public function created(Disposal $disposal): void
    {
        if ($disposal->status !== 'pending') {
            return;
        }

        $reviewers = $this->getEligibleReviewers($disposal);

        foreach ($reviewers as $user) {
            $user->notify(new DisposalActionRequired($disposal, DisposalActionRequired::ACTION_NEEDS_REVIEW));
        }
    }

    /**
     * Handle the Disposal "updated" event.
     */
    public function updated(Disposal $disposal): void
    {
        if ($disposal->wasChanged('status') && $disposal->status === 'reviewed') {
            $approvers = User::withPermission('disposal-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', $disposal->reviewed_by)
                ->whereHas('warehouse', function($q) {
                    $q->where('type', 'central');
                })
                ->get();

            foreach ($approvers as $user) {
                $user->notify(new DisposalActionRequired($disposal, DisposalActionRequired::ACTION_READY_FOR_APPROVAL));
            }
        }
    }

    /**
     * Get eligible reviewers based on record origin.
     */
    private function getEligibleReviewers(Disposal $disposal)
    {
        $query = User::withPermission('disposal-review')
            ->where('is_active', true)
            ->whereNotNull('email');
            
        if ($disposal->disposed_by) {
            $query->where('id', '!=', $disposal->disposed_by);
        }

        // Logic to narrow down by warehouse/region
        if (!empty($disposal->facility)) {
            // Origin is a Facility, notify Regional Warehouse users in same region
            $facility = Facility::where('name', $disposal->facility)->first();
            if ($facility && $facility->region) {
                $query->whereHas('warehouse', function($q) use ($facility) {
                    $q->where('region', $facility->region)
                      ->where('type', 'regional');
                });
            }
        } elseif (!empty($disposal->warehouse)) {
            // Origin is a Warehouse, notify users in that same warehouse
            $warehouse = Warehouse::where('name', $disposal->warehouse)->first();
            if ($warehouse) {
                $query->where('warehouse_id', $warehouse->id);
            }
        } elseif ($disposal->warehouse_id) {
            $query->where('warehouse_id', $disposal->warehouse_id);
        }

        return $query->get();
    }
}
