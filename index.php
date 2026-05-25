<?php
require_once 'config.php';

$page = $_GET['page'] ?? 'online_booking';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>FlexWellness — Студия пилатеса во Владивостоке</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="main-header">
    <div class="container">
        <h1>🧘 FlexWellness</h1>
        <nav>
            <a href="?page=online_booking">📅 Онлайн-запись</a>
            <a href="admin.php">🔐 Админ-панель</a>
        </nav>
    </div>
</header>

<div class="container">
    <?php if ($page === 'online_booking'): ?>
        <div class="booking-form">
            <h2>Записаться на пилатес</h2>
            <form method="POST" action="?page=booking_submit">
                <input type="text" name="full_name" placeholder="Ваше полное имя" required>
                <input type="tel" name="phone" placeholder="Телефон" required>
                <input type="date" name="date" required>
                <input type="time" name="time" required>
                <button type="submit">Записаться</button>
            </form>
        </div>
    <?php elseif ($page === 'booking_submit' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php
        $result = publicBooking($_POST['phone'], $_POST['full_name'], $_POST['date'], $_POST['time']);
        if ($result):
        ?>
            <div class="success">✅ Запись оформлена! Администратор подтвердит в ближайшее время.</div>
        <?php else: ?>
            <div class="error">❌ Ошибка, попробуйте позже.</div>
        <?php endif; ?>
        <a href="?page=online_booking">← Вернуться</a>
    <?php elseif ($page === 'login'): ?>
        <form method="POST" action="admin.php?action=login">
            <input type="text" name="username" placeholder="Логин" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <button type="submit">Войти в CRM</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>