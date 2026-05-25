<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_client = $_GET['client_id'] ?? '';

// Обработка смен тренеров
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shift'])) {
    $stmt = $pdo->prepare("INSERT INTO trainer_shifts (trainer_id, shift_date, start_time, end_time, is_working, notes) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time), is_working=VALUES(is_working), notes=VALUES(notes)");
    $stmt->execute([$_POST['trainer_id'], $_POST['shift_date'], $_POST['start_time'], $_POST['end_time'], $_POST['is_working'], $_POST['notes']]);
    header("Location: schedule.php?date={$_POST['shift_date']}&msg=Смена сохранена");
    exit;
}

if (isset($_GET['delete_shift'])) {
    $pdo->prepare("DELETE FROM trainer_shifts WHERE shift_id = ?")->execute([$_GET['delete_shift']]);
    header("Location: schedule.php?date=" . ($_GET['date'] ?? date('Y-m-d')) . "&msg=Смена удалена");
    exit;
}

// Списание тренировки
if (isset($_GET['mark_attendance'])) {
    markAttendance($_GET['mark_attendance']);
    header("Location: schedule.php?date=" . ($_GET['date'] ?? $selected_date) . "&msg=Посещение отмечено, занятие списано");
    exit;
}

// Удаление записи
if (isset($_GET['delete_booking'])) {
    deleteBooking($_GET['delete_booking']);
    header("Location: schedule.php?date=" . ($_GET['date'] ?? $selected_date) . "&msg=Запись удалена");
    exit;
}

// Обновление статуса
if (isset($_POST['update_status'])) {
    updateBookingStatus($_POST['booking_id'], $_POST['status']);
    header("Location: schedule.php?date=" . ($_POST['booking_date'] ?? $selected_date) . "&msg=Статус обновлён");
    exit;
}

// Добавление записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    addBooking($_POST);
    header("Location: schedule.php?date={$_POST['booking_date']}&msg=Запись добавлена");
    exit;
}

// ПОЛУЧАЕМ ТОЛЬКО ТРЕНЕРОВ, У КОТОРЫХ ЕСТЬ СМЕНА НА ЭТУ ДАТУ
$trainers_with_shifts = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.full_name, ts.start_time, ts.end_time, ts.is_working
    FROM users u
    INNER JOIN trainer_shifts ts ON u.user_id = ts.trainer_id AND ts.shift_date = ?
    WHERE u.role = 'instructor' AND u.is_active = 1 AND ts.is_working = 1
    ORDER BY u.full_name
");
$trainers_with_shifts->execute([$selected_date]);
$active_trainers = $trainers_with_shifts->fetchAll();

$hours = [9,10,11,12,13,14,15,16,17,18,19,20];

// Получаем записи
$bookings = getBookingsByDate($selected_date);

// Индексируем записи по часу и тренеру
$schedule = [];
foreach ($bookings as $b) {
    $hour = (int)substr($b['booking_time'], 0, 2);
    $trainer_id = $b['trainer_id'];
    if (!isset($schedule[$hour][$trainer_id])) {
        $schedule[$hour][$trainer_id] = [];
    }
    $schedule[$hour][$trainer_id][] = $b;
}

// Получаем смены для отображения времени работы
$shifts = [];
$shiftStmt = $pdo->prepare("SELECT * FROM trainer_shifts WHERE shift_date = ?");
$shiftStmt->execute([$selected_date]);
foreach ($shiftStmt->fetchAll() as $s) {
    $shifts[$s['trainer_id']] = $s;
}

// Календарь на месяц
$cal_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y', strtotime($selected_date));
$cal_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('m', strtotime($selected_date));

if ($cal_month < 1) $cal_month = 1;
if ($cal_month > 12) $cal_month = 12;

$firstDayOfMonth = mktime(0, 0, 0, $cal_month, 1, $cal_year);
$daysInMonth = date('t', $firstDayOfMonth);
$firstDayWeek = date('N', $firstDayOfMonth);
$monthNames = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

