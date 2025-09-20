// Admin Dashboard JavaScript

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', function() {
    // Validate session
    if (!adminAuth.validateSession()) {
        return;
    }
    
    // Initialize dashboard components
    initializeDashboard();
    
    // Load dashboard data
    loadDashboardData();
    
    // Set up auto-refresh
    setupAutoRefresh();
});

// Initialize dashboard components
function initializeDashboard() {
    // Add hover effects to stat cards
    const statCards = document.querySelectorAll('.stat-card, .bg-white');
    statCards.forEach(card => {
        card.classList.add('stat-card');
    });
    
    // Add hover effects to quick action buttons
    const quickActions = document.querySelectorAll('.bg-blue-600, .bg-green-600, .bg-yellow-600, .bg-purple-600');
    quickActions.forEach(button => {
        button.classList.add('quick-action', 'btn-hover');
    });
    
    // Initialize tooltips
    initializeTooltips();
    
    // Set up real-time updates
    setupRealTimeUpdates();
}

// Load dashboard data
async function loadDashboardData() {
    try {
        // Show loading state
        showLoadingOverlay();
        
        // Simulate API calls
        const [users, projects, activity, errors] = await Promise.all([
            fetchUsersData(),
            fetchProjectsData(),
            fetchActivityData(),
            fetchErrorsData()
        ]);
        
        // Update dashboard with real data
        updateStatsCards(users, projects, activity, errors);
        updateActivityFeed(activity);
        updateSystemStatus();
        
        // Hide loading state
        hideLoadingOverlay();
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showNotification('Ошибка загрузки данных', 'error');
        hideLoadingOverlay();
    }
}

// Fetch users data
async function fetchUsersData() {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 500));
    return {
        total: 1247,
        active: 1189,
        new: 23
    };
}

// Fetch projects data
async function fetchProjectsData() {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 400));
    return {
        total: 89,
        active: 67,
        completed: 22
    };
}

// Fetch activity data
async function fetchActivityData() {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 300));
    return [
        {
            type: 'project_created',
            message: 'Новый проект создан',
            user: 'Иван Петров',
            time: '2 мин назад',
            icon: 'fas fa-plus',
            color: 'green'
        },
        {
            type: 'user_registered',
            message: 'Пользователь зарегистрирован',
            user: 'Мария Сидорова',
            time: '15 мин назад',
            icon: 'fas fa-user',
            color: 'blue'
        },
        {
            type: 'project_updated',
            message: 'Проект обновлен',
            user: 'Алексей Козлов',
            time: '1 час назад',
            icon: 'fas fa-edit',
            color: 'yellow'
        }
    ];
}

// Fetch errors data
async function fetchErrorsData() {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 200));
    return {
        total: 3,
        critical: 0,
        warning: 3
    };
}

// Update stats cards
function updateStatsCards(users, projects, activity, errors) {
    // Update users card
    const usersCard = document.querySelector('.grid > div:nth-child(1) dd');
    if (usersCard) {
        usersCard.textContent = users.total.toLocaleString();
    }
    
    // Update projects card
    const projectsCard = document.querySelector('.grid > div:nth-child(2) dd');
    if (projectsCard) {
        projectsCard.textContent = projects.total;
    }
    
    // Update activity card
    const activityCard = document.querySelector('.grid > div:nth-child(3) dd');
    if (activityCard) {
        activityCard.textContent = activity.total || 342;
    }
    
    // Update errors card
    const errorsCard = document.querySelector('.grid > div:nth-child(4) dd');
    if (errorsCard) {
        errorsCard.textContent = errors.total;
    }
}

