<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка авторизации администратора
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $login = $_POST['login'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT id, login, password_hash, is_admin FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = ($user['is_admin'] == 1);
            $isAdmin = $_SESSION['is_admin'];
            $isLoggedIn = true;
            auditLog($user['id'], 'admin_login');
        } else {
            $loginError = 'Неверный логин или пароль';
        }
    } elseif ($_POST['action'] === 'logout' && $isLoggedIn) {
        auditLog($_SESSION['user_id'], 'admin_logout');
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}

// Обработка удаления пользователя


// Обработка обновления пользователя

// Получение всех пользователей для админа
$users = [];
$languageStats = [];

if ($isAdmin) {
    $stmt = $pdo->query("
        SELECT u.*, GROUP_CONCAT(l.name SEPARATOR ', ') as languages_list
        FROM users u
        LEFT JOIN user_languages ul ON u.id = ul.user_id
        LEFT JOIN programming_languages l ON ul.language_id = l.id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
    
    // Статистика по языкам
    $stmt = $pdo->query("
        SELECT l.name, COUNT(ul.user_id) as count
        FROM programming_languages l
        LEFT JOIN user_languages ul ON l.id = ul.language_id
        GROUP BY l.id
        ORDER BY count DESC
    ");
    $languageStats = $stmt->fetchAll();
} elseif ($isLoggedIn) {
    // Обычный пользователь видит только свои данные
    $stmt = $pdo->prepare("
        SELECT u.*, GROUP_CONCAT(l.name SEPARATOR ', ') as languages_list
        FROM users u
        LEFT JOIN user_languages ul ON u.id = ul.user_id
        LEFT JOIN programming_languages l ON ul.language_id = l.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $users = [$stmt->fetch()];
}

$languagesList = $pdo->query("SELECT id, name FROM programming_languages")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | WebStudio</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Open Sans', sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 80px 20px 40px;
        }
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .admin-header .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-title {
            font-size: 24px;
        }
        .admin-title a {
            color: white;
            text-decoration: none;
        }
        .admin-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .admin-nav a, .logout-btn {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .admin-nav a:hover, .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stats-card h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        .stats-card .total {
            font-size: 36px;
            font-weight: bold;
            color: #764ba2;
        }
        .lang-stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .lang-stat .count {
            font-weight: bold;
            color: #667eea;
        }
        .users-table {
            background: white;
            border-radius: 15px;
            overflow-x: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #667eea;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .edit-btn, .delete-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin: 0 3px;
        }
        .edit-btn {
            background: #667eea;
            color: white;
        }
        .delete-btn {
            background: #e74c3c;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-content h3 {
            margin-bottom: 20px;
        }
        .modal-content .form-group {
            margin-bottom: 15px;
        }
        .modal-content label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .modal-content input, .modal-content select, .modal-content textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .modal-content select[multiple] {
            height: 100px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .save-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .cancel-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .login-form {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .login-form h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-form input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .login-form button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        .error-message {
            color: #e74c3c;
            margin-bottom: 15px;
            text-align: center;
        }
        .success-message {
            color: #28a745;
            margin-bottom: 15px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .admin-container {
                padding: 100px 15px 30px;
            }
            th, td {
                padding: 8px 10px;
                font-size: 12px;
            }
            .stats-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<script>
// Функция удаления
async function deleteUser(userId) {
    if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) {
        return;
    }
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete_user&id=' + userId
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Пользователь успешно удалён');
            location.reload();
        } else {
            alert('Ошибка: ' + result.message);
        }
    } catch (error) {
        alert('Ошибка соединения: ' + error.message);
    }
}

// Функция редактирования
async function editUser(userId) {
    const fullname = document.getElementById('edit_fullname').value;
    const phone = document.getElementById('edit_phone').value;
    const email = document.getElementById('edit_email').value;
    const birthdate = document.getElementById('edit_birthdate').value;
    const gender = document.getElementById('edit_gender').value;
    const biography = document.getElementById('edit_biography').value;
    
    const languagesSelect = document.getElementById('edit_languages');
    const languages = [];
    for (let option of languagesSelect.options) {
        if (option.selected) {
            languages.push(option.value);
        }
    }
    
    const formData = new URLSearchParams();
    formData.append('action', 'update_user');
    formData.append('id', userId);
    formData.append('fullname', fullname);
    formData.append('phone', phone);
    formData.append('email', email);
    formData.append('birthdate', birthdate);
    formData.append('gender', gender);
    formData.append('biography', biography);
    
    for (let lang of languages) {
        formData.append('languages[]', lang);
    }
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Данные обновлены');
            location.reload();
        } else {
            alert('Ошибка: ' + result.message);
        }
    } catch (error) {
        alert('Ошибка: ' + error.message);
    }
}

// Функция открытия модального окна
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_fullname').value = user.fullname;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_birthdate').value = user.birthdate;
    document.getElementById('edit_gender').value = user.gender;
    document.getElementById('edit_biography').value = user.biography || '';
    
    const langsSelect = document.getElementById('edit_languages');
    const userLangs = user.languages_list ? user.languages_list.split(', ') : [];
    for (let option of langsSelect.options) {
        option.selected = userLangs.includes(option.text);
    }
    
    document.getElementById('editModal').classList.add('active');
}

