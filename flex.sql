-- ============================================
-- CRM ДЛЯ СТУДИИ ПИЛАТЕСА FLEXWELLNESS (Владивосток)
-- ============================================

CREATE DATABASE IF NOT EXISTS flexwellness_crm;
USE flexwellness_crm;

-- Клиенты
CREATE TABLE IF NOT EXISTS clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    birth_date DATE,
    address TEXT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Договоры (абонементы / разовые)
CREATE TABLE IF NOT EXISTS contracts (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(50) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    contract_type ENUM('subscription', 'single', 'pack') DEFAULT 'subscription',
    sessions_count INT NOT NULL COMMENT 'количество занятий по договору',
    sessions_used INT DEFAULT 0 COMMENT 'использовано занятий',
    price DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
);

-- Записи на занятия
CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    contract_id INT, -- может быть NULL, если клиент без договора
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    duration INT DEFAULT 60 COMMENT 'минуты',
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE SET NULL
);

-- Рассылки / уведомления (лог)
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    type ENUM('reminder', 'promo', 'custom') DEFAULT 'reminder',
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE SET NULL
);

-- Пользователи (администраторы / инструкторы)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150),
    role ENUM('admin', 'manager', 'instructor') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Вставка тестового администратора (пароль: admin123)
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', 'admin');

-- Индексы для производительности
CREATE INDEX idx_bookings_date ON bookings(booking_date);
CREATE INDEX idx_clients_phone ON clients(phone);
CREATE INDEX idx_contracts_client ON contracts(client_id);