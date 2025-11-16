document.addEventListener('DOMContentLoaded', function() {
    // Focus the username field on page load
    document.getElementById('username').focus();
    
    // Add password visibility toggle
    const passwordInput = document.getElementById('password');
    const togglePassword = document.createElement('span');
    togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
    togglePassword.style.cursor = 'pointer';
    togglePassword.style.marginLeft = '10px';
    togglePassword.title = 'Show password';
    
    passwordInput.insertAdjacentElement('afterend', togglePassword);
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.title = type === 'password' ? 'Show password' : 'Hide password';
    });
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});