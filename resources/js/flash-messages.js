function dismissFlash(item) {
    if (!item || item.dataset.dismissing === 'true') return;
    item.dataset.dismissing = 'true';
    item.classList.add('translate-x-6', 'opacity-0');
    window.setTimeout(() => item.remove(), 300);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-flash-dismiss]').forEach((button) => {
        button.addEventListener('click', () => dismissFlash(button.closest('[data-flash-item]')));
    });

    document.querySelectorAll('[data-flash-item][data-auto-dismiss="true"]').forEach((item) => {
        window.setTimeout(() => dismissFlash(item), 5000);
    });
});
