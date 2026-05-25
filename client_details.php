<?php
require_once 'config.php';
requireAuth();

$client_id = $_GET['id'] ?? 0;
$client = getClientById($client_id);
if (!$client) {
    header('Location: clients.php?error=Клиент не найден');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contract'])) {
    createContract($_POST);
    header("Location: client_details.php?id=$client_id&msg=Договор создан");
    exit;
}

$contracts = getClientContracts($client_id);
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Карточка клиента - FlexWellness CRM</title>
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
        <a href="clients.php" class="active">👥 Клиенты</a>
        <a href="schedule.php">📅 Расписание</a>
        <a href="sales.php">💳 Продажи</a>
        <?php if (isAdmin()): ?>
            <a href="trainers.php">🧘 Тренеры</a>
            <a href="admins.php">👑 Сотрудники</a>
        <?php endif; ?>
        <a href="inactive.php">⚠️ Неактивные</a>
        <a href="notifications.php">📨 Рассылка</a>
    </nav>
    
    <div class="container">
        <?php if ($msg): ?>
            <div class="alert">✅ <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3>📄 Информация о клиенте</h3>
            <p><strong><?= htmlspecialchars($client['last_name'] . ' ' . $client['first_name'] . ' ' . $client['middle_name']) ?></strong></p>
            <p>📞 <?= htmlspecialchars($client['phone']) ?> | 📧 <?= htmlspecialchars($client['email']) ?></p>
            <p>🎂 Дата рождения: <?= $client['birth_date'] ?: 'не указана' ?></p>
            <p>🏠 Адрес: <?= $client['address'] ?: 'не указан' ?></p>
            <p>📅 Зарегистрирован: <?= date('d.m.Y', strtotime($client['registration_date'])) ?></p>
            <div style="margin-top: 15px;">
                <a href="schedule.php?client_id=<?= $client['client_id'] ?>" class="btn btn-primary">📅 Записать на занятие</a>
                <a href="sales.php?client_id=<?= $client['client_id'] ?>" class="btn btn-success">💳 Продать абонемент</a>
            </div>
        </div>
        
        <div class="card">
            <h3>➕ Создать договор</h3>
            <form method="POST">
                <input type="hidden" name="client_id" value="<?= $client['client_id'] ?>">
                <div class="form-row">
                    <select name="contract_type" required>
                        <option value="subscription">Абонемент</option>
                        <option value="pack">Пакет занятий</option>
                        <option value="single">Разовое занятие</option>
                    </select>
                    <input type="number" name="sessions_count" placeholder="Кол-во занятий" required>
                    <input type="number" step="0.01" name="price" placeholder="Стоимость" required>
                    <input type="number" name="validity_days" placeholder="Срок действия (дней)" value="30" required>
                    <select name="payment_type">
                        <option value="card">💳 Безналичные</option>
                        <option value="cash">💰 Наличные</option>
                    </select>
                </div>
                <button type="submit" name="add_contract" class="btn btn-primary">Создать договор</button>
            </form>
        </div>
        
        <div class="card">
            <h3>📜 Договоры клиента</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>№ договора</th>
                            <th>Тип</th>
                            <th>Занятий</th>
                            <th>Использовано</th>
                            <th>Осталось</th>
                            <th>Цена</th>
                            <th>Действует до</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $c): ?>
                            <?php $remaining = $c['sessions_count'] - $c['sessions_used']; ?>
                            <tr>
                                <td><?= htmlspecialchars($c['contract_number']) ?></td>
                                <td><?= $c['contract_type'] == 'subscription' ? 'Абонемент' : ($c['contract_type'] == 'pack' ? 'Пакет' : 'Разовое') ?></td>
                                <td><?= $c['sessions_count'] ?></td>
                                <td><?= $c['sessions_used'] ?></td>
                                <td><strong style="color: <?= $remaining > 0 ? '#2c7a4d' : '#c2410c' ?>;"><?= $remaining ?></strong></td>
                                <td><?= number_format($c['price'], 2) ?> ₽</td>
                                <td><?= date('d.m.Y', strtotime($c['end_date'])) ?></td>
                                <td>
                                    <?php if ($c['status'] == 'active'): ?>
                                        <span style="color: #2c7a4d;">Активен</span>
                                    <?php else: ?>
                                        <span style="color: #c2410c;"><?= $c['status'] ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>