<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('sale-form') }}" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Record Sale</h1>
                            <p class="text-gray-500">{{ $pig->code }} - {{ $pig->ear_tag_number }} ({{ $pig->breed }}, {{ ucfirst($pig->gender === 'M' ? 'Male' : 'Female') }})</p>
                            @if($pig->deaths()->exists())
                                <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                        <div>
                                            <p class="text-sm font-medium text-yellow-800">Selling Deceased Pig</p>
                                            <p class="text-xs text-yellow-700">This pig is marked as deceased. Final weight from death record will be used.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Pig Information Card -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Pig Information</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Ear Tag & Code</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $pig->ear_tag_number }} - {{ $pig->code }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Age</p>
                            <p class="text-lg font-semibold text-gray-900">{{ floor($pig->current_age_in_days / 30) }} months ({{ $pig->current_age_in_days }} days)</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Latest Weight</p>
                            <p class="text-lg font-semibold text-gray-900">
                                @if($pig->latestWeight)
                                    {{ $pig->latestWeight->formatted_weight }}
                                    <span class="text-sm text-gray-500">({{ $pig->latestWeight->days_ago }} days ago)</span>
                                @else
                                    <span class="text-gray-400">No weight data</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sale Form -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Sale Details</h2>
                </div>
                <form method="POST" action="{{ route('pigs.record-sale', $pig->id) }}" class="p-6 space-y-6">
                    @csrf
                    
                    <!-- Sale Date -->
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sale Date *</label>
                            <input type="date" name="sale_date" value="{{ old('sale_date', date('Y-m-d')) }}" required 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Weight and Weight Type -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sale Weight (kg) *</label>
                            <input type="number" id="sale_weight" name="sale_weight" value="{{ old('sale_weight', $pig->deaths()->exists() ? $pig->deaths()->first()->final_weight : $pig->latestWeight?->weight) }}" 
                                   step="0.1" min="0" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">
                                @if($pig->deaths()->exists())
                                    Final weight from death record
                                @else
                                    Weight at time of sale
                                @endif
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weight Type *</label>
                            <select id="weight_type" name="weight_type" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="" disabled selected>Select Weight Type</option>
                                <option value="live" {{ old('weight_type') == 'live' ? 'selected' : '' }}>Live Weight (₵{{ \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00) }}/kg)</option>
                                <option value="carcass" {{ old('weight_type') == 'carcass' ? 'selected' : '' }}>Carcass Weight (₵{{ \App\Models\Setting::get('sales.pricing.carcass_weight_price', 50.00) }}/kg)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Price will be calculated automatically</p>
                        </div>
                    </div>

                    <!-- Calculated Price Display -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-900">Calculated Sale Price</h3>
                                <p class="text-sm text-blue-700">Total amount based on weight and type</p>
                            </div>
                            <div class="text-right">
                                <div id="calculated_price" class="text-3xl font-bold text-blue-900">₵0.00</div>
                                <div id="price_breakdown" class="text-sm text-blue-600">Enter weight and select type</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sale Purpose -->
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sale Purpose *</label>
                            <select name="sale_purpose" id="sale-purpose" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="" disabled selected>Select Purpose</option>
                                <option value="market" {{ old('sale_purpose') == 'market' ? 'selected' : '' }}>Market Sale</option>
                                <option value="breeding" {{ old('sale_purpose') == 'breeding' ? 'selected' : '' }}>Breeding Stock</option>
                                <option value="slaughter" {{ old('sale_purpose') == 'slaughter' ? 'selected' : '' }}>Slaughter</option>
                                <option value="export" {{ old('sale_purpose') == 'export' ? 'selected' : '' }}>Export</option>
                                <option value="other" {{ old('sale_purpose') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            <div id="sale-purpose-other" class="mt-2 hidden">
                                <input type="text" name="sale_purpose_other" placeholder="Please specify the sale purpose..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Buyer Information -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Buyer Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Buyer Name *</label>
                                <input type="text" name="buyer_name" value="{{ old('buyer_name') }}" required 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Information</label>
                                <input type="text" name="buyer_contact" value="{{ old('buyer_contact') }}" 
                                       placeholder="Phone, email, etc." 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Buyer Address</label>
                            <textarea name="buyer_address" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('buyer_address') }}</textarea>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                <select name="payment_method" id="payment-method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="" disabled selected>Select Method</option>
                                    <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="check" {{ old('payment_method') == 'check' ? 'selected' : '' }}>Check</option>
                                    <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="mobile_money" {{ old('payment_method') == 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                                    <option value="other" {{ old('payment_method') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                <div id="payment-method-other" class="mt-2 hidden">
                                    <input type="text" name="payment_method_other" placeholder="Please specify the payment method..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status *</label>
                                <select name="payment_status" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="pending" {{ old('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="paid" {{ old('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="partial" {{ old('payment_status') == 'partial' ? 'selected' : '' }}>Partial Payment</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Transport Information -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Transport Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Transport Method</label>
                                <select name="transport_method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="" disabled selected>Select Method</option>
                                    <option value="pickup" {{ old('transport_method') == 'pickup' ? 'selected' : '' }}>Buyer Pickup</option>
                                    <option value="delivery" {{ old('transport_method') == 'delivery' ? 'selected' : '' }}>Farm Delivery</option>
                                    <option value="farm_gate" {{ old('transport_method') == 'farm_gate' ? 'selected' : '' }}>Farm Gate Sale</option>
                                    <option value="other" {{ old('transport_method') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Transport Cost</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₵</span>
                                    <input type="number" name="transport_cost" value="{{ old('transport_cost') }}" step="0.01" min="0" 
                                           class="w-full pl-8 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sold By</label>
                                <input type="text" name="sold_by" value="{{ old('sold_by', auth()->user()->name ?? '') }}" 
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes about the sale..." 
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="border-t border-gray-200 pt-6 flex justify-end space-x-4">
                        <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('sale-form') }}"
                           class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Record Sale
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Price calculation rates from settings
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
            const weight = parseFloat(weightInput.value) || 0;
            const weightType = weightTypeSelect.value;
            
            if (weight > 0 && weightType) {
                const rate = PRICE_RATES[weightType];
                const totalPrice = weight * rate;
                
                // Update display
                calculatedPriceDiv.textContent = `₵${totalPrice.toFixed(2)}`;
                priceBreakdownDiv.textContent = `${weight}kg × ₵${rate}/kg = ₵${totalPrice.toFixed(2)}`;
                
                // Add visual feedback
                calculatedPriceDiv.classList.remove('text-gray-500');
                calculatedPriceDiv.classList.add('text-blue-900');
            } else {
                // Reset display
                calculatedPriceDiv.textContent = '₵0.00';
                if (!weightType) {
                    priceBreakdownDiv.textContent = 'Select weight type';
                } else if (weight <= 0) {
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
