// Admin Dashboard JavaScript with Japanese Aesthetics

// Check authentication on load
document.addEventListener('DOMContentLoaded', function() {
    // Validate session
    if (!window.adminAuth || !window.adminAuth.validateSession()) {
        window.location.href = 'index.html';
        return;
    }
    
    // Initialize dashboard
    initializeDashboard();
    
    // Add Japanese animations
    addDashboardAnimations();
    
    // Setup event listeners
    setupEventListeners();
    
    // Load initial data
    loadDashboardData();
});

// Initialize dashboard
function initializeDashboard() {
    // Update user info
    const session = window.adminAuth.getCurrentSession();
    if (session) {
        const userElements = document.querySelectorAll('[data-user-name]');
        userElements.forEach(el => {
            el.textContent = session.username;
        });
    }
    
    // Start real-time updates
    startRealtimeUpdates();

    // Render API statuses from localStorage
    try { renderApiStatuses(); } catch(_) {}

    // Trigger immediate health poll so sorting/labels update without delay
    try { pollHealth(); } catch(_) {}
    // Trigger immediate errors counter update
    try { updateErrorsCard(); } catch(_) {}
}

// Add Japanese animations
function addDashboardAnimations() {
    // Анимация появления карточек
    const cards = document.querySelectorAll('.stat-card, .tour-card, .glass-panel');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Анимация чисел в статистике
    animateCounters();
    
    // Добавляем интерактивность меню
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Не блокируем клики по кнопкам в .api-actions; ограничиваем только ссылки внутри .api-actions
            if (e.target && e.target.closest && e.target.closest('.api-actions') && e.target.closest('a')) { e.preventDefault(); return; }
            const href = this.getAttribute('href') || '';
            if (href.startsWith('#')) {
                e.preventDefault();
                menuItems.forEach(mi => mi.classList.remove('active'));
                this.classList.add('active');
                const section = href.substring(1);
                loadSection(section);
            } else if (href) {
                // allow default navigation for external links
            }
        });
    });
}

// Анимация счетчиков
function animateCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-counter') || counter.textContent);
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;
        
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };
        
        updateCounter();
    });
}

// Setup event listeners
function setupEventListeners() {
    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            if (confirm('Вы уверены, что хотите выйти?')) {
                // Анимация выхода
                document.body.style.transition = 'opacity 0.5s ease-out';
                document.body.style.opacity = '0';
                
                setTimeout(() => {
                    window.adminAuth.logout();
                }, 500);
            }
        });
    }
    
    // Theme toggle
    const themeToggle = document.querySelector('[data-theme-toggle]');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Notification bell
    const notificationBell = document.querySelector('[data-notifications]');
    if (notificationBell) {
        notificationBell.addEventListener('click', showNotifications);
    }

    // Show errors log modal
    const btnShowErrors = document.querySelector('[data-show-errors]');
    if (btnShowErrors){
        btnShowErrors.addEventListener('click', async function(e){
            e.preventDefault();
            try{
                const res = await fetch('../api/error-log.php?action=list&limit=50', { cache:'no-store' });
                const j = await res.json();
                const items = (j && j.items) ? j.items : [];
                const html = items.length ? (
                    '<div class="space-y-2">'+
                    items.map(e=>{
                        const ts = new Date((e.ts||0)*1000).toLocaleString();
                        const ctx = e.ctx ? ('<pre class="bg-gray-50 border border-gray-200 rounded p-2 text-xs overflow-auto">'+escapeHtml(JSON.stringify(e.ctx, null, 2))+'</pre>') : '';
                        return '<div class="p-2 bg-white border border-gray-200 rounded">'+
                               '<div class="text-sm text-gray-800">'+escapeHtml(e.msg||'')+'</div>'+
                               '<div class="text-xs text-gray-500 mt-1">'+ts+'</div>'+ctx+
                               '</div>';
                    }).join('')+
                    '</div>') : '<div class="text-sm text-gray-600">Лог пуст.</div>';
                const modal = createModal('Ошибки в работе сайта', html);
                document.body.appendChild(modal);
                setTimeout(()=>{ modal.style.opacity='1'; modal.querySelector('.modal-content').style.transform='scale(1)'; }, 10);
            }catch(_){ showError('Не удалось загрузить лог ошибок'); }
        });
    }
    
    // Create tour button
    const createTourBtn = document.querySelector('[data-create-tour]');
    if (createTourBtn) {
        createTourBtn.addEventListener('click', openCreateTourModal);
    }
    
    // Table row actions
    document.addEventListener('click', function(e) {
        if (e.target.closest('[data-action="view"]')) {
            viewTour(e.target.closest('tr').dataset.tourId);
        }
        if (e.target.closest('[data-action="edit"]')) {
            editTour(e.target.closest('tr').dataset.tourId);
        }
        if (e.target.closest('[data-action="delete"]')) {
            deleteTour(e.target.closest('tr').dataset.tourId);
        }
    });

    // Явный обработчик для ссылки Каталог API (надёжный fallback)
    const apiCatalogLink = document.getElementById('linkApiCatalog');
    if (apiCatalogLink) {
        apiCatalogLink.addEventListener('click', function(e){
            e.preventDefault();
            window.location.assign(this.getAttribute('href'));
        });
    }

    // Listen for storage changes from api-settings page
    window.addEventListener('storage', function(e){
        if (e.key === 'konstructour_api_status') {
            try { renderApiStatuses(); } catch(_) {}
        }
    });
}

