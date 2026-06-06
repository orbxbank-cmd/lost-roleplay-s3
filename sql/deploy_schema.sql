-- Deployment schema with shop_ prefix for shared game DB
CREATE TABLE IF NOT EXISTS shop_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(50) DEFAULT 'box',
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  coin_price INT DEFAULT 0,
  badge VARCHAR(50) DEFAULT NULL,
  is_popular TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  image VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  game_uid INT DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  ingame_name VARCHAR(50) DEFAULT NULL,
  discord VARCHAR(100) DEFAULT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  coins INT DEFAULT 0,
  referral_code VARCHAR(50) DEFAULT NULL,
  referred_by INT DEFAULT NULL,
  total_referral_earnings INT DEFAULT 0,
  last_spin DATETIME DEFAULT NULL,
  last_daily_reward DATE DEFAULT NULL,
  is_youtuber TINYINT(1) DEFAULT 0,
  is_admin TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  ingame_name VARCHAR(50) DEFAULT NULL,
  total DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  payment_status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
  order_status ENUM('pending','processing','delivered','cancelled') DEFAULT 'pending',
  transaction_id VARCHAR(100) DEFAULT NULL,
  proof_file VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 1,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_payment_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  instructions TEXT DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_coin_purchases (
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
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_coin_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount INT NOT NULL,
  type ENUM('bonus','purchase','payment','admin','reward','gift_card') NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  ingame_name VARCHAR(50) DEFAULT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 1,
  status ENUM('pending','completed','failed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_gift_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  coins INT NOT NULL,
  created_by INT DEFAULT NULL,
  used_by INT DEFAULT NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES shop_users(id) ON DELETE SET NULL,
  FOREIGN KEY (used_by) REFERENCES shop_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_referral_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  referred_user_id INT DEFAULT NULL,
  referred_username VARCHAR(50) DEFAULT NULL,
  type VARCHAR(50) NOT NULL,
  coins INT DEFAULT 0,
  amount DECIMAL(10,2) DEFAULT NULL,
  order_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referrer_id) REFERENCES shop_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_unban_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  user_id INT DEFAULT NULL,
  ban_reason VARCHAR(255) DEFAULT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  admin_note TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_staff_applications (
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

CREATE TABLE IF NOT EXISTS shop_bundles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_daily_missions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(255) NOT NULL,
  target INT NOT NULL,
  reward INT NOT NULL DEFAULT 10,
  active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT IGNORE INTO shop_daily_missions (type, label, target, reward) VALUES
('wheel_spins', 'Spin the Lucky Wheel', 1, 10),
('dn_plays', 'Play Guess the Number', 3, 15),
('dice_plays', 'Play Dice Duel', 3, 15),
('mystery_boxes', 'Open Mystery Boxes', 1, 20),
('coins_won', 'Win coins from games', 100, 25);

CREATE TABLE IF NOT EXISTS shop_user_daily (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  wheel_spins INT DEFAULT 0,
  dn_plays INT DEFAULT 0,
  dice_plays INT DEFAULT 0,
  mystery_boxes INT DEFAULT 0,
  coins_won INT DEFAULT 0,
  wheel_spins_claimed TINYINT DEFAULT 0,
  dn_plays_claimed TINYINT DEFAULT 0,
  dice_plays_claimed TINYINT DEFAULT 0,
  mystery_boxes_claimed TINYINT DEFAULT 0,
  coins_won_claimed TINYINT DEFAULT 0,
  UNIQUE KEY unique_daily (user_id, date),
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_bundle_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bundle_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT DEFAULT 1,
  FOREIGN KEY (bundle_id) REFERENCES shop_bundles(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_login_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  action ENUM('login','logout','password_change') DEFAULT 'login',
  success TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_vip_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  level INT NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  duration_days INT NOT NULL,
  daily_coins INT NOT NULL,
  price_coins INT NOT NULL,
  description TEXT,
  color VARCHAR(20) DEFAULT '#ffc107',
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT IGNORE INTO shop_vip_plans (level, name, duration_days, daily_coins, price_coins, description, color) VALUES
(1, 'VIP Bronze', 7, 50, 50, '50 coins daily • 7 days • Bronze role', '#cd7f32'),
(2, 'VIP Silver', 30, 100, 250, '100 coins daily • 30 days • Silver role • Priority support', '#c0c0c0'),
(3, 'VIP Gold', 90, 150, 500, '150 coins daily • 90 days • Gold role • Priority support • Exclusive items', '#ffd700');

CREATE TABLE IF NOT EXISTS shop_user_vip (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  end_date TIMESTAMP NOT NULL,
  last_daily DATE DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES shop_vip_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_recovery_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  ingame_name VARCHAR(50) NOT NULL,
  contact_info VARCHAR(255) DEFAULT NULL,
  reason TEXT NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  admin_note TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES shop_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default data
INSERT IGNORE INTO shop_categories (name, slug, icon, sort_order) VALUES
('Admin', 'admin', 'shield', 1),
('Cars', 'cars', 'car', 2),
('House & Job', 'house-job', 'home', 3),
('Money', 'money', 'wallet', 4),
('Boosts', 'boosts', 'trending-up', 6),
('Promos', 'promos', 'gift', 7);

INSERT IGNORE INTO shop_products (category_id, name, description, price, coin_price, badge, is_popular, image) VALUES
(1, 'Admin Level 1', 'Upgrade your admin level to 1', 30.00, 30, NULL, 0, 'admin.jfif'),
(1, 'Admin Level 10', 'Upgrade your admin level to 10', 120.00, 120, 'Best Seller', 1, 'admin.jfif'),
(1, 'Admin Level 30 (MAX)', 'Upgrade to max admin level 30', 200.00, 200, 'MAX', 0, 'admin.jfif'),
(2, '2 Cars', '2 exclusive cars from server shop', 15.00, 15, NULL, 0, 'carss.jfif'),
(2, '4 Cars', '4 exclusive cars from server shop', 25.00, 25, NULL, 0, 'carss.jfif'),
(2, '8 Cars', '8 exclusive cars from server shop', 40.00, 40, 'Popular', 1, 'carss.jfif'),
(2, '16 Cars', '16 exclusive cars from server shop', 70.00, 70, NULL, 0, 'carss.jfif'),
(3, 'House', 'Get a house in the server', 10.00, 10, NULL, 0, 'house.jfif'),
(3, 'Job', 'Get a job in the server', 10.00, 10, NULL, 0, 'house.jfif'),
(3, 'House + Job', 'House and job bundle deal', 15.00, 15, 'Deal', 1, 'house.jfif'),
(4, '$100 Million', '$100 million in server wallet', 10.00, 10, NULL, 0, 'money.jfif'),
(4, '$250 Million', '$250 million in server wallet', 20.00, 20, NULL, 0, 'money.jfif'),
(4, '$500 Million', '$500 million in server wallet', 35.00, 35, 'Best Value', 1, 'money.jfif'),
(4, '$1 Billion', '$1 billion in server wallet', 60.00, 60, NULL, 0, 'money.jfif'),
(5, '+5 Levels', 'Boost your level by 5', 10.00, 10, NULL, 0, 'boosts.jfif'),
(5, '+10 Levels', 'Boost your level by 10', 15.00, 15, 'Popular', 1, 'boosts.jfif'),
(5, '+20 Levels', 'Boost your level by 20', 25.00, 25, NULL, 0, 'boosts.jfif'),
(6, '4 Sultan + 2 NRG + 2 Elegy', 'Exclusive bundle: 4 Sultan + 2 NRG + 2 Elegy', 40.00, 40, NULL, 0, 'promos.jfif'),
(6, '$100M + 2 Sultan', 'Money + cars bundle: $100M + 2 Sultan', 30.00, 30, 'Deal', 1, 'promos.jfif'),
(6, 'R5 + $250M + Sultan', 'Premium: Rank R5 + $250M + Sultan', 60.00, 60, NULL, 0, 'promos.jfif'),
(1, 'Unban Account', 'Unban your account', 10.00, 10, NULL, 0, 'admin.jfif'),
(1, 'Name Change', 'Change your in-game name', 10.00, 10, NULL, 0, 'admin.jfif'),
(1, 'Password Reset', 'Reset your account password', 10.00, 10, NULL, 0, 'admin.jfif');

INSERT IGNORE INTO shop_payment_methods (name, code, instructions, sort_order) VALUES
('Inwi', 'inwi', 'حول المبلغ إلى رقم Inwi: 0780589707 وأرسل صورة الإيداع', 1),
('Cash Plus', 'cashplus', 'أودع المبلغ في أي وكالة Cash Plus وأرسل الوصل', 2),
('Wafacash', 'wafacash', 'أودع المبلغ في أي وكالة Wafacash وأرسل الوصل', 3),
('CIH Bank', 'cih', 'حول المبلغ إلى حساب CIH Bank وأرسل الإشعار', 4);

INSERT IGNORE INTO shop_users (username, password, is_admin) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

INSERT IGNORE INTO shop_bundles (name, total_price) VALUES ('Admin + Cars Pack', 35.00);
INSERT IGNORE INTO shop_bundle_products (bundle_id, product_id, quantity) VALUES (1, 1, 1), (1, 4, 1);
