<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
requireAuth();

$selected_month = $_GET['month'] ?? date('Y-m');
$year = substr($selected_month, 0, 4);
$month = substr($selected_month, 5, 2);
$monthNames = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

// Выручка за месяц - наличные и безналичные (только занятия)
$cash = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE YEAR(booking_date)=? AND MONTH(booking_date)=? AND payment_type='cash' AND status='completed'");
$cash->execute([$year, $month]); $cashAm = $cash->fetchColumn();

$card = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE YEAR(booking_date)=? AND MONTH(booking_date)=? AND payment_type='card' AND status='completed'");
$card->execute([$year, $month]); $cardAm = $card->fetchColumn();

// Продажи абонементов за месяц
$salesCash = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM sales WHERE YEAR(sale_date)=? AND MONTH(sale_date)=? AND payment_type='cash'");
$salesCash->execute([$year, $month]); $salesCashAm = $salesCash->fetchColumn();

$salesCard = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM sales WHERE YEAR(sale_date)=? AND MONTH(sale_date)=? AND payment_type='card'");
$salesCard->execute([$year, $month]); $salesCardAm = $salesCard->fetchColumn();

// Итоговые суммы
$totalCash = $cashAm + $salesCashAm;
$totalCard = $cardAm + $salesCardAm;
$total = $totalCash + $totalCard;

// Разовые и абонементы из занятий
$single = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE YEAR(booking_date)=? AND MONTH(booking_date)=? AND is_subscription=0 AND status='completed'");
$single->execute([$year, $month]); $singleAm = $single->fetchColumn();

$subscription = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE YEAR(booking_date)=? AND MONTH(booking_date)=? AND is_subscription=1 AND status='completed'");
$subscription->execute([$year, $month]); $subscriptionAm = $subscription->fetchColumn();

$salesTotal = $salesCashAm + $salesCardAm;

// Количество проведённых занятий за месяц
$sessionsCount = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE YEAR(booking_date)=? AND MONTH(booking_date)=? AND status='completed'");
$sessionsCount->execute([$year, $month]); $sessionsCount = $sessionsCount->fetchColumn();

// Количество проданных абонементов за месяц - ИСПРАВЛЕНО
$subscriptionsSold = $pdo->prepare("
    SELECT sale_type, COUNT(*) as count, SUM(price) as total 
    FROM sales 
    WHERE YEAR(sale_date)=? AND MONTH(sale_date)=? 
    GROUP BY sale_type
");
$subscriptionsSold->execute([$year, $month]);
$subscriptionsData = $subscriptionsSold->fetchAll();

// Доходность по тренерам
$trainerStats = [];
$trainers = $pdo->query("SELECT user_id, full_name FROM users WHERE role='instructor' AND is_active=1 ORDER BY full_name")->fetchAll();

foreach ($trainers as $t) {
    $rev = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE trainer_id=? AND status='completed' AND YEAR(booking_date)=? AND MONTH(booking_date)=?");
    $rev->execute([$t['user_id'], $year, $month]);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE trainer_id=? AND status='completed' AND YEAR(booking_date)=? AND MONTH(booking_date)=?");
    $cnt->execute([$t['user_id'], $year, $month]);
    
    // Расчёт зарплаты тренера (почасовая ставка)
    $hours = $pdo->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, booking_time, DATE_ADD(booking_time, INTERVAL 1 HOUR))),0) FROM bookings WHERE trainer_id=? AND status='completed' AND YEAR(booking_date)=? AND MONTH(booking_date)=?");
    $hours->execute([$t['user_id'], $year, $month]);
    $hoursWorked = $hours->fetchColumn();
    
    $rate = $pdo->prepare("SELECT rate_value FROM trainer_salary_rates WHERE trainer_id = ?");
    $rate->execute([$t['user_id']]);
    $rateValue = $rate->fetchColumn();
    if (!$rateValue) $rateValue = 500;
    
    $salary = $hoursWorked * $rateValue;
    
    $trainerStats[] = [
        'user_id' => $t['user_id'],
        'name' => $t['full_name'],
        'sessions' => $cnt->fetchColumn(),
        'revenue' => $rev->fetchColumn(),
        'hours' => $hoursWorked,
        'rate' => $rateValue,
        'salary' => $salary
    ];
}