$bookingCounts = [];
$countStmt = $pdo->prepare("SELECT booking_date, COUNT(*) as cnt FROM bookings WHERE MONTH(booking_date) = ? AND YEAR(booking_date) = ? GROUP BY booking_date");
$countStmt->execute([$cal_month, $cal_year]);
foreach ($countStmt->fetchAll() as $row) {
    $bookingCounts[$row['booking_date']] = $row['cnt'];
}

$prevMonth = $cal_month == 1 ? 12 : $cal_month - 1;
$prevYear = $cal_month == 1 ? $cal_year - 1 : $cal_year;
$nextMonth = $cal_month == 12 ? 1 : $cal_month + 1;
$nextYear = $cal_month == 12 ? $cal_year + 1 : $cal_year;

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расписание - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .mini-calendar { background: white; border-radius: 16px; overflow: hidden; margin-bottom: 25px; }
        .calendar-header { background: #1e3a5f; color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; }
        .calendar-header a { color: white; text-decoration: none; font-size: 18px; padding: 0 10px; }
        .calendar-header h4 { font-size: 16px; font-weight: 500; }
        .calendar-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); background: #f1f5f9; text-align: center; font-weight: 600; font-size: 12px; padding: 8px 0; }
        .calendar-days-mini { display: grid; grid-template-columns: repeat(7, 1fr); }
        .calendar-day-mini { min-height: 45px; padding: 6px 4px; text-align: center; cursor: pointer; border: 1px solid #e2e8f0; font-size: 13px; transition: all 0.2s; }
        .calendar-day-mini:hover { background: #e0f2fe; }
        .calendar-day-mini.selected { background: #1e3a5f; color: white; font-weight: bold; }
        .calendar-day-mini.today { background: #dbeafe; border: 2px solid #1e3a5f; }
        .calendar-day-mini.other-month { background: #f8fafc; color: #94a3b8; }
        .day-booking-count { font-size: 9px; background: #d1fae5; color: #065f46; border-radius: 10px; padding: 1px 4px; display: inline-block; margin-top: 2px; }
        
        .schedule-container { overflow-x: auto; }
        .schedule-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 800px; }
        .schedule-table th, .schedule-table td { border: 1px solid #ddd; padding: 10px 8px; vertical-align: top; }
        .schedule-table th { background: #1e3a5f; color: white; font-weight: 600; text-align: center; position: sticky; top: 0; }
        .time-col { background: #f1f5f9; font-weight: 600; text-align: center; width: 70px; }
        .booking-cell { min-height: 85px; background: #f9fafb; border-radius: 8px; padding: 8px; transition: all 0.2s; border-left: 3px solid #1e3a5f; margin-bottom: 5px; }
        .booking-cell:hover { background: #e0f2fe; transform: scale(1.01); }
        .booking-empty { min-height: 85px; background: #f1f5f9; border-radius: 8px; padding: 8px; color: #94a3b8; text-align: center; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .booking-empty:hover { background: #e2e8f0; }
        .booking-client { font-weight: 700; color: #1e3a5f; margin-bottom: 4px; }
        .booking-phone { font-size: 10px; color: #666; margin-bottom: 4px; }
        .booking-status { font-size: 10px; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 4px; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .shift-info { font-size: 10px; background: #e2e8f0; padding: 2px 4px; border-radius: 4px; margin-top: 4px; display: inline-block; }
        .mark-btn { background: #2c7a4d; color: white; border: none; border-radius: 4px; padding: 2px 6px; font-size: 10px; cursor: pointer; margin-top: 5px; margin-right: 5px; }
        .mark-btn:hover { background: #1e5a3d; }
        
        .trainers-grid { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px; margin-bottom: 20px; }
        .trainer-card { background: #f8fafc; border-radius: 12px; padding: 10px 15px; border: 1px solid #e2e8f0; min-width: 160px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; max-height: 85vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 20px; }
        .modal-content select, .modal-content input, .modal-content textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
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
        <a href="analytics.php">💰 Финансы</a>
        <a href="clients.php">👥 Клиенты</a>
        <a href="schedule.php" class="active">📅 Расписание</a>
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

        <!-- Мини-календарь -->
        <div class="mini-calendar">
            <div class="calendar-header">
                <a href="schedule.php?date=<?= $selected_date ?>&cal_year=<?= $prevYear ?>&cal_month=<?= $prevMonth ?>">←</a>
                <h4><?= $monthNames[$cal_month] ?> <?= $cal_year ?></h4>
                <a href="schedule.php?date=<?= $selected_date ?>&cal_year=<?= $nextYear ?>&cal_month=<?= $nextMonth ?>">→</a>
            </div>
            <div class="calendar-weekdays">
                <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
            </div>
            <div class="calendar-days-mini">
                <?php
                $day = 1;
                $startOffset = $firstDayWeek - 1;
                for ($i = 0; $i < $startOffset; $i++) {
                    echo '<div class="calendar-day-mini other-month"></div>';
                }
                while ($day <= $daysInMonth) {
                    $currentDate = sprintf("%04d-%02d-%02d", $cal_year, $cal_month, $day);
                    $hasBookings = isset($bookingCounts[$currentDate]);
                    $isSelected = $currentDate == $selected_date;
                    $isToday = $currentDate == date('Y-m-d');
                    ?>
                    <div class="calendar-day-mini <?= $isSelected ? 'selected' : '' ?> <?= $isToday ? 'today' : '' ?>" 
                         onclick="window.location.href='schedule.php?date=<?= $currentDate ?>&cal_year=<?= $cal_year ?>&cal_month=<?= $cal_month ?>'">
                        <?= $day ?>
                        <?php if ($hasBookings): ?>
                            <div class="day-booking-count"><?= $bookingCounts[$currentDate] ?></div>
                        <?php endif; ?>
                    </div>
                    <?php
                    $day++;
                }
                $remaining = 42 - ($startOffset + $daysInMonth);
                for ($i = 0; $i < $remaining; $i++) {
                    echo '<div class="calendar-day-mini other-month"></div>';
                }
                ?>
            </div>
        </div>

        <!-- Только тренеры со сменами -->
        <div class="card">
            <h3>🧘 Тренеры, работающие <?= date('d.m.Y', strtotime($selected_date)) ?></h3>
            <div class="trainers-grid">
                <?php foreach ($active_trainers as $t): ?>
                    <div class="trainer-card">
                        <strong><?= htmlspecialchars($t['full_name']) ?></strong><br>
                        🕐 <?= substr($t['start_time'], 0, 5) ?> - <?= substr($t['end_time'], 0, 5) ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($active_trainers)): ?>
                    <p style="color: #666;">Нет тренеров со сменами на эту дату. Добавьте смены в панели ниже.</p>
                <?php endif; ?>
            </div>
            
            <!-- Управление сменами (для админа) -->
            <?php if (isAdmin()): ?>
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #1e3a5f;">🔧 Управление сменами тренеров</summary>
                    <div class="trainers-grid" style="margin-top: 15px;">
                        <?php 
                        $all_trainers = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'instructor' AND is_active = 1 ORDER BY full_name")->fetchAll();
                        foreach ($all_trainers as $t): 
                            $shift = $shifts[$t['user_id']] ?? null;
                        ?>
                            <div class="trainer-card">
                                <strong><?= htmlspecialchars($t['full_name']) ?></strong><br>
                                <?php if ($shift): ?>
                                    🕐 <?= substr($shift['start_time'], 0, 5) ?> - <?= substr($shift['end_time'], 0, 5) ?>
                                    <?php if (!$shift['is_working']): ?>
                                        <span style="color:#c2410c;">(Выходной)</span>
                                    <?php endif; ?>
                                    <a href="schedule.php?delete_shift=<?= $shift['shift_id'] ?>&date=<?= $selected_date ?>" class="btn btn-danger btn-sm" style="margin-left: 8px;" onclick="return confirm('Удалить смену?')">🗑️</a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Нет смены</span>
                                    <button class="btn btn-primary btn-sm" style="margin-left: 8px;" onclick="openShiftModal(<?= $t['user_id'] ?>, '<?= addslashes($t['full_name']) ?>')">➕</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>

        <!-- Форма добавления записи -->
        <div class="card">
            <h3>➕ Добавить запись</h3>
            <form method="POST" id="addBookingForm">
                <input type="hidden" name="add_booking" value="1">
                <input type="hidden" name="booking_date" value="<?= $selected_date ?>">
                <div class="form-row">
                    <select name="client_id" id="client_id" required style="flex:2" onchange="loadClientContracts(this.value)">
                        <option value="">Выберите клиента</option>
                        <?php foreach (getAllClients() as $c): ?>
                            <option value="<?= $c['client_id'] ?>" <?= $selected_client == $c['client_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name'] . ' — ' . $c['phone']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="trainer_id" required style="flex:1">
                        <option value="">Тренер</option>
                        <?php foreach ($active_trainers as $t): ?>
                            <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="time" name="booking_time" required style="flex:1">
                </div>
                <div class="form-row">
                    <select name="contract_id" id="contract_id" style="flex:2">
                        <option value="">Без абонемента (разовое)</option>
                    </select>
                    <select name="payment_type" style="flex:1">
                        <option value="card">💳 Безналичные</option>
                        <option value="cash">💰 Наличные</option>
                    </select>
                    <input type="number" step="0.01" name="payment_amount" id="payment_amount" placeholder="Сумма" value="700" style="flex:1">
                </div>
                <button type="submit" class="btn btn-primary">➕ Добавить запись</button>
            </form>
        </div>

        <!-- Расписание сеткой -->
        <?php if (empty($active_trainers)): ?>
            <div class="card">
                <p>⚠️ Нет тренеров со сменами на эту дату. Добавьте смены в панели выше.</p>
            </div>
        <?php else: ?>
            <div class="card schedule-container">
                <h3>📅 Расписание на <?= date('d.m.Y', strtotime($selected_date)) ?></h3>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th class="time-col">Время</th>
                            <?php foreach ($active_trainers as $trainer): ?>
                                <th>
                                    <?= htmlspecialchars($trainer['full_name']) ?>
                                    <div class="shift-info"><?= substr($trainer['start_time'], 0, 5) ?>-<?= substr($trainer['end_time'], 0, 5) ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hours as $hour): ?>
                            <tr>
                                <td class="time-col"><?= sprintf('%02d:00', $hour) ?></td>
                                <?php foreach ($active_trainers as $trainer): 
                                    $shiftStart = (int)substr($trainer['start_time'], 0, 2);
                                    $shiftEnd = (int)substr($trainer['end_time'], 0, 2);
                                    $isWorkingHour = ($hour >= $shiftStart && $hour < $shiftEnd);
                                ?>
                                    <td style="min-width: 180px;">
                                        <?php if (!$isWorkingHour): ?>
                                            <div class="booking-empty" style="background:#fee2e2; color:#991b1b;">
                                                ⛔ Не в смене
                                            </div>
                                        <?php elseif (isset($schedule[$hour][$trainer['user_id']]) && count($schedule[$hour][$trainer['user_id']]) > 0):
                                            foreach ($schedule[$hour][$trainer['user_id']] as $booking):
                                        ?>
                                            <div class="booking-cell">
                                                <div class="booking-client">👤 <?= htmlspecialchars($booking['client_name']) ?></div>
                                                <div class="booking-phone">📞 <?= htmlspecialchars($booking['phone']) ?></div>
                                                <div class="booking-status status-<?= $booking['status'] ?>">
                                                    <?php 
                                                        $status_text = [
                                                            'pending' => '⏳ Ожидает',
                                                            'confirmed' => '✅ Подтверждена',
                                                            'completed' => '✔️ Проведена',
                                                            'cancelled' => '❌ Отменена'
                                                        ];
                                                        echo $status_text[$booking['status']] ?? $booking['status'];
                                                    ?>
                                                </div>
                                                <?php if ($booking['notes']): ?>
                                                    <div style="font-size: 10px; color: #888;">📝 <?= htmlspecialchars(mb_substr($booking['notes'], 0, 20)) ?></div>
                                                <?php endif; ?>
                                                <div style="margin-top: 5px;">
                                                    <form method="POST" style="display:inline">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                                        <input type="hidden" name="booking_date" value="<?= $booking['booking_date'] ?>">
                                                        <select name="status" onchange="this.form.submit()" style="font-size:10px; padding:2px;">
                                                            <option value="pending" <?= $booking['status']=='pending' ? 'selected' : '' ?>>⏳ Ожидает</option>
                                                            <option value="confirmed" <?= $booking['status']=='confirmed' ? 'selected' : '' ?>>✅ Подтверждена</option>
                                                            <option value="completed" <?= $booking['status']=='completed' ? 'selected' : '' ?>>✔️ Проведена</option>
                                                        </select>
                                                    </form>
                                                    <?php if ($booking['status'] != 'completed'): ?>
                                                        <a href="schedule.php?mark_attendance=<?= $booking['booking_id'] ?>&date=<?= $selected_date ?>" class="mark-btn" onclick="return confirm('Списать занятие и отметить посещение?')">✅ Списать</a>
                                                    <?php endif; ?>
                                                    <a href="schedule.php?delete_booking=<?= $booking['booking_id'] ?>&date=<?= $selected_date ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить запись?')">🗑️</a>
                                                </div>
                                            </div>
                                        <?php endforeach; else: ?>
                                            <div class="booking-empty" onclick="openAddModal(<?= $hour ?>, '<?= $selected_date ?>', <?= $trainer['user_id'] ?>, '<?= addslashes($trainer['full_name']) ?>')">
                                                <div style="font-size: 20px;">✚</div>
                                                <span>Записать</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Модальное окно для добавления смены -->
    <div id="shiftModal" class="modal">
        <div class="modal-content">
            <h3>🕐 Добавить смену на <?= date('d.m.Y', strtotime($selected_date)) ?></h3>
            <form method="POST">
                <input type="hidden" name="save_shift" value="1">
                <input type="hidden" name="shift_date" value="<?= $selected_date ?>">
                <input type="hidden" name="trainer_id" id="shift_trainer_id">
                
                <label>Тренер</label>
                <input type="text" id="shift_trainer_name" readonly style="background:#f5f5f5;">
                
                <label>Начало работы</label>
                <input type="time" name="start_time" value="09:00">
                
                <label>Конец работы</label>
                <input type="time" name="end_time" value="20:00">
                
                <label>Статус</label>
                <select name="is_working">
                    <option value="1">Работает</option>
                    <option value="0">Выходной</option>
                </select>
                
                <label>Заметки</label>
                <textarea name="notes" rows="2" placeholder="Причина отсутствия"></textarea>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('shiftModal')">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить смену</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно для добавления записи -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>➕ Записать клиента</h3>
            <form method="POST" id="modalBookingForm">
                <input type="hidden" name="add_booking" value="1">
                <input type="hidden" name="booking_date" id="add_booking_date">
                <input type="hidden" name="booking_time" id="add_booking_time">
                <input type="hidden" name="trainer_id" id="add_trainer_id">
                
                <label>Клиент *</label>
                <select name="client_id" id="modal_client_id" required onchange="loadModalClientContracts(this.value)">
                    <option value="">Выберите клиента</option>
                    <?php foreach (getAllClients() as $c): ?>
                        <option value="<?= $c['client_id'] ?>"><?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name'] . ' — ' . $c['phone']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label>Тренер</label>
                <input type="text" id="add_trainer_name" readonly style="background:#f5f5f5;">
                
                <label>Дата и время</label>
                <input type="text" id="add_datetime" readonly style="background:#f5f5f5;">
                
                <label>Тип оплаты</label>
                <select name="payment_type" id="modal_payment_type">
                    <option value="card">💳 Безналичные</option>
                    <option value="cash">💰 Наличные</option>
                </select>
                
                <label>Сумма</label>
                <input type="number" step="0.01" name="payment_amount" id="modal_payment_amount" value="700" required>
                
                <label>Договор (опционально)</label>
                <select name="contract_id" id="modal_contract_id">
                    <option value="">Без договора (разовое)</option>
                </select>
                
                <label>Заметки</label>
                <textarea name="notes" rows="2"></textarea>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Отмена</button>
                    <button type="submit" class="btn btn-primary">Записать</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Загрузка активных договоров клиента для основной формы
        function loadClientContracts(clientId) {
            if (clientId) {
                fetch('ajax.php?action=get_contracts&client_id=' + clientId)
                    .then(response => response.json())
                    .then(data => {
                        let select = document.getElementById('contract_id');
                        let paymentInput = document.getElementById('payment_amount');
                        
                        select.innerHTML = '<option value="">Без абонемента (разовое)</option>';
                        
                        if (data && data.length > 0) {
                            data.forEach(contract => {
                                let optionText = contract.contract_type + ' — осталось ' + contract.sessions_left + ' занятий (до ' + contract.end_date + ')';
                                select.innerHTML += '<option value="' + contract.contract_id + '">' + optionText + '</option>';
                            });
                            select.value = data[0].contract_id;
                            paymentInput.value = 0;
                            paymentInput.disabled = true;
                            paymentInput.style.background = '#f5f5f5';
                        } else {
                            paymentInput.value = 700;
                            paymentInput.disabled = false;
                            paymentInput.style.background = 'white';
                        }
                    })
                    .catch(error => console.error('Ошибка загрузки договоров:', error));
            }
        }
        
        // Загрузка активных договоров клиента для модального окна
        function loadModalClientContracts(clientId) {
            if (clientId) {
                fetch('ajax.php?action=get_contracts&client_id=' + clientId)
                    .then(response => response.json())
                    .then(data => {
                        let select = document.getElementById('modal_contract_id');
                        let paymentInput = document.getElementById('modal_payment_amount');
                        
                        select.innerHTML = '<option value="">Без абонемента (разовое)</option>';
                        
                        if (data && data.length > 0) {
                            data.forEach(contract => {
                                let optionText = contract.contract_type + ' — осталось ' + contract.sessions_left + ' занятий (до ' + contract.end_date + ')';
                                select.innerHTML += '<option value="' + contract.contract_id + '">' + optionText + '</option>';
                            });
                            select.value = data[0].contract_id;
                            paymentInput.value = 0;
                            paymentInput.disabled = true;
                            paymentInput.style.background = '#f5f5f5';
                        } else {
                            paymentInput.value = 700;
                            paymentInput.disabled = false;
                            paymentInput.style.background = 'white';
                        }
                    })
                    .catch(error => console.error('Ошибка загрузки договоров:', error));
            }
        }
        
        // При ручном выборе абонемента в основной форме
        document.getElementById('contract_id').addEventListener('change', function() {
            let paymentInput = document.getElementById('payment_amount');
            if (this.value) {
                paymentInput.value = 0;
                paymentInput.disabled = true;
                paymentInput.style.background = '#f5f5f5';
            } else {
                paymentInput.value = 700;
                paymentInput.disabled = false;
                paymentInput.style.background = 'white';
            }
        });
        
        function openShiftModal(trainerId, trainerName) {
            document.getElementById('shiftModal').style.display = 'flex';
            document.getElementById('shift_trainer_id').value = trainerId;
            document.getElementById('shift_trainer_name').value = trainerName;
        }
        
        function openAddModal(hour, date, trainerId, trainerName) {
            document.getElementById('addModal').style.display = 'flex';
            document.getElementById('add_booking_date').value = date;
            document.getElementById('add_booking_time').value = hour + ':00';
            document.getElementById('add_trainer_id').value = trainerId;
            document.getElementById('add_trainer_name').value = trainerName;
            document.getElementById('add_datetime').value = date + ' ' + hour + ':00';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            let clientSelect = document.getElementById('client_id');
            if (clientSelect && clientSelect.value) {
                loadClientContracts(clientSelect.value);
            }
        });
    </script>
</body>
</html>