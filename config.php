<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'flexwellness_crm');
define('DB_USER', 'root');
define('DB_PASS', ''); // Для MAMP на Mac: 'root'

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function updateLastLogin($id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$id]);
}

// ========== КЛИЕНТЫ ==========
function getAllClients() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY registration_date DESC");
    return $stmt->fetchAll();
}

function getClientById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addClient($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO clients (last_name, first_name, middle_name, phone, email, birth_date, address) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            trim($data['last_name']),
            trim($data['first_name']),
            !empty($data['middle_name']) ? trim($data['middle_name']) : null,
            trim($data['phone']),
            !empty($data['email']) ? trim($data['email']) : '',
            !empty($data['birth_date']) ? $data['birth_date'] : null,
            !empty($data['address']) ? trim($data['address']) : null
        ]);
    } catch (PDOException $e) {
        error_log("Ошибка добавления клиента: " . $e->getMessage());
        return false;
    }
}

// ========== ДОГОВОРЫ ==========
function getClientContracts($client_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM contracts WHERE client_id = ? ORDER BY start_date DESC");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll();
}

function getContractById($contract_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ct.*, c.first_name, c.last_name, c.phone, c.email, c.address
        FROM contracts ct 
        JOIN clients c ON ct.client_id = c.client_id 
        WHERE ct.contract_id = ?
    ");
    $stmt->execute([$contract_id]);
    return $stmt->fetch();
}

function getActiveContractByClient($client_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM contracts WHERE client_id = ? AND status = 'active' AND sessions_used < sessions_count AND end_date >= CURDATE() ORDER BY end_date ASC LIMIT 1");
    $stmt->execute([$client_id]);
    return $stmt->fetch();
}

function createContract($data) {
    global $pdo;
    $number = 'CTR-' . date('Ymd') . '-' . rand(1000, 9999);
    $end_date = date('Y-m-d', strtotime('+' . $data['validity_days'] . ' days'));
    
    $stmt = $pdo->prepare("INSERT INTO contracts (contract_number, client_id, contract_type, sessions_count, price, start_date, end_date, status, payment_type, sold_by, sold_at) 
                           VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'active', ?, ?, NOW())");
    return $stmt->execute([
        $number, $data['client_id'], $data['contract_type'],
        $data['sessions_count'], $data['price'], $end_date,
        $data['payment_type'], $_SESSION['user_id']
    ]);
}

// ========== ЗАПИСИ ==========
function getAllTrainers() {
    global $pdo;
    $stmt = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'instructor' AND is_active = 1 ORDER BY full_name");
    return $stmt->fetchAll();
}

function getBookingsByDate($date) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT b.*, c.first_name, c.last_name, c.phone, u.full_name as trainer_name,
               ct.contract_number, ct.sessions_count, ct.sessions_used,
               CONCAT(c.last_name, ' ', c.first_name) as client_name
        FROM bookings b 
        JOIN clients c ON b.client_id = c.client_id 
        LEFT JOIN users u ON b.trainer_id = u.user_id
        LEFT JOIN contracts ct ON b.contract_id = ct.contract_id
        WHERE b.booking_date = ?
        ORDER BY b.booking_time
    ");
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}

function addBooking($data) {
    global $pdo;
    
    $is_subscription = !empty($data['contract_id']) ? 1 : 0;
    $payment_amount = $is_subscription ? 0 : ($data['payment_amount'] ?? 0);
    
    $stmt = $pdo->prepare("
        INSERT INTO bookings (client_id, trainer_id, contract_id, booking_date, booking_time, status, payment_type, payment_amount, is_subscription, notes, created_by) 
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([
        $data['client_id'], $data['trainer_id'], $data['contract_id'] ?: null,
        $data['booking_date'], $data['booking_time'], $data['payment_type'],
        $payment_amount, $is_subscription, $data['notes'] ?? '', $_SESSION['user_id']
    ]);
}

function updateBookingStatus($booking_id, $status) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    return $stmt->execute([$status, $booking_id]);
}

function deleteBooking($booking_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE booking_id = ?");
    return $stmt->execute([$booking_id]);
}

