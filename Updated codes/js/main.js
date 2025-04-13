// Common functions and initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Check authentication for protected pages
    const protectedPages = ['dashboard.html', 'inventory.html'];
    if (protectedPages.some(page => window.location.pathname.includes(page))) {
        if (!localStorage.getItem('user')) {
            window.location.href = 'index.html';
        }
    }

    // Set current year in footer
    document.getElementById('currentYear').textContent = new Date().getFullYear();

    // Logout functionality
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            localStorage.removeItem('user');
            window.location.href = 'index.html';
        });
    }
});

// Custom HFC theme colors
const hfcColors = {
    'hfc-blue': '#052460',
    'hfc-yellow': '#FFCF01',
    'hfc-blue-light': '#1a3a7a',
    'hfc-yellow-dark': '#e6b900',
    'hfc-purple': '#6f42c1',
    'hfc-aqua': '#17a2b8',
    'hfc-red': '#dc3545',
    'hfc-slate': '#6c757d',
    'hfc-green': '#28a745'
};

// Add custom color classes to document
Object.entries(hfcColors).forEach(([name, color]) => {
    const style = document.createElement('style');
    style.textContent = `
        .bg-${name} { background-color: ${color} !important; }
        .text-${name} { color: ${color} !important; }
        .btn-${name} { 
            background-color: ${color}; 
            border-color: ${color};
            color: ${name.includes('yellow') ? '#052460' : 'white'};
        }
        .btn-outline-${name} { 
            color: ${color}; 
            border-color: ${color};
        }
        .btn-outline-${name}:hover { 
            background-color: ${color}; 
            color: ${name.includes('yellow') ? '#052460' : 'white'};
        }
    `;
    document.head.appendChild(style);
});

// Custom alert function
function showHFCAlert(message, type = 'success', duration = 3000) {
    const alertContainer = document.createElement('div');
    alertContainer.className = 'position-fixed top-0 end-0 p-3';
    alertContainer.style.zIndex = '9999';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <strong>${type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Info!'}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    document.body.appendChild(alertContainer);
    
    // Auto dismiss after duration
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, duration);
}

// Export functions if using modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { showHFCAlert };
}