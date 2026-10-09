<x-app-layout>
<div class="min-h-screen bg-gray-50">

    <!-- Primary Header -->
    <div class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <!-- Back Button & Title -->
                <div class="flex items-start space-x-4">
                    <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('sale-form') }}" class="text-gray-500 hover:text-indigo-600 transition mt-1">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-extrabold text-gray-900">Record Pig Sale</h1>
                        <p class="text-sm text-gray-500 mt-0.5">
                            ID: <span class="font-semibold text-indigo-700">{{ $pig->code }}</span> 
                            &middot; Tag: {{ $pig->ear_tag_number }}
                            ({{ $pig->breed }}, {{ ucfirst($pig->gender === 'M' ? 'Male' : 'Female') }})
                        </p>
                    </div>
                </div>
                <!-- Action Placeholder (if needed) -->
            </div>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- ALERT: Deceased Pig Warning -->
        @if($pig->deaths()->exists())
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-red-800">Selling Deceased Pig</p>
                        <p class="text-xs text-red-700">The final weight from the death record ({{ ($pig->deaths()->first()->final_weight ?? 0) * 2.20462 }} lb) has been pre-filled.</p>
                    </div>
                </div>
            </div>
        @endif  
        
        <!-- Pig Information Summary -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 mb-8 p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Summary</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Age</p>
                    <p class="text-lg font-semibold text-gray-900">{{ floor($pig->current_age_in_days / 30) }} months ({{ $pig->current_age_in_days }} days)</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Latest Recorded Weight</p>
                    <p class="text-lg font-semibold text-gray-900">
                        @if($pig->latestWeight)
                            {{ $pig->latestWeight->formatted_weight }}
                            <span class="text-sm text-gray-500">({{ $pig->latestWeight->days_ago }} days ago)</span>
                        @else
                            <span class="text-gray-400">No weight data</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Action Type</p>
                    <p class="text-lg font-semibold text-green-600">Sale Transaction</p>
                </div>
            </div>
        </div>

        {{--
            This animal is currently on the shop.

            The sale is still allowed — a farm-gate sale is a real sale — but
            whoever is recording it should know the listing exists, because the
            pig will leave it the moment this form is saved. Finding out later,
            from a shop that quietly has one fewer animal, is worse.

            A pig already COMMITTED to an order never reaches this page: the
            controller refuses, because a customer has been promised it.
        --}}
        @isset($storefrontListingLink)
            @if($storefrontListingLink)
                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-900">This pig is on the shop</p>
                    <p class="mt-1 text-sm text-amber-800">
                        It is part of the storefront listing
                        <strong>{{ $storefrontListingLink->listing?->title ?? ('#' . $storefrontListingLink->storefront_listing_id) }}</strong>.
                        Recording this sale removes it from that listing and reduces what the shop
                        can offer. Nobody has ordered this animal yet, so the sale is safe to make.
                    </p>
                </div>
            @endif
        @endisset

        {{-- Medicine withdrawal: the sale is refused on save (decision D8), so say so first. --}}
        @isset($withdrawal)
            @if($withdrawal['restricted'])
                <div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4">
                    <p class="font-semibold text-red-900">This pig cannot be sold yet</p>
                    <p class="mt-1 text-sm text-red-800">{{ app(\App\Services\Health\WithdrawalStatusService::class)->message($withdrawal) }}</p>
                </div>
            @elseif($withdrawal['cautions']->isNotEmpty())
                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-900">Check the medicine label before selling</p>
                    @foreach($withdrawal['cautions']->take(3) as $dose)
                        <p class="mt-1 text-sm text-amber-800">No withdrawal period was recorded for {{ $dose->medication_name ?: 'a treatment' }} given on {{ $dose->administration_date->format('d M Y') }}.</p>
                    @endforeach
                </div>
            @endif
        @endisset

        <!-- Sale Form -->
        <form method="POST" action="{{ route('pigs.record-sale', $pig->id) }}" class="space-y-8">
            @csrf
            
            <!-- SECTION 1: CORE SALE DETAILS (Blue Card) -->
            <div class="bg-white rounded-xl shadow-lg border-t-4 border-blue-500 p-8">
                <h2 class="text-2xl font-bold text-blue-800 mb-6">1. Sale Specifics</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Sale Date -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="sale_date">Sale Date <span class="text-red-500">*</span></label>
                        <input type="date" name="sale_date" id="sale_date" value="{{ old('sale_date', date('Y-m-d')) }}" required 
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Sale Weight -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="sale_weight">Sale Weight (kg) <span class="text-red-500">*</span></label>
                        <input type="number" id="sale_weight" name="sale_weight" value="{{ old('sale_weight', ($pig->deaths()->exists() ? ($pig->deaths()->first()->final_weight ?? 0) : ($pig->latestWeight?->weight ?? 0))) }}" 
                               step="0.1" min="0" required 
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Weight used for final price calculation.</p>
                    </div>

                    <!-- Weight Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="weight_type">Weight Type <span class="text-red-500">*</span></label>
                        <select id="weight_type" name="weight_type" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>-- Select Weight Type --</option>
                            <option value="live" {{ old('weight_type') == 'live' ? 'selected' : '' }}>Live Weight (₵{{ \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00) }}/kg)</option>
                            <option value="carcass" {{ old('weight_type') == 'carcass' ? 'selected' : '' }}>Carcass Weight (₵{{ \App\Models\Setting::get('sales.pricing.carcass_weight_price', 50.00) }}/kg)</option>
                        </select>
                        <p class="text-xs text-blue-600 mt-1 font-semibold">Price will be calculated automatically.</p>
                    </div>
                </div>

                <!-- Calculated Price Display -->
                <div class="mt-8 bg-blue-50 border border-blue-300 rounded-xl p-5 shadow-inner">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-blue-900">Final Sale Price</h3>
                            <p class="text-sm text-blue-700">Total amount based on the weight and rate.</p>
                        </div>
                        <div class="text-right">
                            <div id="calculated_price" class="text-4xl font-extrabold text-blue-900 transition duration-300">₵0.00</div>
                            <div id="price_breakdown" class="text-sm text-blue-600 mt-1">Enter weight and select type</div>
                        </div>
                    </div>
                </div>

                <!-- Sale Purpose -->
                <div class="mt-8">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="sale-purpose">Sale Purpose <span class="text-red-500">*</span></label>
                    <select name="sale_purpose" id="sale-purpose" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="" disabled selected>-- Select Purpose --</option>
                        <option value="market" {{ old('sale_purpose') == 'market' ? 'selected' : '' }}>Market Sale</option>
                        <option value="breeding" {{ old('sale_purpose') == 'breeding' ? 'selected' : '' }}>Breeding Stock</option>
                        <option value="slaughter" {{ old('sale_purpose') == 'slaughter' ? 'selected' : '' }}>Slaughter</option>
                        <option value="export" {{ old('sale_purpose') == 'export' ? 'selected' : '' }}>Export</option>
                        <option value="other" {{ old('sale_purpose') == 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                    <div id="sale-purpose-other" class="mt-3 hidden">
                        <input type="text" name="sale_purpose_other" placeholder="Please specify the sale purpose..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- SECTION 2: BUYER & PAYMENT DETAILS -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Buyer Information -->
                <div class="bg-white rounded-xl shadow-lg border-t-4 border-indigo-500 p-8">
                    <h3 class="text-xl font-bold text-indigo-800 mb-6">2. Buyer Information</h3>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Buyer Name</label>
                            <input type="text" name="buyer_name" value="{{ old('buyer_name') }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Information</label>
                            <input type="text" name="buyer_contact" value="{{ old('buyer_contact') }}" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Buyer Address</label>
                            <textarea name="buyer_address" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('buyer_address') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-xl shadow-lg border-t-4 border-green-500 p-8">
                    <h3 class="text-xl font-bold text-green-800 mb-6">3. Payment & Logistics</h3>
                    
                    <div class="space-y-5">
                        <!-- Payment Method -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" for="payment-method">Payment Method</label>
                            <select name="payment_method" id="payment-method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select Method --</option>
                                <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="check" {{ old('payment_method') == 'check' ? 'selected' : '' }}>Check</option>
                                <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="mobile_money" {{ old('payment_method') == 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                                <option value="other" {{ old('payment_method') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            <div id="payment-method-other" class="mt-3 hidden">
                                <input type="text" name="payment_method_other" placeholder="Please specify the payment method..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                        </div>

                        <!-- Payment Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status <span class="text-red-500">*</span></label>
                            <select name="payment_status" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select Status --</option>
                                <option value="pending" {{ old('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="paid" {{ old('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="partial" {{ old('payment_status') == 'partial' ? 'selected' : '' }}>Partial Payment</option>
                            </select>
                        </div>

                        <!-- Transport Method -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Transport Method</label>
                            <select name="transport_method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select Method --</option>
                                <option value="pickup" {{ old('transport_method') == 'pickup' ? 'selected' : '' }}>Buyer Pickup</option>
                                <option value="delivery" {{ old('transport_method') == 'delivery' ? 'selected' : '' }}>Farm Delivery</option>
                                <option value="farm_gate" {{ old('transport_method') == 'farm_gate' ? 'selected' : '' }}>Farm Gate Sale</option>
                                <option value="other" {{ old('transport_method') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        
                        <!-- Transport Cost -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Transport Cost</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₵</span>
                                <input type="number" name="transport_cost" value="{{ old('transport_cost') }}" step="0.01" min="0" 
                                       class="w-full pl-8 border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border-t-4 border-amber-500 bg-white p-8 shadow-lg">
                <h3 class="text-xl font-bold text-amber-900">4. Physical ear tag</h3>
                <p class="mt-2 text-sm text-gray-600">Confirm what happened to the physical tag when the animal left the farm.</p>
                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="tag-outcome" class="mb-2 block text-sm font-medium text-gray-700">Tag outcome <span class="text-red-500">*</span></label>
                        <select id="tag-outcome" name="tag_outcome" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="pending_recovery" @selected(old('tag_outcome', 'pending_recovery') === 'pending_recovery')>Expected back, not yet confirmed</option>
                            <option value="available" @selected(old('tag_outcome') === 'available')>Removed and retained</option>
                            <option value="retired" @selected(old('tag_outcome') === 'retired')>Left with the sold animal</option>
                            <option value="lost" @selected(old('tag_outcome') === 'lost')>Lost</option>
                            <option value="damaged" @selected(old('tag_outcome') === 'damaged')>Damaged</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Tag condition notes</label>
                        <input name="tag_condition_notes" value="{{ old('tag_condition_notes') }}" placeholder="Recovery or condition details…" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>
                </div>
            </div>

            <!-- SECTION 4: NOTES AND META -->
            <div class="bg-white rounded-xl shadow-lg border-t-4 border-gray-500 p-8">
                <h3 class="text-xl font-bold text-gray-800 mb-6">5. Additional Details</h3>
                
                <div class="space-y-5">
                    <!-- Sold By (display only — the recorded seller is always the authenticated user) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recorded By</label>
                        <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-800">
                            {{ auth()->user()->name }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500">The sale will be attributed to your account automatically.</p>
                    </div>
                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional notes about the sale..." 
                                  class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-500 focus:border-gray-500">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-6 flex justify-end space-x-4">
                <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('sale-form') }}"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-100 transition shadow-sm">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition shadow-lg">
                    <svg class="w-5 h-5 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-6 4h14a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Record Sale
                </button>
            </div>
        </form>
    </main>
</div>

<script>
    // Price calculation rates from settings (MUST be loaded from PHP)
    const PRICE_RATES = {
        'live': {{ \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00) }},
        'carcass': {{ \App\Models\Setting::get('sales.pricing.carcass_weight_price', 50.00) }}
    };

    // Get form elements
    const weightInput = document.getElementById('sale_weight');
    const weightTypeSelect = document.getElementById('weight_type');
    const calculatedPriceDiv = document.getElementById('calculated_price');
    const priceBreakdownDiv = document.getElementById('price_breakdown');

    // Function to calculate and display price
    function calculatePrice() {
        const kg = parseFloat(weightInput.value) || 0;
        const weightType = weightTypeSelect.value;
        
        if (kg > 0 && weightType) {
            const rate = PRICE_RATES[weightType];
            const totalPrice = kg * rate;
            
            // Update display
            calculatedPriceDiv.textContent = `₵${totalPrice.toFixed(2)}`;
            priceBreakdownDiv.textContent = `${kg.toFixed(2)} kg × ₵${rate}/kg rate`;
            
            // Add visual feedback
            calculatedPriceDiv.classList.remove('text-gray-500');
            calculatedPriceDiv.classList.add('text-blue-900');
        } else {
            // Reset display
            calculatedPriceDiv.textContent = '₵0.00';
            if (!weightType) {
                priceBreakdownDiv.textContent = 'Select weight type';
            } else if (pounds <= 0) {
                priceBreakdownDiv.textContent = 'Enter weight';
            } else {
                priceBreakdownDiv.textContent = 'Enter weight and select type';
            }
            calculatedPriceDiv.classList.remove('text-blue-900');
            calculatedPriceDiv.classList.add('text-gray-500');
        }
    }

    // Add event listeners
    weightInput.addEventListener('input', calculatePrice);
    weightTypeSelect.addEventListener('change', calculatePrice);

    // Calculate on page load if values are pre-filled
    document.addEventListener('DOMContentLoaded', function() {
        calculatePrice();
        
        // Handle "other" dropdown functionality
        function handleOtherDropdown(selectId, otherDivId) {
            const select = document.getElementById(selectId);
            const otherDiv = document.getElementById(otherDivId);
            
            if (select && otherDiv) {
                // Check initial state
                if (select.value === 'other') {
                    otherDiv.classList.remove('hidden');
                }

                select.addEventListener('change', function() {
                    if (this.value === 'other') {
                        otherDiv.classList.remove('hidden');
                        const input = otherDiv.querySelector('input');
                        if (input) {
                            input.required = true;
                            input.focus();
                        }
                    } else {
                        otherDiv.classList.add('hidden');
                        const input = otherDiv.querySelector('input');
                        if (input) {
                            input.required = false;
                            // Clear value on change to prevent accidental submission
                            input.value = ''; 
                        }
                    }
                });
            }
        }
        
        // Initialize other dropdowns
        handleOtherDropdown('sale-purpose', 'sale-purpose-other');
        handleOtherDropdown('payment-method', 'payment-method-other');
    });
</script>
</x-app-layout>
