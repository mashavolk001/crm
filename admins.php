<?php
require_once 'config.php';
requireAuth();

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    createUser($_POST);
    header('Location: admins.php?msg=Сотрудник добавлен');
    exit;
}

if (isset($_GET['delete_admin'])) {
    deleteUser($_GET['delete_admin']);
    header('Location: admins.php?msg=Сотрудник удален');
    exit;
}

$users = getAllUsers();
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сотрудники - FlexWellness CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header">
        <h1>🧘 FlexWellness CRM</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['full_name']) ?> (Админ)</span>
            <a href="logout.php">🚪 Выход</a>
        </div>
    </header>
    
    <nav class="main-nav">
        <a href="dashboard.php">📊 Главная</a>
        <a href="analytics.php">💰 Финансы</a>
        <a href="clients.php">👥 Клиенты</a>
        <a href="schedule.php">📅 Расписание</a>
        <a href="sales.php">💳 Продажи</a>
        <a href="trainers.php">🧘 Тренеры</a>
        <a href="admins.php" class="active">👑 Сотрудники</a>
        <a href="inactive.php">⚠️ Неактивные</a>
        <a href="notifications.php">📨 Рассылка</a>
    </nav>
    
    <div class="container">
        <?php if ($msg): ?>
            <div class="alert">✅ <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3>👑 Добавить сотрудника</h3>
            <form method="POST" class="form-row">
                <input type="text" name="username" placeholder="Логин" required>
                <input type="text" name="password" placeholder="Пароль" required>
                <input type="text" name="full_name" placeholder="ФИО" required>
                <input type="email" name="email" placeholder="Email">
                <input type="text" name="phone" placeholder="Телефон">
                <select name="role">
                    <option value="admin">Администратор</option>
                    <option value="manager">Менеджер</option>
                </select>
                <button type="submit" name="create_admin" class="btn btn-primary">➕ Добавить</button>
            </form>
        </div>
        
        <div class="card">
            <h3>📋 Список сотрудников</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>ФИО</th>
                            <th>Роль</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): if ($u['role'] != 'instructor'): ?>
                            <tr>
                                <td><?= $u['user_id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= $u['role'] == 'admin' ? '👑 Администратор' : '📋 Менеджер' ?></td>
                                <td>
                                    <?php if ($u['username'] != 'admin'): ?>
                                        <a href="admins.php?delete_admin=<?= $u['user_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить сотрудника?')">🗑️</a>
                                    <?php else: ?>
                                        <span style="color:#999;">Главный</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>