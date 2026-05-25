<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    sendNotification($_POST['client_id'], $_POST['message'], 'manual');
    header('Location: notifications.php?msg=Напоминание отправлено');
    exit;
}

if (isset($_GET['auto_reminder'])) {
    $count = sendAutoReminders();
    header("Location: notifications.php?msg=Отправлено $count напоминаний");
    exit;
}

$notifications = $pdo->query("
    SELECT n.*, c.first_name, c.last_name, c.phone 
    FROM notifications n 
    LEFT JOIN clients c ON n.client_id = c.client_id 
    ORDER BY n.sent_at DESC LIMIT 50
")->fetchAll();

$msg = $_GET['msg'] ?? '';
$selected_client = $_GET['client_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Рассылка - FlexWellness CRM</title>
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
        <a href="inactive.php">⚠️ Неактивные</a>
        <a href="notifications.php" class="active">📨 Рассылка</a>
    </nav>
    
    <div class="container">
        <?php if ($msg): ?>
            <div class="alert">✅ <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <h3>📨 Ручная рассылка</h3>
                <form method="POST">
                    <label>Клиент</label>
                    <select name="client_id" required style="width:100%; padding:10px; margin-bottom:15px;">
                        <option value="">Выберите клиента</option>
                        <?php foreach (getAllClients() as $c): ?>
                            <option value="<?= $c['client_id'] ?>" <?= $selected_client == $c['client_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name'] . ' — ' . $c['phone']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Текст сообщения</label>
                    <textarea name="message" rows="4" placeholder="Текст напоминания..." style="width:100%; padding:10px; margin-bottom:15px;"></textarea>
                    
                    <button type="submit" name="send_reminder" class="btn btn-primary">📨 Отправить</button>
                </form>
            </div>
            
            <div class="card">
                <h3>🤖 Автоматическая рассылка</h3>
                <p>Отправляет напоминания всем клиентам, у которых завтра занятие</p>
                <a href="notifications.php?auto_reminder=1" class="btn btn-warning">📢 Запустить</a>
            </div>
        </div>
        
        <div class="card">
            <h3>📋 История уведомлений</h3>
            <?php if (count($notifications) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width:100%">
                        <thead>
                            <tr>
                                <th>Клиент</th>
                                <th>Тип</th>
                                <th>Сообщение</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $n): ?>
                                <tr>
                                    <td><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></td>
                                    <td><?= $n['type'] == 'auto' ? '🤖 Авто' : '📨 Ручная' ?></td>
                                    <td style="max-width:300px;"><?= htmlspecialchars(mb_substr($n['message'], 0, 80)) ?>...</td>
                                    <td><?= date('d.m.Y H:i', strtotime($n['sent_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>История уведомлений пуста</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>