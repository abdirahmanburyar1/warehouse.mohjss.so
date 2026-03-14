<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const toast = useToast();

const props = defineProps({
    facilities: Array,
    canUpload: Boolean,
});

const showUploadModal = ref(false);
const selectedFacility = ref(null);
const selectedFile = ref(null);
const fileInput = ref(null);
const isUploading = ref(false);

const openUploadModal = () => {
    selectedFacility.value = null;
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    selectedFacility.value = null;
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

const onFileChange = (e) => {
    const files = e.target?.files;
    selectedFile.value = files?.length ? files[0] : null;
};

const downloadTemplate = () => {
    if (!selectedFacility.value) {
        toast.error('Please select a facility first.');
        return;
    }
    const url = route('inventories.facility-inventory.template', { facility_id: selectedFacility.value.id });
    window.open(url, '_blank');
    toast.success('Template download started.');
};

const submitUpload = async () => {
    if (!selectedFacility.value) {
        toast.error('Please select a facility.');
        return;
    }
    if (!selectedFile.value) {
        toast.error('Please select an Excel file.');
        return;
    }

    const loadingToast = toast.info('Uploading...', {
        timeout: false,
        closeOnClick: false,
        draggable: false,
    });

    isUploading.value = true;
    const formData = new FormData();
    formData.append('facility_id', selectedFacility.value.id);
    formData.append('file', selectedFile.value);

    try {
        const response = await axios.post(
            route('inventories.facility-inventory.import'),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        toast.dismiss(loadingToast);
        isUploading.value = false;
        closeUploadModal();
        toast.success(response.data.message || 'Facility inventory imported successfully.');
    } catch (error) {
        isUploading.value = false;
        toast.dismiss(loadingToast);
        toast.error(error.response?.data?.message || 'Upload failed. Please try again.');
    }
};
</script>

<template>
    <Head title="Facility Inventory" />

    <AuthenticatedLayout
        title="Facility Inventory"
        description="Upload facility inventory from Excel (Supply Chain only)"
    >
        <div class="py-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Facility Inventory</h2>
                    <p class="text-gray-600 mb-6">
                        Upload facility inventory from an Excel file. Select a facility and file, then submit.
                        Only items eligible for the selected facility will be imported.
                    </p>

                    <button
                        @click="openUploadModal"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all shadow-sm"
                    >
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Upload Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <Modal :show="showUploadModal" @close="closeUploadModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Upload Facility Inventory</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Facility <span class="text-red-500">*</span></label>
                        <Multiselect
                            v-model="selectedFacility"
                            :options="props.facilities || []"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false"
                            placeholder="Select a facility..."
                            :allow-empty="true"
                            track-by="id"
                            label="name"
                            :custom-label="(opt) => `${opt.name} (${opt.facility_type || 'N/A'})`"
                            class="facility-multiselect w-full"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="downloadTemplate"
                                :disabled="!selectedFacility"
                                :class="[
                                    !selectedFacility ? 'bg-gray-300 cursor-not-allowed' : 'bg-slate-100 hover:bg-slate-200',
                                ]"
                                class="px-3 py-2 rounded-md text-sm font-medium text-slate-700 border border-slate-300 transition"
                            >
                                Download Template (.xlsx)
                            </button>
                            <span v-if="!selectedFacility" class="text-xs text-gray-500">Select facility first</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Template includes eligible items and category for the selected facility.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Excel File <span class="text-red-500">*</span></label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            @change="onFileChange"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                        />
                        <p v-if="selectedFile" class="mt-1 text-xs text-gray-500">{{ selectedFile.name }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="closeUploadModal"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitUpload"
                        :disabled="isUploading || !selectedFacility || !selectedFile"
                        :class="[
                            isUploading || !selectedFacility || !selectedFile
                                ? 'bg-gray-400 cursor-not-allowed'
                                : 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
                        ]"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition"
                    >
                        <svg v-if="isUploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        {{ isUploading ? 'Uploading...' : 'Submit' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