// Функция закрытия модального окна
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Закрытие по клику вне окна
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="admin-title">
                <a href="index.php">WebStudio</a> / Админ-панель
            </div>
            <div class="admin-nav">
                <?php if ($isLoggedIn): ?>
                    <span>Вы вошли как <?php echo htmlspecialchars($_SESSION['login'] ?? ''); ?></span>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Выйти</button>
                    </form>
                <?php endif; ?>
                <a href="index.php"><i class="fas fa-home"></i> На сайт</a>
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <?php if (!$isLoggedIn): ?>
            <div class="login-form">
                <h2><i class="fas fa-lock"></i> Вход в админ-панель</h2>
                <?php if (isset($loginError)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="login" placeholder="Логин" required>
                    <input type="password" name="password" placeholder="Пароль" required>
                    <button type="submit"><i class="fas fa-sign-in-alt"></i> Войти</button>
                </form>
                <p style="text-align:center; margin-top:15px; font-size:12px; color:#666;">
                    Используйте логин и пароль, полученные при регистрации
                </p>
            </div>
        <?php else: ?>
            <?php if ($isAdmin): ?>
                <div class="stats-section">
                    <div class="stats-card">
                        <h3><i class="fas fa-users"></i> Всего пользователей</h3>
                        <div class="total"><?php echo count($users); ?></div>
                    </div>
                    <div class="stats-card">
                        <h3><i class="fas fa-chart-bar"></i> Популярность языков</h3>
                        <?php foreach ($languageStats as $stat): ?>
                            <div class="lang-stat">
                                <span><?php echo htmlspecialchars($stat['name']); ?></span>
                                <span class="count"><?php echo $stat['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($editSuccess)): ?>
                <div class="success-message"><?php echo htmlspecialchars($editSuccess); ?></div>
            <?php endif; ?>
            <?php if (isset($editError)): ?>
                <div class="error-message"><?php echo htmlspecialchars($editError); ?></div>
            <?php endif; ?>
            
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата рождения</th>
                            <th>Пол</th>
                            <th>Языки</th>
                            <th>Дата регистрации</th>
                            <?php if ($isAdmin): ?>
                                <th>Действия</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['birthdate']); ?></td>
                                <td><?php echo $user['gender'] == 'male' ? 'Мужской' : ($user['gender'] == 'female' ? 'Женский' : 'Другой'); ?></td>
                                <td><?php echo htmlspecialchars($user['languages_list'] ?? ''); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td>
                                        <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i> Ред.
                                        </button>
                                       <button class="delete-btn" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i> Уд.
                                       </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно редактирования -->
    <div id="editModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> Редактирование пользователя</h3>
        
        <div id="editForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label>ФИО</label>
                <input type="text" name="fullname" id="edit_fullname" required>
            </div>
            
            <div class="form-group">
                <label>Телефон</label>
                <input type="tel" name="phone" id="edit_phone" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email" required>
            </div>
            
            <div class="form-group">
                <label>Дата рождения</label>
                <input type="date" name="birthdate" id="edit_birthdate" required>
            </div>
            
            <div class="form-group">
                <label>Пол</label>
                <select name="gender" id="edit_gender">
                    <option value="male">Мужской</option>
                    <option value="female">Женский</option>
                    <option value="other">Другой</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Языки программирования</label>
                <select name="languages[]" id="edit_languages" multiple>
                    <?php foreach ($languagesList as $lang): ?>
                        <option value="<?php echo $lang['id']; ?>"><?php echo htmlspecialchars($lang['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Биография</label>
                <textarea name="biography" id="edit_biography" rows="3"></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="save-btn" onclick="editUser(document.getElementById('edit_user_id').value)">
                    <i class="fas fa-save"></i> Сохранить
                </button>
                <button type="button" class="cancel-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Отмена
                </button>
            </div>
        </div>
    </div>
</div>
    
    <script>
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_fullname').value = user.fullname;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_birthdate').value = user.birthdate;
            document.getElementById('edit_gender').value = user.gender;
            document.getElementById('edit_biography').value = user.biography || '';
            
            // Выбор языков
            const langsSelect = document.getElementById('edit_languages');
            const userLangs = user.languages_list ? user.languages_list.split(', ') : [];
            for (let option of langsSelect.options) {
                option.selected = userLangs.includes(option.text);
            }
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Закрытие по клику вне окна
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
