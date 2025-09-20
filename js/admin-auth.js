// Admin Authentication JavaScript

// Configuration
const ADMIN_CREDENTIALS = {
    username: 'admin',
    password: 'admin123'
};

// DOM Elements
const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');
const errorMessage = document.getElementById('errorMessage');
const errorText = document.getElementById('errorText');

// Session management
const SESSION_KEY = 'konstructour_admin_session';
const SESSION_DURATION = 24 * 60 * 60 * 1000; // 24 hours

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if already logged in
    if (isLoggedIn()) {
        redirectToDashboard();
        return;
    }

    // Add form submission handler
    loginForm.addEventListener('submit', handleLogin);
    
    // Add enter key handler
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !loginBtn.disabled) {
            handleLogin(e);
        }
    });
});

// Handle login form submission
async function handleLogin(event) {
    event.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const rememberMe = document.getElementById('remember-me').checked;
    
    // Validate input
    if (!username || !password) {
        showError('Пожалуйста, заполните все поля');
        return;
    }
    
    // Show loading state
    setLoadingState(true);
    hideError();
    
    // Simulate API call delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Check credentials
    if (username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password) {
        // Create session
        createSession(username, rememberMe);
        
        // Show success message
        showSuccess('Успешный вход! Перенаправление...');
        
        // Redirect after delay
        setTimeout(() => {
            redirectToDashboard();
        }, 1500);
    } else {
        // Show error
        showError('Неверное имя пользователя или пароль');
        setLoadingState(false);
    }
}

// Create admin session
function createSession(username, rememberMe) {
    const sessionData = {
        username: username,
        loginTime: Date.now(),
        expires: rememberMe ? Date.now() + (30 * 24 * 60 * 60 * 1000) : Date.now() + SESSION_DURATION
    };
    
    localStorage.setItem(SESSION_KEY, JSON.stringify(sessionData));
}

// Check if user is logged in
function isLoggedIn() {
    const sessionData = localStorage.getItem(SESSION_KEY);
    
    if (!sessionData) {
        return false;
    }
    
    try {
        const session = JSON.parse(sessionData);
        
        // Check if session is expired
        if (Date.now() > session.expires) {
            localStorage.removeItem(SESSION_KEY);
            return false;
        }
        
        return true;
    } catch (error) {
        localStorage.removeItem(SESSION_KEY);
        return false;
    }
}

// Get current session
function getCurrentSession() {
    const sessionData = localStorage.getItem(SESSION_KEY);
    
    if (!sessionData) {
        return null;
    }
    
    try {
        return JSON.parse(sessionData);
    } catch (error) {
        localStorage.removeItem(SESSION_KEY);
        return null;
    }
}

// Redirect to dashboard
function redirectToDashboard() {
    window.location.href = 'dashboard.html';
}

// Logout function
function logout() {
    if (confirm('Вы уверены, что хотите выйти?')) {
        localStorage.removeItem(SESSION_KEY);
        window.location.href = 'index.html';
    }
}

// Set loading state
function setLoadingState(loading) {
    loginBtn.disabled = loading;
    
    if (loading) {
        loginBtn.innerHTML = '<span class="spinner"></span>Вход...';
        loginBtn.classList.add('opacity-75', 'cursor-not-allowed');
    } else {
        loginBtn.innerHTML = `
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
            </span>
            Войти
        `;
        loginBtn.classList.remove('opacity-75', 'cursor-not-allowed');
    }
}

// Show error message
function showError(message) {
    errorText.textContent = message;
    errorMessage.classList.remove('hidden');
    errorMessage.classList.add('error-slide');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        hideError();
    }, 5000);
}

// Hide error message
function hideError() {
    errorMessage.classList.add('hidden');
    errorMessage.classList.remove('error-slide');
}

// Show success message
function showSuccess(message) {
    hideError();
    
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message';
    successDiv.innerHTML = `
        <i class="fas fa-check-circle mr-2"></i>
        ${message}
    `;
    
    loginForm.appendChild(successDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.parentNode.removeChild(successDiv);
        }
    }, 3000);
}

// Validate session on dashboard load
function validateSession() {
    const session = getCurrentSession();
    
    if (!session) {
        window.location.href = 'index.html';
        return false;
    }
    
    // Update admin name if element exists
    const adminNameElement = document.getElementById('adminName');
    if (adminNameElement) {
        adminNameElement.textContent = session.username;
    }
    
    return true;
}

// Auto logout on session expiry
function checkSessionExpiry() {
    const session = getCurrentSession();
    
    if (session && Date.now() > session.expires) {
        localStorage.removeItem(SESSION_KEY);
        alert('Сессия истекла. Пожалуйста, войдите снова.');
        window.location.href = 'index.html';
    }
}

// Check session every minute
setInterval(checkSessionExpiry, 60000);

// Export functions for use in other files
window.adminAuth = {
    logout,
    validateSession,
    getCurrentSession,
    isLoggedIn
};




