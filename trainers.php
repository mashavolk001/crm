<?php
require_once 'config.php';
requireAuth();

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// Добавление тренера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trainer'])) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, is_active) VALUES (?, ?, ?, ?, ?, 'instructor', 1)");
    $stmt->execute([$_POST['username'], $_POST['password'], $_POST['full_name'], $_POST['email'], $_POST['phone']]);
    header('Location: trainers.php?msg=Тренер добавлен');
    exit;
}

// Обновление тренера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_trainer'])) {
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, is_active = ? WHERE user_id = ?");
    $stmt->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['is_active'], $_POST['trainer_id']]);
    header('Location: trainers.php?msg=Данные тренера обновлены');
    exit;
}

// Удаление тренера
if (isset($_GET['delete_trainer'])) {
    $id = $_GET['delete_trainer'];
    $pdo->prepare("DELETE FROM bookings WHERE trainer_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM trainer_shifts WHERE trainer_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM trainer_salary_rates WHERE trainer_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'instructor'")->execute([$id]);
    header('Location: trainers.php?msg=Тренер удалён');
    exit;
}

// Обновление ставки зарплаты
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
    $stmt = $pdo->prepare("INSERT INTO trainer_salary_rates (trainer_id, rate_type, rate_value, updated_by) VALUES (?, 'hourly', ?, ?) ON DUPLICATE KEY UPDATE rate_value = VALUES(rate_value), updated_by = VALUES(updated_by)");
    $stmt->execute([$_POST['trainer_id'], $_POST['rate_value'], $_SESSION['user_id']]);
    header('Location: trainers.php?msg=Ставка обновлена');
    exit;
}

$trainers = $pdo->query("
    SELECT u.user_id, u.username, u.full_name, u.email, u.phone, u.is_active,
           COALESCE(tsr.rate_value, 500) as rate_value
    FROM users u 
    LEFT JOIN trainer_salary_rates tsr ON u.user_id = tsr.trainer_id
    WHERE u.role = 'instructor'
    ORDER BY u.full_name
")->fetchAll();

// Расчёт зарплаты за текущий месяц
$year = date('Y');
$month = date('m');
$salaryData = [];

foreach ($trainers as $t) {
    // Часы работы
    $hours = $pdo->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, booking_time, DATE_ADD(booking_time, INTERVAL 1 HOUR))), 0) as hours 
                            FROM bookings WHERE trainer_id = ? AND status = 'completed' AND YEAR(booking_date) = ? AND MONTH(booking_date) = ?");
    $hours->execute([$t['user_id'], $year, $month]);
    $hoursWorked = $hours->fetchColumn();
    
    // Выручка с тренера
    $revenue = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) 
                              FROM bookings WHERE trainer_id = ? AND status = 'completed' AND YEAR(booking_date) = ? AND MONTH(booking_date) = ?");
    $revenue->execute([$t['user_id'], $year, $month]);
    $revenueAmount = $revenue->fetchColumn();
    
    // Количество занятий
    $sessions = $pdo->prepare("SELECT COUNT(*) 
                               FROM bookings WHERE trainer_id = ? AND status = 'completed' AND YEAR(booking_date) = ? AND MONTH(booking_date) = ?");
    $sessions->execute([$t['user_id'], $year, $month]);
    $sessionsCount = $sessions->fetchColumn();
    
    $salaryData[$t['user_id']] = [
        'hours' => $hoursWorked,
        'sessions' => $sessionsCount,
        'revenue' => $revenueAmount,
        'salary' => $hoursWorked * $t['rate_value']
    ];
}

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Тренеры - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header">
        <h1>🧘 FlexWellness</h1>
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
        <a href="trainers.php" class="active">🧘 Тренеры</a>
        <a href="admins.php">👑 Сотрудники</a>
        <a href="inactive.php">⚠️ Неактивные</a>
        <a href="notifications.php">📨 Рассылка</a>
    </nav>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert">✅ <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Добавление тренера -->
        <div class="card">
            <h3>🧘 Добавить тренера</h3>
            <form method="POST" class="form-row">
                <input type="text" name="username" placeholder="Логин" required>
                <input type="text" name="password" placeholder="Пароль" required>
                <input type="text" name="full_name" placeholder="ФИО" required>
                <input type="email" name="email" placeholder="Email">
                <input type="text" name="phone" placeholder="Телефон">
                <button type="submit" name="add_trainer" class="btn btn-primary">➕ Добавить</button>
            </form>
        </div>

        <!-- Список тренеров и расчёт зарплаты -->
        <div class="card">
            <h3>📋 Список тренеров и расчёт зарплаты за <?= date('F Y') ?></h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>ФИО</th>
                            <th>Занятий</th>
                            <th>Часов</th>
                            <th>Выручка</th>
                            <th>Ставка (₽/час)</th>
                            <th>Зарплата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainers as $t): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td><strong><?= htmlspecialchars($t['full_name']) ?></strong></td>
                                <td><?= $salaryData[$t['user_id']]['sessions'] ?></td>
                                <td><?= $salaryData[$t['user_id']]['hours'] ?></td>
                                <td><?= number_format($salaryData[$t['user_id']]['revenue'], 2) ?> ₽</td>
                                <td>
                                    <form method="POST" style="display: inline-flex; gap: 5px;">
                                        <input type="hidden" name="trainer_id" value="<?= $t['user_id'] ?>">
                                        <input type="number" step="10" name="rate_value" value="<?= $t['rate_value'] ?>" style="width: 80px;">
                                        <button type="submit" name="update_rate" class="btn btn-primary btn-sm">💾</button>
                                    </form>
                                </td>
                                <td><strong style="color: #2c7a4d;"><?= number_format($salaryData[$t['user_id']]['salary'], 2) ?> ₽</strong></td>
                                <td>
                                    <a href="trainers.php?delete_trainer=<?= $t['user_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить тренера?')">🗑️</a>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" style="background: #f8fafc; padding: 10px;">
                                    <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <input type="hidden" name="trainer_id" value="<?= $t['user_id'] ?>">
                                        <input type="text" name="full_name" value="<?= htmlspecialchars($t['full_name']) ?>" placeholder="ФИО" style="flex: 2;">
                                        <input type="email" name="email" value="<?= htmlspecialchars($t['email']) ?>" placeholder="Email">
                                        <input type="text" name="phone" value="<?= htmlspecialchars($t['phone']) ?>" placeholder="Телефон">
                                        <select name="is_active">
                                            <option value="1" <?= $t['is_active'] ? 'selected' : '' ?>>Активен</option>
                                            <option value="0" <?= !$t['is_active'] ? 'selected' : '' ?>>Неактивен</option>
                                        </select>
                                        <button type="submit" name="update_trainer" class="btn btn-secondary btn-sm">✏️ Редактировать</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="shift-info" style="margin-top: 15px; padding: 10px; background: #f1f5f9; border-radius: 8px;">
                📌 <strong>Расчёт зарплаты:</strong> Зарплата = Количество отработанных часов × Ставка (₽/час).<br>
                📌 Администратор может изменить ставку для каждого тренера.
            </div>
        </div>
    </div>
</body>
</html>