<?php
require_once 'config.php';
requireAuth();

$contract_id = $_GET['id'] ?? 0;
$contract = getContractById($contract_id);

if (!$contract) {
    header('Location: sales.php?error=Договор не найден');
    exit;
}

$remaining = $contract['sessions_count'] - $contract['sessions_used'];
$status_text = $contract['status'] == 'active' ? 'Действует' : 'Завершён';
$status_color = $contract['status'] == 'active' ? '#2c7a4d' : '#c2410c';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Договор №<?= htmlspecialchars($contract['contract_number']) ?> - FlexWellness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; padding: 40px; }
        .contract-container { max-width: 900px; margin: 0 auto; }
        .contract-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .contract-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #1e3a5f; }
        .contract-header h1 { color: #1e3a5f; font-size: 28px; }
        .contract-number { background: #1e3a5f; color: white; padding: 8px 16px; border-radius: 30px; display: inline-block; margin-top: 15px; }
        .info-row { display: flex; margin-bottom: 15px; padding: 10px; background: #f8fafc; border-radius: 10px; }
        .info-label { width: 180px; font-weight: 600; color: #1e3a5f; }
        .info-value { flex: 1; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?= $status_color ?>20; color: <?= $status_color ?>; }
        .progress-bar { height: 20px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: #2c7a4d; width: <?= ($contract['sessions_used'] / $contract['sessions_count']) * 100 ?>%; }
        .print-btn { background: #1e3a5f; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; margin-top: 20px; font-size: 16px; width: 100%; }
        .print-btn:hover { background: #2c4e7a; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #1e3a5f; text-decoration: none; }
        @media print {
            body { background: white; padding: 0; }
            .print-btn, .back-link { display: none; }
            .contract-card { box-shadow: none; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="contract-container">
        <div class="contract-card">
            <div class="contract-header">
                <h1>🧘 FlexWellness</h1>
                <p>Студия пилатеса во Владивостоке</p>
                <div class="contract-number">Договор №<?= htmlspecialchars($contract['contract_number']) ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Клиент:</div>
                <div class="info-value"><strong><?= htmlspecialchars($contract['last_name'] . ' ' . $contract['first_name']) ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Телефон:</div>
                <div class="info-value"><?= htmlspecialchars($contract['phone']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?= htmlspecialchars($contract['email']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Тип договора:</div>
                <div class="info-value">
                    <?= $contract['contract_type'] == 'subscription' ? 'Абонемент' : 'Пакет занятий' ?>
                    <span class="status-badge"><?= $status_text ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Количество занятий:</div>
                <div class="info-value"><?= $contract['sessions_count'] ?> занятий</div>
            </div>
            <div class="info-row">
                <div class="info-label">Стоимость:</div>
                <div class="info-value"><strong><?= number_format($contract['price'], 2) ?> ₽</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Дата начала:</div>
                <div class="info-value"><?= date('d.m.Y', strtotime($contract['start_date'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Дата окончания:</div>
                <div class="info-value"><?= date('d.m.Y', strtotime($contract['end_date'])) ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Использовано занятий:</div>
                <div class="info-value"><?= $contract['sessions_used'] ?> из <?= $contract['sessions_count'] ?></div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div style="text-align: right; font-size: 14px; color: #2c7a4d;">Осталось: <?= $remaining ?> занятий</div>
            
            <div class="info-row">
                <div class="info-label">Способ оплаты:</div>
                <div class="info-value"><?= $contract['payment_type'] == 'cash' ? '💰 Наличные' : '💳 Безналичный расчёт' ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Дата оформления:</div>
                <div class="info-value"><?= date('d.m.Y H:i', strtotime($contract['sold_at'] ?? $contract['created_at'])) ?></div>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                <h3>📋 Условия договора</h3>
                <p style="margin-top: 10px;"><strong>Отмена занятия:</strong> Об отмене необходимо предупредить не менее чем за 24 часа. В противном случае занятие считается проведенным.</p>
                <p style="margin-top: 10px;"><strong>Срок действия:</strong> Неиспользованные занятия по истечении срока действия договора сгорают.</p>
                <p style="margin-top: 10px;"><strong>Продление:</strong> Абонемент можно продлить только путем покупки нового.</p>
            </div>
            
            <button class="print-btn" onclick="window.print()">🖨️ Распечатать договор</button>
            <a href="sales.php" class="back-link">← Вернуться к продажам</a>
        </div>
    </div>
</body>
</html>