// Ручное списание тренировки
function markAttendance($booking_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) return false;
    if ($booking['status'] == 'completed') return false;
    
    if ($booking['is_subscription'] && $booking['contract_id']) {
        $pdo->prepare("UPDATE contracts SET sessions_used = sessions_used + 1 WHERE contract_id = ? AND sessions_used < sessions_count")->execute([$booking['contract_id']]);
    }
    
    $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE booking_id = ?")->execute([$booking_id]);
    return true;
}

// ========== СТАТИСТИКА ==========
function getTodayStats() {
    global $pdo;
    $today = date('Y-m-d');
    
    $cash = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE booking_date = ? AND payment_type = 'cash' AND status = 'completed'");
    $cash->execute([$today]);
    $cashAm = $cash->fetchColumn();
    
    $card = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE booking_date = ? AND payment_type = 'card' AND status = 'completed'");
    $card->execute([$today]);
    $cardAm = $card->fetchColumn();
    
    $count = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND status = 'completed'");
    $count->execute([$today]);
    $sessionCount = $count->fetchColumn();
    
    return [
        'cash' => $cashAm,
        'card' => $cardAm,
        'total' => $cashAm + $cardAm,
        'sessions_count' => $sessionCount
    ];
}

function getMonthlyStats($year, $month) {
    global $pdo;
    
    $cash = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE YEAR(booking_date) = ? AND MONTH(booking_date) = ? AND payment_type = 'cash' AND status = 'completed'");
    $cash->execute([$year, $month]);
    $cashAm = $cash->fetchColumn();
    
    $card = $pdo->prepare("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE YEAR(booking_date) = ? AND MONTH(booking_date) = ? AND payment_type = 'card' AND status = 'completed'");
    $card->execute([$year, $month]);
    $cardAm = $card->fetchColumn();
    
    return [
        'cash' => $cashAm,
        'card' => $cardAm,
        'total' => $cashAm + $cardAm
    ];
}

function getDailyStats($year, $month) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT booking_date, COUNT(*) as cnt, COALESCE(SUM(payment_amount),0) as day_total 
        FROM bookings 
        WHERE YEAR(booking_date) = ? AND MONTH(booking_date) = ? AND status = 'completed'
        GROUP BY booking_date
        ORDER BY booking_date
    ");
    $stmt->execute([$year, $month]);
    return $stmt->fetchAll();
}

function getInactiveClients($days = 30) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.*, MAX(b.booking_date) as last_visit 
        FROM clients c 
        LEFT JOIN bookings b ON c.client_id = b.client_id 
        GROUP BY c.client_id 
        HAVING last_visit IS NULL OR last_visit < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY last_visit ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

// ========== УВЕДОМЛЕНИЯ ==========
function sendNotification($client_id, $message, $type = 'manual') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (client_id, type, message, status) VALUES (?, ?, ?, 'sent')");
    return $stmt->execute([$client_id, $type, $message]);
}

function sendAutoReminders() {
    global $pdo;
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $stmt = $pdo->prepare("
        SELECT b.*, c.first_name, c.last_name 
        FROM bookings b 
        JOIN clients c ON b.client_id = c.client_id 
        WHERE b.booking_date = ? AND b.status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$tomorrow]);
    $bookings = $stmt->fetchAll();
    
    $count = 0;
    foreach ($bookings as $b) {
        $message = "Уважаемый(ая) {$b['first_name']} {$b['last_name']}! Напоминаем, что завтра в {$b['booking_time']} у вас занятие.";
        sendNotification($b['client_id'], $message, 'auto');
        $count++;
    }
    return $count;
}

// ========== ПОЛЬЗОВАТЕЛИ ==========
function getAllUsers() {
    global $pdo;
    $stmt = $pdo->query("SELECT user_id, username, full_name, email, phone, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getUserByUsername($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function createUser($data) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $data['username'], $data['password'], $data['full_name'],
        $data['email'], $data['phone'], $data['role'], $data['is_active'] ?? 1
    ]);
}

function deleteUser($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND username != 'admin'");
    return $stmt->execute([$id]);
}

// ========== ТИПЫ АБОНЕМЕНТОВ ==========
function getSubscriptionTypes() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM subscription_types WHERE is_active = 1 ORDER BY sessions_count");
    return $stmt->fetchAll();
}

function getSubscriptionTypeById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM subscription_types WHERE type_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
?>