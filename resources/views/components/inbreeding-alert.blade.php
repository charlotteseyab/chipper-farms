@props(['id' => 'inbreeding-alert'])

<div id="{{ $id }}" class="hidden mb-4" role="alert">
    <!-- Critical Alert -->
    <div id="{{ $id }}-critical" class="hidden bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-red-800">
                    🚫 Breeding Not Allowed
                </h3>
                <div class="mt-2 text-sm text-red-700">
                    <p id="{{ $id }}-critical-message"></p>
                    <div id="{{ $id }}-critical-details" class="mt-2 text-xs"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Warning Alert -->
    <div id="{{ $id }}-warning" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-yellow-800">
                    ⚠️ High Inbreeding Risk
                </h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p id="{{ $id }}-warning-message"></p>
                    <div id="{{ $id }}-warning-details" class="mt-2 text-xs"></div>
                    <div class="mt-3">
                        <label class="flex items-center">
                            <input type="checkbox" id="{{ $id }}-warning-override" class="rounded border-yellow-300 text-yellow-600 shadow-sm focus:border-yellow-300 focus:ring focus:ring-yellow-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-yellow-700">I understand the risks and want to proceed</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Caution Alert -->
    <div id="{{ $id }}-caution" class="hidden bg-blue-50 border-l-4 border-blue-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-blue-800">
                    ℹ️ Moderate Inbreeding Risk
                </h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p id="{{ $id }}-caution-message"></p>
                    <div id="{{ $id }}-caution-details" class="mt-2 text-xs"></div>
                    <p class="mt-2 text-xs">Consider alternative parents for better genetic diversity.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Alert -->
    <div id="{{ $id }}-success" class="hidden bg-green-50 border-l-4 border-green-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-green-800">
                    ✅ Safe Breeding Pair
                </h3>
                <div class="mt-2 text-sm text-green-700">
                    <p>No close relationships detected. This is a genetically diverse pairing.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class InbreedingAlert {
    constructor(alertId) {
        this.alertId = alertId;
        this.alertContainer = document.getElementById(alertId);
        this.currentAlert = null;
    }

    show(data) {
        this.hide(); // Hide any existing alerts
        
        const riskLevel = data.risk_level;
        const alertElement = document.getElementById(`${this.alertId}-${riskLevel}`);
        
        if (!alertElement) return;

        // Update message
        const messageElement = document.getElementById(`${this.alertId}-${riskLevel}-message`);
        if (messageElement) {
            messageElement.textContent = data.message || '';
        }

        // Update details
        const detailsElement = document.getElementById(`${this.alertId}-${riskLevel}-details`);
        if (detailsElement && data.details) {
            detailsElement.innerHTML = this.formatDetails(data.details);
        }

        // Show the alert
        this.alertContainer.classList.remove('hidden');
        alertElement.classList.remove('hidden');
        this.currentAlert = riskLevel;

        // Handle form submission blocking for critical alerts
        this.handleFormSubmission(data);
    }

    hide() {
        this.alertContainer.classList.add('hidden');
        ['critical', 'warning', 'caution', 'success'].forEach(level => {
            const element = document.getElementById(`${this.alertId}-${level}`);
            if (element) element.classList.add('hidden');
        });
        this.currentAlert = null;
    }

    formatDetails(details) {
        let html = '';
        if (details.relationship) {
            html += `<strong>Relationship:</strong> ${details.relationship}<br>`;
        }
        if (details.shared_father) {
            html += `<strong>Shared Father:</strong> ${details.shared_father}<br>`;
        }
        if (details.shared_mother) {
            html += `<strong>Shared Mother:</strong> ${details.shared_mother}<br>`;
        }
        if (details.shared_parent) {
            html += `<strong>Shared ${details.shared_parent}:</strong> ${details.parent_tag}<br>`;
        }
        return html;
    }

    handleFormSubmission(data) {
        const form = this.alertContainer.closest('form');
        if (!form) return;

        const submitButton = form.querySelector('button[type="submit"]');
        if (!submitButton) return;

        if (data.risk_level === 'critical') {
            // Block submission for critical alerts
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        } else if (data.risk_level === 'warning') {
            // Require override checkbox for warnings
            const overrideCheckbox = document.getElementById(`${this.alertId}-warning-override`);
            if (overrideCheckbox) {
                const updateSubmitButton = () => {
                    if (overrideCheckbox.checked) {
                        submitButton.disabled = false;
                        submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    } else {
                        submitButton.disabled = true;
                        submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                };
                
                overrideCheckbox.addEventListener('change', updateSubmitButton);
                updateSubmitButton(); // Initial state
            }
        } else {
            // Allow submission for caution and success
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    canProceed() {
        if (this.currentAlert === 'critical') return false;
        if (this.currentAlert === 'warning') {
            const overrideCheckbox = document.getElementById(`${this.alertId}-warning-override`);
            return overrideCheckbox && overrideCheckbox.checked;
        }
        return true;
    }
}

// Make it globally available
window.InbreedingAlert = InbreedingAlert;
</script>
