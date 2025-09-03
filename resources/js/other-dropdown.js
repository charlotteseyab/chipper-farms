/**
 * Other Dropdown Handler
 * Handles showing/hiding text input when "other" option is selected in dropdowns
 */

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
        
        // Handle form validation for "other" fields
        const form = select.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (select.value === 'other') {
                    const input = otherDiv.querySelector('input');
                    if (input && !input.value.trim()) {
                        e.preventDefault();
                        input.focus();
                        input.classList.add('border-red-500');
                        return false;
                    }
                }
            });
        }
    }
}

// Initialize all other dropdowns on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-detect and initialize other dropdowns
    const otherSelects = document.querySelectorAll('select option[value="other"]');
    otherSelects.forEach(option => {
        const select = option.closest('select');
        if (select && select.id) {
            const otherDivId = select.id + '-other';
            const otherDiv = document.getElementById(otherDivId);
            if (otherDiv) {
                handleOtherDropdown(select.id, otherDivId);
            }
        }
    });
});

// Export for manual initialization
window.handleOtherDropdown = handleOtherDropdown;
