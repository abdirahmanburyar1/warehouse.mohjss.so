<?php

namespace App\Notifications;

use App\Models\ReceivedBackorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceivedBackorderActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';

    protected ReceivedBackorder $receivedBackorder;
    protected string $action;

    /**
     * @param  ReceivedBackorder  $receivedBackorder
     * @param  string  $action  self::ACTION_NEEDS_REVIEW or self::ACTION_READY_FOR_APPROVAL
     */
    public function __construct(ReceivedBackorder $receivedBackorder, string $action)
    {
        $this->receivedBackorder = $receivedBackorder->load(['receivedBy', 'backOrder']);
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $rbNumber = $this->receivedBackorder->received_backorder_number ?? '#' . $this->receivedBackorder->id;
        $type = $this->receivedBackorder->type ? str_replace('_', ' ', $this->receivedBackorder->type) : 'N/A';
        
        // Determine the correct base URL based on the recipient's primary platform
        $baseUrl = config('app.url'); // Default to current platform
        
        if ($notifiable->warehouse_id) {
            $warehouseType = $notifiable->warehouse->type ?? null;
            if ($warehouseType === 'central') {
                $baseUrl = 'https://warehouse.mohjss.so';
            } elseif ($warehouseType === 'regional') {
                $baseUrl = 'https://regionalwrjss.vistawims.com';
            }
        } elseif ($notifiable->facility_id) {
            $baseUrl = 'https://hcjss.vistawims.com';
        }

        $showUrl = rtrim($baseUrl, '/') . '/supplies/received-backorder/' . $this->receivedBackorder->id;

        if ($this->action === self::ACTION_NEEDS_REVIEW) {
            $subject = 'Received back order ' . $rbNumber . ' needs your review';
            $line1 = 'A received back order has been submitted and needs your review.';
        } else {
            $subject = 'Received back order ' . $rbNumber . ' is ready for approval';
            $line1 = 'Received back order ' . $rbNumber . ' has been reviewed and is ready for your approval.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Received back order: ' . $rbNumber)
            ->line('Type: ' . $type)
            ->action('View received back order', $showUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'received_backorder_id' => $this->receivedBackorder->id,
            'received_backorder_number' => $this->receivedBackorder->received_backorder_number,
            'action' => $this->action,
        ];
    }
}
