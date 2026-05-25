<?php
require_once 'config.php';
requireAuth();

$inactive = getInactiveClients(30);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Неактивные клиенты - FlexWellness CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header">
        <h1>🧘 FlexWellness CRM</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['full_name']) ?> (<?= $_SESSION['role'] == 'admin' ? 'Админ' : 'Менеджер' ?>)</span>
            <a href="logout.php">🚪 Выход</a>
        </div>
    </header>
    
    <nav class="main-nav">
        <a href="dashboard.php">📊 Главная</a>
        <a href="analytics.php">💰 Финансы</a>
        <a href="clients.php">👥 Клиенты</a>
        <a href="schedule.php">📅 Расписание</a>
        <a href="sales.php">💳 Продажи</a>
        <?php if (isAdmin()): ?>
            <a href="trainers.php">🧘 Тренеры</a>
            <a href="admins.php">👑 Сотрудники</a>
        <?php endif; ?>
        <a href="inactive.php" class="active">⚠️ Неактивные</a>
        <a href="notifications.php">📨 Рассылка</a>
    </nav>
    
    <div class="container">
        <div class="card">
            <h3>⚠️ Клиенты без посещений более 30 дней</h3>
            <?php if (count($inactive) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width:100%">
                        <thead>
                            <tr>
                                <th>Клиент</th>
                                <th>Телефон</th>
                                <th>Последнее посещение</th>
                                <th>Дней</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inactive as $c): 
                                $days = $c['last_visit'] ? round((time() - strtotime($c['last_visit']))/86400) : '—';
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name']) ?></td>
                                    <td><?= htmlspecialchars($c['phone']) ?></td>
                                    <td><?= $c['last_visit'] ? date('d.m.Y', strtotime($c['last_visit'])) : 'никогда' ?></td>
                                    <td><strong style="color: #e67e22;"><?= $days ?></strong></td>
                                    <td>
                                        <a href="schedule.php?client_id=<?= $c['client_id'] ?>" class="btn btn-primary btn-sm">📅 Записать</a>
                                        <a href="sales.php?client_id=<?= $c['client_id'] ?>" class="btn btn-success btn-sm">💳 Продать</a>
                                        <a href="notifications.php?client_id=<?= $c['client_id'] ?>" class="btn btn-warning btn-sm">📨 Напомнить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>✅ Все клиенты активны! Нет должников по посещениям более 30 дней.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>