// Update activity feed
function updateActivityFeed(activities) {
    const activityList = document.querySelector('.flow-root ul');
    if (!activityList) return;
    
    // Clear existing activities
    activityList.innerHTML = '';
    
    // Add new activities
    activities.forEach((activity, index) => {
        const li = document.createElement('li');
        li.className = 'activity-item';
        
        const isLast = index === activities.length - 1;
        const lineClass = isLast ? '' : 'pb-8';
        
        li.innerHTML = `
            <div class="relative ${lineClass}">
                ${!isLast ? '<span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>' : ''}
                <div class="relative flex space-x-3">
                    <div>
                        <span class="h-8 w-8 rounded-full bg-${activity.color}-500 flex items-center justify-center ring-8 ring-white">
                            <i class="${activity.icon} text-white text-sm"></i>
                        </span>
                    </div>
                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                        <div>
                            <p class="text-sm text-gray-500">${activity.message}</p>
                            <p class="text-xs text-gray-400">${activity.user}</p>
                        </div>
                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                            ${activity.time}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        activityList.appendChild(li);
    });
}

// Update system status
function updateSystemStatus() {
    // This would typically fetch real system status
    // For now, we'll use the static status from HTML
}

// Setup auto-refresh
function setupAutoRefresh() {
    // Refresh dashboard data every 30 seconds
    setInterval(() => {
        loadDashboardData();
    }, 30000);
}

// Setup real-time updates
function setupRealTimeUpdates() {
    // This would typically use WebSockets or Server-Sent Events
    // For demo purposes, we'll simulate real-time updates
    
    setInterval(() => {
        // Randomly update activity feed
        if (Math.random() > 0.7) {
            addNewActivity();
        }
    }, 45000);
}

// Add new activity (simulated)
function addNewActivity() {
    const activities = [
        {
            type: 'system_update',
            message: 'Система обновлена',
            user: 'Система',
            time: 'только что',
            icon: 'fas fa-sync',
            color: 'blue'
        },
        {
            type: 'backup_completed',
            message: 'Резервное копирование завершено',
            user: 'Система',
            time: 'только что',
            icon: 'fas fa-database',
            color: 'green'
        }
    ];
    
    const randomActivity = activities[Math.floor(Math.random() * activities.length)];
    
    // Add to activity feed
    const activityList = document.querySelector('.flow-root ul');
    if (activityList) {
        const li = document.createElement('li');
        li.className = 'activity-item';
        
        li.innerHTML = `
            <div class="relative pb-8">
                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>
                <div class="relative flex space-x-3">
                    <div>
                        <span class="h-8 w-8 rounded-full bg-${randomActivity.color}-500 flex items-center justify-center ring-8 ring-white animate-pulse">
                            <i class="${randomActivity.icon} text-white text-sm"></i>
                        </span>
                    </div>
                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                        <div>
                            <p class="text-sm text-gray-500">${randomActivity.message}</p>
                            <p class="text-xs text-gray-400">${randomActivity.user}</p>
                        </div>
                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                            ${randomActivity.time}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Insert at the top
        activityList.insertBefore(li, activityList.firstChild);
        
        // Remove oldest activity if more than 10
        const activitiesList = activityList.querySelectorAll('li');
        if (activitiesList.length > 10) {
            activityList.removeChild(activitiesList[activitiesList.length - 1]);
        }
        
        // Show notification
        showNotification('Новая активность в системе', 'info');
    }
}

// Initialize tooltips
function initializeTooltips() {
    // Simple tooltip implementation
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(event) {
    const element = event.target;
    const tooltipText = element.getAttribute('data-tooltip');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-800 rounded shadow-lg';
    tooltip.textContent = tooltipText;
    tooltip.id = 'tooltip';
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
}

// Hide tooltip
function hideTooltip() {
    const tooltip = document.getElementById('tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Show loading overlay
function showLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner"></div>';
    overlay.id = 'loadingOverlay';
    document.body.appendChild(overlay);
}

// Hide loading overlay
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
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
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
        notification.style.transition = 'transform 0.3s ease';
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Export functions
window.adminDashboard = {
    loadDashboardData,
    showNotification,
    addNewActivity
};




