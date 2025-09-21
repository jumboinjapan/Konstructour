// Admin Authentication JavaScript with Japanese Aesthetics

// Configuration
const ADMIN_CREDENTIALS = {
    username: 'admin',
    password: 'admin123'
};

// DOM Elements
const loginForm = document.getElementById('adminLoginForm');
const loginBtn = document.querySelector('button[type="submit"]');
const errorMessage = document.getElementById('errorMessage');
const errorText = errorMessage;

// Session management
const SESSION_KEY = 'konstructour_admin_session';
const SESSION_DURATION = 24 * 60 * 60 * 1000; // 24 hours

// Japanese aesthetic animations
function addJapaneseAnimations() {
    // Добавляем плавную анимацию при загрузке
    const form = document.querySelector('.login-container');
    form.style.opacity = '0';
    form.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        form.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        form.style.opacity = '1';
        form.style.transform = 'translateY(0)';
    }, 100);
    
    // Добавляем эффект ряби при клике на кнопки
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-japanese')) {
            const button = e.target;
            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                top: ${y}px;
                left: ${x}px;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        }
    });
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    .error-slide {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add Japanese animations
    addJapaneseAnimations();
    
    // Check if already logged in
    if (isLoggedIn()) {
        redirectToDashboard();
        return;
    }

    // Add form submission handler
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Add enter key handler
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && loginBtn && !loginBtn.disabled) {
            handleLogin(e);
        }
    });
});

// Handle login form submission
async function handleLogin(event) {
    event.preventDefault();
    
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const rememberInput = document.getElementById('remember') || document.getElementById('remember-me');
    const username = usernameInput ? usernameInput.value.trim() : '';
    const password = passwordInput ? passwordInput.value : '';
    const rememberMe = !!(rememberInput && rememberInput.checked);
    
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
        
        // Анимация успешного входа
        loginBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Успешно!';
        loginBtn.classList.add('bg-green-500');
        
        // Анимация перехода
        setTimeout(() => {
            document.body.style.transition = 'opacity 0.5s ease-out';
            document.body.style.opacity = '0';
            
            setTimeout(() => {
                redirectToDashboard();
            }, 500);
        }, 1000);
    } else {
        // Show error with animation
        showError('Неверное имя пользователя или пароль');
        setLoadingState(false);
        
        // Добавляем тряску формы
        const form = document.querySelector('.login-container');
        form.style.animation = 'shake 0.5s ease-out';
        
        setTimeout(() => {
            form.style.animation = '';
        }, 500);
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
    if (!loginBtn) return;
    
    loginBtn.disabled = loading;
    
    if (loading) {
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Проверка...';
        loginBtn.classList.add('opacity-75', 'cursor-not-allowed');
    } else {
        loginBtn.innerHTML = '<i class="fas fa-torii-gate mr-2"></i>Войти в систему';
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




