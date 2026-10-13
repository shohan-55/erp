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

// Global toast trigger helper (safe DOM textContent rendering)
window.showToast = function(title, message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `rac-toast rac-toast-${type}`;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    
    const iconName = type === 'success' ? 'check-circle-2' : 
                     type === 'warning' ? 'alert-triangle' : 
                     type === 'danger' ? 'alert-octagon' : 'info';

    const iconDiv = document.createElement('div');
    iconDiv.className = 'rac-toast-icon';
    const iconElem = document.createElement('i');
    iconElem.setAttribute('data-lucide', iconName);
    iconElem.className = 'w-5 h-5';
    iconDiv.appendChild(iconElem);

    const contentDiv = document.createElement('div');
    contentDiv.className = 'rac-toast-content';

    const titleDiv = document.createElement('div');
    titleDiv.className = 'rac-toast-title';
    titleDiv.textContent = title;

    const descDiv = document.createElement('div');
    descDiv.className = 'rac-toast-desc';
    descDiv.textContent = message;

    contentDiv.appendChild(titleDiv);
    contentDiv.appendChild(descDiv);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'rac-toast-close';
    closeBtn.setAttribute('aria-label', 'Close notification');
    const closeIcon = document.createElement('i');
    closeIcon.setAttribute('data-lucide', 'x');
    closeIcon.className = 'w-4 h-4';
    closeBtn.appendChild(closeIcon);
    closeBtn.onclick = () => {
        toast.classList.add('rac-toast-leave');
        setTimeout(() => toast.remove(), 200);
    };

    toast.appendChild(iconDiv);
    toast.appendChild(contentDiv);
    toast.appendChild(closeBtn);

    container.appendChild(toast);
    createIcons({ icons });

    setTimeout(() => {
        if (toast.isConnected) {
            toast.classList.add('rac-toast-leave');
            setTimeout(() => toast.remove(), 250);
        }
    }, 4500);
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
