<?php
// includes/functions.php

// Валидация ФИО (только буквы, пробелы, дефисы, не длиннее 150)
function validateFullname($fullname, &$errors) {
    $fullname = trim($fullname);
    if (empty($fullname)) {
        $errors['fullname'] = 'ФИО обязательно для заполнения';
        return false;
    }
    if (strlen($fullname) > 150) {
        $errors['fullname'] = 'ФИО не должно превышать 150 символов';
        return false;
    }
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $fullname)) {
        $errors['fullname'] = 'ФИО может содержать только буквы, пробелы и дефисы';
        return false;
    }
    return htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
}

// Валидация телефона
function validatePhone($phone, &$errors) {
    $phone = trim($phone);
    if (empty($phone)) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
        return false;
    }
    if (!preg_match('/^(\+7|7|8)?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $phone)) {
        $errors['phone'] = 'Введите корректный номер телефона';
        return false;
    }
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (strlen($phone) === 10) $phone = '7' . $phone;
    if (strlen($phone) === 11 && $phone[0] === '8') $phone = '7' . substr($phone, 1);
    return $phone;
}

// Валидация email
function validateEmail($email, &$errors) {
    $email = trim($email);
    if (empty($email)) {
        $errors['email'] = 'Email обязателен для заполнения';
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес';
        return false;
    }
    if (strlen($email) > 100) {
        $errors['email'] = 'Email не должен превышать 100 символов';
        return false;
    }
    return htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
}

// Валидация даты рождения
function validateBirthdate($birthdate, &$errors) {
    if (empty($birthdate)) {
        $errors['birthdate'] = 'Дата рождения обязательна для заполнения';
        return false;
    }
    $date = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$date || $date->format('Y-m-d') !== $birthdate) {
        $errors['birthdate'] = 'Неверный формат даты';
        return false;
    }
    $age = $date->diff(new DateTime())->y;
    if ($age < 18) {
        $errors['birthdate'] = 'Вам должно быть не менее 18 лет';
        return false;
    }
    if ($age > 120) {
        $errors['birthdate'] = 'Проверьте корректность даты рождения';
        return false;
    }
    return $birthdate;
}

// Валидация пола
function validateGender($gender, &$errors) {
    $allowed = ['male', 'female', 'other'];
    if (empty($gender) || !in_array($gender, $allowed)) {
        $errors['gender'] = 'Выберите корректный пол';
        return false;
    }
    return $gender;
}

// Валидация языков программирования
function validateLanguages($languages, &$errors) {
    global $pdo;
    if (empty($languages) || !is_array($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
        return false;
    }
    
    $stmt = $pdo->query("SELECT id FROM programming_languages");
    $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $validLanguages = [];
    foreach ($languages as $langId) {
        if (in_array($langId, $validIds)) {
            $validLanguages[] = (int)$langId;
        }
    }
    
    if (empty($validLanguages)) {
        $errors['languages'] = 'Выберите корректные языки программирования';
        return false;
    }
    
    return $validLanguages;
}

// Валидация биографии
function validateBiography($bio, &$errors) {
    $bio = trim($bio);
    if (strlen($bio) > 5000) {
        $errors['biography'] = 'Биография не должна превышать 5000 символов';
        return false;
    }
    return htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');
}

// Валидация чекбокса контракта
function validateContract($contract, &$errors) {
    if ($contract != 'on' && $contract != '1') {
        $errors['contract'] = 'Вы должны ознакомиться с контрактом';
        return false;
    }
    return true;
}

// Генерация логина и пароля
function generateCredentials($fullname, $email) {
    $emailParts = explode('@', $email);
    $login = $emailParts[0];
    $login = preg_replace('/[^a-zA-Z0-9._]/', '', $login);
    $login = substr($login, 0, 40);
    $login .= rand(100, 999);
    
    if (empty($login)) {
        $login = 'user' . rand(10000, 99999);
    }
    
    $password = generateRandomPassword(10);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    return ['login' => $login, 'password' => $password, 'hash' => $passwordHash];
}

function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function transliterate($text) {
    $cyr = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
            'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'];
    $lat = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','kh','ts','ch','sh','sch','','y','','e','yu','ya',
            'A','B','V','G','D','E','Yo','Zh','Z','I','Y','K','L','M','N','O','P','R','S','T','U','F','Kh','Ts','Ch','Sh','Sch','','Y','','E','Yu','Ya'];
    
    return str_replace($cyr, $lat, $text);
}

// Сохранение данных в cookies
function saveToCookies($data) {
    $cookieData = [
        'fullname' => $data['fullname'] ?? '',
        'phone' => $data['phone'] ?? '',
        'email' => $data['email'] ?? '',
        'birthdate' => $data['birthdate'] ?? '',
        'gender' => $data['gender'] ?? '',
        'biography' => $data['biography'] ?? ''
    ];
    setcookie('saved_form_data', json_encode($cookieData), time() + 365*24*3600, '/', '', false, true);
}

function loadFromCookies() {
    if (isset($_COOKIE['saved_form_data'])) {
        return json_decode($_COOKIE['saved_form_data'], true);
    }
    return [];
}

function saveErrorsToCookies($errors, $oldInput) {
    setcookie('form_errors', json_encode($errors), 0, '/', '', false, true);
    setcookie('old_input', json_encode($oldInput), 0, '/', '', false, true);
}

function loadErrorsFromCookies() {
    $errors = [];
    $oldInput = [];
    if (isset($_COOKIE['form_errors'])) {
        $errors = json_decode($_COOKIE['form_errors'], true);
        setcookie('form_errors', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['old_input'])) {
        $oldInput = json_decode($_COOKIE['old_input'], true);
        setcookie('old_input', '', time() - 3600, '/');
    }
    return ['errors' => $errors, 'oldInput' => $oldInput];
}

function auditLog($userId, $action, $details = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $action,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $details ? json_encode($details) : null
    ]);
}

function checkAdminAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header('Location: index.php');
        exit;
    }
}

// ===== ДОБАВЬТЕ ЭТУ ФУНКЦИЮ =====
// Аутентификация для API
function authenticate() {
    global $pdo;
    
    // Получаем заголовок Authorization
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    
    // Проверяем Bearer токен
    if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
        $token = $matches[1];
        $stmt = $pdo->prepare("SELECT id, login, is_admin FROM users WHERE session_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }
    }
    
    // Если нет токена, проверяем сессию (для админки)
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, login, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    return null;
}
// ===== КОНЕЦ ДОБАВЛЕННОЙ ФУНКЦИИ =====

// НЕТ ЗАКРЫВАЮЩЕГО ТЕГА ?>
