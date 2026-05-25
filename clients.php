<?php
require_once 'config.php';
requireAuth();

$msg = '';
$error = '';

// Добавление клиента с проверкой на дубликат
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    // Проверяем обязательные поля
    if (empty($_POST['last_name']) || empty($_POST['first_name']) || empty($_POST['phone'])) {
        $error = "Заполните обязательные поля: Фамилия, Имя, Телефон";
    } else {
        // Проверяем, существует ли уже клиент с таким телефоном
        $checkPhone = $pdo->prepare("SELECT client_id, last_name, first_name FROM clients WHERE phone = ?");
        $checkPhone->execute([trim($_POST['phone'])]);
        $existing = $checkPhone->fetch();
        
        if ($existing) {
            $error = "Клиент с номером телефона {$_POST['phone']} уже существует! (ID: {$existing['client_id']}, {$existing['last_name']} {$existing['first_name']})";
        } else {
            $result = addClient($_POST);
            if ($result) {
                $msg = "Клиент успешно добавлен!";
                // Очищаем форму
                $_POST = [];
            } else {
                $error = "Ошибка при добавлении клиента. Проверьте правильность заполнения полей.";
            }
        }
    }
}

$clients = getAllClients();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Клиенты - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
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
        <?php if ($error): ?>
            <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>➕ Добавить нового клиента</h3>
            <form method="POST" id="addClientForm">
                <div class="form-row">
                    <input type="text" name="last_name" placeholder="Фамилия *" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    <input type="text" name="first_name" placeholder="Имя *" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    <input type="text" name="middle_name" placeholder="Отчество" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <input type="tel" name="phone" id="phone" placeholder="Телефон *" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <input type="text" name="address" placeholder="Адрес" style="flex:2" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <button type="submit" name="add_client" class="btn btn-primary">Сохранить клиента</button>
            </form>
        </div>

        <div class="card">
            <h3>👥 Все клиенты</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата регистрации</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><?= $c['client_id'] ?></td>
                                <td><?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name'] . ' ' . $c['middle_name']) ?></td>
                                <td><?= htmlspecialchars($c['phone']) ?></td>
                                <td><?= htmlspecialchars($c['email']) ?></td>
                                <td><?= date('d.m.Y', strtotime($c['registration_date'])) ?></td>
                                <td>
                                    <a href="client_details.php?id=<?= $c['client_id'] ?>" class="btn btn-primary btn-sm">Подробнее</a>
                                    <a href="schedule.php?client_id=<?= $c['client_id'] ?>" class="btn btn-success btn-sm">📅 Записать</a>
                                </tr>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Проверка на дубликат телефона при вводе
        let phoneTimeout;
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                clearTimeout(phoneTimeout);
                const phone = this.value.trim();
                const errorDiv = document.getElementById('phoneError');
                
                if (phone.length >= 10) {
                    phoneTimeout = setTimeout(() => {
                        fetch('ajax.php?action=check_phone&phone=' + encodeURIComponent(phone))
                            .then(r => r.json())
                            .then(data => {
                                if (data.exists) {
                                    if (!errorDiv) {
                                        const newErrorDiv = document.createElement('div');
                                        newErrorDiv.id = 'phoneError';
                                        newErrorDiv.className = 'alert-error';
                                        newErrorDiv.style.marginTop = '5px';
                                        this.parentNode.appendChild(newErrorDiv);
                                    }
                                    const currentErrorDiv = document.getElementById('phoneError');
                                    if (currentErrorDiv) {
                                        currentErrorDiv.innerHTML = '⚠️ Клиент с таким телефоном уже существует: ' + data.client_name;
                                    }
                                    document.querySelector('button[type="submit"]').disabled = true;
                                } else {
                                    if (errorDiv) errorDiv.remove();
                                    document.querySelector('button[type="submit"]').disabled = false;
                                }
                            })
                            .catch(err => console.error('Ошибка проверки телефона:', err));
                    }, 500);
                } else {
                    if (errorDiv) errorDiv.remove();
                    document.querySelector('button[type="submit"]').disabled = false;
                }
            });
        }
    </script>
</body>
</html>