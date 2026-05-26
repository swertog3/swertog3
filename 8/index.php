<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['general'] = 'Ошибка безопасности. Попробуйте снова.';
        saveErrorsToCookies($errors, $_POST);
        header('Location: index.php');
        exit;
    // После успешного сохранения в БД (перед header)
    $pdo->commit();
    
    // Сохраняем в сессию
    $_SESSION['last_registered_login'] = $credentials['login'];
    $_SESSION['last_registered_password'] = $credentials['password'];
    
    // ОТЛАДКА: записываем в файл
    file_put_contents('/tmp/debug_session.txt', date('Y-m-d H:i:s') . " - Login: " . $credentials['login'] . " - Password: " . $credentials['password'] . "\n", FILE_APPEND);
    
    // Также проверим, что записалось в сессию
    error_log("SESSION after save: " . print_r($_SESSION, true));
    
    // Перенаправление
    header('Location: index.php?success=1');
    exit;
    }
    
    $errors = [];
    $validData = [];
    
    // Валидация всех полей
    $validData['fullname'] = validateFullname($_POST['fullname'] ?? '', $errors);
    $validData['phone'] = validatePhone($_POST['phone'] ?? '', $errors);
    $validData['email'] = validateEmail($_POST['email'] ?? '', $errors);
    $validData['birthdate'] = validateBirthdate($_POST['birthdate'] ?? '', $errors);
    $validData['gender'] = validateGender($_POST['gender'] ?? '', $errors);
    $validData['languages'] = validateLanguages($_POST['languages'] ?? [], $errors);
    $validData['biography'] = validateBiography($_POST['biography'] ?? '', $errors);
    $validContract = validateContract($_POST['contract'] ?? '', $errors);
    
    if (!empty($errors)) {
        saveErrorsToCookies($errors, $_POST);
        header('Location: index.php');
        exit;
    }
    
    try {
        // Генерация логина и пароля
        $credentials = generateCredentials($validData['fullname'], $validData['email']);
        
        // Вставка в БД
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (fullname, phone, email, birthdate, gender, biography, contract_accepted, login, password_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $validData['fullname'],
            $validData['phone'],
            $validData['email'],
            $validData['birthdate'],
            $validData['gender'],
            $validData['biography'],
            1,
            $credentials['login'],
            $credentials['hash']
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Вставка языков
        $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
        foreach ($validData['languages'] as $langId) {
            $stmtLang->execute([$userId, $langId]);
        }
        
        $pdo->commit();
        
        // Сохранение в cookies на год
        saveToCookies($validData);
        
        // Аудит
        auditLog($userId, 'register', ['email' => $validData['email']]);
        
        // Сохраняем логин/пароль в сессию для отображения
        $_SESSION['last_registered_login'] = $credentials['login'];
        $_SESSION['last_registered_password'] = $credentials['password'];

        error_log("Session login: " . ($_SESSION['last_registered_login'] ?? 'NOT SET'));
        error_log("Session password: " . ($_SESSION['last_registered_password'] ?? 'NOT SET'));
        
        // Перенаправление для отображения сообщения об успехе
        header('Location: index.php?success=1');
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors['general'] = 'Ошибка при сохранении данных. Попробуйте снова.';
        saveErrorsToCookies($errors, $_POST);
        header('Location: index.php');
        exit;
    }
}

// Загрузка сохранённых данных
$savedData = loadFromCookies();
$errorData = loadErrorsFromCookies();
$errors = $errorData['errors'];
$oldInput = $errorData['oldInput'];

