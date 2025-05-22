if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        const errorBox = document.getElementById('loginError');
        const spinner = document.getElementById('loginSpinner');

        spinner.classList.remove('d-none');
        loginForm.querySelector('button').disabled = true;

        try {
            const res = await fetch('/hfc_inventory/process_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ email, password })
            });

            const data = await res.json();
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                errorBox.textContent = data.message;
                errorBox.classList.remove('d-none');
            }
        } catch (err) {
            errorBox.textContent = 'Something went wrong. Try again.';
            errorBox.classList.remove('d-none');
        } finally {
            spinner.classList.add('d-none');
            loginForm.querySelector('button').disabled = false;
        }
    });
}


    // ================= REGISTRATION FORM =================
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
    
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const role = document.getElementById('role').value;
            const errorElement = document.getElementById('registerError');
            const registerBtn = document.getElementById('registerBtn');
            const spinner = document.getElementById('registerSpinner');
    
            registerBtn.disabled = true;
            spinner.classList.remove('d-none');
    
            try {
                const res = await fetch('/hfc_inventory/includes/registerBE.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ name, email, password, role })
                });
    
                const data = await res.json();
                if (data.success) {
                    showSuccessMessage(data.message);
                    setTimeout(() => {
                        window.location.href = data.redirect; // ✅ Redirect here
                    }, 1000);
                } else {
                    showError(errorElement, data.message);
                }
            } catch (err) {
                showError(errorElement, 'Something went wrong. Please try again.');
            } finally {
                registerBtn.disabled = false;
                spinner.classList.add('d-none');
            }
        });
    }
    

    // ================= LOGOUT FUNCTIONALITY =================
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            localStorage.removeItem('user');
            window.location.href = '../index.php';
        });
    }

    // ================= HELPER FUNCTIONS =================
    function showError(element, message) {
        if (!element) return;
        element.textContent = message;
        element.classList.remove('d-none');
        setTimeout(() => {
            element.classList.add('d-none');
        }, 5000);
    }

    function showSuccessMessage(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success text-center mt-3';
        successDiv.textContent = message;

        const form = document.getElementById('registerForm') || document.getElementById('loginForm');
        if (form) {
            form.insertBefore(successDiv, form.firstChild);
            setTimeout(() => {
                successDiv.style.opacity = '0';
                setTimeout(() => successDiv.remove(), 300);
            }, 1300);
        }
    }


// Date formatting utility
function formatDate(dateString) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}