// Load dashboard data
async function loadDashboardData() {
    try {
        // Показываем индикатор загрузки
        showLoadingIndicator();
        
        // Имитация загрузки данных (в реальном проекте - API запросы)
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Обновляем статистику
        updateStatistics({
            activeTours: 24,
            newClients: 156,
            monthlyRevenue: 2400000,
            rating: 4.9
        });
        
        // Обновляем график
        updateChart();
        
        // Скрываем индикатор загрузки
        hideLoadingIndicator();
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showError('Ошибка загрузки данных');
    }
}

// Update statistics
function updateStatistics(stats) {
    // Активные туры
    const activeToursEl = document.querySelector('[data-stat="active-tours"]');
    if (activeToursEl) {
        activeToursEl.textContent = stats.activeTours;
        activeToursEl.setAttribute('data-counter', stats.activeTours);
    }
    
    // Новые клиенты
    const newClientsEl = document.querySelector('[data-stat="new-clients"]');
    if (newClientsEl) {
        newClientsEl.textContent = stats.newClients;
        newClientsEl.setAttribute('data-counter', stats.newClients);
    }
    
    // Доход
    const revenueEl = document.querySelector('[data-stat="revenue"]');
    if (revenueEl) {
        revenueEl.textContent = `¥${(stats.monthlyRevenue / 1000000).toFixed(1)}M`;
    }
    
    // Рейтинг
    const ratingEl = document.querySelector('[data-stat="rating"]');
    if (ratingEl) {
        ratingEl.textContent = stats.rating.toFixed(1);
    }
    
    // Перезапускаем анимацию счетчиков
    animateCounters();
}

// Update chart
function updateChart() {
    const bars = document.querySelectorAll('[data-chart-bar]');
    const heights = [60, 75, 45, 85, 70, 90];
    
    bars.forEach((bar, index) => {
        if (heights[index]) {
            bar.style.height = '0%';
            setTimeout(() => {
                bar.style.transition = 'height 1s ease-out';
                bar.style.height = `${heights[index]}%`;
            }, index * 100);
        }
    });
}