// Получение языков для формы
$stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
$languages = $stmt->fetchAll();

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | WebStudio</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Дополнительные стили для формы */
        .form-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 0;
        }
        .registration-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .form-control.error {
            border-color: #e74c3c;
            background-color: #fff5f5;
        }
        .error-message {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        select[multiple] {
            height: 150px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .credentials-box {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
        }
        .credentials-box p {
            margin: 5px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .admin-link {
            text-align: center;
            margin-top: 20px;
        }
        .admin-link a {
            color: #667eea;
            text-decoration: none;
        }
        @media (max-width: 768px) {
            .registration-form {
                padding: 20px;
                margin: 20px;
            }
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<header class="header" id="home">
    <!-- Фоновое видео -->
    <video class="video-bg" autoplay muted loop playsinline>
        <source src="video.mp4" type="video/mp4">
        Ваш браузер не поддерживает видео.
    </video>
    <div class="header-overlay"></div>
    <div class="header-content">
        <h1>Регистрация участника</h1>
        <p>Заполните форму для участия в программе</p>
        <a href="#contact-form" class="btn">Заполнить форму</a>
    </div>
</header>
<body>
    <!-- Навигация (ваша существующая) -->
    <nav class="navbar">
        <div class="navbar-container container">
            <a href="#" class="logo">Web<span>Studio</span></a>
            <ul class="nav-desktop nav-list">
                <li><a href="#home">Главная</a></li>
                <li><a href="#about">О нас</a></li>
                <li><a href="#contact-form">Регистрация</a></li>
                <li><a href="admin.php">Админ-панель</a></li>
            </ul>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
    <section id="about" class="about-section">
        <div class="container">
            <h2 class="section-title">О <span>проекте</span></h2>
            <div class="about-content">
                <div class="about-text">
                    <h3>Я не хотел этого делать</h3>
                    <p>но пришлось</p>
                </div>
                <div class="about-image">
                    <img src="images/team.jpg" alt="О проекте">
                </div>
            </div>
        </div>
    </section>
    <!-- Секция с формой -->
    <section class="form-section" id="contact-form">
        <div class="container">
            <h2 class="section-title">Регистрация <span>участника</span></h2>
            
            <div class="registration-form" id="registrationFormContainer">
                
                
                <?php if (isset($errors['general'])): ?>
                    <div class="form-message error" style="display:block; background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px;">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form id="mainForm" data-api-url="api.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="register">
                    
                    <!-- ФИО -->
                    <div class="form-group">
                        <label for="fullname">ФИО <span class="required">*</span></label>
                        <input type="text" id="fullname" name="fullname" class="form-control <?php echo isset($errors['fullname']) ? 'error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($oldInput['fullname'] ?? $savedData['fullname'] ?? ''); ?>">
                        <?php if (isset($errors['fullname'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['fullname']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Телефон -->
                    <div class="form-group">
                        <label for="phone">Телефон <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control <?php echo isset($errors['phone']) ? 'error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($oldInput['phone'] ?? $savedData['phone'] ?? ''); ?>">
                        <?php if (isset($errors['phone'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['phone']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">E-mail <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($oldInput['email'] ?? $savedData['email'] ?? ''); ?>">
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Дата рождения -->
                    <div class="form-group">
                        <label for="birthdate">Дата рождения <span class="required">*</span></label>
                        <input type="date" id="birthdate" name="birthdate" class="form-control <?php echo isset($errors['birthdate']) ? 'error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($oldInput['birthdate'] ?? $savedData['birthdate'] ?? ''); ?>">
                        <?php if (isset($errors['birthdate'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['birthdate']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Пол -->
                    <div class="form-group">
                        <label>Пол <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="gender" value="male" <?php echo (($oldInput['gender'] ?? $savedData['gender'] ?? '') == 'male') ? 'checked' : ''; ?>> Мужской</label>
                            <label><input type="radio" name="gender" value="female" <?php echo (($oldInput['gender'] ?? $savedData['gender'] ?? '') == 'female') ? 'checked' : ''; ?>> Женский</label>
                            <label><input type="radio" name="gender" value="other" <?php echo (($oldInput['gender'] ?? $savedData['gender'] ?? '') == 'other') ? 'checked' : ''; ?>> Другой</label>
                        </div>
                        <?php if (isset($errors['gender'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['gender']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Любимый язык программирования -->
                    <div class="form-group">
                        <label for="languages">Любимый язык программирования <span class="required">*</span></label>
                        <select name="languages[]" id="languages" multiple class="form-control <?php echo isset($errors['languages']) ? 'error' : ''; ?>">
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?php echo $lang['id']; ?>" 
                                    <?php 
                                    $selectedLangs = $oldInput['languages'] ?? [];
                                    echo (in_array($lang['id'], $selectedLangs)) ? 'selected' : '';
                                    ?>>
                                    <?php echo htmlspecialchars($lang['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Удерживайте Ctrl (Cmd) для выбора нескольких языков</small>
                        <?php if (isset($errors['languages'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['languages']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Биография -->
                    <div class="form-group">
                        <label for="biography">Биография</label>
                        <textarea id="biography" name="biography" rows="5" class="form-control <?php echo isset($errors['biography']) ? 'error' : ''; ?>"><?php echo htmlspecialchars($oldInput['biography'] ?? $savedData['biography'] ?? ''); ?></textarea>
                        <?php if (isset($errors['biography'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['biography']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Чекбокс контракта -->
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="contract" <?php echo (isset($oldInput['contract']) && $oldInput['contract'] == 'on') ? 'checked' : ''; ?>>
                            Я ознакомлен(а) с <a href="#" target="_blank">контрактом</a> <span class="required">*</span>
                        </label>
                        <?php if (isset($errors['contract'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['contract']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                    
                    <div id="formMessage" class="form-message" style="display:none; margin-top:15px;"></div>
                </form>
                
                <div class="admin-link">
                    <a href="admin.php"><i class="fas fa-lock"></i> Вход в административную панель</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Футер -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">Web<span>Studio</span></div>
                <div class="footer-links">
                    <a href="#home">Главная</a>
                    <a href="#about">О нас</a>
                    <a href="#contact-form">Регистрация</a>
                </div>
                <p>© 2024 WebStudio. Все права защищены.</p>
            </div>
        </div>
    </footer>
    
    <!-- Мобильное меню (ваше существующее) -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="mobile-menu-close" id="mobileMenuClose"><i class="fas fa-times"></i></button>
        <ul class="mobile-nav-list">
            <li><a href="#home">Главная</a></li>
            <li><a href="#about">О нас</a></li>
            <li><a href="#contact-form">Регистрация</a></li>
            <li><a href="admin.php">Админ-панель</a></li>
        </ul>
    </div>
    <div class="overlay" id="overlay"></div>
    
    <script src="js/main.js"></script>
    <script>
        // AJAX отправка формы без перезагрузки (задание 8)
        const form = document.getElementById('mainForm');
        const submitBtn = document.getElementById('submitBtn');
        const formMessage = document.getElementById('formMessage');
        
        if (form) {
            form.addEventListener('submit', async function(e) {
                // Предотвращаем стандартную отправку только если есть поддержка fetch
                if (window.fetch) {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    const apiUrl = form.dataset.apiUrl;
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
                    formMessage.style.display = 'none';
                    
                    try {
                        const response = await fetch(apiUrl, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            formMessage.className = 'form-message success';
                            formMessage.innerHTML = '<i class="fas fa-check-circle"></i> ' + result.message;
                            formMessage.style.display = 'block';
                            form.reset();
                            
                            if (result.credentials) {
                                const credHtml = '<div class="credentials-box" style="margin-top:10px;"><p><strong>Логин:</strong> ' + result.credentials.login + '</p><p><strong>Пароль:</strong> ' + result.credentials.password + '</p></div>';
                                formMessage.innerHTML += credHtml;
                            }
                            
                           
                        } else {
                            formMessage.className = 'form-message error';
                            formMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + result.message;
                            formMessage.style.display = 'block';
                            
                            // Подсветка полей с ошибками
                            if (result.errors) {
                                for (const [field, error] of Object.entries(result.errors)) {
                                    const input = document.querySelector(`[name="${field}"]`);
                                    if (input) {
                                        input.classList.add('error');
                                        let errorSpan = input.parentElement.querySelector('.error-message');
                                        if (!errorSpan) {
                                            errorSpan = document.createElement('span');
                                            errorSpan.className = 'error-message';
                                            input.parentElement.appendChild(errorSpan);
                                        }
                                        errorSpan.textContent = error;
                                    }
                                }
                            }
                        }
                    } catch (error) {
                        formMessage.className = 'form-message error';
                        formMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Ошибка соединения. Форма будет отправлена обычным способом.';
                        formMessage.style.display = 'block';
                        setTimeout(() => {
                            form.submit();
                        }, 1500);
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Сохранить';
                    }
                }
            });
        }
        
        // Мобильное меню
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileMenuClose = document.getElementById('mobileMenuClose');
        const overlay = document.getElementById('overlay');
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }
        
        function closeMobileMenu() {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (mobileMenuClose) mobileMenuClose.addEventListener('click', closeMobileMenu);
        if (overlay) overlay.addEventListener('click', closeMobileMenu);
        
        // Удаляем подсветку ошибок при фокусе
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.classList.remove('error');
                const errorSpan = this.parentElement.querySelector('.error-message');
                if (errorSpan) errorSpan.remove();
            });
        });
    </script>
</body>
</html>
