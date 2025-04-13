// Check authentication state
document.addEventListener('DOMContentLoaded', function() {
    // Redirect to inventory if already logged in
    if (localStorage.getItem('user') && window.location.pathname.includes('index.html')) {
        window.location.href = 'inventory.html';
    }

    // ================= LOGIN FORM =================
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorElement = document.getElementById('loginError');
            const spinner = document.getElementById('loginSpinner');
            
            // Show loading state
            spinner.classList.remove('d-none');
            loginForm.querySelector('button').disabled = true;
            
            try {
                // Simulate API call delay
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                if (!email || !password) {
                    throw new Error('Please fill in all fields');
                }
                
                // Mock authentication
                if (email === 'admin@hfc.com' && password === 'hfc123') {
                    localStorage.setItem('user', JSON.stringify({
                        name: 'Administrator',
                        email: email,
                        role: 'admin',
                        lastLogin: new Date().toISOString()
                    }));
                    window.location.href = 'inventory.html';
                } else {
                    throw new Error('Invalid email or password');
                }
            } catch (error) {
                errorElement.textContent = error.message;
                errorElement.classList.remove('d-none');
                setTimeout(() => errorElement.classList.add('d-none'), 5000);
            } finally {
                spinner.classList.add('d-none');
                loginForm.querySelector('button').disabled = false;
            }
        });
    }

    // ================= REGISTRATION FORM =================
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorElement = document.getElementById('registerError');
            const registerBtn = document.getElementById('registerBtn');
            const spinner = document.getElementById('registerSpinner');

            // Show loading state
            registerBtn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');

            // Validation
            if (!name || !email || !password) {
                showError(errorElement, 'Please fill in all fields');
                registerBtn.disabled = false;
                if (spinner) spinner.classList.add('d-none');
                return;
            }
            
            if (password.length < 6) {
                showError(errorElement, 'Password must be at least 6 characters');
                registerBtn.disabled = false;
                if (spinner) spinner.classList.add('d-none');
                return;
            }

            // Simulate registration processing
            setTimeout(() => {
                try {
                    // Create user object
                    const newUser = {
                        name: name,
                        email: email,
                        role: 'member',
                        lastLogin: new Date().toISOString()
                    };

                    // Store user and show success
                    localStorage.setItem('user', JSON.stringify(newUser));
                    showSuccessMessage('Registration successful! Redirecting...');

                    // Redirect to inventory after delay
                    setTimeout(() => {
                        window.location.href = 'inventory.html';
                    }, 1500);
                } catch (error) {
                    showError(errorElement, 'Registration failed. Please try again.');
                } finally {
                    registerBtn.disabled = false;
                    if (spinner) spinner.classList.add('d-none');
                }
            }, 1000);
        });
    }

    // ================= LOGOUT FUNCTIONALITY =================
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            localStorage.removeItem('user');
            window.location.href = 'index.html';
        });
    }

    // ================= HELPER FUNCTIONS =================
    function showError(element, message) {
        if (!element) return;
        element.textContent = message;
        element.classList.remove('d-none');
        setTimeout(() => {
            if (element) element.classList.add('d-none');
        }, 5000);
    }

    function showSuccessMessage(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success text-center mt-3';
        successDiv.style.animation = 'fadeIn 0.5s ease-out';
        successDiv.textContent = message;

        const form = document.getElementById('registerForm') || 
                     document.getElementById('loginForm');
        if (form) {
            form.insertBefore(successDiv, form.firstChild);
            
            setTimeout(() => {
                successDiv.style.opacity = '0';
                setTimeout(() => successDiv.remove(), 300);
            }, 1300);
        }
    }
});

// Date formatting utility
function formatDate(dateString) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}