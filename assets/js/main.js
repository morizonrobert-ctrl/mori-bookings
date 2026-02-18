// Open admin modal
function openAdminModal() {
    document.getElementById('adminModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    // Clear previous errors
    document.getElementById('adminLoginError').style.display = 'none';
    document.getElementById('adminLoginError').innerHTML = '';
}

// Close admin modal
function closeAdminModal() {
    document.getElementById('adminModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.toggle-password i');
    if (input.type === 'password') {
        input.type = 'text';
        button.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        button.className = 'fas fa-eye';
    }
}

// Handle admin login form submission via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const adminForm = document.getElementById('adminLoginForm');
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('adminEmail').value;
            const password = document.getElementById('adminPassword').value;
            const errorDiv = document.getElementById('adminLoginError');
            const submitBtn = document.getElementById('adminLoginBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const spinner = submitBtn.querySelector('.spinner');
            
            // Disable button, show spinner
            submitBtn.disabled = true;
            btnText.style.opacity = '0.5';
            spinner.style.display = 'inline-block';
            
            // Clear previous errors
            errorDiv.style.display = 'none';
            errorDiv.innerHTML = '';
            
            // Send AJAX request
            fetch(BASE_URL + 'admin/ajax_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email, password: password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to appropriate dashboard
                    window.location.href = data.redirect;
                } else {
                    // Show error
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                    errorDiv.style.display = 'flex';
                    // Re-enable button
                    submitBtn.disabled = false;
                    btnText.style.opacity = '1';
                    spinner.style.display = 'none';
                }
            })
            .catch(error => {
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
                errorDiv.style.display = 'flex';
                submitBtn.disabled = false;
                btnText.style.opacity = '1';
                spinner.style.display = 'none';
            });
        });
    }
});

// Close modal when clicking overlay or pressing ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminModal();
    }
});

// Also close on overlay click (already handled in onclick)