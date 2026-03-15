<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationSetting;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class EmailNotificationSettingController extends Controller
{
    public function index()
    {
        $expirySetting = null;
        $lowStockSetting = null;
        if (Schema::hasTable('email_notification_settings')) {
            $expirySetting = EmailNotificationSetting::expiryItems();
            $lowStockSetting = EmailNotificationSetting::lowStockItems();
        }

        $roles = Role::orderBy('name')->get();

        $c = $expirySetting ? ($expirySetting->config ?? []) : [];
        $expiryItemsConfig = [
            'enabled' => $expirySetting ? $expirySetting->enabled : false,
            'role_ids' => is_array($c['role_ids'] ?? null) ? $c['role_ids'] : [],
            'notify_expired' => (bool) ($c['notify_expired'] ?? true),
            'notify_expiring_6_months' => (bool) ($c['notify_expiring_6_months'] ?? true),
            'notify_expiring_1_year' => (bool) ($c['notify_expiring_1_year'] ?? true),
            'send_time' => $c['send_time'] ?? '07:00',
            'expiring_6_months_interval_days' => (int) ($c['expiring_6_months_interval_days'] ?? 4),
            'expiring_1_year_interval_days' => (int) ($c['expiring_1_year_interval_days'] ?? 14),
        ];

        $lc = $lowStockSetting ? ($lowStockSetting->config ?? []) : [];
        $lowStockItemsConfig = [
            'enabled' => $lowStockSetting ? $lowStockSetting->enabled : false,
            'role_ids' => is_array($lc['role_ids'] ?? null) ? $lc['role_ids'] : [],
            'send_time' => $lc['send_time'] ?? '09:00',
        ];

        return Inertia::render('Settings/EmailNotification/Index', [
            'roles' => $roles,
            'expiryItems' => $expiryItemsConfig,
            'lowStockItems' => $lowStockItemsConfig,
        ]);
    }

    public function update(Request $request)
    {
        if (!Schema::hasTable('email_notification_settings')) {
            return back()->with('error', 'Email notification settings table is not set up. Please run migrations.');
        }

        $validated = $request->validate([
            'expiry_items.enabled' => 'boolean',
            'expiry_items.role_ids' => 'array',
            'expiry_items.role_ids.*' => 'exists:roles,id',
            'expiry_items.notify_expired' => 'boolean',
            'expiry_items.notify_expiring_6_months' => 'boolean',
            'expiry_items.notify_expiring_1_year' => 'boolean',
            'expiry_items.send_time' => 'required|string|regex:/^\d{1,2}:\d{2}$/',
            'expiry_items.expiring_6_months_interval_days' => 'required|integer|min:1|max:365',
            'expiry_items.expiring_1_year_interval_days' => 'required|integer|min:1|max:365',

            'low_stock_items.enabled' => 'boolean',
            'low_stock_items.role_ids' => 'array',
            'low_stock_items.role_ids.*' => 'exists:roles,id',
            'low_stock_items.send_time' => 'required|string|regex:/^\d{1,2}:\d{2}$/',
        ]);

        $data = $validated['expiry_items'] ?? [];
        $enabled = (bool) ($data['enabled'] ?? false);
        $roleIds = array_values(array_filter(array_map('intval', $data['role_ids'] ?? [])));
        $sendTime = preg_match('/^\d{1,2}:\d{2}$/', $data['send_time'] ?? '') ? $data['send_time'] : '07:00';
        if (strlen($sendTime) === 4) {
            $sendTime = '0' . $sendTime; // 7:00 -> 07:00
        }

        EmailNotificationSetting::updateOrCreate(
            ['key' => 'expiry_items'],
            [
                'enabled' => $enabled,
                'config' => [
                    'role_ids' => $roleIds,
                    'notify_expired' => (bool) ($data['notify_expired'] ?? true),
                    'notify_expiring_6_months' => (bool) ($data['notify_expiring_6_months'] ?? true),
                    'notify_expiring_1_year' => (bool) ($data['notify_expiring_1_year'] ?? true),
                    'send_time' => $sendTime,
                    'expiring_6_months_interval_days' => (int) ($data['expiring_6_months_interval_days'] ?? 4),
                    'expiring_1_year_interval_days' => (int) ($data['expiring_1_year_interval_days'] ?? 14),
                ],
            ]
        );

        $lowStockData = $validated['low_stock_items'] ?? [];
        $lowStockEnabled = (bool) ($lowStockData['enabled'] ?? false);
        $lowStockRoleIds = array_values(array_filter(array_map('intval', $lowStockData['role_ids'] ?? [])));
        $lowStockSendTime = preg_match('/^\d{1,2}:\d{2}$/', $lowStockData['send_time'] ?? '') ? $lowStockData['send_time'] : '09:00';
        if (strlen($lowStockSendTime) === 4) {
            $lowStockSendTime = '0' . $lowStockSendTime;
        }

        EmailNotificationSetting::updateOrCreate(
            ['key' => 'low_stock_items'],
            [
                'enabled' => $lowStockEnabled,
                'config' => [
                    'role_ids' => $lowStockRoleIds,
                    'send_time' => $lowStockSendTime,
                ],
            ]
        );

        return back()->with('success', 'Email notification settings saved.');
    }
}
