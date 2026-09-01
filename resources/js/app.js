import './bootstrap';

// Global UI store (Alpine)
document.addEventListener('alpine:init', () => {
    Alpine.store('ui', {
        catOpen: false,
        filterOpen: false,
    });
});
