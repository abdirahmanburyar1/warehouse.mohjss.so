<template>
    <AuthenticatedLayout title="System Configuration" description="Favicon and logo settings" img="/assets/images/settings.png">
        <Head title="System Configuration" />
        <div class="p-6 max-w-2xl">
            <Link
                :href="route('settings.index')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 mb-6 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to settings
            </Link>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">System Configuration</h1>
            <p class="mt-1 text-sm text-slate-500 mb-8">
                Customize the favicon (browser tab icon) and logo displayed in the application header and login page.
            </p>

            <form @submit.prevent="submit" class="space-y-8">
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 space-y-6">
                    <!-- Favicon -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Favicon</label>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center bg-slate-100 rounded-lg border border-slate-200 overflow-hidden">
                                <img
                                    v-if="faviconPreview"
                                    :src="faviconPreview"
                                    alt="Favicon preview"
                                    class="w-12 h-12 object-contain"
                                />
                                <span v-else class="text-2xl text-slate-400">🔖</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input
                                    ref="faviconInput"
                                    type="file"
                                    accept=".ico,.png,.jpg,.jpeg,.gif,.svg"
                                    class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200"
                                    @change="onFaviconChange"
                                />
                                <p class="text-xs text-slate-500">Recommended: 32×32 or 64×64 PNG/ICO. Max 512 KB.</p>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.remove_favicon" class="rounded border-slate-300" />
                                    <span class="text-sm text-slate-600">Revert to default favicon</span>
                                </label>
                                <p v-if="form.errors.favicon" class="text-sm text-red-600">{{ form.errors.favicon }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Logo -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Logo</label>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-32 h-16 flex items-center justify-center bg-slate-100 rounded-lg border border-slate-200 overflow-hidden">
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt="Logo preview"
                                    class="max-w-full max-h-full object-contain"
                                />
                                <span v-else class="text-2xl text-slate-400">🖼️</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input
                                    ref="logoInput"
                                    type="file"
                                    accept=".png,.jpg,.jpeg,.gif,.svg"
                                    class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200"
                                    @change="onLogoChange"
                                />
                                <p class="text-xs text-slate-500">PNG, JPG, GIF or SVG. Max 2 MB. Used in header and login page.</p>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.remove_logo" class="rounded border-slate-300" />
                                    <span class="text-sm text-slate-600">Revert to default logo</span>
                                </label>
                                <p v-if="form.errors.logo" class="text-sm text-red-600">{{ form.errors.logo }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                        </svg>
                        {{ form.processing ? 'Saving…' : 'Save' }}
                    </button>
                    <p v-if="page.props.flash?.success" class="text-sm text-emerald-600">{{ page.props.flash.success }}</p>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const page = usePage();
const props = defineProps({
    faviconUrl: { type: String, default: '' },
    logoUrl: { type: String, default: '' },
});

const faviconInput = ref(null);
const logoInput = ref(null);

const form = useForm({
    favicon: null,
    logo: null,
    remove_favicon: false,
    remove_logo: false,
});

// Reset form when props change (after successful save)
watch(() => [props.faviconUrl, props.logoUrl], () => {
    form.favicon = null;
    form.logo = null;
    form.remove_favicon = false;
    form.remove_logo = false;
    if (faviconInput.value) faviconInput.value.value = '';
    if (logoInput.value) logoInput.value.value = '';
}, { immediate: false });

const faviconPreview = computed(() => {
    if (form.favicon instanceof File) {
        return URL.createObjectURL(form.favicon);
    }
    return props.faviconUrl || null;
});

const logoPreview = computed(() => {
    if (form.logo instanceof File) {
        return URL.createObjectURL(form.logo);
    }
    return props.logoUrl || null;
});

function onFaviconChange(e) {
    const file = e.target.files?.[0];
    form.favicon = file || null;
    if (file) form.remove_favicon = false;
}

function onLogoChange(e) {
    const file = e.target.files?.[0];
    form.logo = file || null;
    if (file) form.remove_logo = false;
}

function submit() {
    form.post(route('settings.system-config.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>
