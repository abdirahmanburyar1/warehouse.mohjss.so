<template>
    <AuthenticatedLayout
        title="Pending Asset Approvals"
        description="Comprehensive asset tracking and approval system"
        img="/assets/images/asset-header.png"
    >
        <div class="space-y-6">


            <!-- Filter and Search Section -->
            <div
                class="bg-white rounded-xl mb-6 shadow-sm border border-gray-100"
            >
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row lg:items-end gap-6">
                        <div class="flex-1">
                            <label
                                for="search"
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Search Items</label
                            >
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                </span>
                                <input
                                    v-model="search"
                                    type="text"
                                    id="search"
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-gray-50/50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all sm:text-sm"
                                    placeholder="Search by name, tag, serial..."
                                />
                            </div>
                        </div>

                        <div class="w-full lg:w-72">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Select Asset (Batch)</label
                            >
                            <Multiselect
                                v-model="assetFilter"
                                :options="props.unapprovedAssets || []"
                                placeholder="Select an Asset"
                                label="asset_number"
                                track-by="id"
                                :show-labels="false"
                                :close-on-select="true"
                                class="asset-selector-multiselect"
                                @select="reloadAssets"
                                @remove="reloadAssets"
                            />
                        </div>

                        <div class="flex items-center space-x-3">
                            <select
                                v-model="per_page"
                                @change="
                                    props.filters.page = 1;
                                    reloadAssets();
                                "
                                class="border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-gray-50/50"
                            >
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                            </select>

                            <button
                                @click="clearFilters"
                                class="inline-flex items-center px-5 py-2.5 bg-gray-100 text-gray-600 font-medium rounded-xl hover:bg-gray-200 hover:text-gray-900 transition-all text-sm"
                            >
                                <svg
                                    class="w-4 h-4 mr-2"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assets Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-24">
                <div
                    v-if="loading"
                    class="flex justify-center items-center py-12"
                >
                    <div
                        class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"
                    ></div>
                </div>

                <div
                    v-else-if="props.assets.data.length === 0"
                    class="text-center py-12"
                >
                    <svg
                        v-if="!hasActiveFilters"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-16 h-16 text-gray-400 mx-auto mb-4"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"
                        />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="w-16 h-16 text-gray-400 mx-auto mb-4"
                    >
                        <path
                            d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27z"
                        />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        {{
                            hasActiveFilters
                                ? "No items found"
                                : "Select an asset to view pending items"
                        }}
                    </h3>
                    <p class="text-gray-500">
                        {{
                            hasActiveFilters
                                ? "No items match your search or selection."
                                : "Please select an asset from the dropdown above to review its items."
                        }}
                    </p>
                </div>

                <div
                    v-else
                    class="relative bg-white/90 backdrop-blur-sm rounded-xl shadow-sm ring-1 ring-gray-200/70"
                >
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background-color: #f4f7fb">
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Asset Tag & Name</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Serial Number</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Category</span>
                                </th>
                                <th
                                    class="px-3 py-3 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Type</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Status</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Assignee</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Region</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Location</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Sub Location</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Acquisition Date</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Value</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold border-r"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                        border-right-color: #b7c6e6;
                                    "
                                >
                                    <span>Fund Source</span>
                                </th>
                                <th
                                    class="px-3 py-2 text-xs font-bold"
                                    style="
                                        color: #4f6fcb;
                                        border-bottom: 2px solid #b7c6e6;
                                    "
                                >
                                    <span>Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white divide-y divide-y divide-gray-100"
                        >
                            <tr
                                v-for="asset in props.assets.data"
                                :key="asset.id"
                                class="odd:bg-white even:bg-gray-50/60 hover:bg-indigo-50 transition-colors duration-150 border-b border-gray-100 group"
                            >
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        <Link
                                            :href="
                                                route(
                                                    'assets.show',
                                                    asset.asset_id ||
                                                        asset.asset?.id,
                                                )
                                            "
                                            class="text-blue-600 hover:text-blue-800 underline"
                                        >
                                            {{ asset.asset_tag || "N/A" }}
                                        </Link>
                                        <div
                                            class="text-xs font-semibold text-gray-900 mt-1"
                                        >
                                            {{ asset.asset_name || "N/A" }}
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.serial_number || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.category?.name || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.type?.name || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <span
                                        :class="{
                                            'bg-green-100 text-green-800':
                                                asset.status === 'functioning',
                                            'bg-orange-100 text-orange-800':
                                                asset.status ===
                                                    'maintenance' ||
                                                asset.status ===
                                                    'not_functioning',
                                            'bg-red-100 text-red-800':
                                                asset.status === 'disposed',
                                            'bg-gray-100 text-gray-800': ![
                                                'functioning',
                                                'maintenance',
                                                'not_functioning',
                                                'disposed',
                                            ].includes(asset.status),
                                        }"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    >
                                        <span
                                            v-if="
                                                asset.status === 'functioning'
                                            "
                                            class="w-2 h-2 bg-green-400 rounded-full mr-1"
                                        ></span>
                                        <span
                                            v-else-if="
                                                asset.status ===
                                                    'maintenance' ||
                                                asset.status ===
                                                    'not_functioning'
                                            "
                                            class="w-2 h-2 bg-orange-400 rounded-full mr-1 animate-pulse"
                                        ></span>
                                        <span
                                            v-else
                                            class="w-2 h-2 bg-gray-400 rounded-full mr-1"
                                        ></span>
                                        {{ formatStatus(asset.status) }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.assignee?.name || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.region?.name || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.facility?.name || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.sub_location?.name || "N/A" }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ formatDate(asset.acquisition_date) }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{
                                            formatCurrency(asset.original_value)
                                        }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-top border-r border-gray-100"
                                >
                                    <div class="text-xs text-gray-900">
                                        {{ asset.fund_source?.name || "N/A" }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top text-center text-gray-400 italic text-xs">
                                    Batch Approval Required
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="hasActiveFilters"
                    class="bg-gray-50 px-6 py-3 border-t border-gray-200 mb-[80px] flex justify-between"
                >
                    <div class="text-sm text-gray-500">
                        Showing {{ props.assets?.meta?.from || 0 }} to
                        {{ props.assets?.meta?.to || 0 }} of
                        {{ props.assets?.meta?.total || 0 }} assets
                    </div>
                    <div class="flex items-center justify-end">
                        <TailwindPagination
                            v-if="props.assets?.meta"
                            :data="props.assets"
                            @pagination-change-page="getResults"
                            :limit="2"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Float Action Panel -->
        <div
            v-if="assetFilter && assetItem"
            class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 animate-in fade-in slide-in-from-bottom-8 duration-500"
        >
            <div
                class="bg-white/80 backdrop-blur-xl border border-blue-100 shadow-[0_20px_50px_rgba(0,0,0,0.15)] rounded-2xl px-6 py-4 flex items-center gap-8 ring-1 ring-black/5"
            >
                <!-- Asset Info -->
                <div class="flex items-center gap-4 pr-8 border-r border-gray-200/50">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-0.5">Current Batch</div>
                        <div class="text-sm font-bold text-gray-900 leading-tight uppercase">{{ assetItem.asset_number }}</div>
                        <div class="text-[10px] text-gray-500 mt-0.5">{{ props.assets.meta?.total || 0 }} items pending</div>
                    </div>
                </div>

                <!-- Workflow Actions -->
                <div class="flex items-center gap-3">
                    <!-- Review Button -->
                    <button
                        v-if="!assetItem.reviewed_at && !assetItem.approved_at && page.props.auth.can.asset_review"
                        @click="handleBatchReview(assetItem)"
                        :disabled="processingAction"
                        class="group relative flex items-center gap-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-6 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg hover:shadow-blue-200 active:scale-95 overflow-hidden"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/10 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <img src="/assets/images/review.png" class="w-5 h-5 brightness-0 invert" />
                        <span>{{ processingAction ? 'Reviewing...' : 'Review Batch' }}</span>
                    </button>

                    <!-- Approve Button (Dependent on Review) -->
                    <button
                        v-if="assetItem.reviewed_at && !assetItem.approved_at && page.props.auth.can.asset_approve"
                        @click="handleBatchApprove(assetItem)"
                        :disabled="processingAction"
                        class="group relative flex items-center gap-3 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white px-6 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg hover:shadow-green-200 active:scale-95 overflow-hidden"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/10 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <img src="/assets/images/approved.png" class="w-5 h-5 brightness-0 invert" />
                        <span>{{ processingAction ? 'Approving...' : 'Approve Batch' }}</span>
                    </button>

                    <!-- Reject Button -->
                    <button
                        v-if="!assetItem.approved_at && (page.props.auth.can.asset_reject)"
                        @click="handleBatchReject(assetItem)"
                        :disabled="processingAction"
                        class="flex items-center gap-3 bg-white hover:bg-red-50 text-red-600 border border-red-200 px-6 py-2.5 rounded-xl font-bold text-sm transition-all active:scale-95"
                    >
                        <img src="/assets/images/rejected.png" class="w-5 h-5" />
                        <span>Reject</span>
                    </button>

                    <!-- Visual Progress Indicator -->
                    <div class="ml-4 flex items-center gap-3">
                        <div class="flex items-center gap-1.5" :class="assetItem.reviewed_at ? 'text-green-600' : 'text-gray-400'">
                            <div class="w-2 h-2 rounded-full shadow-sm"
                                :class="assetItem.reviewed_at ? 'bg-green-500 ring-4 ring-green-100' : 'bg-gray-300'"></div>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Reviewed</span>
                        </div>
                        <div class="w-4 h-0.5" :class="assetItem.reviewed_at ? 'bg-green-300' : 'bg-gray-200'"></div>
                        <div class="flex items-center gap-1.5" :class="assetItem.approved_at ? 'text-green-600' : 'text-gray-400'">
                            <div class="w-2 h-2 rounded-full shadow-sm"
                                :class="assetItem.approved_at ? 'bg-green-500 ring-4 ring-green-100' : 'bg-gray-300'"></div>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Approved</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <TransitionRoot as="template" :show="showReviewModal">
            <Dialog
                as="div"
                class="fixed inset-0 z-50 overflow-y-auto"
                @close="showReviewModal = false"
            >
                <div
                    class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
                >
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-300"
                        enter-from="opacity-0"
                        enter-to="opacity-100"
                        leave="ease-in duration-200"
                        leave-from="opacity-100"
                        leave-to="opacity-0"
                    >
                        <DialogOverlay
                            class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        />
                    </TransitionChild>

                    <span
                        class="hidden sm:inline-block sm:h-screen sm:align-middle"
                        aria-hidden="true"
                        >&#8203;</span
                    >

                    <TransitionChild
                        as="template"
                        enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div
                            class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100"
                        >
                            <div class="px-8 pt-8 pb-4 border-b border-gray-50">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <DialogTitle
                                            as="h3"
                                            class="text-xl font-bold text-gray-900"
                                        >
                                            Review Asset Item
                                        </DialogTitle>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Verify item details before approval
                                        </p>
                                    </div>
                                    <button
                                        @click="showReviewModal = false"
                                        class="p-2 text-gray-400 hover:text-gray-500 rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        <svg
                                            class="w-6 h-6"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="px-8 py-8" v-if="selectedItemForReview">
                                <div class="grid grid-cols-2 gap-x-8 gap-y-6">
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Asset Tag</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview.asset_tag ||
                                                "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Serial Number</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview.serial_number ||
                                                "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div class="col-span-2">
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Asset Name</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview.asset_name ||
                                                "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Category</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview
                                                    .category?.name || "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Type</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview.type
                                                    ?.name || "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Status</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                formatStatus(
                                                    selectedItemForReview.status,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Assignee</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview
                                                    .assignee?.name || "N/A"
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Acquisition Date</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                formatDate(
                                                    selectedItemForReview.acquisition_date,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Original Value</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                formatCurrency(
                                                    selectedItemForReview.original_value,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="col-span-2">
                                        <label
                                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"
                                            >Fund Source</label
                                        >
                                        <p
                                            class="text-sm font-semibold text-gray-900 bg-gray-50 p-2 rounded-lg border border-gray-100"
                                        >
                                            {{
                                                selectedItemForReview
                                                    .fund_source?.name ||
                                                "N/A"
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-8 flex items-center justify-end space-x-3"
                                >
                                    <button
                                        @click="showReviewModal = false"
                                        class="px-6 py-2.5 text-sm font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition-all"
                                    >
                                        Close
                                    </button>
                                    
                                    <!-- Batch Review Action -->
                                    <button
                                        v-if="!selectedItemForReview.asset.reviewed_at && page.props.auth.can.asset_review"
                                        @click="handleBatchReview(selectedItemForReview.asset); showReviewModal = false;"
                                        :disabled="processingAction"
                                        class="px-6 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-lg shadow-blue-500/20 transition-all disabled:opacity-50"
                                    >
                                        Review Entire Batch
                                    </button>

                                    <!-- Batch Reject Action -->
                                    <button
                                        v-if="selectedItemForReview.asset.reviewed_at && !selectedItemForReview.asset.approved_at && page.props.auth.can.asset_approve"
                                        @click="handleBatchReject(selectedItemForReview.asset); showReviewModal = false;"
                                        :disabled="processingAction"
                                        class="px-6 py-2.5 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-lg shadow-red-500/20 transition-all disabled:opacity-50"
                                    >
                                        Reject Entire Batch
                                    </button>

                                    <!-- Batch Approve Action -->
                                    <button
                                        v-if="selectedItemForReview.asset.reviewed_at && !selectedItemForReview.asset.approved_at && page.props.auth.can.asset_approve"
                                        @click="handleBatchApprove(selectedItemForReview.asset); showReviewModal = false;"
                                        :disabled="processingAction"
                                        class="px-6 py-2.5 text-sm font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl shadow-lg shadow-green-500/20 transition-all disabled:opacity-50"
                                    >
                                        Approve Entire Batch
                                    </button>
                                </div>
                            </div>
                        </div>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch, computed, onMounted, reactive } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import { TailwindPagination } from "laravel-vue-pagination";
import Swal from "sweetalert2";
import { debounce } from "lodash";
import { format } from "date-fns";
import moment from "moment";
import axios from "axios";
import {
    Dialog,
    DialogOverlay,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from "@headlessui/vue";
const toast = {
    error: (msg) => Swal.fire({ icon: 'error', title: 'Error', text: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }),
    success: (msg) => Swal.fire({ icon: 'success', title: 'Success', text: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 })
};

const props = defineProps({
    assets: Object,
    unapprovedAssets: Array,
    assetItem: Object,
    filters: Object,
    districts: Array,
    facilities: Array,
    regions: Array,
    categories: Array,
    types: Array,
    assignees: Array,
    fundSources: Array,
});

const page = usePage();
const loading = ref(false);
const search = ref(props.filters.search || "");
const per_page = ref(props.filters.per_page || "10");
const assetFilter = ref(null);
const processingAction = ref(false);
const showReviewModal = ref(false);
const selectedItemForReview = ref(null);

function openReviewModal(item) {
    selectedItemForReview.value = item;
    showReviewModal.value = true;
}

function reloadAssets() {
    router.get(route("assets.approvals.index"), {
        search: search.value,
        per_page: per_page.value,
        selectedAsset: assetFilter.value ? assetFilter.value.asset_number : null,
        page: props.filters.page,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['assets', 'filters', 'assetItem'],
        onStart: () => {
            loading.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

onMounted(() => {
    if (props.filters?.selectedAsset && props.unapprovedAssets) {
        assetFilter.value = props.unapprovedAssets.find(
            (a) => a.asset_number == props.filters.selectedAsset
        );
    }
});

const hasActiveFilters = computed(() => search.value !== "" || assetFilter.value !== null);

function getResults(page = 1) {
    props.filters.page = page;
    reloadAssets();
}

function clearFilters() {
    search.value = "";
    assetFilter.value = null;
    reloadAssets();
}

const formatStatus = (status) => {
    if (!status) return "N/A";
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const formatDate = (date) => {
    if (!date) return "N/A";
    return format(new Date(date), 'MMM dd, yyyy');
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0);
};

// Batch Actions with SweetAlert2
async function handleBatchReview(asset) {
    const result = await Swal.fire({
        title: 'Review Asset Batch?',
        text: `Are you sure you want to mark batch ${asset.asset_number} as reviewed?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'Yes, Review',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    });

    if (result.isConfirmed) {
        processingAction.value = true;
        try {
            await axios.post(route('assets.review', asset.id));
            toast.success('Batch marked as reviewed');
            router.reload();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to review batch');
        } finally {
            processingAction.value = false;
        }
    }
}

async function handleBatchApprove(asset) {
    const result = await Swal.fire({
        title: 'Approve Asset Batch?',
        text: `Are you sure you want to finalize batch ${asset.asset_number}? Items will be moved to active inventory.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, Approve',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    });

    if (result.isConfirmed) {
        processingAction.value = true;
        try {
            await axios.post(route('assets.approve', asset.id));
            toast.success('Batch approved successfully');
            router.reload();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to approve batch');
        } finally {
            processingAction.value = false;
        }
    }
}

async function handleBatchReject(asset) {
    const { value: reason } = await Swal.fire({
        title: 'Reject Asset Batch?',
        input: 'textarea',
        inputLabel: 'Reason for rejection',
        inputPlaceholder: 'Type your reason here...',
        inputAttributes: { 'aria-label': 'Type your reason here' },
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Reject',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        inputValidator: (value) => {
            if (!value) return 'You need to provide a reason for rejection!';
        }
    });

    if (reason) {
        processingAction.value = true;
        try {
            await axios.post(route('assets.reject', asset.id), { rejection_reason: reason });
            toast.success('Batch rejected');
            router.reload();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to reject batch');
        } finally {
            processingAction.value = false;
        }
    }
}

// debouncedSearch...
watch(search, () => debouncedSearch());
</script>

<style scoped>
.asset-selector-multiselect :deep(.multiselect__tags) {
    border-radius: 12px !important;
    border: 1px solid #e2e8f0 !important;
    padding-top: 6px !important;
    min-height: 42px !important;
    background: #f9fafb !important;
}
</style>
