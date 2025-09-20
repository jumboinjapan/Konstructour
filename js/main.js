// Main Website JavaScript

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeWebsite();
    setupSmoothScrolling();
    setupAnimations();
    setupNavigation();
});

// Initialize website
function initializeWebsite() {
    // Add animation classes
    addAnimationClasses();
    
    // Setup intersection observer for animations
    setupIntersectionObserver();
    
    // Initialize counters
    initializeCounters();
    
    // Setup form handlers
    setupFormHandlers();
}

// Add animation classes to elements
function addAnimationClasses() {
    // Hero section
    const heroTitle = document.querySelector('h1');
    if (heroTitle) heroTitle.classList.add('hero-title');
    
    const heroSubtitle = document.querySelector('p');
    if (heroSubtitle) heroSubtitle.classList.add('hero-subtitle');
    
    const heroButtons = document.querySelector('.flex.flex-col.sm\\:flex-row');
    if (heroButtons) heroButtons.classList.add('hero-buttons');
    
    // Feature cards
    const featureCards = document.querySelectorAll('.bg-blue-50, .bg-green-50, .bg-purple-50, .bg-yellow-50, .bg-red-50, .bg-indigo-50');
    featureCards.forEach(card => {
        card.classList.add('feature-card');
    });
    
    // Buttons
    const buttons = document.querySelectorAll('a, button');
    buttons.forEach(button => {
        if (button.classList.contains('bg-blue-600') || button.classList.contains('bg-white')) {
            button.classList.add('btn-animate');
        }
    });
    
    // Navigation links
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(link => {
        link.classList.add('nav-link');
    });
    
    // Add floating animation to main icon
    const mainIcon = document.querySelector('.fa-hammer');
    if (mainIcon) mainIcon.classList.add('float-animation');
}

// Setup smooth scrolling
function setupSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Setup animations
function setupAnimations() {
    // Add scroll-triggered animations
    const animatedElements = document.querySelectorAll('.feature-card, .hero-title, .hero-subtitle');
    
    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
    });
}

// Setup intersection observer for scroll animations
function setupIntersectionObserver() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.transition = 'all 0.6s ease-out';
            }
        });
    }, observerOptions);
    
    // Observe all animated elements
    const animatedElements = document.querySelectorAll('.feature-card, .hero-title, .hero-subtitle, .hero-buttons');
    animatedElements.forEach(element => {
        observer.observe(element);
    });
}

// Setup navigation
function setupNavigation() {
    const nav = document.querySelector('nav');
    const navLinks = document.querySelectorAll('nav a[href^="#"]');
    
    // Highlight active section on scroll
    window.addEventListener('scroll', function() {
        const scrollPos = window.scrollY + 100;
        
        navLinks.forEach(link => {
            const targetId = link.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const targetTop = targetElement.offsetTop;
                const targetBottom = targetTop + targetElement.offsetHeight;
                
                if (scrollPos >= targetTop && scrollPos <= targetBottom) {
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('text-blue-600', 'font-semibold'));
                    // Add active class to current link
                    link.classList.add('text-blue-600', 'font-semibold');
                }
            }
        });
        
        // Add shadow to navbar on scroll
        if (window.scrollY > 50) {
            nav.classList.add('shadow-xl');
        } else {
            nav.classList.remove('shadow-xl');
        }
    });
}

// Initialize counters
function initializeCounters() {
    const counters = document.querySelectorAll('.text-3xl.font-bold');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/\D/g, ''));
        const suffix = counter.textContent.replace(/\d/g, '');
        
        if (target) {
            animateCounter(counter, 0, target, suffix, 2000);
        }
    });
}

// Animate counter
function animateCounter(element, start, end, suffix, duration) {
    const startTime = performance.now();
    
    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const current = Math.floor(progress * (end - start) + start);
        element.textContent = current + suffix;
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    }
    
    requestAnimationFrame(updateCounter);
}

// Setup form handlers
function setupFormHandlers() {
    // Contact form (if exists)
    const contactForm = document.querySelector('form');
    if (contactForm) {
        contactForm.addEventListener('submit', handleFormSubmit);
    }
}

// Handle form submission
function handleFormSubmit(event) {
    event.preventDefault();
    
    // Show loading state
    const submitButton = event.target.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    
    submitButton.innerHTML = '<div class="loading-spinner inline-block mr-2"></div>Отправка...';
    submitButton.disabled = true;
    
    // Simulate form submission
    setTimeout(() => {
        showNotification('Сообщение успешно отправлено!', 'success');
        event.target.reset();
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    }, 2000);
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white transform translate-x-full transition-transform duration-300 ${
        type === 'error' ? 'bg-red-500' :
        type === 'success' ? 'bg-green-500' :
        type === 'warning' ? 'bg-yellow-500' :
        'bg-blue-500'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${
                type === 'error' ? 'exclamation-circle' :
                type === 'success' ? 'check-circle' :
                type === 'warning' ? 'exclamation-triangle' :
                'info-circle'
            } mr-2"></i>
            ${message}
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Add scroll-to-top button
function addScrollToTopButton() {
    const scrollButton = document.createElement('button');
    scrollButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollButton.className = 'fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-all duration-300 opacity-0 pointer-events-none z-50';
    scrollButton.id = 'scrollToTop';
    
    document.body.appendChild(scrollButton);
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', throttle(() => {
        if (window.scrollY > 300) {
            scrollButton.classList.remove('opacity-0', 'pointer-events-none');
        } else {
            scrollButton.classList.add('opacity-0', 'pointer-events-none');
        }
    }, 100));
    
    // Scroll to top on click
    scrollButton.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Initialize scroll-to-top button
addScrollToTopButton();

// Export functions for global use
window.mainWebsite = {
    showNotification,
    animateCounter,
    debounce,
    throttle
};