// Render API status list from localStorage shared state
function renderApiStatuses(){
    const list = document.getElementById('api-status-list');
    if (!list) return;
    let map = {};
    try { map = JSON.parse(localStorage.getItem('konstructour_api_status')||'{}') || {}; } catch(_) {}
    const rows = Array.from(list.querySelectorAll('[data-provider]'));
    rows.forEach(row => {
        const provider = row.getAttribute('data-provider');
        const dot = row.querySelector('[data-dot]');
        const text = row.querySelector('[data-text]');
        const entry = map[provider];
        const state = entry && entry.state ? entry.state : 'none';
        let label = entry && entry.text ? entry.text : 'Ожидание';
        let color = 'bg-gray-300';
        if (state === 'ok') color = 'bg-green-500';
        else if (state === 'err') color = 'bg-red-500';
        else if (state === 'loading') color = 'bg-purple-500';
        dot.className = 'w-2 h-2 rounded-full mr-2 ' + color;
        if (state === 'ok') label = 'Подключено';
        if (state === 'none') label = 'Ожидание';
        text.textContent = label;
    });
    // Move green (ok) rows to the top
    const weight = s => (s==='ok'?0:(s==='loading'?1:(s==='err'?2:3)));
    rows
      .sort((a,b)=>{
        const sa = (map[a.getAttribute('data-provider')]||{}).state || 'none';
        const sb = (map[b.getAttribute('data-provider')]||{}).state || 'none';
        return weight(sa) - weight(sb);
      })
      .forEach(row => list.appendChild(row));
}

// Poll aggregated server health and mirror to UI
async function pollHealth(){
    try {
        const res = await fetch('/api/health.php', { cache:'no-store' });
        const data = await res.json();
        if (!data || !data.results) return;
        const map = {};
        Object.keys(data.results).forEach(p => {
            const r = data.results[p];
            let state = 'err';
            if (r.ok === true) state = 'ok';
            else if (r.ok === null) state = 'none';
            map[p] = { state, text: r.text || (state==='ok' ? 'Подключено' : state==='none' ? 'Ожидание' : 'Ошибка'), ts: (r.checked_at||0) * 1000 };
        });
        try { localStorage.setItem('konstructour_api_status', JSON.stringify(map)); } catch(_) {}
        renderApiStatuses();
    } catch (e) {
        // ignore
    }
}

// Toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Анимация переключения
    document.body.style.transition = 'none';
    document.body.style.opacity = '0.8';
    
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.3s ease-out';
        document.body.style.opacity = '1';
    }, 100);
}

// Show notifications
function showNotifications() {
    // Создаем модальное окно с уведомлениями
    const modal = createModal('Уведомления', `
        <div class="space-y-3">
            <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg">
                <p class="text-sm text-purple-800">Новый клиент зарегистрировался</p>
                <p class="text-xs text-purple-600 mt-1">5 минут назад</p>
            </div>
            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-800">Тур "Токио - Киото" подтвержден</p>
                <p class="text-xs text-green-600 mt-1">1 час назад</p>
            </div>
            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">Обновление системы завершено</p>
                <p class="text-xs text-blue-600 mt-1">3 часа назад</p>
            </div>
        </div>
    `);
    
    document.body.appendChild(modal);
    
    // Анимация появления
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
    }, 10);
}

// Create modal
function createModal(title, content) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    modal.style.opacity = '0';
    modal.style.transition = 'opacity 0.3s ease-out';
    
    modal.innerHTML = `
        <div class="modal-content glass-panel texture-washi max-w-md w-full p-6 rounded-lg" style="transform: scale(0.9); transition: transform 0.3s ease-out;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-800">${title}</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            ${content}
        </div>
    `;
    
    // Закрытие по клику вне модала
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    return modal;
}

function escapeHtml(str){
    if (typeof str !== 'string') return String(str);
    return str.replace(/[&<>"]/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c;
    });
}

// Load section
async function loadSection(section) {
    showLoadingIndicator();
    
    // Имитация загрузки раздела
    await new Promise(resolve => setTimeout(resolve, 500));
    
    switch(section) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'tours':
            loadToursSection();
            break;
        case 'clients':
            loadClientsSection();
            break;
        case 'locations':
            loadLocationsSection();
            break;
        case 'ai-tools':
            loadAIToolsSection();
            break;
        case 'settings':
            loadSettingsSection();
            break;
    }
    
    hideLoadingIndicator();
}

// Tour actions
function viewTour(tourId) {
    console.log('Viewing tour:', tourId);
    // Здесь будет логика просмотра тура
}

