<template>
    <AuthenticatedLayout :title="'Settings'" img="/assets/images/settings.png">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">System Settings</h1>
            </div>

            <div class="space-y-8">
                <!-- Settings list - icon + title only -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <Link
                        v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin"
                        :href="route('settings.users.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-blue-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">Users Management</span>
                    </Link>

                    <Link
                        v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin"
                        :href="route('settings.audit-trail.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-purple-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">Audit Logs</span>
                    </Link>

                    <Link
                        v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin"
                        :href="route('settings.email-notifications.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-yellow-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-yellow-50 text-yellow-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">Email Notifications</span>
                    </Link>

                    <Link
                        v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin"
                        :href="route('settings.report-schedules.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-red-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-red-50 text-red-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">Report Schedules</span>
                    </Link>

                    <Link
                        v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin"
                        :href="route('settings.report-submission.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-green-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-green-50 text-green-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">Report Submission Rate</span>
                    </Link>

                    <Link
                        v-if="page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.can.view_system || page.props.auth.isAdmin"
                        :href="route('settings.logistics.companies.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-orange-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-orange-50 text-orange-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">Logistic Companies</span>
                    </Link>


                    <Link
                        v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin"
                        :href="route('settings.system-config.index')"
                        class="flex items-center gap-4 text-gray-900 hover:text-indigo-600"
                    >
                        <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <span class="text-lg font-semibold">System Configuration</span>
                    </Link>
                </div>

                <!-- System Status (for view-system users) -->
                <div v-if="page.props.auth.can.view_system && !page.props.auth.can.manage_system && !page.props.auth.isAdmin" class="rounded-2xl bg-blue-50 border border-blue-100 p-6 shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">System View Mode Active</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>You currently have view-only access to system modules. Contact your system administrator if you require action permissions to manage these settings.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fallback for users with no specific settings permissions -->
                <div v-if="!hasAnySettingsPermission" class="rounded-2xl bg-yellow-50 border border-yellow-100 p-6 shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Limited Access</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>You do not have the required permissions to view or modify system settings. Please contact an administrator for assistance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';

const page = usePage();

const hasAnySettingsPermission = computed(() => {
    const can = page.props.auth?.can || {};
    return can.permission_manage || 
           can.system_settings || 
           can.manage_system || 
           can.view_system || 
           can.asset_manage || 
           can.setting_manage || 
           page.props.auth?.isAdmin;
});
</script>