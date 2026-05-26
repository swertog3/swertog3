<?php
// api.php - REST API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Обработка preflight запросов (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===== ПОЛУЧАЕМ МЕТОД =====
$method = $_SERVER['REQUEST_METHOD'];

// ===== ПОЛУЧАЕМ ДЕЙСТВИЕ =====
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ===== ОБРАБОТКА POST ЗАПРОСОВ =====
if ($method === 'POST') {
    
    // === УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ===
    if ($action === 'delete_user') {
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
    
    // === ОБНОВЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ===
    if ($action === 'update_user') {
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
        
        // Обрабатываем языки
        $languages = isset($_POST['languages']) ? (array)$_POST['languages'] : [];
        
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
            
            if (!empty($validLanguages)) {
                $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
                foreach ($validLanguages as $langId) {
                    $stmtLang->execute([$userId, $langId]);
                }
            }
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Данные обновлены']);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // === РЕГИСТРАЦИЯ НОВОГО ПОЛЬЗОВАТЕЛЯ ===
    if ($action === 'register') {
        $errors = [];
        
        $fullname = validateFullname($_POST['fullname'] ?? '', $errors);
        $phone = validatePhone($_POST['phone'] ?? '', $errors);
        $email = validateEmail($_POST['email'] ?? '', $errors);
        $birthdate = validateBirthdate($_POST['birthdate'] ?? '', $errors);
        $gender = validateGender($_POST['gender'] ?? '', $errors);
        $biography = validateBiography($_POST['biography'] ?? '', $errors);
        $contract = validateContract($_POST['contract'] ?? '', $errors);
        
        $languages = isset($_POST['languages']) ? (array)$_POST['languages'] : [];
        $validLanguages = validateLanguages($languages, $errors);
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => 'Ошибки валидации', 'errors' => $errors]);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            $credentials = generateCredentials($fullname, $email);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (fullname, phone, email, birthdate, gender, biography, contract_accepted, login, password_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullname, $phone, $email, $birthdate, $gender, $biography, 1, $credentials['login'], $credentials['hash']]);
            
            $userId = $pdo->lastInsertId();
            
            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            foreach ($validLanguages as $langId) {
                $stmtLang->execute([$userId, $langId]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Данные успешно сохранены',
                'credentials' => [
                    'login' => $credentials['login'],
                    'password' => $credentials['password']
                ]
            ]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ===== ЕСЛИ НИ ОДНО ДЕЙСТВИЕ НЕ ПОДОШЛО =====
echo json_encode(['success' => false, 'message' => 'Неверный запрос']);
?>
