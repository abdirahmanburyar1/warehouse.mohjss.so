<template>
    <AuthenticatedLayout
        title="Packing List"
        img="/assets/images/orders.png"
        description="Manage your packing list"
    >
        <!-- Back Navigation -->
        <Link
            :href="route('supplies.packing-list.showPK')"
            class="inline-flex items-center text-gray-600 hover:text-indigo-600 transition-colors duration-200 group mb-6"
        >
            <svg
                class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 19l-7-7 7-7"
                ></path>
            </svg>
            Back to Supplies
        </Link>

        <!-- Header Section -->
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-2xl p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Packing List</h1>
                    <p class="text-gray-600 mt-1">Create and manage packing lists for received items</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">Processing</div>
                </div>
            </div>
        </div>

        <!-- Purchase Order Selection Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Purchase Order Selection
            </h2>
            <div class="max-w-md">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Purchase Order</label>
                <Multiselect
                    v-model="form"
                    :value="form?.purchase_order_id"
                    :options="props.purchaseOrders"
                    :searchable="true"
                    :close-on-select="true"
                    :show-labels="false"
                    :allow-empty="true"
                    placeholder="Search and select purchase order..."
                    track-by="id"
                    label="po_number"
                    class="multiselect-modern"
                    @select="handlePOSelect"
                >
                </Multiselect>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="animate-pulse space-y-6">
                <div class="h-6 bg-gray-200 rounded w-1/4"></div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-3">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                    </div>
                    <div class="space-y-3">
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                    </div>
                    <div class="space-y-3">
                        <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supplier Information Card -->
        <div v-else-if="!isLoading && form" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Supplier Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Company Details</h3>
                    <p class="text-base font-semibold text-gray-900">{{ form.supplier?.name }}</p>
                    <p class="text-sm text-gray-600">{{ form.supplier?.contact_person }}</p>
                </div>
                <div class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Contact Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            {{ form.supplier?.email }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            {{ form.supplier?.phone }}
                        </div>
                        <div class="flex items-start text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            </svg>
                            {{ form.supplier?.address }}
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Packing List Details</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">PL Number:</span>
                            <input type="text" v-model="form.packing_list_number"
                                class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                placeholder="Enter PL number" />
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">Reference No:</span>
                            <input type="text" v-model="form.ref_no"
                                class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                placeholder="Enter reference" />
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">P.O Date:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ moment(form.po_date).format("DD/MM/YYYY") }}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">PL Date:</span>
                            <input type="date" v-model="form.pk_date"
                                class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                :min="form.po_date" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div v-if="!isLoading && form" class="mt-4 w-full">
            <table class="min-w-full divide-y divide-gray-200 table-auto">
                <colgroup>
                    <col class="w-8" />
                    <col class="w-48" />
                    <col class="w-[120px]" />
                    <col class="w-[150px]" />
                    <col class="w-64" />
                    <col class="w-56" />
                    <col class="w-28" />
                </colgroup>
                <thead class="bg-gray-50 border border-black">
                    <tr>
                        <th class="px-3 py-2 text-xs font-bold rounded-tl-lg sticky left-0 z-10 w-8 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            #
                        </th>
                        <th class="px-3 py-2 text-xs font-bold sticky left-8 z-10 w-[200px] text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Item
                        </th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            UoM
                        </th>
                        <th class="px-3 py-2 text-xs font-bold w-32 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Qty
                        </th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Locations
                        </th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Item Detail
                        </th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Unit Cost
                        </th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Total Cost
                        </th>
                        <th class="px-3 py-2 text-xs font-bold rounded-tr-lg w-20 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                            Fulfillment
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in form.items" :key="index"
                        :class="{
                            'hover:bg-gray-50 transition-colors duration-150': true,
                            'bg-red-50': hasIncompleteBackOrder(item),
                            'border-red-500 border-2': item.hasError,
                            'bg-red-50/20': item.hasError,
                        }"
                        style="border-bottom: 1px solid #B7C6E6;"
                        :data-row="index + 1"
                    >
                        <td class="px-3 py-2 text-xs text-gray-900 sticky left-0 z-10 bg-white w-8"
                            style="border-bottom: 1px solid #B7C6E6;">
                            {{ index + 1 }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 sticky left-8 z-10 bg-white w-[200px]"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <p class="text-xs text-break">
                                {{ item.product?.name }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <span class="font-bold text-xs text-gray-500">{{ item.uom }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 w-32"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <div class="flex flex-col">
                                <div>
                                    <input type="number" v-model="item.quantity" readonly
                                        class="block w-full text-left text-black focus:ring-0 sm:text-sm border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <div>
                                    <label for="received_quantity text-xs" class="text-xs">Received QTY</label>
                                    <input type="number" v-model="item.received_quantity" required min="1"
                                        :disabled="item.status == 'approved'"
                                        class="block w-full text-left text-black focus:ring-0 sm:text-sm border-0 bg-transparent border-b border-gray-300"
                                        @input="handleReceivedQuantityChange(index)" />
                                </div>
                                <div>
                                    <label for="mismatches" class="text-xs">Mismatches</label>
                                    <input type="text" :value="calculateMismatches(item)" readonly
                                        class="block w-full text-left text-black focus:ring-0 sm:text-sm border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <button v-if="calculateFulfillmentRate(item) < 100" @click="openBackOrderModal(index)"
                                    class="mt-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    Back Order
                                </button>

                                <!-- Add tooltip for incomplete back orders -->
                                <div v-if="calculateFulfillmentRate(item) < 100 || !hasRequiredFields(item)" 
                                    :class="{
                                        'mt-2 text-xs px-2 py-1 rounded': true,
                                        'bg-red-100 text-red-800': !hasRequiredFields(item) || getMismatchStatus(item).status === 'unrecorded' || getMismatchStatus(item).status === 'partial',
                                        'bg-yellow-100 text-yellow-800': getMismatchStatus(item).status === 'excess',
                                        'bg-green-100 text-green-800': hasRequiredFields(item) && getMismatchStatus(item).status === 'complete'
                                    }">
                                    {{ !hasRequiredFields(item) ? 'Missing required fields' : getMismatchStatus(item).message }}
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <div class="space-y-1">
                                <div class="grid grid-cols-7 gap-1 text-[10px] text-gray-500 font-medium">
                                    <span class="col-span-2 text-right pr-1">Qty</span>
                                    <span class="col-span-4">Location</span>
                                    <span class="col-span-1 text-center">&nbsp;</span>
                                </div>

                                <div v-for="(alloc, aIndex) in item.allocations" :key="aIndex"
                                    class="grid grid-cols-7 gap-1 items-center">
                                    <!-- Allocated quantity -->
                                    <input
                                        type="number"
                                        v-model.number="alloc.quantity"
                                        min="0"
                                        class="col-span-2 w-full text-right px-1 py-0.5 border border-gray-300 rounded text-xs"
                                        :disabled="item.status === 'approved' || !item.received_quantity || item.received_quantity <= 0"
                                        @input="syncAllocatedWithReceived(item, aIndex)"
                                    />

                                    <!-- Location select -->
                                    <Multiselect
                                        v-model="alloc.location"
                                        class="col-span-4"
                                        :disabled="item.status === 'approved' || !item.received_quantity || item.received_quantity <= 0"
                                        :options="getAllocationLocationOptions(item, aIndex)"
                                        :searchable="true"
                                        :close-on-select="true"
                                        :show-labels="false"
                                        :allow-empty="true"
                                        placeholder="Select Location"
                                        @select="handleLocationSelectForAlloc(item, aIndex, $event)"
                                        track-by="location"
                                        label="location"
                                        :custom-label="(option) =>
                                            typeof option === 'string'
                                                ? option
                                                : (option && option.location ? option.location : '')"
                                    >
                                        <template #option="{ option }">
                                            <div :class="{ 'add-new-option': typeof option === 'string' }">
                                                <span v-if="typeof option === 'string'" class="text-indigo-600 font-medium">
                                                    + {{ option }}
                                                </span>
                                                <span v-else-if="option && option.location">{{ option.location }}</span>
                                                <span v-else>Select Location</span>
                                            </div>
                                        </template>
                                    </Multiselect>

                                    <!-- Remove allocation -->
                                    <button
                                        v-if="item.allocations.length > 1"
                                        type="button"
                                        class="col-span-1 text-red-500 text-xs px-1"
                                        @click="removeAllocation(item, aIndex)"
                                    >
                                        ✕
                                    </button>
                                </div>

                                <!-- Add allocation -->
                                <button
                                    v-if="canAddAllocation(item)"
                                    type="button"
                                    class="mt-1 text-xs text-indigo-600 hover:underline"
                                    @click="addAllocation(item)"
                                    :disabled="item.status === 'approved' || !item.received_quantity || item.received_quantity <= 0"
                                >
                                    + Add location
                                </button>

                                <!-- Summary -->
                                <div class="mt-1 text-[10px]"
                                    :class="allocatedTotal(item) === (item.received_quantity || 0) ? 'text-green-700' : 'text-red-600'">
                                    Allocated: {{ allocatedTotal(item) }} / {{ item.received_quantity || 0 }}
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="space-y-1">
                                <div>
                                    <label class="text-[10px] text-block">Batch</label>
                                    <input type="text" v-model="item.batch_number" required
                                        :disabled="item.status === 'approved'"
                                        class="block w-full text-xs text-black focus:ring-0 p-1 border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <div>
                                    <label class="text-[10px] text-block">Expiry</label>
                                    <input type="date" :value="formatDateForInput(item.expire_date)" required
                                        @input="item.expire_date = $event.target.value"
                                        :min="moment().add(6, 'months').format('YYYY-MM-DD')"
                                        :disabled="item.status === 'approved'"
                                        class="block w-full text-xs text-black focus:ring-0 p-1 border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <div>
                                    <label class="text-[10px] text-block">Barcode</label>
                                    <input type="text" v-model="item.barcode" required
                                        :disabled="item.status === 'approved'"
                                        class="block w-full text-xs text-black focus:ring-0 p-1 border-0 bg-transparent border-b border-gray-300" />
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="text-sm">
                                {{ Number(item.unit_cost).toLocaleString("en-US", { style: "currency", currency: "USD" }) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="text-sm">
                                {{ Number(item.total_cost).toLocaleString("en-US", { style: "currency", currency: "USD" }) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 text-center w-20"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="text-sm">
                                <span>{{ calculateFulfillmentRate(item) }}%</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="form?.items?.length === 0">
                        <td colspan="9" class="px-3 py-4 text-center text-sm text-gray-500">
                            No items added. Click "Add Item" to start creating your purchase order.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Memo Field -->
        <div v-if="!isLoading && form" class="mt-4 bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Memo</h3>
            <textarea v-model="form.notes" rows="3"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                placeholder="Enter memo or additional notes here..."></textarea>
        </div>
        <div v-else>
            <span>No P.O Data found</span>
        </div>

        <!-- Action Buttons -->
        <div v-if="!isLoading && form" class="flex justify-end space-x-3 mb-[80px]">
            <Link :href="route('supplies.index')" :disabled="isSubmitting"
                class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Exit
            </Link>
            <button @click="submit" :disabled="isSubmitting || !canSubmit || !$page.props.auth.can.purchase_order_create" :title="submitButtonTitle"
                class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                {{ isSubmitting ? "Saving..." : "Save and Exit" }}
            </button>
        </div>

        <!-- Back Order Modal -->
        <Modal :show="showBackOrderModal" @close="attemptCloseModal" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Back Order Details</h2>
                    <button @click="attemptCloseModal" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mb-6 bg-gradient-to-r from-yellow-50 to-orange-50 p-4 rounded-lg border border-yellow-200">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 font-medium">Product:</span>
                            <p class="text-gray-900 font-semibold">{{ selectedItem?.product?.name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600 font-medium">Expected:</span>
                            <p class="text-gray-900 font-semibold">{{ selectedItem?.quantity }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600 font-medium">Received:</span>
                            <p class="text-gray-900 font-semibold">{{ selectedItem?.received_quantity || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600 font-medium">Mismatches:</span>
                            <p class="text-yellow-800 font-semibold">{{ actualMismatches }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(row, index) in backOrderRows" :key="index" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="number" v-model="row.quantity" :disabled="row.finalized != null"
                                        class="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        min="0" />
                                </td>
                                <td class="px-4 py-3">
                                    <select v-model="row.status"
                                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option v-for="status in [row.status, ...availableStatuses.filter((s) => s !== row.status)]"
                                            :key="status" :value="status">
                                            {{ status }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" v-model="row.notes"
                                        class="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        placeholder="Enter note..." />
                                </td>
                                <td class="px-4 py-3">
                                    <button @click="removeBackOrderRow(index, row)"
                                        class="text-red-600 hover:text-red-800 transition-colors duration-150">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <button @click="addBackOrderRow"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                            :disabled="!canAddMoreRows">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Row
                        </button>
                        <div class="text-sm">
                            <span :class="{ 'text-green-600': isValidForSave, 'text-red-600': !isValidForSave }">
                                {{ totalBackOrderQuantity }}
                            </span>
                            <span class="text-gray-600">
                                / {{ selectedItem?.quantity - (selectedItem?.received_quantity || 0) }} items recorded
                            </span>
                        </div>
                    </div>
                    <PrimaryButton @click="attemptCloseModal">Save and Exit</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Location Modal -->
        <Modal :show="showLocationModal" @close="showLocationModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Add New Location</h2>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="new_location" value="Location Name" />
                        <input id="new_location" type="text" class="mt-1 block w-full" v-model="newLocation" required />
                    </div>
                    <div>
                        <InputLabel for="warehouse_id" value="Warehouse" />
                        <Multiselect v-model="selectedWarehouse" :options="props.warehouses" :searchable="true"
                            :close-on-select="true" :show-labels="false" :allow-empty="false"
                            placeholder="Select Warehouse" track-by="id" label="name" required class="multiselect-modern">
                        </Multiselect>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showLocationModal = false" :disabled="isNewLocation">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewLocation || !selectedWarehouse" @click="createLocation">
                        {{ isNewLocation ? "Creating..." : "Create Location" }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { nextTick, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import axios from "axios";
import moment from "moment";
import Swal from "sweetalert2";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

import Modal from "@/Components/Modal.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

import { useToast } from "vue-toastification";

const toast = useToast();

// Add New Location option constant
const ADD_NEW_LOCATION_OPTION = {
    isAddNew: true,
    location: "Add New Location",
};

const props = defineProps({
    purchaseOrders: {
        type: Array,
        required: true,
    },
    warehouses: {
        required: true,
        type: Array,
    },
});

const form = ref(null);

const isLoading = ref(false);
const isSubmitting = ref(false);
const showLocationModal = ref(false);
const newLocation = ref("");
// For location modal: track which item + allocation index is being edited
const selectedItemIndex = ref(null); // { itemIndex, allocIndex }
const selectedWarehouse = ref(null);
const loadedLocation = ref([]);

async function loadLocations(warehouse) {
    if (!warehouse) {
        loadedLocation.value = [];
        location.value = "";
        return;
    }
    await axios
        .get(route("inventories.getLocations"), {
            params: {
                warehouse: warehouse,
            },
        })
        .then((response) => {
            loadedLocation.value = response.data;
            // Normalize existing form locations after new locations are loaded
            normalizeFormLocations();
        })
        .catch((error) => {
            toast.error(error.response.data);
        });
}

const subTotal = computed(() => {
    return (
        form.value?.items?.reduce((sum, i) => sum + i.total_cost || 0, 0) || 0
    );
});

function closeLocationModal() {
    showLocationModal.value = false;
    newLocation.value = "";
    selectedWarehouse.value = null;
}

const isNewLocation = ref(false);

async function createLocation() {
    if (!newLocation.value) {
        toast.error("Please enter a location name");
        return;
    }

    if (!selectedWarehouse.value) {
        toast.error("Please select a warehouse");
        return;
    }

    isNewLocation.value = true;

    try {
        const response = await axios.post(route("supplies.store-location"), {
            location: newLocation.value,
            warehouse: selectedWarehouse.value.name,
        });

        isNewLocation.value = false;
        const newLocationData = response.data.location;

        // Ensure the location data has the correct structure
        const formattedLocation = {
            id: newLocationData.id,
            location: newLocationData.location,
            warehouse: newLocationData.warehouse,
        };

        // Add to loadedLocation array (for the current warehouse)
        loadedLocation.value.push(formattedLocation);

        // Update the selected item's allocation location with the object
        if (selectedItemIndex.value !== null) {
            const item = form.value.items[selectedItemIndex.value.itemIndex];
            if (item && Array.isArray(item.allocations)) {
                const alloc = item.allocations[selectedItemIndex.value.allocIndex];
                if (alloc) {
                    alloc.location = formattedLocation;
                }
            }
        }

        toast.success(response.data.message);
        closeLocationModal();
    } catch (error) {
        isNewLocation.value = false;
        console.error("Location creation error:", error);
        toast.error(
            error.response?.data ||
                "An error occurred while adding the location"
        );
    }
}

function handleReceivedQuantityChange(index) {
    const item = form.value.items[index];
    // Ensure received quantity doesn't exceed total quantity
    if (item.received_quantity > item.quantity) {
        item.received_quantity = item.quantity;
    }
    calculateTotal(index);
}

function calculateTotal(index) {
    const item = form.value.items[index];
    item.total_cost = item.received_quantity * item.unit_cost;
}

function calculateMismatches(item) {
    if (!item.quantity || !item.received_quantity) return 0;
    return item.quantity - item.received_quantity;
}

function calculateFulfillmentRate(item) {
    if (!item.quantity || !item.received_quantity) return 0;
    const rate = (item.received_quantity / item.quantity) * 100;
    return rate.toFixed(2);
}

async function handlePOSelect(selected) {
    if (!selected) {
        form.value = null;
        return;
    }
    isLoading.value = true;
    await axios
        .get(route("supplies.get-purchaseOrder", selected.id))
        .then((response) => {
            isLoading.value = false;
            form.value = response.data;
            // Initialize allocations per item (default qty 0; user will distribute received quantity)
            form.value.items.forEach((item) => {
                if (!item.allocations || !Array.isArray(item.allocations) || !item.allocations.length) {
                    item.allocations = [
                        {
                            id: null,
                            location: item.location || null,
                            quantity: 0,
                        },
                    ];
                }
            });

            // Initialize locations for the current user's warehouse (first warehouse in props.warehouses)
            if (props.warehouses && props.warehouses.length > 0) {
                const userWarehouse = props.warehouses[0];
                // Preload locations for this warehouse
                loadLocations(userWarehouse.name);
            }
        })
        .catch((error) => {
            isLoading.value = false;
            console.log(error.response);
            toast.error(error);
        });
}

const validateForm = () => {
    let hasErrors = false;
    let errorItems = [];

    form.value.items.forEach((item, index) => {
        item.hasError = false;
        item.errorMessages = [];

        // Required field validation
        const requiredFields = [
            {
                field: "received_quantity",
                message: "Received quantity is required",
            },
            { field: "location", message: "Location selection is required" },
            { field: "batch_number", message: "Batch number is required" },
            { field: "expire_date", message: "Expiry date is required" },
            { field: "barcode", message: "Barcode is required" },
            { field: "uom", message: "UOM is required" },
        ];

        requiredFields.forEach(({ field, message }) => {
            if (!item[field]) {
                item.hasError = true;
                item.errorMessages.push(message);
            }
        });

        // Additional validations
        if (item.received_quantity && item.received_quantity <= 0) {
            item.hasError = true;
            item.errorMessages.push("Received quantity must be greater than 0");
        }

        if (item.expire_date && new Date(item.expire_date) <= new Date()) {
            item.hasError = true;
            item.errorMessages.push("Expiry date must be in the future");
        }

        if (item.hasError) {
            hasErrors = true;
            errorItems.push(index + 1); // Add 1 for human-readable row numbers
        }
    });

    if (hasErrors) {
        // Show global error message with row numbers
        toast.error(`Please fix the errors in rows: ${errorItems.join(", ")}`);

        // Scroll to the first error row
        if (errorItems.length > 0) {
            const firstErrorRow = document.querySelector(
                `tr[data-row="${errorItems[0]}"]`
            );
            if (firstErrorRow) {
                firstErrorRow.scrollIntoView({
                    behavior: "smooth",
                    block: "center",
                });
            }
        }
    }

    return !hasErrors;
};

// Format date to YYYY-MM-DD for HTML date inputs
function formatDateForInput(dateString) {
    if (!dateString) return "";

    // If it's already in YYYY-MM-DD format, return as is
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        return dateString;
    }

    // Parse the date and format it as YYYY-MM-DD
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return ""; // Invalid date

    return date.toISOString().split("T")[0];
}

async function submit() {
    if (!form.value?.items?.length) {
        toast.error("No items to submit");
        return;
    }

    // Flatten allocations so each allocation becomes a separate item entry for the backend
    const flattenedItems = [];
    form.value.items.forEach((item) => {
        (item.allocations || []).forEach((alloc, allocIndex) => {
            flattenedItems.push({
                id: alloc.id || null,
                po_item_id: item.id ?? item.po_item_id,
                product_id: item.product_id,
                received_quantity: alloc.quantity,
                quantity: alloc.quantity,
                location:
                    typeof alloc.location === "string"
                        ? alloc.location
                        : alloc.location?.location || null,
                uom: item.uom,
                batch_number: item.batch_number,
                expire_date: item.expire_date,
                barcode: item.barcode,
                unit_cost: item.unit_cost,
                total_cost: item.unit_cost * alloc.quantity,
                status: item.status || "pending",
                // Only attach differences to the first allocation to prevent duplicates in backend
                differences: allocIndex === 0 ? (item.differences || []) : [],
            });
        });
    });

    const preparedForm = {
        id: form.value.id,
        packing_list_number: form.value.packing_list_number,
        ref_no: form.value.ref_no,
        pk_date: form.value.pk_date,
        items: flattenedItems,
    };

    // Check for incomplete back orders with enhanced validation
    const incompleteItems = form.value.items.filter(item => !validateMismatchRecording(item));
    if (incompleteItems.length > 0) {
        const itemNames = incompleteItems.map(item => item.product?.name || 'Unknown Item').join(', ');
        toast.error(
            `Please record all mismatches for: ${itemNames}`
        );
        return;
    }

    // Format dates properly for all items
    preparedForm.items.forEach((item) => {
        if (item.expire_date) {
            item.expire_date = formatDateForInput(item.expire_date);
        }
    });

    const confirm = await Swal.fire({
        title: "Are you sure?",
        text: "You want to create packing list?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, create it!",
    });

    if (confirm.isConfirmed) {
        isSubmitting.value = true;

        await axios
            .post(route("supplies.storePK"), preparedForm)
            .then((response) => {
                console.log(response.data);
                isSubmitting.value = false;
                Swal.fire({
                    title: "Success!",
                    text: "Packing list created successfully",
                    icon: "success",
                    confirmButtonColor: "#10B981",
                }).then(() => {
                    router.visit(route("supplies.packing-list.showPK"));
                });
            })
            .catch((error) => {
                isSubmitting.value = false;
                console.log(error);
                console.error("Submit error:", error);
                toast.error(
                    error.response?.data || "Error submitting packing list"
                );
            });
    }
}

const processing = ref(false);
const showBackOrderModal = ref(false);
const selectedItem = ref(null);
const backOrderRows = ref([]);

// Computed properties for back order quantities
const totalExistingDifferences = computed(() => {
    if (!selectedItem.value?.differences) return 0;
    return selectedItem.value.differences.reduce(
        (total, diff) => total + (parseInt(diff.quantity) || 0),
        0
    );
});

const actualMismatches = computed(() => {
    if (!selectedItem.value) return 0;
    return (
        selectedItem.value.quantity -
        (selectedItem.value.received_quantity || 0)
    );
});

const missingQuantity = computed(() => {
    if (!selectedItem.value) return 0;
    return (
        selectedItem.value.quantity -
        (selectedItem.value.received_quantity || 0)
    );
});

const allocatedQuantity = computed(() => {
    return backOrderRows.value.reduce(
        (total, row) => total + (parseInt(row.quantity) || 0),
        0
    );
});

const remainingToAllocate = computed(() => {
    if (!selectedItem.value) return 0;
    const total =
        selectedItem.value.quantity -
        (selectedItem.value.received_quantity || 0);
    return total - allocatedQuantity.value;
});

function openBackOrderModal(index) {
    const item = form.value.items[index];
    if (!item) return;

    selectedItem.value = item;

    // Initialize backOrderRows with existing differences or a new row
    backOrderRows.value =
        item.differences?.length > 0
            ? [...item.differences] // Create a copy of existing differences
            : [
                  {
                      id: null,
                      quantity: 0,
                      status: "Missing",
                      notes: "",
                  },
              ];

    showBackOrderModal.value = true;
}

const hasIncompleteBackOrder = (item) => {
    if (!item?.received_quantity || item.received_quantity === item?.quantity)
        return false;

    const mismatches = item.quantity - item.received_quantity;
    const totalDifferences = (item.differences || []).reduce(
        (total, diff) => total + (parseInt(diff?.quantity) || 0),
        0
    );

    // Check if mismatch quantity equals total recorded back order quantity
    return totalDifferences !== mismatches;
};

// Function to check if item has all required fields
const allocatedTotal = (item) =>
    (item.allocations || []).reduce(
        (sum, a) => sum + (Number(a.quantity) || 0),
        0
    );

const remainingAllocation = (item) =>
    Math.max((Number(item.received_quantity) || 0) - allocatedTotal(item), 0);

const hasInvalidAllocationQuantity = (item) =>
    (item.allocations || []).some((alloc) => {
        const qty = parseFloat(alloc?.quantity);
        return !Number.isFinite(qty) || qty <= 0;
    });

const hasQtyWithoutLocation = (item) =>
    (item.allocations || []).some((alloc) => {
        const qty = parseFloat(alloc?.quantity);
        const hasPositiveQty = Number.isFinite(qty) && qty > 0;
        const hasLocation = !!(typeof alloc?.location === "string"
            ? alloc.location.trim()
            : alloc?.location?.location);
        return hasPositiveQty && !hasLocation;
    });

const locationKey = (location) =>
    (typeof location === "string"
        ? location
        : location?.location || ""
    ).trim().toLowerCase();

const hasDuplicateLocations = (item) => {
    const keys = (item.allocations || [])
        .map((alloc) => locationKey(alloc?.location))
        .filter(Boolean);
    return new Set(keys).size !== keys.length;
};

const hasZeroQtyAndNoLocation = (item) =>
    (item.allocations || []).some((alloc) => {
        const qty = parseFloat(alloc?.quantity);
        const isZeroOrEmptyQty = !Number.isFinite(qty) || qty <= 0;
        const hasLocation = !!(typeof alloc?.location === "string"
            ? alloc.location.trim()
            : alloc?.location?.location);
        return isZeroOrEmptyQty && !hasLocation;
    });

const getAllocationLocationOptions = (item, allocIndex) => {
    const selectedInOtherRows = new Set(
        (item.allocations || [])
            .map((alloc, idx) => (idx === allocIndex ? "" : locationKey(alloc?.location)))
            .filter(Boolean)
    );

    return [
        "Add new location",
        ...loadedLocation.value.filter((loc) => !selectedInOtherRows.has(locationKey(loc))),
    ];
};

const canAddAllocation = (item) =>
    (Number(item.received_quantity) || 0) > 0 &&
    remainingAllocation(item) > 0 &&
    !hasInvalidAllocationQuantity(item) &&
    !hasQtyWithoutLocation(item) &&
    !hasDuplicateLocations(item);

function syncAllocatedWithReceived(item, changedIndex = null) {
    const received = Number(item.received_quantity) || 0;
    const total = allocatedTotal(item);
    if (total <= received) return;

    const overflow = total - received;
    const fallbackIndex = (item.allocations?.length || 1) - 1;
    const targetIndex = changedIndex ?? fallbackIndex;
    const current = Number(item.allocations?.[targetIndex]?.quantity) || 0;
    if (item.allocations?.[targetIndex]) {
        item.allocations[targetIndex].quantity = Math.max(0, current - overflow);
    }
}

function addAllocation(item) {
    if (!item.received_quantity || item.received_quantity <= 0) {
        toast.error("Please enter a received quantity greater than 0 before adding locations");
        return;
    }
    if (!item.allocations) item.allocations = [];

    if (hasInvalidAllocationQuantity(item)) {
        toast.error("Please enter quantity greater than 0 (decimal allowed) for current location before adding another");
        return;
    }
    if (hasQtyWithoutLocation(item)) {
        toast.error("Please select location for quantities greater than 0 before adding another");
        return;
    }
    if (hasZeroQtyAndNoLocation(item)) {
        toast.error("Please set quantity > 0 and select location before adding another");
        return;
    }

    const remaining = remainingAllocation(item);
    if (remaining <= 0) return;
    const qty = remaining > 0 ? remaining : 0;
    item.allocations.push({
        id: null,
        location: null,
        quantity: qty,
    });
}

function removeAllocation(item, index) {
    if (!item.allocations) return;
    item.allocations.splice(index, 1);
}

function handleLocationSelectForAlloc(item, allocIndex, selected) {
    if (selected === 'Add new location') {
        const itemIndex = form.value.items.indexOf(item);
        if (itemIndex === -1) return;

        selectedItemIndex.value = { itemIndex, allocIndex };

        // Use current user's warehouse (first warehouse) for new locations
        selectedWarehouse.value = (props.warehouses && props.warehouses.length > 0)
            ? props.warehouses[0]
            : null;
        if (!selectedWarehouse.value) {
            toast.error("Warehouse is not configured for this user");
            return;
        }
        showLocationModal.value = true;
        return;
    }

    if (!item.allocations || !item.allocations[allocIndex]) return;
    const selectedKey = locationKey(selected);
    const hasDuplicate = (item.allocations || []).some((alloc, idx) => {
        if (idx === allocIndex) return false;
        return locationKey(alloc?.location) === selectedKey;
    });
    if (hasDuplicate) {
        item.allocations[allocIndex].location = null;
        toast.error("This location is already selected for this item");
        return;
    }
    item.allocations[allocIndex].location = selected;
}

const hasRequiredFields = (item) => {
    if (!item.received_quantity) return false;
    if (!item.allocations || !item.allocations.length) return false;

    // All allocations must have location and quantity > 0
    if (item.allocations.some((a) => !a.location || !a.quantity || a.quantity <= 0)) {
        return false;
    }
    if (hasDuplicateLocations(item)) {
        return false;
    }

    // Batch/expiry/uom/barcode are item-level as before
    return (
        item.batch_number &&
        item.expire_date &&
        item.uom &&
        item.barcode &&
        allocatedTotal(item) === item.received_quantity
    );
};

// Enhanced validation to check if all mismatches are properly recorded
const validateMismatchRecording = (item) => {
    if (!item?.received_quantity || item.received_quantity === item?.quantity)
        return true; // No mismatch, so it's valid

    const mismatchQuantity = item.quantity - item.received_quantity;
    const recordedBackOrderQuantity = (item.differences || []).reduce(
        (total, diff) => total + (parseInt(diff?.quantity) || 0),
        0
    );

    return recordedBackOrderQuantity === mismatchQuantity;
};

// Get mismatch status for display
const getMismatchStatus = (item) => {
    if (!item?.received_quantity || item.received_quantity === item?.quantity)
        return { status: 'none', message: 'No mismatch' };

    const mismatchQuantity = item.quantity - item.received_quantity;
    const recordedBackOrderQuantity = (item.differences || []).reduce(
        (total, diff) => total + (parseInt(diff?.quantity) || 0),
        0
    );

    if (recordedBackOrderQuantity === 0) {
        return { status: 'unrecorded', message: `${mismatchQuantity} mismatch not recorded` };
    } else if (recordedBackOrderQuantity < mismatchQuantity) {
        return { status: 'partial', message: `${mismatchQuantity - recordedBackOrderQuantity} remaining` };
    } else if (recordedBackOrderQuantity === mismatchQuantity) {
        return { status: 'complete', message: 'All mismatches recorded' };
    } else {
        return { status: 'excess', message: `${recordedBackOrderQuantity - mismatchQuantity} excess recorded` };
    }
};

const canSubmit = computed(() => {
    if (!form.value?.items?.length) return false;
    
    // Check if all items have basic required fields
    const hasAllRequiredFields = form.value.items.every(hasRequiredFields);
    
    if (!hasAllRequiredFields) return false;
    
    // Check if all items have their mismatches properly recorded
    return form.value.items.every(validateMismatchRecording);
});

const submitButtonTitle = computed(() => {
    if (!form.value?.items?.length) return "No items to submit";
    
    // Check for missing required fields
    const itemsWithMissingFields = form.value.items.filter(item => !hasRequiredFields(item));
    if (itemsWithMissingFields.length > 0) {
        const itemNames = itemsWithMissingFields.map(item => item.product?.name || 'Unknown Item').join(', ');
        return `Please complete all required fields for: ${itemNames}`;
    }
    
    // Check for incomplete mismatch recording
    const incompleteItems = form.value.items.filter(item => !validateMismatchRecording(item));
    if (incompleteItems.length > 0) {
        const itemNames = incompleteItems.map(item => item.product?.name || 'Unknown Item').join(', ');
        return `Please record all mismatches for: ${itemNames}`;
    }
    
    return "";
});

const attemptCloseModal = async () => {
    if (!selectedItem.value) {
        closeBackOrderModal();
        return;
    }

    const totalDifferences = backOrderRows.value.reduce(
        (total, row) => total + (parseInt(row.quantity) || 0),
        0
    );
    const expectedMismatches =
        selectedItem.value.quantity -
        (selectedItem.value.received_quantity || 0);

    if (totalDifferences !== expectedMismatches) {
        const result = await Swal.fire({
            title: "Incomplete Back Orders",
            html:
                `You need to record all mismatches.<br><br>` +
                `Expected: ${expectedMismatches}<br>` +
                `Recorded: ${totalDifferences}<br>` +
                `Remaining: ${expectedMismatches - totalDifferences}`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Continue Editing",
            cancelButtonText: "Close Anyway",
            customClass: {
                container: "swal-higher-z-index",
            },
        });

        if (result.isConfirmed) {
            return; // Keep modal open
        }
    } else {
        toast.success("All mismatches have been recorded");
    }

    closeBackOrderModal();
};

const closeBackOrderModal = () => {
    // Only clear if we have a selected item
    if (selectedItem.value) {
        // Sync one final time before closing
        syncBackOrdersWithDifferences();
    }
    showBackOrderModal.value = false;
    selectedItem.value = null;
    backOrderRows.value = [];
};

// Define all possible statuses
const allStatuses = ["Missing", "Damaged", "Lost", "Expired", "Low quality"];

// Compute available statuses (not used in other rows)
const availableStatuses = computed(() => {
    const usedStatuses = new Set(backOrderRows.value.map((row) => row.status));
    return allStatuses.filter((status) => !usedStatuses.has(status));
});

const addBackOrderRow = () => {
    const remaining = remainingToAllocate.value;
    if (remaining <= 0) return;

    // Get first available status
    const status = availableStatuses.value[0] || "Missing";
    if (!status) {
        toast.error("No more status types available");
        return;
    }

    backOrderRows.value.push({
        id: null,
        quantity: 0,
        status: status,
        notes: "",
    });

    // Sync after adding row
    nextTick(() => {
        syncBackOrdersWithDifferences();
    });
};

const removeBackOrderRow = async (index, item) => {
    try {
        if (item.id) {
            // If item has an ID, delete it from the server first
            const response = await axios.get(
                route("supplies.deletePackingListDiff", item.id)
            );
            toast.success(response.data.message);
            // Update backOrderRows with server response
            if (response.data.differences) {
                backOrderRows.value = [...response.data.differences];
            } else {
                // If no server response differences, just remove the local row
                const newRows = [...backOrderRows.value];
                newRows.splice(index, 1);
                backOrderRows.value = newRows;
            }
        } else {
            // For items without ID, just remove locally
            const newRows = [...backOrderRows.value];
            newRows.splice(index, 1);
            backOrderRows.value = newRows;
        }

        // After successful removal, sync the differences
        await nextTick();
        syncBackOrdersWithDifferences();

        // Check if we need to add a new row
        await nextTick();
        const remaining = remainingToAllocate.value;
        if (remaining > 0 && backOrderRows.value.length === 0) {
            addBackOrderRow();
        }
    } catch (error) {
        toast.error(error.response?.data || "Error removing back order");
    }
};

// Computed properties for validation
const totalBackOrderQuantity = computed(() => {
    return backOrderRows.value.reduce(
        (total, row) => total + (parseInt(row.quantity) || 0),
        0
    );
});

const isValidForSave = computed(() => {
    if (!selectedItem.value) return false;
    const missingQty =
        selectedItem.value.quantity -
        (selectedItem.value.received_quantity || 0);
    return totalBackOrderQuantity.value <= missingQty;
});

const canAddMoreRows = computed(() => {
    return remainingToAllocate.value > 0;
});

const validateBackOrderQuantities = () => {
    // Calculate total allocated excluding current row
    const validateRow = (currentRow, index) => {
        const otherRowsTotal = backOrderRows.value.reduce((total, row, i) => {
            if (i === index) return total; // Skip current row
            return total + (parseInt(row.quantity) || 0);
        }, 0);

        // Calculate max allowed for this row
        const maxAllowed = missingQuantity.value - otherRowsTotal;

        // Parse and validate the quantity
        let qty = parseInt(currentRow.quantity);
        if (isNaN(qty) || qty < 0) {
            qty = 0;
        } else if (qty > maxAllowed) {
            qty = maxAllowed;
        }

        // Update the row with validated quantity
        currentRow.quantity = qty;
    };

    // Validate each row
    backOrderRows.value.forEach((row, index) => validateRow(row, index));

    // After validation, sync with differences array
    syncBackOrdersWithDifferences();
};

const syncBackOrdersWithDifferences = () => {
    if (!selectedItem.value || !isValidForSave.value) return;

    const itemIndex = form.value.items.findIndex(
        (item) => item === selectedItem.value
    );
    if (itemIndex === -1) return;

    // Update the differences array with current back orders
    form.value.items[itemIndex].differences = backOrderRows.value.map(
        (row) => ({
            id: row.id ?? null,
            quantity: parseInt(row.quantity) || 0,
            status: row.status,
            notes: row.notes,
        })
    );
};

// Watch for changes in loadedLocation and normalize form locations
watch(loadedLocation, () => {
    normalizeFormLocations();
}, { deep: true });

// Watch for form changes to update validation status in real-time
watch(() => form.value?.items, () => {
    if (form.value?.items) {
        form.value.items.forEach(item => {
            // Update validation status in real-time
            item.hasValidationError = !hasRequiredFields(item) || !validateMismatchRecording(item);
        });
    }
}, { deep: true });

// Function to normalize location data for multiselect
const normalizeLocationData = (locationData) => {
    if (!locationData) return null;
    if (typeof locationData === 'string') {
        // If it's a string, find the corresponding object in loadedLocation
        const locationObj = loadedLocation.value.find(loc => loc.location === locationData);
        return locationObj || { location: locationData };
    }
    return locationData;
};

// Function to normalize all items' location data
const normalizeFormLocations = () => {
    if (!form.value?.items) return;
    form.value.items.forEach(item => {
        if (item.location) {
            item.location = normalizeLocationData(item.location);
        }
    });
};

</script>

<style>
.swal-higher-z-index {
    z-index: 9999 !important;
}
</style>
