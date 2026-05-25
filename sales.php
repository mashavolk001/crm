<?php
require_once 'config.php';
requireAuth();

// Получаем типы абонементов
$subscription_types = $pdo->query("SELECT * FROM subscription_types WHERE is_active = 1 ORDER BY sessions_count")->fetchAll();

// Обработка продажи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sale'])) {
    try {
        $pdo->beginTransaction();
        
        if (empty($_POST['subscription_type_id'])) {
            throw new Exception("Выберите тип абонемента");
        }
        
        $stmt = $pdo->prepare("SELECT * FROM subscription_types WHERE type_id = ?");
        $stmt->execute([$_POST['subscription_type_id']]);
        $st = $stmt->fetch();
        
        if (!$st) {
            throw new Exception("Тип абонемента не найден");
        }
        
        // Расчёт финальной цены со скидкой
        $discount = !empty($_POST['discount']) ? (float)$_POST['discount'] : 0;
        $final_price = $st['price'] - ($st['price'] * $discount / 100);
        
        $contract_number = 'CTR-' . date('Ymd') . '-' . rand(1000, 9999);
        $end_date = date('Y-m-d', strtotime('+' . $st['validity_days'] . ' days'));
        
        // Создаём договор
        $stmt = $pdo->prepare("INSERT INTO contracts (contract_number, client_id, subscription_type_id, contract_type, sessions_count, price, final_price, discount_percent, start_date, end_date, status, payment_type, sold_by, sold_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'active', ?, ?, NOW())");
        $stmt->execute([
            $contract_number,
            $_POST['client_id'],
            $st['type_id'],
            $st['name'],
            $st['sessions_count'],
            $st['price'],
            $final_price,
            $discount,
            $end_date,
            $_POST['payment_type'],
            $_SESSION['user_id']
        ]);
        $contract_id = $pdo->lastInsertId();
        
        // Записываем продажу
        $stmt2 = $pdo->prepare("INSERT INTO sales (client_id, contract_id, sale_type, sessions_count, price, final_price, discount_percent, payment_type, sale_date, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
        $stmt2->execute([
            $_POST['client_id'],
            $contract_id,
            $st['name'],
            $st['sessions_count'],
            $st['price'],
            $final_price,
            $discount,
            $_POST['payment_type'],
            $_SESSION['user_id']
        ]);
        
        $pdo->commit();
        
        $discount_text = $discount > 0 ? " (скидка {$discount}%, сумма: " . number_format($final_price, 2) . " ₽)" : "";
        header("Location: sales.php?msg=" . urlencode("Абонемент «{$st['name']}» оформлен!{$discount_text}"));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: sales.php?error=" . urlencode("Ошибка: " . $e->getMessage()));
        exit;
    }
}

