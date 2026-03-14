<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRegistered extends Notification implements ShouldQueue
{
    use Queueable;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;
    
    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    protected $password;
    protected $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $user, $password = null)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Decide which application URL to use based on user context
        $warehouseUrl = rtrim(env('WAREHOUSE_URL', config('app.url')), '/');
        $hcUrl = rtrim(env('HC_URL', ''), '/');

        $baseUrl = $warehouseUrl;
        if ($this->user->facility_id && ! $this->user->warehouse_id && $hcUrl !== '') {
            $baseUrl = $hcUrl;
        }

        // Generate a password reset token manually so the user can set their own password
        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->user->email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );
        $resetUrl = $baseUrl . '/reset-password/' . $plainToken . '?email=' . urlencode($this->user->email);

        $mailMessage = (new MailMessage)
            ->subject('Welcome to ' . config('app.name') . ' - Your Account Details')
            ->greeting('Hello ' . $this->user->name . '!')
            ->line('We are pleased to inform you that an account has been created for you on the ' . config('app.name') . ' platform.')
            ->line('Here are your account details:')
            ->line('Username: ' . $this->user->username)
            ->line('Email: ' . $this->user->email);

        // Add permission information if available
        if ($this->user->permissions->count() > 0) {
            $permissionNames = $this->user->permissions->pluck('name')->implode(', ');
            $mailMessage->line('Assigned Permission(s): ' . $permissionNames);
        }

        // Add warehouse information if available
        if ($this->user->warehouse) {
            $mailMessage->line('Assigned Warehouse: ' . $this->user->warehouse->name);
        }

        // Add facility information if available
        if ($this->user->facility) {
            $mailMessage->line('Assigned Facility: ' . $this->user->facility->name);
        }

        $footerPrefix = $this->user->facility
            ? $this->user->facility->name
            : (env('APP_STATE') ?: '');

        $mailMessage
            ->line('To get started, please set your password using the button below. This link can be used once and may expire after some time.')
            ->action('Set Your Password', $resetUrl)
            ->line('After setting your password, you can log in using your username or email and the new password.')
            ->salutation('')
            ->line(trim($footerPrefix . ' Warehouse Inventory Management System'));

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ];
    }
}
