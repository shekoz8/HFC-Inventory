document.addEventListener('DOMContentLoaded', function () {
    // Set current year in footer (if exists)
    const yearSpan = document.getElementById('currentYear');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }

   // Enforce localStorage auth for frontend-only protection
const protectedPages = ['dashboard.php', 'inventory.php'];
if (protectedPages.some(p => window.location.pathname.includes(p))) {
    // Check localStorage first
    const user = localStorage.getItem('user');
    if (!user) {
       // Only fetch from server if localStorage is empty
fetch('/hfc_inventory/includes/session.php')
.then(response => response.json())
.then(data => {
    if (!data.isLoggedIn) {
        console.warn('User not logged in. Redirecting...');
        window.location.href = '/hfc_inventory/Frontend/index.php';
    }
})
.catch((error) => {
    console.error('Error checking session:', error);
    window.location.href = '/hfc_inventory/Frontend/index.php';
});
} else {
console.log('User found in localStorage:', user);
}
}

const logoutBtn = document.getElementById('logoutBtn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
        e.preventDefault();
        
        // Clear localStorage
        localStorage.removeItem('user');
        
        // Make a request to clear the server session
        fetch('/hfc_inventory/includes/logout.php')
        .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Logout successful');
                    window.location.href = '/hfc_inventory/Frontend/index.php'; // Correct redirect path
                } else {
                    console.error('Logout failed');
                }
            })
            .catch((error) => {
                console.error('Error during logout:', error);
            });
    });
}
});

// Theme class injection
const hfcColors = {
    'hfc-blue': '#052460',
    'hfc-yellow': '#FFCF01',
    'hfc-blue-light': '#1a3a7a',
    'hfc-purple': '#6f42c1',
    'hfc-green': '#28a745'
};

const styleTag = document.createElement('style');
Object.entries(hfcColors).forEach(([key, color]) => {
    styleTag.innerHTML += `
        .bg-${key} { background-color: ${color} !important; }
        .text-${key} { color: ${color} !important; }
        .btn-${key} {
            background-color: ${color};
            border-color: ${color};
            color: ${key.includes('yellow') ? '#052460' : '#fff'};
        }
        .btn-outline-${key} {
            color: ${color};
            border-color: ${color};
        }
        .btn-outline-${key}:hover {
            background-color: ${color};
            color: ${key.includes('yellow') ? '#052460' : '#fff'};
        }
    `;
});
document.head.appendChild(styleTag);

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
