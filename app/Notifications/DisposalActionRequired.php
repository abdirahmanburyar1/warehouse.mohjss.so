<?php

namespace App\Notifications;

use App\Models\Disposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisposalActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';

    protected Disposal $disposal;
    protected string $action;

    /**
     * @param  Disposal  $disposal
     * @param  string  $action  self::ACTION_NEEDS_REVIEW or self::ACTION_READY_FOR_APPROVAL
     */
    public function __construct(Disposal $disposal, string $action)
    {
        $this->disposal = $disposal->load(['disposedBy', 'items']);
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $disposalId = $this->disposal->disposal_id ?? '#' . $this->disposal->id;
        $source = $this->disposal->source ? str_replace('_', ' ', $this->disposal->source) : 'N/A';
        
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

        $showUrl = rtrim($baseUrl, '/') . '/liquidate-disposal/disposals/' . $this->disposal->id;

        if ($this->action === self::ACTION_NEEDS_REVIEW) {
            $subject = 'Disposal ' . $disposalId . ' needs your review';
            $line1 = 'A disposal has been submitted and needs your review.';
        } else {
            $subject = 'Disposal ' . $disposalId . ' is ready for approval';
            $line1 = 'Disposal ' . $disposalId . ' has been reviewed and is ready for your approval.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Disposal: ' . $disposalId)
            ->line('Source: ' . $source)
            ->action('View disposal', $showUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'disposal_id' => $this->disposal->id,
            'disposal_number' => $this->disposal->disposal_id,
            'action' => $this->action,
        ];
    }
}
