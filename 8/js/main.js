// Основные переменные и инициализация
document.addEventListener('DOMContentLoaded', function() {
    // Навигация
    const navbar = document.querySelector('.navbar');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuClose = document.getElementById('mobileMenuClose');
    const overlay = document.getElementById('overlay');
    const mobileDropdownToggle = document.getElementById('mobileDropdownToggle');
    const mobileDropdownMenu = document.getElementById('mobileDropdownMenu');
    const contactBtn = document.getElementById('contactBtn');
    const mobileContactBtn = document.getElementById('mobileContactBtn');
    const aboutContactBtn = document.getElementById('aboutContactBtn');
    
    // Модальное окно
    const modalOverlay = document.getElementById('modalOverlay');
    const modal = document.getElementById('modal');
    const modalClose = document.getElementById('modalClose');
    const modalForm = document.getElementById('modalForm');
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');
    const modalFormMessage = document.getElementById('modalFormMessage');
    
    // Форма
    const contactForm = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const formMessage = document.getElementById('formMessage');
    
    // Слайдер
    const slider = document.getElementById('slider');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const sliderDots = document.getElementById('sliderDots');
    
    // Текущее состояние
    let currentSlide = 0;
    let totalSlides = sliderData.length;
    let isModalOpen = false;
    let animationFrameId = null;
    
    // Инициализация
    initVideoBackground();
    initSlider();
    loadFormData();
    
    // Инициализация видео фона
    function initVideoBackground() {
        const header = document.querySelector('.header');
        const video = document.createElement('video');
        video.className = 'video-bg';
        video.autoplay = true;
        video.muted = true;
        video.loop = true;
        
        const source = document.createElement('source');
        source.src = siteConfig.videoUrl;
        source.type = 'video/mp4';
        
        video.appendChild(source);
        video.innerHTML += 'Ваш браузер не поддерживает видео.';
        
        header.insertBefore(video, header.firstChild);
    }
    
    // Обработчик скролла для навигации
    window.addEventListener('scroll', function() {
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Открытие мобильного меню
    mobileMenuBtn.addEventListener('click', function() {
        mobileMenu.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
    
    // Закрытие мобильного меню
    function closeMobileMenu() {
        mobileMenu.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        mobileDropdownMenu.classList.remove('active');
        mobileDropdownToggle.classList.remove('active');
    }
    
    mobileMenuClose.addEventListener('click', closeMobileMenu);
    overlay.addEventListener('click', closeMobileMenu);
    
    // Выпадающее меню в мобильной версии
    mobileDropdownToggle.addEventListener('click', function(e) {
        e.preventDefault();
        mobileDropdownMenu.classList.toggle('active');
        mobileDropdownToggle.classList.toggle('active');
    });
    
    // Плавная прокрутка по якорным ссылкам
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') return;
            
            const targetId = this.getAttribute('href');
            if (targetId.startsWith('#')) {
                e.preventDefault();
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    closeMobileMenu();
                    
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // Открытие модального окна
    function openModal(button) {
        isModalOpen = true;
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        animateModalAppearance(button);
        loadModalFormData();
    }
    
    // Анимация появления модального окна
    function animateModalAppearance(button) {
        if (!button) return;
        
        const buttonRect = button.getBoundingClientRect();
        const modalRect = modal.getBoundingClientRect();
        
        const startX = buttonRect.left + buttonRect.width / 2;
        const startY = buttonRect.top + buttonRect.height / 2;
        
        const endX = window.innerWidth / 2;
        const endY = window.innerHeight / 2;
        
        let startTime = null;
        
        function animate(time) {
            if (!startTime) startTime = time;
            const elapsed = time - startTime;
            const duration = 400;
            
            if (elapsed < duration) {
                const progress = elapsed / duration;
                const easeProgress = easeOutCubic(progress);
                
                const currentX = startX + (endX - startX) * easeProgress;
                const currentY = startY + (endY - startY) * easeProgress;
                
                const startScale = 0.1;
                const endScale = 1;
                const currentScale = startScale + (endScale - startScale) * easeProgress;
                
                modal.style.transform = `translate(${currentX - modalRect.width / 2}px, ${currentY - modalRect.height / 2}px) scale(${currentScale})`;
                
                animationFrameId = requestAnimationFrame(animate);
            } else {
                modal.style.transform = 'translate(-50%, -50%) scale(1)';
                modal.style.left = '50%';
                modal.style.top = '50%';
                modal.style.position = 'fixed';
                cancelAnimationFrame(animationFrameId);
            }
        }
        
        modal.style.left = `${startX}px`;
        modal.style.top = `${startY}px`;
        modal.style.transform = 'scale(0.1)';
        modal.style.position = 'fixed';
        
        animationFrameId = requestAnimationFrame(animate);
    }
    
    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }
    
    // Закрытие модального окна
    function closeModal() {
        isModalOpen = false;
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
        
        modalFormMessage.className = 'form-message';
        modalFormMessage.textContent = '';
        modalFormMessage.style.display = 'none';
    }
    
    modalClose.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });
    
    // Обработчики кнопок для открытия модального окна
    [contactBtn, mobileContactBtn, aboutContactBtn].forEach(btn => {
        if (btn) {
            btn.addEventListener('click', function() {
                openModal(this);
                closeMobileMenu();
            });
        }
    });
    
    // Инициализация слайдера
    function initSlider() {
        // Создаем слайды
        sliderData.forEach((slideData, index) => {
            const slide = document.createElement('div');
            slide.className = 'slide';
            slide.innerHTML = `
                <div class="slide-icon">
                    <i class="${slideData.icon}"></i>
                </div>
                <div>
                    <h3>${slideData.title}</h3>
                    <p>${slideData.description}</p>
                </div>
            `;
            slider.appendChild(slide);
        });
        
        // Создаем точки для слайдера
        for (let i = 0; i < totalSlides; i++) {
            const dot = document.createElement('div');
            dot.classList.add('dot');
            if (i === 0) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(i));
            sliderDots.appendChild(dot);
        }
        
        // Обработчики кнопок слайдера
        prevBtn.addEventListener('click', showPrevSlide);
        nextBtn.addEventListener('click', showNextSlide);
        
        // Автопереключение слайдов каждые 5 секунд
        setInterval(showNextSlide, 5000);
    }
    
    // Функции слайдера
    function showPrevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        updateSlider();
    }
    
    function showNextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        updateSlider();
    }
    
    function goToSlide(index) {
        currentSlide = index;
        updateSlider();
    }
    
    function updateSlider() {
        slider.style.transform = `translateX(-${currentSlide * 100}%)`;
        
        document.querySelectorAll('.dot').forEach((dot, index) => {
            if (index === currentSlide) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }
    
    // Сохранение данных формы в LocalStorage
    function saveFormData(formId, formData) {
        localStorage.setItem(`formData_${formId}`, JSON.stringify(formData));
    }
    
    // Загрузка данных формы из LocalStorage
    function loadFormData() {
        const savedData = localStorage.getItem('formData_main');
        if (savedData) {
            try {
                const formData = JSON.parse(savedData);
                document.getElementById('name').value = formData.name || '';
                document.getElementById('email').value = formData.email || '';
                document.getElementById('phone').value = formData.phone || '';
                document.getElementById('message').value = formData.message || '';
            } catch (e) {
                console.error('Ошибка при загрузке данных формы:', e);
            }
        }
    }
    
    // Загрузка данных в модальную форму
    function loadModalFormData() {
        const savedData = localStorage.getItem('formData_modal');
        if (savedData) {
            try {
                const formData = JSON.parse(savedData);
                document.getElementById('modalName').value = formData.name || '';
                document.getElementById('modalEmail').value = formData.email || '';
                document.getElementById('modalPhone').value = formData.phone || '';
                document.getElementById('modalMessage').value = formData.message || '';
            } catch (e) {
                console.error('Ошибка при загрузке данных модальной формы:', e);
            }
        }
    }
    
    // Обработка отправки основной формы
    contactForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            message: document.getElementById('message').value
        };
        
        saveFormData('main', formData);
        await submitForm(formData, submitBtn, formMessage, false);
    });
    
    // Обработка отправки модальной формы
    modalForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('modalName').value,
            email: document.getElementById('modalEmail').value,
            phone: document.getElementById('modalPhone').value,
            message: document.getElementById('modalMessage').value
        };
        
        saveFormData('modal', formData);
        const success = await submitForm(formData, modalSubmitBtn, modalFormMessage, true);
        
        if (success) {
            setTimeout(() => {
                closeModal();
            }, 2000);
        }
    });
    
    // Функция отправки формы
    async function submitForm(formData, submitButton, messageElement, isModal) {
        submitButton.disabled = true;
        submitButton.classList.add('btn-loading');
        
        try {
            // Реальная отправка через fetch
            const response = await fetch(siteConfig.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            if (response.ok) {
                messageElement.textContent = 'Сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.';
                messageElement.className = 'form-message success';
                messageElement.style.display = 'block';
                
                if (!isModal) {
                    contactForm.reset();
                    localStorage.removeItem('formData_main');
                }
                
                const newUrl = window.location.pathname + '?form=submitted';
                window.history.pushState({ form: 'submitted' }, '', newUrl);
                
                return true;
            } else {
                throw new Error('Ошибка сервера');
            }
        } catch (error) {
            messageElement.textContent = 'Произошла ошибка при отправке формы. Пожалуйста, попробуйте еще раз.';
            messageElement.className = 'form-message error';
            messageElement.style.display = 'block';
            
            console.error('Ошибка отправки формы:', error);
            return false;
        } finally {
            submitButton.disabled = false;
            submitButton.classList.remove('btn-loading');
        }
    }
    
    // Обработка History API
    window.addEventListener('popstate', function(event) {
        console.log('Состояние истории изменено:', event.state);
    });
});

// Добавьте в конец существующего файла main.js:

// Сохранение формы через API (для задания 8)
const apiForm = document.getElementById('mainForm');
if (apiForm && window.fetch) {
    // Уже добавлено в index.php, этот код для совместимости
    console.log('API форма готова к отправке');
}

// Функция для загрузки и отображения видео (если есть)
function initVideoBackground() {
    const header = document.querySelector('.header');
    if (header && !header.querySelector('.video-bg')) {
        const video = document.createElement('video');
        video.className = 'video-bg';
        video.autoplay = true;
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        
        const source = document.createElement('source');
        source.src = 'video.mp4';
        source.type = 'video/mp4';
        
        video.appendChild(source);
        video.innerHTML = 'Ваш браузер не поддерживает видео';
        
        const overlay = header.querySelector('.header-overlay');
        if (overlay) {
            header.insertBefore(video, overlay);
        } else {
            header.insertBefore(video, header.firstChild);
        }
    }
}

// Инициализация видео при загрузке
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVideoBackground);
} else {
    initVideoBackground();
}
