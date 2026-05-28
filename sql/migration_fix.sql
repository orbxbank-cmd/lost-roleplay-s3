-- SQL Migration: Fix missing tables and columns
-- Run this in phpMyAdmin or MySQL CLI

-- Missing tables
CREATE TABLE IF NOT EXISTS gift_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  coins INT NOT NULL,
  created_by INT DEFAULT NULL,
  used_by INT DEFAULT NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unban_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  user_id INT DEFAULT NULL,
  ban_reason VARCHAR(255) DEFAULT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  admin_note TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coin_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount INT NOT NULL,
  type ENUM('bonus','purchase','payment','admin','reward','gift_card') NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coin_purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  coins INT NOT NULL,
  amount_mad DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  proof_file VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
  admin_note TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  ingame_name VARCHAR(50) DEFAULT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 1,
  status ENUM('pending','completed','failed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ingame_name VARCHAR(100) NOT NULL,
  age INT NOT NULL,
  country VARCHAR(100) DEFAULT NULL,
  play_hours INT DEFAULT NULL,
  experience TEXT,
  why_staff TEXT NOT NULL,
  strengths TEXT,
  weaknesses TEXT,
  discord VARCHAR(100) DEFAULT NULL,
  whatsapp VARCHAR(100) DEFAULT NULL,
  status ENUM('pending','accepted','rejected') DEFAULT 'pending',
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bundles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bundle_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bundle_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT DEFAULT 1,
  FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Missing columns in users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS game_uid INT DEFAULT NULL AFTER username;
ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(50) DEFAULT NULL AFTER coins;
ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by INT DEFAULT NULL AFTER referral_code;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_referral_earnings INT DEFAULT 0 AFTER referred_by;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_spin DATETIME DEFAULT NULL AFTER total_referral_earnings;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_daily_reward DATE DEFAULT NULL AFTER last_spin;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_youtuber TINYINT(1) DEFAULT 0 AFTER last_daily_reward;
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL AFTER discord;

-- Missing columns in orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS proof_file VARCHAR(255) DEFAULT NULL AFTER transaction_id;