function editTour(tourId) {
    console.log('Editing tour:', tourId);
    // Здесь будет логика редактирования тура
}

function deleteTour(tourId) {
    if (confirm('Вы уверены, что хотите удалить этот тур?')) {
        console.log('Deleting tour:', tourId);
        // Здесь будет логика удаления тура
    }
}

function openCreateTourModal() {
    const modal = createModal('Создать новый тур', `
        <form class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Название тура
                </label>
                <input type="text" class="input-japanese" placeholder="Токио - Киото - Осака">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Клиент
                </label>
                <select class="input-japanese">
                    <option>Выберите клиента</option>
                    <option>Иван Петров</option>
                    <option>Мария Сидорова</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Дата начала
                    </label>
                    <input type="date" class="input-japanese">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Дата окончания
                    </label>
                    <input type="date" class="input-japanese">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="this.closest('.fixed').remove()" class="btn-japanese">
                    Отмена
                </button>
                <button type="submit" class="btn-japanese accent">
                    <i class="fas fa-plus mr-2"></i>
                    Создать тур
                </button>
            </div>
        </form>
    `);
    
    document.body.appendChild(modal);
    
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
    }, 10);
}

// Loading indicator
function showLoadingIndicator() {
    const loader = document.createElement('div');
    loader.id = 'loading-indicator';
    loader.className = 'fixed top-4 right-4 glass-panel px-4 py-2 rounded-lg flex items-center';
    loader.innerHTML = `
        <div class="w-4 h-4 border-2 border-purple-500 border-t-transparent rounded-full animate-spin mr-2"></div>
        <span class="text-sm text-gray-600">Загрузка...</span>
    `;
    
    document.body.appendChild(loader);
}

function hideLoadingIndicator() {
    const loader = document.getElementById('loading-indicator');
    if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => loader.remove(), 300);
    }
}

// Show error
function showError(message) {
    const error = document.createElement('div');
    error.className = 'fixed top-4 right-4 glass-panel bg-red-50 border border-red-200 px-4 py-3 rounded-lg';
    error.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
            <span class="text-sm text-red-800">${message}</span>
        </div>
    `;
    
    document.body.appendChild(error);
    
    setTimeout(() => {
        error.style.opacity = '0';
        setTimeout(() => error.remove(), 300);
    }, 5000);
}

// Start realtime updates
function startRealtimeUpdates() {
    // Обновляем статистику каждые 30 секунд
    setInterval(() => { updateRealtimeStats(); }, 30000);
    // Периодический опрос серверного health (каждые 20с)
    setInterval(() => { try { pollHealth(); } catch(_) {} }, 20000);
    // Периодический опрос счетчика ошибок за час (каждые 20с)
    setInterval(() => { try { updateErrorsCard(); } catch(_) {} }, 20000);
}

// Update realtime stats
function updateRealtimeStats() {
    // Имитация обновления в реальном времени
    const activeTours = document.querySelector('[data-stat="active-tours"]');
    if (activeTours) {
        const current = parseInt(activeTours.textContent);
        const change = Math.floor(Math.random() * 3) - 1; // -1, 0, или 1
        const newValue = Math.max(0, current + change);
        
        if (change !== 0) {
            activeTours.textContent = newValue;
            activeTours.style.color = change > 0 ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)';
            
            setTimeout(() => {
                activeTours.style.color = '';
            }, 2000);
        }
    }
}

// Initialize theme
const savedTheme = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);

// Обновить карточку "Ошибки за час"
async function updateErrorsCard(){
    try{
        const res = await fetch('../api/error-log.php?action=count&window=3600', { cache:'no-store' });
        const j = await res.json();
        if (!j || !j.ok) return;
        const count = j.count || 0;
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach(card=>{
            const title = card.querySelector('p.text-sm');
            if (title && /Ошибки в работе сайта/i.test(title.textContent || '')){
                const val = card.querySelector('p.text-2xl');
                if (val) val.textContent = String(count);
            }
        });
    }catch(_){ }
}