// Получаем список продаж
$sales = $pdo->query("
    SELECT s.*, c.first_name, c.last_name, c.phone, u.full_name as seller_name
    FROM sales s
    JOIN clients c ON s.client_id = c.client_id
    LEFT JOIN users u ON s.created_by = u.user_id
    ORDER BY s.created_at DESC
    LIMIT 100
")->fetchAll();

// Получаем активные договоры
$activeContracts = $pdo->query("
    SELECT ct.*, c.first_name, c.last_name, c.phone,
           (ct.sessions_count - ct.sessions_used) as remaining
    FROM contracts ct
    JOIN clients c ON ct.client_id = c.client_id
    WHERE ct.status = 'active' AND ct.sessions_used < ct.sessions_count AND ct.end_date >= CURDATE()
    ORDER BY ct.end_date ASC
")->fetchAll();

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$selected_client = $_GET['client_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Продажи - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .sale-card {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c4e7a 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .sale-card:hover { transform: translateY(-3px); }
        .sale-card.selected { transform: scale(1.02); box-shadow: 0 0 0 3px white, 0 0 0 6px #1e3a5f; }
        .sale-card .price { font-size: 28px; font-weight: bold; margin: 10px 0; }
        .sale-card .old-price { font-size: 14px; text-decoration: line-through; opacity: 0.7; }
        .sale-card .sessions { font-size: 14px; }
        .sale-card .validity { font-size: 11px; opacity: 0.8; margin-top: 5px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .discount-row { display: flex; gap: 15px; align-items: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .final-price { font-size: 18px; font-weight: bold; color: #2c7a4d; margin-top: 5px; }
        .discount-input { flex: 1; }
        .final-price-input { flex: 1; }
        .discount-input input, .final-price-input input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; }
        .final-price-input input { background: #f5f5f5; font-weight: bold; color: #2c7a4d; }
        @media (max-width: 768px) { .grid-3 { grid-template-columns: 1fr; } }
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
        <a href="schedule.php">📅 Расписание</a>
        <a href="sales.php" class="active">💳 Продажи</a>
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
            <h2>💳 Оформление продажи</h2>
            <form method="POST" id="saleForm">
                <div class="form-row">
                    <div style="flex: 2;">
                        <label>Клиент *</label>
                        <select name="client_id" id="client_id" required>
                            <option value="">Выберите клиента</option>
                            <?php foreach (getAllClients() as $c): ?>
                                <option value="<?= $c['client_id'] ?>" <?= $selected_client == $c['client_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name'] . ' — ' . $c['phone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label>Тип оплаты</label>
                        <select name="payment_type" id="payment_type">
                            <option value="card">💳 Безналичные</option>
                            <option value="cash">💰 Наличные</option>
                        </select>
                    </div>
                </div>

                <div class="grid-3">
                    <?php foreach ($subscription_types as $st): ?>
                        <div class="sale-card" 
                             data-type-id="<?= $st['type_id'] ?>" 
                             data-name="<?= htmlspecialchars($st['name']) ?>"
                             data-sessions="<?= $st['sessions_count'] ?>" 
                             data-price="<?= $st['price'] ?>" 
                             data-days="<?= $st['validity_days'] ?>" 
                             onclick="selectSale(this)">
                            <h3><?= htmlspecialchars($st['name']) ?></h3>
                            <div class="price"><?= number_format($st['price'], 0) ?> ₽</div>
                            <div class="sessions"><?= $st['sessions_count'] ?> занятий</div>
                            <div class="validity">Срок: <?= $st['validity_days'] ?> дней</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="discount-row">
                    <div class="discount-input">
                        <label>Скидка (%)</label>
                        <input type="number" step="1" name="discount" id="discount" value="0" min="0" max="100" onchange="updateFinalPrice()" oninput="updateFinalPrice()">
                    </div>
                    <div class="final-price-input">
                        <label>Итоговая сумма</label>
                        <input type="text" id="final_price_display" readonly>
                    </div>
                </div>

                <input type="hidden" name="subscription_type_id" id="subscription_type_id" required>
                <input type="hidden" name="sessions_count" id="sessions_count" required>
                <input type="hidden" name="price" id="price" required>
                <input type="hidden" name="validity_days" id="validity_days" required>

                <button type="submit" name="add_sale" class="btn btn-primary" style="margin-top: 20px; width: 100%;" id="submitBtn" disabled>💳 Оформить продажу</button>
            </form>
        </div>

        <div class="card">
            <h3>📋 Активные договоры</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>Клиент</th>
                            <th>№ договора</th>
                            <th>Тип</th>
                            <th>Осталось</th>
                            <th>Сумма</th>
                            <th>Действует до</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeContracts as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                <td><?= htmlspecialchars($c['contract_number']) ?></td>
                                <td><?= htmlspecialchars($c['contract_type']) ?></td>
                                <td><strong style="color: <?= $c['remaining'] > 0 ? '#2c7a4d' : '#c2410c' ?>;"><?= $c['remaining'] ?></strong></td>
                                <td>
                                    <?= number_format($c['final_price'] ?? $c['price'], 2) ?> ₽
                                    <?php if ($c['discount_percent'] > 0): ?>
                                        <small style="color:#e67e22;">(скидка <?= $c['discount_percent'] ?>%)</small>
                                    <?php endif; ?>
                                 
                                </tr>
                                <td><?= date('d.m.Y', strtotime($c['end_date'])) ?></td>
                                <td><a href="contract.php?id=<?= $c['contract_id'] ?>" class="btn btn-primary btn-sm">📄 Договор</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>📜 История продаж</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Клиент</th>
                            <th>Тип</th>
                            <th>Занятий</th>
                            <th>Сумма</th>
                            <th>Скидка</th>
                            <th>Оплата</th>
                            <th>Продавец</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td><?= date('d.m.Y', strtotime($s['sale_date'])) ?></td>
                                <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                <td><?= htmlspecialchars($s['sale_type']) ?></td>
                                <td><?= $s['sessions_count'] ?></td>
                                <td><strong><?= number_format($s['final_price'] ?? $s['price'], 2) ?> ₽</strong></td>
                                <td><?= $s['discount_percent'] > 0 ? $s['discount_percent'] . '%' : '-' ?></td>
                                <td><?= $s['payment_type'] == 'cash' ? '💰 Наличные' : '💳 Безналичные' ?></td>
                                <td><?= htmlspecialchars($s['seller_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let selectedCard = null;
        let currentPrice = 0;
        
        function selectSale(card) {
            if (selectedCard) {
                selectedCard.classList.remove('selected');
            }
            card.classList.add('selected');
            selectedCard = card;
            
            currentPrice = parseFloat(card.dataset.price);
            
            document.getElementById('subscription_type_id').value = card.dataset.typeId;
            document.getElementById('sessions_count').value = card.dataset.sessions;
            document.getElementById('price').value = currentPrice;
            document.getElementById('validity_days').value = card.dataset.days;
            
            // Сбрасываем скидку при выборе нового абонемента
            document.getElementById('discount').value = 0;
            updateFinalPrice();
            
            document.getElementById('submitBtn').disabled = !document.getElementById('client_id').value;
        }
        
        function updateFinalPrice() {
            let discount = parseFloat(document.getElementById('discount').value) || 0;
            if (discount < 0) discount = 0;
            if (discount > 100) discount = 100;
            
            let finalPrice = currentPrice - (currentPrice * discount / 100);
            document.getElementById('final_price_display').value = finalPrice.toFixed(2) + ' ₽';
        }
        
        document.getElementById('discount').addEventListener('input', function() {
            let val = parseFloat(this.value);
            if (val < 0) this.value = 0;
            if (val > 100) this.value = 100;
            updateFinalPrice();
        });
        
        document.getElementById('client_id').addEventListener('change', function() {
            document.getElementById('submitBtn').disabled = !this.value || !selectedCard;
        });
        
        // Если клиент выбран через GET параметр
        <?php if ($selected_client): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('client_id').value = '<?= $selected_client ?>';
        });
        <?php endif; ?>
    </script>
</body>
</html>