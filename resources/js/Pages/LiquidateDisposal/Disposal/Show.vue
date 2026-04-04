<template>
    <Head title="Disposal Details" />
    <AuthenticatedLayout
        title="Disposal Details"
        description="View disposal information and items"
        img="/assets/images/orders.png"
    >
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-red-600 to-pink-700 rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-3">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Disposal {{ disposal.disposal_id }}</h1>
                        <p class="text-red-100">View disposal details and items</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('liquidate-disposal.index')"
                        class="inline-flex items-center px-4 py-2 bg-white text-red-600 rounded-lg font-semibold hover:bg-red-50 transition-colors duration-200"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to List
                    </Link>
                </div>
            </div>
        </div>

        <!-- Disposal Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Disposal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Disposal ID</p>
                    <p class="text-lg font-semibold text-gray-900">{{ disposal.disposal_id }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Source</p>
                    <p class="text-lg font-semibold text-gray-900">{{ disposal.source_display || disposal.source?.replace('_', ' ') || 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Status</p>
                    <span :class="getStatusClasses(disposal.status)" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full">
                        {{ disposal.status }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Disposed By</p>
                    <p class="text-lg font-semibold text-gray-900">{{ disposedByName }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Disposal Date</p>
                    <p class="text-lg font-semibold text-gray-900">{{ formatDate(disposal.disposed_at) }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Items</p>
                    <p class="text-lg font-semibold text-gray-900">{{ disposal.items?.length || 0 }} items</p>
                </div>
            </div>

            <!-- Approval Information -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-md font-semibold text-gray-900 mb-3">Approval Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-if="disposal.reviewed_at" class="flex items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Reviewed By</p>
                            <p class="text-sm text-gray-900">{{ disposal.reviewedBy?.name || 'N/A' }}</p>
                            <p class="text-xs text-gray-500">Reviewed At: {{ formatDate(disposal.reviewed_at) }}</p>
                        </div>
                    </div>
                    <div v-if="disposal.approved_at" class="flex items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Approved By</p>
                            <p class="text-sm text-gray-900">{{ disposal.approvedBy?.name || 'N/A' }}</p>
                            <p class="text-xs text-gray-500">Approved At: {{ formatDate(disposal.approved_at) }}</p>
                        </div>
                    </div>
                    <div v-if="!disposal.reviewed_at && !disposal.approved_at" class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-500">Awaiting approval process</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-2 mb-6 px-6">
            <div class="relative">
                <!-- Timeline Track Background -->
                <div class="absolute top-7 left-0 right-0 h-2 bg-gray-200 z-0"></div>

                <!-- Timeline Progress -->
                <div class="absolute top-7 left-0 h-2 bg-green-500 z-0 transition-all duration-500 ease-in-out"
                    :style="{
                        width: `${(statusOrder.indexOf(disposal.status) /
                            (statusOrder.length - 1)) *
                            100
                            }%`,
                    }"></div>

                <!-- Timeline Steps -->
                <div class="relative flex justify-between">
                    <!-- Pending -->
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                            statusOrder.indexOf(disposal.status) >=
                                statusOrder.indexOf('pending')
                                ? 'bg-white border-orange-500'
                                : 'bg-white border-gray-200',
                        ]">
                            <svg class="w-7 h-7 text-orange-500" :class="statusOrder.indexOf(disposal.status) >=
                                statusOrder.indexOf('pending')
                                ? ''
                                : 'opacity-40'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(disposal.status) >=
                            statusOrder.indexOf('pending')
                            ? 'text-green-600'
                            : 'text-gray-500'
                            ">Pending</span>
                    </div>

                    <!-- Reviewed -->
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                            statusOrder.indexOf(disposal.status) >=
                                statusOrder.indexOf('reviewed')
                                ? 'bg-white border-blue-500'
                                : 'bg-white border-gray-200',
                        ]">
                            <svg class="w-7 h-7 text-blue-500" :class="statusOrder.indexOf(disposal.status) >=
                                statusOrder.indexOf('reviewed')
                                ? ''
                                : 'opacity-40'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(disposal.status) >=
                            statusOrder.indexOf('reviewed')
                            ? 'text-green-600'
                            : 'text-gray-500'
                            ">Reviewed</span>
                    </div>

                    <!-- Approved -->
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                            statusOrder.indexOf(disposal.status) >=
                                statusOrder.indexOf('approved')
                                ? 'bg-white border-green-500'
                                : 'bg-white border-gray-200',
                        ]">
                            <svg class="w-7 h-7 text-green-500" :class="statusOrder.indexOf(disposal.status) >=
                                statusOrder.indexOf('approved')
                                ? ''
                                : 'opacity-40'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(disposal.status) >=
                            statusOrder.indexOf('approved')
                            ? 'text-green-600'
                            : 'text-gray-500'
                            ">Approved</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disposal Items -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Disposal Items</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-sm">
                    <thead style="background-color: #F4F7FB;">
                        <tr>
                            <th class="text-left text-xs font-bold uppercase rounded-tl-lg px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Product
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Product Category
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Quantity
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Unit Cost
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Total Cost
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Type
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Location
                            </th>
                            <th class="text-left text-xs font-bold uppercase rounded-tr-lg px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Note
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-if="disposal.items?.length === 0" class="align-middle">
                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                No items found
                            </td>
                        </tr>
                        <tr v-for="item in disposal.items" :key="item.id" class="hover:bg-gray-50 border-b align-middle" style="border-bottom: 1px solid #B7C6E6;">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>
                                    <div class="font-medium text-gray-900">{{ item.product?.name || 'N/A' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.product?.category?.name || 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.quantity }} {{ item.uom }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ formatNumber(item.unit_cost) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ formatNumber(item.total_cost) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="capitalize">{{ item.type || 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.location || 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="max-w-xs truncate" :title="item.note">
                                    {{ item.note || 'No note' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Disposal Status Actions -->
        <div class="mt-8 mb-6 bg-white rounded-lg shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">
                Disposal Status Actions
            </h3>
            <div class="flex items-start mb-6">
                <!-- Status Action Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-4 px-1 py-2">
                    <!-- Review button -->
                    <div class="relative">
                        <div class="flex flex-col">
                            <button @click="changeStatus(disposal.id, 'reviewed', 'is_reviewing')" 
                                :disabled="isType['is_reviewing'] || disposal.status !== 'pending' || !$page.props.auth.can.disposal_review || !props.disposal.can_review"
                                :class="[
                                    disposal.status === 'pending'
                                    ? 'bg-yellow-500 hover:bg-yellow-600'
                                    : ['reviewed', 'approved'].includes(disposal.status)
                                        ? 'bg-green-500'
                                        : 'bg-gray-300 cursor-not-allowed',
                                ]" 
                                class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-bold text-white">{{
                                    isType["is_reviewing"]
                                        ? "Please Wait..."
                                        : ['reviewed', 'approved'].includes(disposal.status)
                                        ? "Reviewed"
                                        : "Review"
                                }}</span>
                            </button>
                            <span v-show="disposal?.reviewed_at" class="text-sm text-gray-600">
                                On {{ moment(disposal?.reviewed_at).format('DD/MM/YYYY HH:mm') }}
                            </span>
                            <span v-show="disposal?.reviewed_by" class="text-sm text-gray-600">
                                By {{ disposal?.reviewed_by?.name }}
                            </span>
                            <!-- Authority Indicator -->
                            <span v-if="disposal.status === 'pending' && !props.disposal.can_review && $page.props.auth.user.warehouse?.type === 'central'" 
                                class="text-[10px] text-blue-600 mt-1 font-medium italic">
                                * Review by Regional Warehouse
                            </span>
                        </div>
                        <div v-if="disposal.status === 'pending'"
                            class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                    </div>

                    <!-- Approved button -->
                    <div class="relative">
                        <div class="flex flex-col">
                            <button @click="changeStatus(disposal.id, 'approved', 'is_approve')" 
                                :disabled="isType['is_approve'] || disposal.status !== 'reviewed' || !$page.props.auth.can.disposal_approve || !props.disposal.can_approve"
                                :class="[
                                    disposal.status === 'reviewed'
                                    ? 'bg-yellow-500 hover:bg-yellow-600'
                                    : disposal.status === 'approved'
                                    ? 'bg-green-500'
                                    : 'bg-gray-300 cursor-not-allowed',
                            ]" 
                            class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                <svg v-if="isType['is_approve']" 
                                    class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4">
                                    </circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <template v-else>
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-sm font-bold text-white">{{
                                        isType["is_approve"] 
                                            ? "Please Wait..." 
                                            : disposal.status === 'approved'
                                            ? "Approved"
                                            : "Approve"
                                    }}</span>
                                </template>
                            </button>
                            <span v-show="disposal?.approved_by" class="text-sm text-gray-600">
                                On {{ moment(disposal?.approved_at).format('DD/MM/YYYY HH:mm') }}
                            </span>
                            <span v-show="disposal?.approved_by" class="text-sm text-gray-600">
                                By {{ disposal?.approved_by?.name }}
                            </span>
                            <!-- Authority Indicator -->
                            <div class="flex flex-col mt-1">
                                <span v-if="disposal.status === 'reviewed' && !props.disposal.can_approve && $page.props.auth.user.warehouse?.type === 'regional'" 
                                    class="text-[10px] text-blue-600 font-medium italic">
                                    * Approval by Central Warehouse
                                </span>
                                <span v-if="disposal.status === 'pending' && $page.props.auth.user.warehouse?.type === 'central'" 
                                    class="text-[10px] text-amber-600 font-medium italic">
                                    * Awaiting Regional Review
                                </span>
                            </div>
                        </div>
                        <div v-if="disposal.status === 'reviewed'"
                            class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import moment from 'moment';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    disposal: Object,
});

