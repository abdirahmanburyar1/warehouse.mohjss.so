<template>
    <div>
        <Head title="Warehouse AMC Report" />
        <AuthenticatedLayout 
            title="Warehouse AMC Report" 
            description="View and analyze warehouse Average Monthly Consumption data"
            img="/assets/images/products.png"
        >
            <template #header>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Warehouse AMC Report
                </h2>
            </template>

            <div class="py-6 space-y-6">
                <!-- Header Card with Actions -->
                <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">Warehouse AMC Management</h3>
                            <p class="text-sm text-gray-600">
                                Manage and track monthly consumption data for the warehouse.
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <button
                                @click="openTemplateModal"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8l-8-8-8 8"></path>
                                </svg>
                                Template
                            </button>
                            <button
                                @click="openUploadModal"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                </svg>
                                Upload Data
                            </button>
                            <button
                                @click="exportData"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export
                            </button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search Products</label>
                            <div class="absolute inset-y-0 left-0 pl-3 pt-6 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input 
                                v-model="search" 
                                type="text"
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                placeholder="Filter results..." 
                            />
                        </div>
                        <div class="sm:w-48">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Year</label>
                            <select 
                                v-model="year" 
                                @change="applyFilters"
                                class="block w-full border border-gray-300 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option v-for="yearOption in years" :key="yearOption" :value="yearOption">{{ yearOption }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Pivot Table Card -->
                <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider w-12 border-r border-gray-200">
                                        SN
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 border-r border-gray-200 min-w-[300px]">
                                        Item / Product Name
                                    </th>
                                    <th v-for="monthYear in monthYears" :key="monthYear" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider min-w-[100px] border-r border-gray-200">
                                        {{ formatMonthYear(monthYear) }}
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-blue-800 uppercase tracking-wider min-w-[100px] bg-blue-50">
                                        AMC
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template v-if="filteredPivotData && filteredPivotData.length > 0">
                                    <tr v-for="(product, index) in filteredPivotData" :key="product.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-3 py-4 text-xs text-center text-gray-400 border-r border-gray-200 font-mono">
                                            {{ (index + 1).toString().padStart(2, '0') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-gray-900 sticky left-0 bg-white border-r border-gray-200 min-w-[300px]">
                                            {{ product.name }}
                                        </td>
                                        <td v-for="monthYear in monthYears" :key="monthYear" class="px-3 py-4 whitespace-nowrap text-sm text-center text-gray-700 border-r border-gray-200">
                                            {{ formatNumber(product.months[monthYear] || 0) }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-center text-blue-900 font-black bg-blue-50">
                                            {{ product.amc === null || product.amc === undefined ? '0' : formatNumber(product.amc) }}
                                        </td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr>
                                        <td :colspan="3 + monthYears.length" class="px-6 py-12 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-12 h-12 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                </svg>
                                                <p>No warehouse AMC records found for this criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Redesigned Upload Modal -->
            <div v-if="showUploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeUploadModal">
                <div class="relative top-20 mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-xl bg-white" @click.stop>
                    <div>
                        <div class="flex items-center justify-between mb-4 border-b pb-4">
                            <h3 class="text-xl font-bold text-gray-900">
                                Upload Warehouse AMC Data
                            </h3>
                            <button @click="closeUploadModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-4">
                                Update monthly consumption data for all products in the main warehouse.
                            </p>
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-blue-700">
                                            Blank cells in your Excel will be treated as <strong>0</strong>. Products must match existing names exactly. 
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- File Input Drag & Drop Zone -->
                            <div class="mb-4">
                                <input 
                                    ref="fileInput"
                                    type="file" 
                                    accept=".xlsx,.xls"
                                    @change="handleFileUpload"
                                    class="hidden"
                                >
                                
                                <div v-if="!selectedFile" 
                                     @click="triggerFileInput" 
                                     class="border-2 border-dashed border-gray-300 rounded-xl p-10 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-all group">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-indigo-100 transition-colors">
                                        <svg class="w-8 h-8 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900">Click or drag Excel file to upload</p>
                                    <p class="text-xs text-gray-500 mt-1">Supports .xlsx and .xls (Max 20MB)</p>
                                </div>
                                
                                <div v-else class="border border-indigo-200 rounded-xl p-4 bg-indigo-50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-gray-900">{{ selectedFile.name }}</p>
                                                <p class="text-xs text-gray-500">{{ (selectedFile.size / 1024).toFixed(1) }} KB</p>
                                            </div>
                                        </div>
                                        <button @click="removeSelectedFile" class="text-gray-400 hover:text-red-500 transition-colors p-1">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progress Bar Section -->
                            <div v-if="isUploading" class="mt-6 space-y-2">
                                <div class="flex justify-between items-center text-xs font-semibold uppercase tracking-wider">
                                    <span class="text-indigo-600 animate-pulse">{{ uploadStatus }}</span>
                                    <span class="text-gray-500">{{ uploadProgress }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden border border-gray-200">
                                    <div class="bg-indigo-600 h-full transition-all duration-300 ease-out" :style="{ width: uploadProgress + '%' }"></div>
                                </div>
                            </div>
                            
                            <!-- Detailed Results Summary -->
                            <div v-if="uploadResults" class="mt-6">
                                <div v-if="uploadResults.success" class="rounded-xl border border-green-200 bg-green-50 p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 bg-green-100 rounded-full p-1 mr-3">
                                            <svg class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-green-900">Upload Processed</h4>
                                            <p class="text-xs text-green-700 mt-1">{{ uploadResults.message }}</p>
                                            
                                            <div v-if="uploadResults.results" class="grid grid-cols-2 gap-2 mt-3">
                                                <div class="bg-white rounded-lg p-2 border border-green-100 shadow-sm text-center">
                                                    <div class="text-[10px] text-gray-500 uppercase font-bold">New</div>
                                                    <div class="text-lg font-black text-green-600">{{ uploadResults.results.imported }}</div>
                                                </div>
                                                <div class="bg-white rounded-lg p-2 border border-green-100 shadow-sm text-center">
                                                    <div class="text-[10px] text-gray-500 uppercase font-bold">Updated</div>
                                                    <div class="text-lg font-black text-indigo-600">{{ uploadResults.results.updated }}</div>
                                                </div>
                                            </div>

                                            <div v-if="uploadResults.warnings?.length" class="mt-4">
                                                <p class="text-[10px] font-bold text-red-600 uppercase mb-1">Warnings / Missing Products</p>
                                                <div class="bg-white rounded-lg p-2 border border-red-100 max-h-32 overflow-y-auto text-xs text-red-700 font-medium font-mono">
                                                    <div v-for="(err, i) in uploadResults.warnings" :key="i" class="py-0.5 border-b border-red-50 last:border-0">• {{ err }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-else class="rounded-xl border border-red-200 bg-red-50 p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 bg-red-100 rounded-full p-1 mr-3">
                                            <svg class="h-4 w-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-red-900">Upload Failed</h4>
                                            <p class="text-xs text-red-700 mt-1">{{ uploadResults.message }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Buttons -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t mt-6">
                            <button 
                                @click="closeUploadModal" 
                                class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-bold text-xs uppercase tracking-widest hover:bg-gray-200 transition-colors">
                                Close
                            </button>
                            <button 
                                v-if="!uploadResults"
                                @click="uploadFile"
                                :disabled="!selectedFile || isUploading"
                                class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg font-bold text-xs uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-md active:scale-95">
                                {{ isUploading ? 'Processing...' : 'Upload Data' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Download Modal -->
            <div v-if="showTemplateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeTemplateModal">
                <div class="relative top-20 mx-auto p-6 border w-96 shadow-xl rounded-xl bg-white" @click.stop>
                    <div>
                        <div class="flex items-center justify-between mb-4 border-b pb-4">
                            <h3 class="text-lg font-bold text-gray-900 text-center w-full">
                                Download Template
                            </h3>
                            <button @click="closeTemplateModal" class="text-gray-400 hover:text-gray-600 absolute right-6">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-4 pt-2">
                            <p class="text-xs text-gray-500 mb-6 text-center">
                                Select a year to generate a customized AMC template.
                            </p>
                            
                            <div class="mb-6">
                                <label class="block text-[10px] font-black uppercase text-gray-400 mb-1 tracking-widest">Year</label>
                                <select 
                                    v-model="templateYear" 
                                    class="block w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50"
                                >
                                    <option v-for="yearOption in years" :key="yearOption" :value="yearOption">{{ yearOption }}</option>
                                </select>
                            </div>

                            <button 
                                @click="downloadTemplate"
                                class="w-full py-3 bg-blue-600 text-white rounded-lg font-bold text-xs uppercase tracking-widest hover:bg-blue-700 transition-all shadow-md active:scale-95 flex items-center justify-center"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const toast = useToast();

const props = defineProps({
    products: Object,
    pivotData: Array,
    monthYears: Array,
    filters: Object,
    years: Array,
    months: Array,
});

// Reactive variables
const search = ref(props.filters.search || '');
const year = ref(props.filters.year || new Date().getFullYear());
const templateYear = ref(new Date().getFullYear());

// Upload related variables
const showUploadModal = ref(false);
const selectedFile = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadStatus = ref('Ready');
const uploadResults = ref(null);
const fileInput = ref(null);
const importId = ref(null);
const progressInterval = ref(null);

// Template modal
const showTemplateModal = ref(false);

// Watch for year changes
watch(year, () => {
    applyFilters();
});

// Methods
const applyFilters = () => {
    router.get(route('inventories.warehouse-amc'), {
        year: year.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const filteredPivotData = computed(() => {
    if (!search.value || !props.pivotData) return props.pivotData || [];
    const term = search.value.toLowerCase();
    return (props.pivotData || []).filter(product =>
        (product.name || '').toLowerCase().includes(term)
    );
});

const exportData = () => {
    const params = new URLSearchParams();
    if (search.value) params.append('search', search.value);
    if (year.value) params.append('year', year.value.toString());
    window.open(route('inventories.warehouse-amc.export') + '?' + params.toString(), '_blank');
};

const openTemplateModal = () => showTemplateModal.value = true;
const closeTemplateModal = () => showTemplateModal.value = false;

const openUploadModal = () => {
    showUploadModal.value = true;
    resetUploadState();
};

const closeUploadModal = () => {
    if (isUploading.value) {
        if (!confirm("Processing is in progress. Closing this will not stop the background import. Close anyway?")) {
            return;
        }
    }
    showUploadModal.value = false;
    resetUploadState();
};

const resetUploadState = () => {
    selectedFile.value = null;
    isUploading.value = false;
    uploadProgress.value = 0;
    uploadStatus.value = 'Ready';
    uploadResults.value = null;
    importId.value = null;
    if (fileInput.value) fileInput.value.value = null;
    if (progressInterval.value) {
        clearInterval(progressInterval.value);
        progressInterval.value = null;
    }
};

const triggerFileInput = () => fileInput.value.click();

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (!file.name.match(/\.(xlsx|xls)$/i)) {
        toast.error('Invalid file type. Please upload an Excel file.');
        event.target.value = null;
        return;
    }

    selectedFile.value = file;
    uploadResults.value = null;
};

const removeSelectedFile = () => {
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = null;
};

const uploadFile = async () => {
    if (!selectedFile.value) return;

    isUploading.value = true;
    uploadProgress.value = 5;
    uploadStatus.value = 'Uploading...';
    uploadResults.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);

        const response = await axios.post(route('inventories.warehouse-amc.import'), formData);

        if (response.data.success) {
            importId.value = response.data.import_id;
            uploadStatus.value = 'Uploaded, processing...';
            uploadProgress.value = 15;
            startProgressPolling(response.data.import_id);
        } else {
            uploadResults.value = { success: false, message: response.data.message };
            isUploading.value = false;
        }
    } catch (error) {
        isUploading.value = false;
        uploadResults.value = {
            success: false,
            message: error.response?.data?.message || 'Server error during upload.'
        };
    }
};

const startProgressPolling = (id) => {
    progressInterval.value = setInterval(async () => {
        try {
            const response = await axios.get(route('inventories.warehouse-amc.import.status', { importId: id }));
            if (response.data.success) {
                const status = response.data.data;
                uploadProgress.value = status.progress;
                uploadStatus.value = status.message;
                
                if (status.status === 'completed') {
                    clearInterval(progressInterval.value);
                    progressInterval.value = null;
                    isUploading.value = false;
                    uploadResults.value = {
                        success: true,
                        message: status.message,
                        results: status.results,
                        warnings: status.results?.errors || []
                    };
                    toast.success('Warehouse data updated!');
                    setTimeout(() => applyFilters(), 1000);
                } else if (status.status === 'failed') {
                    clearInterval(progressInterval.value);
                    progressInterval.value = null;
                    isUploading.value = false;
                    uploadResults.value = { success: false, message: status.message };
                    toast.error(status.message);
                }
            }
        } catch (e) { console.warn(e); }
    }, 2000);
};

const downloadTemplate = () => {
    const url = route('inventories.warehouse-amc.template') + '?year=' + templateYear.value;
    window.location.href = url;
    toast.info(`Downloading ${templateYear.value} template...`);
    closeTemplateModal();
};

const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0);

const formatMonthYear = (monthYear) => {
    if (!monthYear) return '';
    const [y, m] = monthYear.split('-');
    const date = new Date(y, m - 1);
    return date.toLocaleString('en-US', { month: 'short', year: '2-digit' });
};
</script>
