<?php
// includes/config.php - конфигурация базы данных

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// НАСТРОЙКИ БД - ВАШИ РЕАЛЬНЫЕ ДАННЫЕ
define('DB_HOST', 'localhost');
define('DB_NAME', 'u82187');      // ← ваша БД
define('DB_USER', 'u82187');       // ← ваш логин
define('DB_PASS', '7220016');      // ← ваш пароль

// CSRF защита
define('CSRF_TOKEN_NAME', 'csrf_token');

// Подключение к БД (как у вас в index.php, но с улучшениями)
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false  // Защита от SQL инъекций
        ]
    );
} catch(PDOException $e) {
    // В продакшене не показываем детали ошибки
    error_log("Database error: " . $e->getMessage());
    die("Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.");
}

// Генерация CSRF токена
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Проверка CSRF токена
function verifyCsrfToken($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Настройки безопасности сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
?>