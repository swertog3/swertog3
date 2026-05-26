<?php
// api.php - REST API для отправки формы
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'includes/config.php';
require_once 'includes/functions.php';
// Обработка POST запросов (удаление и обновление)
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ===== УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ =====
    if ($action === 'delete_user') {
        // Проверяем авторизацию (только админ)
        $user = authenticate();
        if (!$user || !isset($user['is_admin']) || !$user['is_admin']) {
            echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
            exit;
        }
        
        $userId = (int)($_POST['id'] ?? 0);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Неверный ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            echo json_encode(['success' => true, 'message' => 'Пользователь удалён']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ===== ОБНОВЛЕНИЕ ПОЛЬЗОВАТЕЛЯ =====
    if ($action === 'update_user') {
        // Проверяем авторизацию (только админ)
        $user = authenticate();
        if (!$user || !isset($user['is_admin']) || !$user['is_admin']) {
            echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
            exit;
        }
        
        $userId = (int)($_POST['id'] ?? 0);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Неверный ID']);
            exit;
        }
        
        $errors = [];
        
        $fullname = validateFullname($_POST['fullname'] ?? '', $errors);
        $phone = validatePhone($_POST['phone'] ?? '', $errors);
        $email = validateEmail($_POST['email'] ?? '', $errors);
        $birthdate = validateBirthdate($_POST['birthdate'] ?? '', $errors);
        $gender = validateGender($_POST['gender'] ?? '', $errors);
        $biography = validateBiography($_POST['biography'] ?? '', $errors);
        $languages = isset($_POST['languages']) ? (array)$_POST['languages'] : [];
        
        // Валидация языков
        $stmt = $pdo->query("SELECT id FROM programming_languages");
        $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $validLanguages = [];
        foreach ($languages as $langId) {
            if (in_array($langId, $validIds)) {
                $validLanguages[] = (int)$langId;
            }
        }
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => 'Ошибки валидации', 'errors' => $errors]);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    fullname = ?, phone = ?, email = ?, 
                    birthdate = ?, gender = ?, biography = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullname, $phone, $email, $birthdate, $gender, $biography, $userId]);
            
            // Обновляем языки
            $stmt = $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            foreach ($validLanguages as $langId) {
                $stmtLang->execute([$userId, $langId]);
            }
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Данные обновлены']);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Если действие не распознано
    echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
    exit;
}
// Получение метода и пути
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Аутентификация через заголовок Authorization
function authenticate() {
    global $pdo;
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
        $token = $matches[1];
        // Проверка сессии по токену (JWT-like, но простой)
        $stmt = $pdo->prepare("SELECT id, login, is_admin FROM users WHERE session_token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    return null;
}

// Генерация токена сессии
function generateSessionToken($userId) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
    $stmt->execute([$token, $userId]);
    return $token;
}

// Обработка POST /api/register (создание новой заявки)
if ($method === 'POST' && ($path === '/' || $path === '/register')) {
    $errors = [];
    $validData = [];
    
    $validData['fullname'] = validateFullname($input['fullname'] ?? '', $errors);
    $validData['phone'] = validatePhone($input['phone'] ?? '', $errors);
    $validData['email'] = validateEmail($input['email'] ?? '', $errors);
    $validData['birthdate'] = validateBirthdate($input['birthdate'] ?? '', $errors);
    $validData['gender'] = validateGender($input['gender'] ?? '', $errors);
    $validData['languages'] = validateLanguages($input['languages'] ?? [], $errors);
    $validData['biography'] = validateBiography($input['biography'] ?? '', $errors);
    $validContract = validateContract($input['contract'] ?? '', $errors);
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Ошибки валидации', 'errors' => $errors]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $credentials = generateCredentials($validData['fullname'], $validData['email']);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (fullname, phone, email, birthdate, gender, biography, contract_accepted, login, password_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $validData['fullname'], $validData['phone'], $validData['email'],
            $validData['birthdate'], $validData['gender'], $validData['biography'],
            1, $credentials['login'], $credentials['hash']
        ]);
        
        $userId = $pdo->lastInsertId();
        
        $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
        foreach ($validData['languages'] as $langId) {
            $stmtLang->execute([$userId, $langId]);
        }
        
        // Генерация токена для API
        $sessionToken = generateSessionToken($userId);
        
        $pdo->commit();
        
        auditLog($userId, 'api_register', ['email' => $validData['email']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Данные успешно сохранены',
            'credentials' => [
                'login' => $credentials['login'],
                'password' => $credentials['password']
            ],
            'token' => $sessionToken,
            'user_id' => $userId
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
    }
    exit;
}

// Обработка GET /api/user/{id} (получение данных пользователя)
if ($method === 'GET' && preg_match('/\/user\/(\d+)/', $path, $matches)) {
    $user = authenticate();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    $userId = $matches[1];
    if ($user['id'] != $userId && !isset($user['is_admin'])) {
        echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, fullname, phone, email, birthdate, gender, biography, contract_accepted, login, created_at, updated_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT l.id, l.name FROM user_languages ul
        JOIN programming_languages l ON ul.language_id = l.id
        WHERE ul.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userData['languages'] = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $userData]);
    exit;
}

// Обработка PUT /api/user/{id} (обновление данных пользователя)
if ($method === 'PUT' && preg_match('/\/user\/(\d+)/', $path, $matches)) {
    $user = authenticate();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    $userId = $matches[1];
    if ($user['id'] != $userId && !isset($user['is_admin'])) {
        echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
        exit;
    }
    
    $errors = [];
    $updateData = [];
    
    if (isset($input['fullname'])) $updateData['fullname'] = validateFullname($input['fullname'], $errors);
    if (isset($input['phone'])) $updateData['phone'] = validatePhone($input['phone'], $errors);
    if (isset($input['email'])) $updateData['email'] = validateEmail($input['email'], $errors);
    if (isset($input['birthdate'])) $updateData['birthdate'] = validateBirthdate($input['birthdate'], $errors);
    if (isset($input['gender'])) $updateData['gender'] = validateGender($input['gender'], $errors);
    if (isset($input['biography'])) $updateData['biography'] = validateBiography($input['biography'], $errors);
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Ошибки валидации', 'errors' => $errors]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        if (!empty($updateData)) {
            $set = [];
            $params = [];
            foreach ($updateData as $key => $value) {
                $set[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $userId;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?");
            $stmt->execute($params);
        }
        
        // Обновление языков
        if (isset($input['languages'])) {
            $langs = validateLanguages($input['languages'], $errors);
            if (!empty($errors)) {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            foreach ($langs as $langId) {
                $stmtLang->execute([$userId, $langId]);
            }
        }
        
        $pdo->commit();
        auditLog($userId, 'api_update', ['updated_fields' => array_keys($updateData)]);
        
        echo json_encode(['success' => true, 'message' => 'Данные обновлены']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
    }
    exit;
}

// Если ничего не подошло
echo json_encode(['success' => false, 'message' => 'Неверный запрос']);
?>
