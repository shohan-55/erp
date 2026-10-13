import { createIcons, icons } from 'lucide';

// Make Lucide available globally
window.createIcons = createIcons;
window.lucideIcons = icons;

// Initialize icons on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });
});

// Re-initialize icons whenever Livewire mutates DOM or navigates
document.addEventListener('livewire:initialized', () => {
    createIcons({ icons });
    
    Livewire.hook('morph.updated', () => {
        setTimeout(() => {
            createIcons({ icons });
        }, 10);
    });
});

document.addEventListener('livewire:navigated', () => {
    createIcons({ icons });
});

// Global toast trigger helper
window.showToast = function(title, message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `rac-toast rac-toast-${type}`;
    
    const iconName = type === 'success' ? 'check-circle-2' : 
                     type === 'warning' ? 'alert-triangle' : 
                     type === 'danger' ? 'alert-octagon' : 'info';

    toast.innerHTML = `
        <div class="rac-toast-icon">
            <i data-lucide="${iconName}" class="w-5 h-5"></i>
        </div>
        <div class="rac-toast-content">
            <div class="rac-toast-title">${title}</div>
            <div class="rac-toast-desc">${message}</div>
        </div>
        <button type="button" class="rac-toast-close" onclick="this.parentElement.remove()" aria-label="Close notification">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;

    container.appendChild(toast);
    createIcons({ icons });

    setTimeout(() => {
        toast.classList.add('rac-toast-leave');
        setTimeout(() => toast.remove(), 250);
    }, 4000);
};

// Global keyboard shortcuts (Ctrl+K or Cmd+K for command palette search)
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('global-search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});
