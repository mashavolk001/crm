<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Проверка существования клиента по телефону
if ($action == 'check_phone' && isset($_GET['phone'])) {
    $stmt = $pdo->prepare("SELECT client_id, last_name, first_name FROM clients WHERE phone = ?");
    $stmt->execute([trim($_GET['phone'])]);
    $client = $stmt->fetch();
    
    if ($client) {
        echo json_encode([
            'exists' => true,
            'client_name' => $client['last_name'] . ' ' . $client['first_name'],
            'client_id' => $client['client_id']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}

// Получение активных договоров клиента
if ($action == 'get_contracts' && isset($_GET['client_id'])) {
    $stmt = $pdo->prepare("
        SELECT contract_id, contract_number, contract_type, sessions_count, sessions_used, 
               (sessions_count - sessions_used) as sessions_left, end_date
        FROM contracts 
        WHERE client_id = ? AND status = 'active' AND sessions_used < sessions_count AND end_date >= CURDATE()
        ORDER BY end_date ASC
    ");
    $stmt->execute([$_GET['client_id']]);
    $contracts = $stmt->fetchAll();
    
    echo json_encode($contracts);
    exit;
}

// Получение информации о записи
if ($action == 'get_booking' && isset($_GET['booking_id'])) {
    $stmt = $pdo->prepare("
        SELECT b.*, c.first_name, c.last_name, c.phone, u.full_name as trainer_name,
               ct.contract_number, ct.sessions_count, ct.sessions_used
        FROM bookings b
        JOIN clients c ON b.client_id = c.client_id
        LEFT JOIN users u ON b.trainer_id = u.user_id
        LEFT JOIN contracts ct ON b.contract_id = ct.contract_id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$_GET['booking_id']]);
    echo json_encode($stmt->fetch());
    exit;
}

// Получение смены тренера
if ($action == 'get_shift' && isset($_GET['trainer_id']) && isset($_GET['date'])) {
    $stmt = $pdo->prepare("SELECT * FROM trainer_shifts WHERE trainer_id = ? AND shift_date = ?");
    $stmt->execute([$_GET['trainer_id'], $_GET['date']]);
    $shift = $stmt->fetch();
    echo json_encode($shift ?: ['start_time' => '09:00', 'end_time' => '20:00', 'is_working' => 1, 'notes' => '']);
    exit;
}

// Если action не распознан
echo json_encode(['error' => 'Unknown action']);
exit;