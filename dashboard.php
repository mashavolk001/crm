<?php
require_once 'config.php';
requireAuth();

$today = date('Y-m-d');

// Выручка за сегодня - включаем продажи абонементов
$cash = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE booking_date = ? AND payment_type = 'cash' AND status = 'completed'");
$cash->execute([$today]); $cashAm = $cash->fetchColumn();

$card = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE booking_date = ? AND payment_type = 'card' AND status = 'completed'");
$card->execute([$today]); $cardAm = $card->fetchColumn();

// Продажи абонементов за сегодня - добавляем к выручке
$salesCash = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM sales WHERE sale_date = ? AND payment_type = 'cash'");
$salesCash->execute([$today]); $salesCashAm = $salesCash->fetchColumn();

$salesCard = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM sales WHERE sale_date = ? AND payment_type = 'card'");
$salesCard->execute([$today]); $salesCardAm = $salesCard->fetchColumn();

// Итоговые суммы с учётом продаж
$totalCash = $cashAm + $salesCashAm;
$totalCard = $cardAm + $salesCardAm;

$total = $totalCash + $totalCard;

// Разовые и абонементы из занятий
$single = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE booking_date = ? AND is_subscription = 0 AND status = 'completed'");
$single->execute([$today]); $singleAm = $single->fetchColumn();

$subscription = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE booking_date = ? AND is_subscription = 1 AND status = 'completed'");
$subscription->execute([$today]); $subscriptionAm = $subscription->fetchColumn();

$sessionCount = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND status = 'completed'");
$sessionCount->execute([$today]); $sessionCount = $sessionCount->fetchColumn();

// Список проведённых занятий
$list = $pdo->prepare("
    SELECT b.*, c.first_name, c.last_name, u.full_name as trainer_name 
    FROM bookings b 
    JOIN clients c ON b.client_id = c.client_id 
    LEFT JOIN users u ON b.trainer_id = u.user_id 
    WHERE b.booking_date = ? AND b.status = 'completed'
    ORDER BY b.booking_time
");
$list->execute([$today]);
$bookings = $list->fetchAll();

// Список продаж абонементов за сегодня
$salesList = $pdo->prepare("
    SELECT s.*, c.first_name, c.last_name, u.full_name as seller_name
    FROM sales s
    JOIN clients c ON s.client_id = c.client_id
    LEFT JOIN users u ON s.created_by = u.user_id
    WHERE s.sale_date = ?
    ORDER BY s.created_at DESC
");
$salesList->execute([$today]);
$sales = $salesList->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Главная - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .finance-card { background: linear-gradient(135deg, #1e3a5f, #2c4e7a); color: white; border-radius: 16px; padding: 20px; }
        .finance-card.green { background: linear-gradient(135deg, #2c7a4d, #3a9b5e); }
        .finance-card.orange { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .finance-card.purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); }
        .finance-card .amount { font-size: 28px; font-weight: 700; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 25px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        @media (max-width: 768px) { .grid-4, .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="main-header">
        <h1>🧘 FlexWellness</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="logout.php">🚪 Выход</a>
        </div>
    </header>

    <nav class="main-nav">
        <a href="dashboard.php" class="active">📊 Главная</a>
        <a href="analytics.php">💰 Финансы</a>
        <a href="clients.php">👥 Клиенты</a>
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
        <div class="grid-4">
            <div class="finance-card">
                <h4>💰 Наличные за сегодня</h4>
                <div class="amount"><?= number_format($totalCash, 2) ?> ₽</div>
                <div class="small">включая продажи</div>
            </div>
            <div class="finance-card green">
                <h4>💳 Безналичные за сегодня</h4>
                <div class="amount"><?= number_format($totalCard, 2) ?> ₽</div>
                <div class="small">включая продажи</div>
            </div>
            <div class="finance-card orange">
                <h4>🏋️ Разовые занятия</h4>
                <div class="amount"><?= number_format($singleAm, 2) ?> ₽</div>
            </div>
            <div class="finance-card purple">
                <h4>📋 Продажи абонементов</h4>
                <div class="amount"><?= number_format($salesCashAm + $salesCardAm, 2) ?> ₽</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h3>📊 Итоги дня</h3>
                <p>📅 <?= date('d.m.Y') ?></p>
                <p>💰 Общая выручка: <strong><?= number_format($total, 2) ?> ₽</strong></p>
                <p>📆 Проведено занятий: <?= $sessionCount ?></p>
                <p>💵 Средний чек: <?= $sessionCount > 0 ? number_format(($singleAm + $subscriptionAm) / $sessionCount, 2) : 0 ?> ₽</p>
            </div>
            <div class="card">
                <h3>🔔 Быстрые действия</h3>
                <a href="schedule.php" class="btn btn-primary">📅 Расписание</a>
                <a href="analytics.php" class="btn btn-warning">📊 Аналитика</a>
                <a href="sales.php" class="btn btn-success">💳 Продать абонемент</a>
                <a href="notifications.php?auto_reminder=1" class="btn btn-secondary">📨 Авторассылка</a>
            </div>
        </div>

        <div class="card">
            <h3>📋 Проведённые занятия за сегодня</h3>
            <?php if (count($bookings) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width:100%">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 12px;">Время</th>
                                <th style="padding: 12px;">Клиент</th>
                                <th style="padding: 12px;">Тренер</th>
                                <th style="padding: 12px;">Тип оплаты</th>
                                <th style="padding: 12px;">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $b): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 12px;"><?= $b['booking_time'] ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($b['last_name'] . ' ' . $b['first_name']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($b['trainer_name'] ?? '—') ?></td>
                                    <td style="padding: 12px;"><?= $b['payment_type'] == 'cash' ? '💰 Наличные' : '💳 Безналичные' ?></td>
                                    <td style="padding: 12px;"><strong><?= number_format($b['payment_amount'], 2) ?> ₽</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Проведенных занятий сегодня пока нет</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>📋 Продажи абонементов за сегодня</h3>
            <?php if (count($sales) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width:100%">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 12px;">Время</th>
                                <th style="padding: 12px;">Клиент</th>
                                <th style="padding: 12px;">Тип</th>
                                <th style="padding: 12px;">Сумма</th>
                                <th style="padding: 12px;">Продавец</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $s): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 12px;"><?= date('H:i', strtotime($s['created_at'])) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($s['last_name'] . ' ' . $s['first_name']) ?></td>
                                    <td style="padding: 12px;"><?= $s['sale_type'] == 'subscription' ? '📋 Абонемент' : '🎯 Пакет' ?> (<?= $s['sessions_count'] ?> з.)</td>
                                    <td style="padding: 12px;"><strong><?= number_format($s['price'], 2) ?> ₽</strong></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($s['seller_name'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Продаж абонементов сегодня нет</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>