// Дневная динамика
$daily = $pdo->prepare("
    SELECT booking_date, COUNT(*) as cnt, 
           COALESCE(SUM(CASE WHEN payment_type='cash' THEN payment_amount ELSE 0 END),0) as cash_total,
           COALESCE(SUM(CASE WHEN payment_type='card' THEN payment_amount ELSE 0 END),0) as card_total,
           COALESCE(SUM(payment_amount),0) as day_total 
    FROM bookings 
    WHERE YEAR(booking_date)=? AND MONTH(booking_date)=? AND status='completed' 
    GROUP BY booking_date 
    ORDER BY booking_date
");
$daily->execute([$year, $month]);
$dailyData = $daily->fetchAll();

// Навигация по месяцам
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

// Обработка обновления ставки тренера
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate']) && isAdmin()) {
    $stmt = $pdo->prepare("INSERT INTO trainer_salary_rates (trainer_id, rate_type, rate_value, updated_by) VALUES (?, 'hourly', ?, ?) ON DUPLICATE KEY UPDATE rate_value = VALUES(rate_value), updated_by = VALUES(updated_by)");
    $stmt->execute([$_POST['trainer_id'], $_POST['rate_value'], $_SESSION['user_id']]);
    header("Location: analytics.php?month=$selected_month&msg=Ставка тренера обновлена");
    exit;
}

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Финансы - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .finance-card { background: linear-gradient(135deg, #1e3a5f, #2c4e7a); color: white; border-radius: 16px; padding: 20px; }
        .finance-card.green { background: linear-gradient(135deg, #2c7a4d, #3a9b5e); }
        .finance-card.orange { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .finance-card.purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); }
        .finance-card .amount { font-size: 28px; font-weight: 700; }
        .finance-card .small { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 25px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 25px; }
        .salary-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .salary-table th, .salary-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .salary-table th { background: #f1f5f9; font-weight: 600; }
        .total-row { background: #f8fafc; font-weight: 700; }
        .progress-bar-container { background: #e2e8f0; border-radius: 10px; overflow: hidden; height: 8px; margin-top: 5px; }
        .progress-bar { background: #2c7a4d; height: 100%; border-radius: 10px; }
        @media (max-width: 768px) { .grid-4, .grid-3 { grid-template-columns: 1fr; } }
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
        <a href="dashboard.php">📊 Главная</a>
        <a href="analytics.php" class="active">💰 Финансы</a>
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
        <?php if ($msg): ?>
            <div class="alert">✅ <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Навигация по месяцам -->
        <div class="card" style="text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <a href="analytics.php?month=<?= sprintf('%04d-%02d', $prevYear, $prevMonth) ?>" class="btn btn-secondary">← <?= $monthNames[$prevMonth] ?></a>
                <h2>📊 Финансовая аналитика за <?= $monthNames[(int)$month] ?> <?= $year ?></h2>
                <a href="analytics.php?month=<?= sprintf('%04d-%02d', $nextYear, $nextMonth) ?>" class="btn btn-secondary"><?= $monthNames[$nextMonth] ?> →</a>
            </div>
        </div>

        <!-- Карточки с итогами -->
        <div class="grid-4">
            <div class="finance-card">
                <h4>💰 Общая выручка</h4>
                <div class="amount"><?= number_format($total, 2) ?> ₽</div>
                <div class="small">включая продажи</div>
            </div>
            <div class="finance-card green">
                <h4>💳 Безналичные</h4>
                <div class="amount"><?= number_format($totalCard, 2) ?> ₽</div>
                <div class="small"><?= $total > 0 ? round($totalCard / $total * 100, 1) : 0 ?>% от выручки</div>
            </div>
            <div class="finance-card">
                <h4>💰 Наличные</h4>
                <div class="amount"><?= number_format($totalCash, 2) ?> ₽</div>
                <div class="small"><?= $total > 0 ? round($totalCash / $total * 100, 1) : 0 ?>% от выручки</div>
            </div>
            <div class="finance-card orange">
                <h4>📋 Продажи абонементов</h4>
                <div class="amount"><?= number_format($salesTotal, 2) ?> ₽</div>
                <div class="small">продано: <?= array_sum(array_column($subscriptionsData, 'count')) ?> шт</div>
            </div>
        </div>

        <!-- Статистика по типам абонементов - ИСПРАВЛЕНО -->
        <div class="card">
            <h3>📋 Структура продаж абонементов</h3>
            <div class="grid-3">
                <?php 
                $subscription_types = $pdo->query("SELECT * FROM subscription_types WHERE is_active = 1 ORDER BY sessions_count")->fetchAll();
                foreach ($subscription_types as $st):
                    $sold = $pdo->prepare("SELECT COALESCE(SUM(price),0) as total, COUNT(*) as cnt FROM sales WHERE sale_type = ? AND YEAR(sale_date)=? AND MONTH(sale_date)=?");
                    $sold->execute([$st['name'], $year, $month]);
                    $soldData = $sold->fetch();
                    $percentage = $salesTotal > 0 ? round($soldData['total'] / $salesTotal * 100, 1) : 0;
                ?>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 15px;">
                        <strong><?= htmlspecialchars($st['name']) ?></strong>
                        <div style="font-size: 24px; font-weight: 700; color: #1e3a5f;"><?= number_format($soldData['total'], 2) ?> ₽</div>
                        <div style="font-size: 12px; color: #666;">Продано: <?= $soldData['cnt'] ?> шт</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?= $percentage ?>%;"></div>
                        </div>
                        <div style="font-size: 11px; color: #666;"><?= $percentage ?>% от продаж</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Расчёт зарплаты тренеров -->
        <div class="card">
            <h3>🧘 Расчёт заработной платы тренеров за <?= $monthNames[(int)$month] ?> <?= $year ?></h3>
            <div style="overflow-x: auto;">
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th>Тренер</th>
                            <th>Занятий</th>
                            <th>Часов работы</th>
                            <th>Выручка с тренера</th>
                            <th>Ставка (₽/час)</th>
                            <th>Зарплата</th>
                            <?php if (isAdmin()): ?>
                                <th>Действие</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalSalary = 0;
                        foreach ($trainerStats as $t): 
                            $totalSalary += $t['salary'];
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                                <td><?= $t['sessions'] ?></td>
                                <td><?= $t['hours'] ?></td>
                                <td><?= number_format($t['revenue'], 2) ?> ₽</td>
                                <td>
                                    <?php if (isAdmin()): ?>
                                        <form method="POST" style="display: inline-flex; gap: 5px;">
                                            <input type="hidden" name="trainer_id" value="<?= $t['user_id'] ?>">
                                            <input type="number" step="10" name="rate_value" value="<?= $t['rate'] ?>" style="width: 80px;">
                                            <button type="submit" name="update_rate" class="btn btn-primary btn-sm">💾</button>
                                        </form>
                                    <?php else: ?>
                                        <?= number_format($t['rate'], 0) ?> ₽
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: #2c7a4d; font-size: 18px;"><?= number_format($t['salary'], 2) ?> ₽</strong></td>
                                <?php if (isAdmin()): ?>
                                    <td><a href="trainers.php" class="btn btn-secondary btn-sm">✏️</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="5" style="text-align: right;"><strong>ИТОГО ФОТ:</strong></td>
                            <td><strong style="font-size: 18px; color: #1e3a5f;"><?= number_format($totalSalary, 2) ?> ₽</strong></td>
                            <?php if (isAdmin()): ?>
                                <td></td>
                            <?php endif; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="shift-info" style="margin-top: 15px; padding: 12px; background: #f1f5f9; border-radius: 8px;">
                📌 <strong>Как рассчитывается зарплата:</strong><br>
                • Зарплата = Количество отработанных часов × Ставка (₽/час)<br>
                • Администратор может изменить ставку для каждого тренера в этой таблице или в разделе "Тренеры"
            </div>
        </div>

        <!-- Дневная динамика выручки -->
        <div class="card">
            <h3>📅 Дневная динамика выручки</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Занятий</th>
                            <th>Наличные</th>
                            <th>Безналичные</th>
                            <th>Итого за день</th>
                            <th>Накоплено</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $runningTotal = 0;
                        foreach ($dailyData as $d): 
                            $dayTotal = $d['day_total'];
                            $runningTotal += $dayTotal;
                        ?>
                            <tr>
                                <td><strong><?= date('d.m.Y', strtotime($d['booking_date'])) ?></strong></td>
                                <td><?= $d['cnt'] ?></td>
                                <td><?= number_format($d['cash_total'], 2) ?> ₽</td>
                                <td><?= number_format($d['card_total'], 2) ?> ₽</td>
                                <td><?= number_format($dayTotal, 2) ?> ₽</td>
                                <td><span style="color: #1e3a5f;"><?= number_format($runningTotal, 2) ?> ₽</span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dailyData)): ?>
                            <tr><td colspan="6" class="text-center">Нет данных за этот месяц</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot style="background: #f1f5f9; font-weight: 700;">
                        <tr>
                            <td><strong>Итого:</strong></td>
                            <td><strong><?= $sessionsCount ?></strong></td>
                            <td><strong><?= number_format(array_sum(array_column($dailyData, 'cash_total')), 2) ?> ₽</strong></td>
                            <td><strong><?= number_format(array_sum(array_column($dailyData, 'card_total')), 2) ?> ₽</strong></td>
                            <td><strong><?= number_format($total, 2) ?> ₽</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Дополнительная статистика -->
        <div class="grid-2">
            <div class="card">
                <h3>📊 Ключевые показатели</h3>
                <p><strong>💰 Средний чек:</strong> <?= $sessionsCount > 0 ? number_format(($singleAm + $subscriptionAm) / $sessionsCount, 2) : 0 ?> ₽</p>
                <p><strong>📈 Средняя выручка в день:</strong> <?= count($dailyData) > 0 ? number_format($total / count($dailyData), 2) : 0 ?> ₽</p>
                <p><strong>🏋️ Средняя загрузка тренера:</strong> <?= count($trainerStats) > 0 ? round($sessionsCount / count($trainerStats), 1) : 0 ?> занятий</p>
                <p><strong>📅 Активных дней в месяце:</strong> <?= count($dailyData) ?></p>
            </div>
            <div class="card">
                <h3>💡 Рекомендации</h3>
                <?php
                $bestTrainer = !empty($trainerStats) ? array_reduce($trainerStats, function($max, $item) {
                    return ($item['revenue'] > $max['revenue']) ? $item : $max;
                }, $trainerStats[0]) : null;
                ?>
                <?php if ($bestTrainer): ?>
                    <p>🏆 <strong>Лучший тренер месяца:</strong> <?= htmlspecialchars($bestTrainer['name']) ?> (<?= number_format($bestTrainer['revenue'], 2) ?> ₽)</p>
                <?php endif; ?>
                <?php if ($singleAm > $subscriptionAm): ?>
                    <p>📋 Рекомендуется активнее продавать абонементы для увеличения прибыли.</p>
                <?php else: ?>
                    <p>🏋️ Хорошая доля абонементов — клиенты лояльны к студии.</p>
                <?php endif; ?>
                <?php if ($salesTotal < 50000): ?>
                    <p>📈 Для увеличения прибыли запустите акцию на абонементы.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>