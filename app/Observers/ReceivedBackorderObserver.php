<?php

namespace App\Observers;

use App\Models\ReceivedBackorder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Notifications\ReceivedBackorderActionRequired;

class ReceivedBackorderObserver
{
    /**
     * Handle the ReceivedBackorder "created" event.
     */
    public function created(ReceivedBackorder $receivedBackorder): void
    {
        if ($receivedBackorder->status !== 'pending') {
            return;
        }

        $reviewers = $this->getEligibleReviewers($receivedBackorder);

        foreach ($reviewers as $user) {
            $user->notify(new ReceivedBackorderActionRequired($receivedBackorder, ReceivedBackorderActionRequired::ACTION_NEEDS_REVIEW));
        }
    }

    /**
     * Handle the ReceivedBackorder "updated" event.
     */
    public function updated(ReceivedBackorder $receivedBackorder): void
    {
        if ($receivedBackorder->wasChanged('status') && $receivedBackorder->status === 'reviewed') {
            $approvers = User::withPermission('received-backorder-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', $receivedBackorder->reviewed_by)
                ->whereHas('warehouse', function($q) {
                    $q->where('type', 'central');
                })
                ->get();

            foreach ($approvers as $user) {
                $user->notify(new ReceivedBackorderActionRequired($receivedBackorder, ReceivedBackorderActionRequired::ACTION_READY_FOR_APPROVAL));
            }
        }
    }

    /**
     * Get eligible reviewers based on record origin.
     */
    private function getEligibleReviewers(ReceivedBackorder $receivedBackorder)
    {
        $query = User::withPermission('received-backorder-review')
            ->where('is_active', true)
            ->whereNotNull('email');
            
        if ($receivedBackorder->received_by) {
            $query->where('id', '!=', $receivedBackorder->received_by);
        }

        if ($receivedBackorder->facility_id) {
            // Origin is a Facility, notify Regional Warehouse users in same region
            $facility = Facility::find($receivedBackorder->facility_id);
            if ($facility && $facility->region) {
                $query->whereHas('warehouse', function($q) use ($facility) {
                    $q->where('region', $facility->region)
                      ->where('type', 'regional');
                });
            }
        } elseif ($receivedBackorder->warehouse_id) {
            // Origin is a Warehouse, notify users in that same warehouse
            $query->where('warehouse_id', $receivedBackorder->warehouse_id);
        }

        return $query->get();
    }
}
