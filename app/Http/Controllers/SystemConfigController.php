<?php

namespace App\Http\Controllers;

use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SystemConfigController extends Controller
{
    private const STORAGE_DIR = 'system-config';

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->hasPermission('permission-manage') && !$user->hasPermission('system-settings') && !$user->hasPermission('manage-system'))) {
            abort(403, 'You do not have permission to access system configuration.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAccess($request);
        $faviconUrl = SystemConfig::faviconUrl();
        $logoUrl = SystemConfig::logoUrl();

        return Inertia::render('Settings/SystemConfig/Index', [
            'faviconUrl' => $faviconUrl,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAccess($request);

        $request->validate([
            'favicon' => 'nullable|file|mimetypes:image/png,image/jpeg,image/gif,image/x-icon,image/svg+xml|max:512',
            'logo' => 'nullable|file|mimetypes:image/png,image/jpeg,image/gif,image/svg+xml|max:2048',
        ], [
            'favicon.max' => 'Favicon must be 512 KB or less.',
            'logo.max' => 'Logo must be 2 MB or less.',
        ]);

        // Process remove/revert first, then uploads
        if (filter_var($request->input('remove_favicon'), FILTER_VALIDATE_BOOLEAN)) {
            $this->removeConfigFile(SystemConfig::FAVICON_KEY);
            SystemConfig::setValue(SystemConfig::FAVICON_KEY, null);
        } elseif ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store(self::STORAGE_DIR, 'public');
            SystemConfig::setValue(SystemConfig::FAVICON_KEY, $path);
        }

        if (filter_var($request->input('remove_logo'), FILTER_VALIDATE_BOOLEAN)) {
            $this->removeConfigFile(SystemConfig::LOGO_KEY);
            SystemConfig::setValue(SystemConfig::LOGO_KEY, null);
        } elseif ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(self::STORAGE_DIR, 'public');
            SystemConfig::setValue(SystemConfig::LOGO_KEY, $path);
        }

        return back()->with('success', 'System configuration saved.');
    }

    private function removeConfigFile(string $key): void
    {
        $path = SystemConfig::getValue($key);
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
