<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = getUserByUsername($_POST['username']);
    if ($user && $user['password'] === $_POST['password'] && $user['is_active'] == 1) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        updateLastLogin($user['user_id']);
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход - FlexWellness CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: white; padding: 40px; border-radius: 20px; width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .login-card h2 { margin-bottom: 10px; text-align: center; color: #1e3a5f; }
        .login-card .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .login-card input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; }
        .login-card input:focus { outline: none; border-color: #1e3a5f; }
        .login-card button { width: 100%; padding: 12px; background: #1e3a5f; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 16px; font-weight: 600; }
        .login-card button:hover { background: #2c4e7a; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .demo { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🧘 FlexWellness</h2>
        <div class="subtitle">CRM система управления студией</div>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Логин" required autofocus>
            <input type="password" name="password" placeholder="Пароль" required>
            <button type="submit" name="login">Войти</button>
        </form>
        
        <div class="demo">
            <p>🔐 Тестовые данные:</p>
            <p><strong>Админ:</strong> admin / admin123</p>
            <p><strong>Менеджер:</strong> manager / manager123</p>
        </div>
    </div>
</body>
</html>