const disposedByName = computed(() => {
    const d = props.disposal;
    return d?.disposed_by_name ?? d?.disposedBy?.name ?? d?.disposed_by?.name ?? '—';
});

// Status configuration
const statusOrder = ['pending', 'reviewed', 'approved'];

// Loading states
const isLoading = ref(false);
const isType = ref({
    is_reviewing: false,
    is_approve: false,
});

const formatDate = (date) => {
    if (!date) return 'N/A';
    return moment(date).format('DD/MM/YYYY HH:mm');
};

const formatNumber = (number) => {
    return Number(number).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

const getStatusClasses = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        reviewed: 'bg-blue-100 text-blue-800',
        approved: 'bg-green-100 text-green-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const changeStatus = (disposalId, newStatus, type) => {
    console.log(disposalId, newStatus, type);
    
    Swal.fire({
        title: "Are you sure?",
        text: `Do you want to change the disposal status to ${newStatus}?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, change it!",
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Set loading state
            isType.value[type] = true;

            // Map the status to the correct route name
            const routeMap = {
                'reviewed': 'review',
                'approved': 'approve',
            };
            
            const routeName = routeMap[newStatus] || newStatus;
            const routeUrl = route(`liquidate-disposal.disposals.${routeName}`, disposalId);

            await axios
                .post(routeUrl, {}, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    withCredentials: true
                })
                .then((response) => {
                    // Reset loading state
                    isType.value[type] = false;

                    Swal.fire({
                        title: "Updated!",
                        text: "Disposal status has been updated.",
                        icon: "success",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    }).then(() => {
                        // Reload the page to show the updated status
                        router.reload();
                    });
                })
                .catch((error) => {
                    // Reset loading state
                    isType.value[type] = false;

                    Swal.fire({
                        title: "Error!",
                        text:
                            error.response?.data?.message ||
                            "Failed to update disposal status",
                        icon: "error",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    });
                });
        }
    });
}

</script> 