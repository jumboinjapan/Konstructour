// POI Form JavaScript Logic
(function() {
    'use strict';
    
    // Категории для мультиселекта
    const CATEGORIES_RU = [
        'Храм', 'Замок', 'Музей', 'Парк', 'Сад', 
        'Историческое место', 'Природная достопримечательность',
        'Рынок', 'Развлечения', 'Гастрономия', 
        'Шоппинг', 'Культура', 'Архитектура'
    ];
    
    const CATEGORIES_EN = [
        'Temple', 'Castle', 'Museum', 'Park', 'Garden',
        'Historical Site', 'Natural Attraction', 
        'Market', 'Entertainment', 'Gastronomy',
        'Shopping', 'Culture', 'Architecture'
    ];
    
    // Префектуры Японии
    const PREFECTURES = {
        ru: {
            'tokyo': 'Токио',
            'kyoto': 'Киото', 
            'osaka': 'Осака',
            'kanagawa': 'Канагава',
            'aichi': 'Айти',
            'fukuoka': 'Фукуока',
            'hokkaido': 'Хоккайдо',
            'hiroshima': 'Хиросима',
            'nara': 'Нара',
            'okinawa': 'Окинава'
        },
        en: {
            'tokyo': 'Tokyo',
            'kyoto': 'Kyoto',
            'osaka': 'Osaka', 
            'kanagawa': 'Kanagawa',
            'aichi': 'Aichi',
            'fukuoka': 'Fukuoka',
            'hokkaido': 'Hokkaido',
            'hiroshima': 'Hiroshima',
            'nara': 'Nara',
            'okinawa': 'Okinawa'
        }
    };
    
    let selectedCategories = {
        ru: [],
        en: []
    };
    
    let uploadedImages = [];
    let selectedTickets = [];
    
    // Инициализация при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        initializeForm();
        setupEventHandlers();
        loadInitialData();
    });
    
    function initializeForm() {
        // Генерация POI ID для новой записи
        const poiIdField = document.getElementById('poi_id');
        if (poiIdField && !poiIdField.value) {
            poiIdField.value = generatePoiId();
        }
        
        // Синхронизация префектур
        syncPrefectureSelects();
    }
    
    function generatePoiId() {
        const timestamp = Date.now().toString(36);
        const random = Math.random().toString(36).substr(2, 5);
        return `POI-${timestamp}-${random}`.toUpperCase();
    }
    
    function setupEventHandlers() {
        // Форма
        const form = document.getElementById('poiForm');
        form.addEventListener('submit', handleFormSubmit);
        
        // Категории
        document.getElementById('cats_ru_container').addEventListener('click', () => openCategoryModal('ru'));
        document.getElementById('cats_en_container').addEventListener('click', () => openCategoryModal('en'));
        
        // Google Place ID
        document.getElementById('checkGoogleBtn').addEventListener('click', checkGooglePlace);
        document.getElementById('place_id').addEventListener('input', validatePlaceId);
        
        // Префектуры - синхронизация
        document.getElementById('pref_ru').addEventListener('change', syncPrefectureFromRu);
        document.getElementById('pref_en').addEventListener('change', syncPrefectureFromEn);
        
        // Drag & Drop для изображений
        setupImageUpload();
        
        // Билеты
        document.getElementById('addTicketBtn').addEventListener('click', openTicketSelector);
    }
    
    function syncPrefectureSelects() {
        const prefRu = document.getElementById('pref_ru');
        const prefEn = document.getElementById('pref_en');
        
        // Очистить и заполнить селекты
        prefRu.innerHTML = '<option value="">Выберите префектуру</option>';
        prefEn.innerHTML = '<option value="">Select prefecture</option>';
        
        Object.keys(PREFECTURES.ru).forEach(key => {
            prefRu.innerHTML += `<option value="${key}">${PREFECTURES.ru[key]}</option>`;
            prefEn.innerHTML += `<option value="${key}">${PREFECTURES.en[key]}</option>`;
        });
    }
    
    function syncPrefectureFromRu() {
        const value = document.getElementById('pref_ru').value;
        document.getElementById('pref_en').value = value;
    }
    
    function syncPrefectureFromEn() {
        const value = document.getElementById('pref_en').value;
        document.getElementById('pref_ru').value = value;
    }
    
    // Работа с категориями
    function openCategoryModal(lang) {
        const modal = document.getElementById('categoryModal');
        const container = document.getElementById('categoryOptions');
        const categories = lang === 'ru' ? CATEGORIES_RU : CATEGORIES_EN;
        const selected = selectedCategories[lang];
        
        container.innerHTML = '';
        categories.forEach(cat => {
            const isChecked = selected.includes(cat);
            container.innerHTML += `
                <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                    <input type="checkbox" 
                           value="${cat}" 
                           ${isChecked ? 'checked' : ''}
                           onchange="toggleCategory('${lang}', '${cat}')"
                           class="form-checkbox h-4 w-4 text-indigo-600 rounded">
                    <span class="ml-2">${cat}</span>
                </label>
            `;
        });
        
        modal.dataset.lang = lang;
        modal.classList.remove('hidden');
    }
    
    window.closeCategoryModal = function() {
        const modal = document.getElementById('categoryModal');
        modal.classList.add('hidden');
        updateCategoryDisplay();
    };
    
    window.toggleCategory = function(lang, category) {
        const index = selectedCategories[lang].indexOf(category);
        if (index > -1) {
            selectedCategories[lang].splice(index, 1);
        } else {
            selectedCategories[lang].push(category);
        }
    };
    
    function updateCategoryDisplay() {
        // Обновление отображения для русских категорий
        const ruContainer = document.getElementById('cats_ru_container');
        if (selectedCategories.ru.length > 0) {
            ruContainer.innerHTML = selectedCategories.ru.map(cat => 
                `<span class="multiselect-tag">
                    ${cat}
                    <button type="button" onclick="removeCategory('ru', '${cat}')" class="ml-2">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </span>`
            ).join('');
        } else {
            ruContainer.innerHTML = '<div class="text-gray-500 text-sm">Нажмите для выбора категорий</div>';
        }
        document.getElementById('cats_ru').value = selectedCategories.ru.join(',');
        
        // Обновление отображения для английских категорий
        const enContainer = document.getElementById('cats_en_container');
        if (selectedCategories.en.length > 0) {
            enContainer.innerHTML = selectedCategories.en.map(cat => 
                `<span class="multiselect-tag">
                    ${cat}
                    <button type="button" onclick="removeCategory('en', '${cat}')" class="ml-2">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </span>`
            ).join('');
        } else {
            enContainer.innerHTML = '<div class="text-gray-500 text-sm">Click to select categories</div>';
        }
        document.getElementById('cats_en').value = selectedCategories.en.join(',');
    }
    
    window.removeCategory = function(lang, category) {
        const index = selectedCategories[lang].indexOf(category);
        if (index > -1) {
            selectedCategories[lang].splice(index, 1);
            updateCategoryDisplay();
        }
    };
    
    // Google Place ID
    function validatePlaceId() {
        const input = document.getElementById('place_id');
        const info = document.getElementById('googlePlaceInfo');
        const pattern = /^[A-Za-z0-9_-]{25,}$/;
        
        if (input.value && pattern.test(input.value)) {
            info.style.display = 'block';
            info.innerHTML = `
                <p class="text-sm text-blue-800">
                    <i class="fas fa-check-circle mr-1"></i>
                    Place ID формат валиден
                </p>
            `;
        } else if (input.value) {
            info.style.display = 'block';
            info.innerHTML = `
                <p class="text-sm text-red-600">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    Неверный формат Place ID
                </p>
            `;
        } else {
            info.style.display = 'none';
        }
    }
    
    function checkGooglePlace() {
        const placeId = document.getElementById('place_id').value;
        if (placeId) {
            const url = `https://www.google.com/maps/search/?api=1&query=place_id:${placeId}`;
            window.open(url, '_blank');
        } else {
            showNotification('Введите Google Place ID', 'warning');
        }
    }
    
    // Загрузка изображений
    function setupImageUpload() {
        const dropzone = document.getElementById('imageDropzone');
        const fileInput = document.getElementById('images');
        
        // Клик на зону
        dropzone.addEventListener('click', () => fileInput.click());
        
        // Выбор файлов
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag & Drop
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
    }
    
    function handleFileSelect(e) {
        handleFiles(e.target.files);
    }
    
    function handleFiles(files) {
        const preview = document.getElementById('imagePreview');
        
        Array.from(files).forEach(file => {
            if (!file.type.startsWith('image/')) {
                showNotification('Только изображения разрешены', 'error');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                showNotification(`Файл ${file.name} слишком большой (макс. 5 МБ)`, 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const id = `img-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
                uploadedImages.push({
                    id: id,
                    file: file,
                    url: e.target.result
                });
                
                const div = document.createElement('div');
                div.className = 'relative group';
                div.innerHTML = `
                    <img src="${e.target.result}" 
                         alt="${file.name}" 
                         class="w-full h-32 object-cover rounded-lg">
                    <button type="button" 
                            onclick="removeImage('${id}')"
                            class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
    
    window.removeImage = function(id) {
        uploadedImages = uploadedImages.filter(img => img.id !== id);
        updateImagePreview();
    };
    
    function updateImagePreview() {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = uploadedImages.map(img => `
            <div class="relative group">
                <img src="${img.url}" 
                     alt="${img.file.name}" 
                     class="w-full h-32 object-cover rounded-lg">
                <button type="button" 
                        onclick="removeImage('${img.id}')"
                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        `).join('');
    }
    
    // Билеты
    function openTicketSelector() {
        // В реальном приложении здесь будет модальное окно со списком билетов
        // Сейчас используем простой пример
        const ticketName = prompt('Название билета:');
        const ticketPrice = prompt('Цена билета (¥):');
        
        if (ticketName && ticketPrice) {
            const ticket = {
                id: `TKT-${Date.now()}`,
                name: ticketName,
                price: ticketPrice,
                type: 'Adult'
            };
            
            selectedTickets.push(ticket);
            updateTicketsList();
        }
    }
    
    function updateTicketsList() {
        const list = document.getElementById('ticketsList');
        const lookups = document.getElementById('ticketLookups');
        
        if (selectedTickets.length > 0) {
            list.innerHTML = selectedTickets.map(ticket => `
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <span>${ticket.name} - ¥${ticket.price}</span>
                    <button type="button" 
                            onclick="removeTicket('${ticket.id}')"
                            class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
            
            // Обновляем lookup поля
            lookups.style.display = 'grid';
            document.getElementById('display_type').value = selectedTickets.map(t => t.type).join(', ');
            document.getElementById('display_price').value = selectedTickets.map(t => `¥${t.price}`).join(', ');
        } else {
            list.innerHTML = '';
            lookups.style.display = 'none';
        }
    }
    
    window.removeTicket = function(id) {
        selectedTickets = selectedTickets.filter(t => t.id !== id);
        updateTicketsList();
    };
    
    // Загрузка начальных данных
    async function loadInitialData() {
        const urlParams = new URLSearchParams(window.location.search);
        const cityName = urlParams.get('city');
        const regionName = urlParams.get('region');
        
        // Загрузка регионов из API
        try {
            const response = await fetch('/api/regions.php', {
                headers: { 'X-Admin-Token': window.ADMIN_TOKEN || '' }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.regions) {
                    const select = document.getElementById('region_ref');
                    select.innerHTML = '<option value="">Выберите регион</option>';
                    data.regions.forEach(region => {
                        const selected = regionName && region.name === regionName ? 'selected' : '';
                        select.innerHTML += `<option value="${region.id}" ${selected}>${region.name}</option>`;
                    });
                }
            }
        } catch (error) {
            console.error('Ошибка загрузки регионов:', error);
        }
        
        // Предзаполнение города если передан в URL
        if (cityName) {
            // В будущем здесь можно будет загрузить список городов и выбрать нужный
            showNotification(`Создание POI для: ${cityName}`, 'info');
        }
        
        // Если редактирование - загрузить данные POI
        const poiId = urlParams.get('id');
        if (poiId) {
            loadPoiData(poiId);
        }
    }
    
    async function loadPoiData(poiId) {
        try {
            // В реальном приложении здесь будет загрузка данных из API
            showNotification('Загрузка данных POI...', 'info');
        } catch (error) {
            showNotification('Ошибка загрузки данных', 'error');
        }
    }
    
    // Отправка формы
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            poi_id: formData.get('poi_id'),
            name_ru: formData.get('name_ru'),
            name_en: formData.get('name_en'),
            region_ref: formData.get('region_ref'),
            pref_ru: formData.get('pref_ru'),
            pref_en: formData.get('pref_en'),
            cats_ru: selectedCategories.ru,
            cats_en: selectedCategories.en,
            place_id: formData.get('place_id'),
            website: formData.get('website'),
            hours: formData.get('hours'),
            desc_ru: formData.get('desc_ru'),
            desc_en: formData.get('desc_en'),
            images: uploadedImages,
            tickets: selectedTickets,
            published: formData.get('published') === 'on',
            notes: formData.get('notes')
        };
        
        try {
            showNotification('Сохранение POI...', 'info');
            
            // В реальном приложении здесь будет отправка на сервер
            const response = await fetch('/api/db-sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Admin-Token': window.ADMIN_TOKEN || ''
                },
                body: JSON.stringify({
                    scope: 'pois',
                    action: 'create',
                    data: data
                })
            });
            
            if (response.ok) {
                showNotification('POI успешно сохранён!', 'success');
                setTimeout(() => {
                    window.location.href = 'databases.html';
                }, 1500);
            } else {
                throw new Error('Ошибка сохранения');
            }
        } catch (error) {
            showNotification('Ошибка: ' + error.message, 'error');
        }
    }
    
    // Уведомления
    function showNotification(message, type = 'info') {
        // Простое уведомление
        const div = document.createElement('div');
        div.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500' :
            type === 'error' ? 'bg-red-500' :
            type === 'warning' ? 'bg-yellow-500' :
            'bg-blue-500'
        } text-white`;
        
        div.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${
                    type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-circle' :
                    type === 'warning' ? 'fa-exclamation-triangle' :
                    'fa-info-circle'
                } mr-2"></i>
                ${message}
            </div>
        `;
        
        document.body.appendChild(div);
        
        setTimeout(() => {
            div.style.opacity = '0';
            div.style.transition = 'opacity 0.3s';
            setTimeout(() => div.remove(), 300);
        }, 3000);
    }
})();
