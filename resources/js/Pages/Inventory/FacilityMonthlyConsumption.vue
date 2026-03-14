<template>
    <div>
        <Head title="Facility AMC" />
        <AuthenticatedLayout
            title="Facility AMC"
            description="Upload and view facility monthly consumption data (Screened AMC)"
            img="/assets/images/report.png"
        >
            <template #header>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Facility AMC
                </h2>
            </template>

            <div class="py-6 space-y-6">
                <!-- Header with Action Buttons -->
                <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">Facility AMC Management</h3>
                            <p class="text-sm text-gray-600">
                                View and manage monthly consumption data for facility AMC.
                            </p>
                        </div>
                        <div class="flex space-x-3">
                            <button
                                @click="downloadTemplate"
                                :disabled="!selectedFacility"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8l-8-8-8 8"></path>
                                </svg>
                                Download Template
                            </button>
                            <button
                                @click="openUploadModal"
                                :disabled="!selectedFacility"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                </svg>
                                Upload Data
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Facility</label>
                            <Multiselect
                                v-model="selectedFacility"
                                :options="props.facilities || []"
                                :searchable="true"
                                :close-on-select="true"
                                :show-labels="false"
                                placeholder="Select facility"
                                track-by="id"
                                label="name"
                                class="w-full facility-multiselect"
                            >
                                <template #option="{ option }">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="truncate">{{ option.name }}</span>
                                        <span class="text-xs text-gray-500">{{ option.facility_type }}</span>
                                    </div>
                                </template>
                            </Multiselect>
                        </div>
                        <div class="flex items-end space-x-3">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select
                                    v-model="selectedYear"
                                    class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm py-2 px-3"
                                >
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                            <button
                                type="button"
                                @click="loadData"
                                :disabled="loading || !selectedFacility"
                                class="inline-flex items-center px-6 py-2 bg-emerald-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 h-[38px]"
                            >
                                <svg
                                    v-if="loading"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ loading ? "Loading..." : "Load Data" }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table card -->
                <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                        <div class="flex-1 max-w-md">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter items</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input
                                    v-model="itemFilter"
                                    type="text"
                                    placeholder="Search by product name..."
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>
                        </div>
                        <div v-if="rows.length" class="text-right">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ facilityInfo?.name }} ({{ selectedYear }})
                            </span>
                            <p class="text-xs text-gray-500">Showing {{ filteredRows.length }} items</p>
                        </div>
                    </div>

                    <div v-if="message" class="mb-4 text-sm text-gray-600 italic">
                        {{ message }}
                    </div>

                    <div v-if="filteredRows.length" class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 border-r border-gray-200 min-w-[300px]">
                                        Product / Item
                                    </th>
                                    <th v-for="m in months" :key="m.key" class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider border-r border-gray-200 min-w-[100px]">
                                        {{ m.label }}
                                    </th>
                                    <th v-if="showAmcColumn" class="px-4 py-3 text-center text-xs font-bold text-blue-800 uppercase tracking-wider bg-blue-50 min-w-[100px]">
                                        AMC
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="row in filteredRows" :key="row.product_id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 sticky left-0 bg-white border-r border-gray-200 min-w-[300px]">
                                        {{ row.item || "—" }}
                                        <div class="text-[10px] text-gray-400 font-normal uppercase mt-0.5">
                                            {{ row.category }} • {{ row.dosage_form }}
                                        </div>
                                    </td>
                                    <td v-for="m in months" :key="m.key" class="px-4 py-3 text-sm text-center text-gray-900 border-r border-gray-200">
                                        {{ row.quantities?.[m.key] != null ? formatNum(row.quantities[m.key]) : "0" }}
                                    </td>
                                    <td v-if="showAmcColumn" class="px-4 py-3 text-sm text-center text-blue-900 font-bold bg-blue-50">
                                        {{ row.amc != null ? formatNum(row.amc) : "0" }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else-if="!loading" class="py-12 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400 mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-gray-900">No data found</h3>
                        <p class="mt-1 text-sm text-gray-500">Select a facility and click "Load Data" to see results.</p>
                    </div>
                </div>
            </div>

            <!-- New Upload Modal (Warehouse Design) -->
            <div v-if="showUploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeUploadModal">
                <div class="relative top-20 mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-xl bg-white" @click.stop>
                    <div>
                        <div class="flex items-center justify-between mb-4 border-b pb-4">
                            <h3 class="text-xl font-bold text-gray-900">
                                Upload Facility AMC Data
                            </h3>
                            <button @click="closeUploadModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-4">
                                Uploading data for: <span class="font-bold text-indigo-600">{{ selectedFacility?.name }}</span>
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
                                    @change="handleFileChange"
                                    class="hidden"
                                >
                                
                                <div v-if="!selectedFile" 
                                     @click="fileInput.click()" 
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
                                        <button @click="removeFile" class="text-gray-400 hover:text-red-500 transition-colors p-1">
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

                                            <div v-if="uploadResults.results?.errors?.length" class="mt-4">
                                                <p class="text-[10px] font-bold text-red-600 uppercase mb-1">Warnings / Missing Products</p>
                                                <div class="bg-white rounded-lg p-2 border border-red-100 max-h-32 overflow-y-auto text-xs text-red-700 font-medium">
                                                    <div v-for="(err, i) in uploadResults.results.errors" :key="i" class="py-0.5 border-b border-red-50 last:border-0">• {{ err }}</div>
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
                                @click="startUpload"
                                :disabled="!selectedFile || isUploading"
                                class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg font-bold text-xs uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-md active:scale-95">
                                {{ isUploading ? 'Processing...' : 'Upload Data' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import axios from "axios";
import { useToast } from "vue-toastification";

const toast = useToast();

const props = defineProps({
    facilities: Array,
    selectedFacilityId: Number,
    currentYear: Number,
    currentMonth: Number,
    yearOptions: Array,
});

const selectedFacility = ref(null);
const selectedYear = ref(props.currentYear);

// Modal and Upload state
const showUploadModal = ref(false);
const selectedFile = ref(null);
const fileInput = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadStatus = ref("Ready to upload");
const uploadResults = ref(null);
const progressPollingInterval = ref(null);

const loading = ref(false);
const months = ref([]);
const rows = ref([]);
const facilityInfo = ref(null);
const itemFilter = ref("");
const message = ref("");

onMounted(() => {
    if (props.selectedFacilityId && Array.isArray(props.facilities)) {
        selectedFacility.value = props.facilities.find(f => f.id === props.selectedFacilityId) || null;
    }
});

const openUploadModal = () => {
    if (!selectedFacility.value) {
        toast.warning("Please select a facility first.");
        return;
    }
    showUploadModal.value = true;
    resetUploadState();
};

const closeUploadModal = () => {
    if (isUploading.value) {
        if (!confirm("Import is still in progress. Closing this window will not stop the import, but you won't see the progress. Close anyway?")) {
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
    uploadStatus.value = "Ready to upload";
    uploadResults.value = null;
    if (fileInput.value) fileInput.value.value = null;
    stopPolling();
};

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const ext = file.name.split(".").pop().toLowerCase();
    if (!["xlsx", "xls"].includes(ext)) {
        toast.error("Please upload an Excel file (.xlsx or .xls).");
        e.target.value = null;
        return;
    }

    selectedFile.value = file;
    uploadResults.value = null;
};

const removeFile = () => {
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = null;
};

const startUpload = async () => {
    if (!selectedFile.value || !selectedFacility.value) return;

    try {
        isUploading.value = true;
        uploadStatus.value = "Uploading file...";
        uploadProgress.value = 5;
        uploadResults.value = null;

        const formData = new FormData();
        formData.append("file", selectedFile.value);
        formData.append("facility_id", selectedFacility.value.id);

        const { data } = await axios.post(
            route("inventories.facility-amc.upload"),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        );

        if (data.success) {
            uploadStatus.value = "Processing data...";
            uploadProgress.value = 20;
            
            if (data.import_id) {
                startPolling(data.import_id);
            } else {
                // Legacy / Synchronous response
                isUploading.value = false;
                uploadResults.value = {
                    success: true,
                    message: data.message,
                    results: data.results || data.data
                };
                toast.success(data.message);
                loadData();
            }
        } else {
            isUploading.value = false;
            uploadResults.value = { success: false, message: data.message };
            toast.error(data.message);
        }
    } catch (e) {
        console.error(e);
        isUploading.value = false;
        uploadResults.value = {
            success: false,
            message: e.response?.data?.message || "Internal server error occurred during upload."
        };
        toast.error(uploadResults.value.message);
    }
};

const startPolling = (importId) => {
    stopPolling();
    progressPollingInterval.value = setInterval(async () => {
        try {
            const { data } = await axios.get(route('inventories.facility-amc.import.status', { importId }));
            
            if (data.success) {
                const status = data.data;
                uploadProgress.value = status.progress;
                uploadStatus.value = status.message;

                if (status.status === 'completed') {
                    stopPolling();
                    isUploading.value = false;
                    uploadResults.value = {
                        success: true,
                        message: status.message,
                        results: status.results
                    };
                    toast.success("Import processed successfully!");
                    loadData();
                } else if (status.status === 'failed') {
                    stopPolling();
                    isUploading.value = false;
                    uploadResults.value = {
                        success: false,
                        message: status.message
                    };
                    toast.error(status.message);
                }
            }
        } catch (e) {
            console.warn("Polling error:", e);
        }
    }, 2000);
};

const stopPolling = () => {
    if (progressPollingInterval.value) {
        clearInterval(progressPollingInterval.value);
        progressPollingInterval.value = null;
    }
};

const loadData = async () => {
    if (!selectedFacility.value) return;

    try {
        loading.value = true;
        message.value = "";
        const { data } = await axios.get(
            route("inventories.facility-amc.data"),
            {
                params: {
                    year: Number(selectedYear.value),
                    facility_id: selectedFacility.value.id,
                },
            }
        );

        if (!data?.success) {
            message.value = data?.message || "Failed to load monthly consumption data.";
            return;
        }

        months.value = data.months || [];
        rows.value = data.rows || [];
        facilityInfo.value = data.facility || null;
        message.value = data.message || "";
    } catch (e) {
        message.value = e.response?.data?.message || "Connection error. Failed to load table.";
    } finally {
        loading.value = false;
    }
};

const downloadTemplate = () => {
    if (!selectedFacility.value) return;
    const url = route("inventories.facility-amc.template", {
        year: Number(selectedYear.value),
        facility_id: selectedFacility.value.id,
    });
    window.location.href = url;
    toast.info("Downloading template...");
};

const formatNum = (value) => {
    const num = Number(value || 0);
    return new Intl.NumberFormat("en-US").format(num);
};

const filteredRows = computed(() => {
    if (!itemFilter.value.trim()) return rows.value;
    const q = itemFilter.value.toLowerCase();
    return rows.value.filter((r) => (r.item || "").toLowerCase().includes(q));
});

const showAmcColumn = computed(() => {
    return Number(selectedYear.value) === Number(props.currentYear);
});
</script>

<style scoped>
.facility-multiselect :deep(.multiselect__content-wrapper) {
    max-height: 280px;
}
</style>
