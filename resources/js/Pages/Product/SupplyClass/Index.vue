<template>
    <AuthenticatedLayout
        title="Supply Classes"
        description="Manage supply class settings"
        img="/assets/images/products.png"
    >
        <div class="flex justify-between items-center mb-6">
            <div>
                <div class="flex items-center space-x-2 text-sm text-gray-600 mb-2">
                    <Link :href="route('products.index')" class="hover:text-gray-900 transition-colors duration-200">
                        Products
                    </Link>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span class="text-gray-900">Supply Classes</span>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Supply Class Settings</h2>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="openUploadModal"
                    :disabled="isUploading"
                    class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg font-medium text-sm text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                >
                    <svg v-if="!isUploading" class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <svg v-else class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ isUploading ? 'Uploading...' : 'Import Excel' }}
                </button>
                <Link
                    :href="route('products.supply-classes.create')"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 shadow-sm"
                >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create Supply Class
                </Link>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 max-w-md">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search supply class, category, or source..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <select
                            v-model="perPage"
                            class="px-3 py-2.5 w-[200px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm"
                            @change="props.filters.page = 1"
                        >
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div v-if="!supplyClasses.data.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No supply classes found</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new supply class or importing from Excel.</p>
                <div class="mt-6 flex justify-center gap-3">
                    <Link :href="route('products.supply-classes.create')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Create Supply Class
                    </Link>
                    <button
                        @click="openUploadModal"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                        Import Excel
                    </button>
                </div>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full overflow-hidden text-sm text-left table-sm rounded-t-lg">
                    <thead>
                        <tr style="background-color: #F4F7FB;">
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Supply Class</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Category</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Source</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Status</th>
                            <th class="px-3 py-2 text-xs font-bold text-right" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in supplyClasses.data"
                            :key="item.id"
                            class="hover:bg-gray-50 transition-colors duration-150 border-b"
                            style="border-bottom: 1px solid #B7C6E6;"
                        >
                            <td class="px-3 py-2 font-medium text-gray-800">{{ item.supply_class }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ item.category || '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ item.source || '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': item.is_active,
                                        'bg-red-100 text-red-800': !item.is_active,
                                    }"
                                >
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <Link
                                        :href="route('products.supply-classes.edit', { supplyClass: item.id })"
                                        class="inline-flex items-center p-1.5 border border-transparent rounded-lg text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        title="Edit"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </Link>
                                    <button
                                        @click="confirmDelete(item)"
                                        class="inline-flex items-center p-1.5 border border-transparent rounded-lg text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                        title="Delete"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                    <button
                                        @click="confirmToggleStatus(item)"
                                        :disabled="loadingItems.has(item.id)"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="{
                                            'opacity-50 cursor-not-allowed': loadingItems.has(item.id),
                                            'bg-red-500': !item.is_active,
                                            'bg-green-500': item.is_active,
                                        }"
                                        title="Toggle Status"
                                    >
                                        <span
                                            :class="{
                                                'translate-x-5': item.is_active,
                                                'translate-x-0': !item.is_active,
                                                'bg-gray-400 animate-pulse': loadingItems.has(item.id),
                                            }"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                        ></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            <TailwindPagination :data="supplyClasses" :limit="2" @pagination-change-page="getResults" />
        </div>

        <!-- Excel Upload Modal -->
        <div
            v-if="showUploadModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click="closeUploadModal"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Import Supply Classes</h3>
                    <button @click="closeUploadModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600">Upload an Excel file with columns: <strong>supply_class</strong>, <strong>category</strong>, <strong>source</strong></p>
                    <a
                        :href="route('products.supply-classes.template.download')"
                        class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800"
                    >
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download Template
                    </a>
                    <div
                        class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:bg-gray-50 cursor-pointer"
                        @click="triggerFileInput"
                    >
                        <input
                            type="file"
                            ref="fileInput"
                            class="hidden"
                            @change="handleFileUpload"
                            accept=".xlsx,.xls,.csv"
                        />
                        <p class="text-sm text-gray-600">{{ selectedFile ? selectedFile.name : 'Click to select file' }}</p>
                    </div>
                </div>
                <div class="flex justify-end p-6 border-t border-gray-200 gap-3">
                    <button
                        @click="closeUploadModal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="uploadFile"
                        :disabled="!selectedFile || isUploading"
                        class="px-4 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ isUploading ? 'Uploading...' : 'Upload' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import axios from "axios";
import { TailwindPagination } from "laravel-vue-pagination";
import { useToast } from "vue-toastification";
import Swal from "sweetalert2";

const toast = useToast();

const props = defineProps({
    supplyClasses: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search || "");
const perPage = ref(props.filters.per_page || "25");
const loadingItems = ref(new Set());
const showUploadModal = ref(false);
const selectedFile = ref(null);
const fileInput = ref(null);
const isUploading = ref(false);

function getResults(page = 1) {
    props.filters.page = page;
}

function updateRoute() {
    const query = {};
    if (search.value) query.search = search.value;
    if (perPage.value) query.per_page = perPage.value;
    if (props.filters.page) query.page = props.filters.page;
    router.get(route("products.supply-classes.index"), query, {
        preserveState: true,
        preserveScroll: true,
        only: ["supplyClasses", "filters"],
    });
}

watch([() => search.value, () => perPage.value, () => props.filters.page], () => {
    updateRoute();
});

function confirmToggleStatus(item) {
    Swal.fire({
        title: "Are you sure?",
        text: `Do you want to ${item.is_active ? "deactivate" : "activate"} this supply class?`,
        showCancelButton: true,
        confirmButtonText: "Yes, proceed!",
    }).then((result) => {
        if (result.isConfirmed) {
            loadingItems.value.add(item.id);
            axios.get(route("products.supply-classes.toggle-status", item.id))
                .then(() => {
                    toast.success("Status updated");
                    updateRoute();
                })
                .catch(() => toast.error("An error occurred"))
                .finally(() => loadingItems.value.delete(item.id));
        }
    });
}

function confirmDelete(item) {
    Swal.fire({
        title: "Are you sure?",
        text: `Delete "${item.supply_class}"?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete!",
    }).then((result) => {
        if (result.isConfirmed) {
            axios.delete(route("products.supply-classes.destroy", item.id))
                .then(() => {
                    toast.success("Supply class deleted");
                    updateRoute();
                })
                .catch(() => toast.error("Could not delete"));
        }
    });
}

const openUploadModal = () => { showUploadModal.value = true; };
const closeUploadModal = () => {
    showUploadModal.value = false;
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = null;
};
const triggerFileInput = () => fileInput.value?.click();

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file && [".xlsx", ".xls", ".csv"].includes("." + file.name.split(".").pop().toLowerCase())) {
        selectedFile.value = file;
    }
}

async function uploadFile() {
    if (!selectedFile.value) return;
    isUploading.value = true;
    const formData = new FormData();
    formData.append("file", selectedFile.value);
    try {
        const res = await axios.post(route("products.supply-classes.import"), formData);
        toast.success(res.data.message);
        closeUploadModal();
        updateRoute();
    } catch (err) {
        toast.error(err.response?.data?.message || "Import failed");
    } finally {
        isUploading.value = false;
    }
}
</script>
