<?php
// logout.php - выход из системы
session_start();

// Логируем выход, если пользователь был авторизован
if (isset($_SESSION['user_id'])) {
    // Опционально: запись в лог (если есть соединение с БД)
    // auditLog($_SESSION['user_id'], 'logout');
    
    // Очищаем сессию
    $_SESSION = array();
    
    // Удаляем cookie сессии
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// Уничтожаем сессию
session_destroy();

// Перенаправляем на главную
header('Location: index.php');
exit;
?>