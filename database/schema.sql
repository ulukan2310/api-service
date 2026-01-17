-- Paraşüt API v2 Entegrasyon Projesi - Veritabanı Şeması
-- Tüm tabloların CREATE TABLE scriptleri

-- 1. OAuth Token Cache Tablosu
CREATE TABLE IF NOT EXISTS `parasut_token_cache` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `access_token` TEXT NOT NULL,
  `refresh_token` TEXT DEFAULT NULL,
  `token_type` VARCHAR(50) DEFAULT 'Bearer',
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Contacts (Müşteri/Tedarikçi) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_contacts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `tax_number` VARCHAR(50) DEFAULT NULL,
  `tax_office` VARCHAR(100) DEFAULT NULL,
  `contact_type` ENUM('customer', 'supplier', 'both') DEFAULT 'customer',
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `tr_balance` DECIMAL(15,2) DEFAULT 0.00,
  `us_balance` DECIMAL(15,2) DEFAULT 0.00,
  `eu_balance` DECIMAL(15,2) DEFAULT 0.00,
  `gb_balance` DECIMAL(15,2) DEFAULT 0.00,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `district` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_name` (`name`),
  KEY `idx_email` (`email`),
  KEY `idx_tax_number` (`tax_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Products (Ürün/Hizmet) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_products` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(100) DEFAULT NULL,
  `vat_rate` DECIMAL(5,2) DEFAULT 0.00,
  `sales_excise_duty` DECIMAL(5,2) DEFAULT 0.00,
  `sales_excise_duty_type` VARCHAR(50) DEFAULT NULL,
  `purchase_excise_duty` DECIMAL(5,2) DEFAULT 0.00,
  `purchase_excise_duty_type` VARCHAR(50) DEFAULT NULL,
  `unit` VARCHAR(50) DEFAULT NULL,
  `archived` TINYINT(1) DEFAULT 0,
  `list_price` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'TRY',
  `buying_price` DECIMAL(15,2) DEFAULT 0.00,
  `buying_currency` VARCHAR(3) DEFAULT 'TRY',
  `inventory_tracking` TINYINT(1) DEFAULT 0,
  `initial_stock_count` DECIMAL(15,2) DEFAULT 0.00,
  `category` VARCHAR(255) DEFAULT NULL,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_name` (`name`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Accounts (Kasa/Banka Hesapları) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_accounts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'TRY',
  `account_type` VARCHAR(50) DEFAULT NULL,
  `bank_name` VARCHAR(255) DEFAULT NULL,
  `bank_branch` VARCHAR(255) DEFAULT NULL,
  `account_number` VARCHAR(100) DEFAULT NULL,
  `iban` VARCHAR(34) DEFAULT NULL,
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `archived` TINYINT(1) DEFAULT 0,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_name` (`name`),
  KEY `idx_account_type` (`account_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Sales Invoices (Satış Faturaları) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_sales_invoices` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `contact_id` INT(11) UNSIGNED DEFAULT NULL,
  `invoice_series` VARCHAR(50) DEFAULT NULL,
  `invoice_number` VARCHAR(50) DEFAULT NULL,
  `invoice_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `net_total` DECIMAL(15,2) DEFAULT 0.00,
  `vat_total` DECIMAL(15,2) DEFAULT 0.00,
  `gross_total` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'TRY',
  `exchange_rate` DECIMAL(10,4) DEFAULT 1.0000,
  `withholding_rate` DECIMAL(5,2) DEFAULT 0.00,
  `vat_withholding_rate` DECIMAL(5,2) DEFAULT 0.00,
  `invoice_status` VARCHAR(50) DEFAULT NULL,
  `payment_status` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `item_type` VARCHAR(50) DEFAULT NULL,
  `archived` TINYINT(1) DEFAULT 0,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_invoice_number` (`invoice_number`),
  CONSTRAINT `fk_sales_invoices_contact` FOREIGN KEY (`contact_id`) REFERENCES `parasut_contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Purchase Bills (Alış Faturaları) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_purchase_bills` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `contact_id` INT(11) UNSIGNED DEFAULT NULL,
  `bill_series` VARCHAR(50) DEFAULT NULL,
  `bill_number` VARCHAR(50) DEFAULT NULL,
  `bill_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `net_total` DECIMAL(15,2) DEFAULT 0.00,
  `vat_total` DECIMAL(15,2) DEFAULT 0.00,
  `gross_total` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'TRY',
  `exchange_rate` DECIMAL(10,4) DEFAULT 1.0000,
  `withholding_rate` DECIMAL(5,2) DEFAULT 0.00,
  `vat_withholding_rate` DECIMAL(5,2) DEFAULT 0.00,
  `bill_status` VARCHAR(50) DEFAULT NULL,
  `payment_status` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `item_type` VARCHAR(50) DEFAULT NULL,
  `archived` TINYINT(1) DEFAULT 0,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_bill_date` (`bill_date`),
  KEY `idx_bill_number` (`bill_number`),
  CONSTRAINT `fk_purchase_bills_contact` FOREIGN KEY (`contact_id`) REFERENCES `parasut_contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Payments (Ödemeler) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_payments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `account_id` INT(11) UNSIGNED DEFAULT NULL,
  `contact_id` INT(11) UNSIGNED DEFAULT NULL,
  `payment_date` DATE DEFAULT NULL,
  `amount` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'TRY',
  `exchange_rate` DECIMAL(10,4) DEFAULT 1.0000,
  `payment_type` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `archived` TINYINT(1) DEFAULT 0,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_payments_account` FOREIGN KEY (`account_id`) REFERENCES `parasut_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_contact` FOREIGN KEY (`contact_id`) REFERENCES `parasut_contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tags (Etiketler) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_tags` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parasut_id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `raw_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parasut_id` (`parasut_id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tag Relations (Etiket İlişkileri - Many-to-Many) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_tag_relations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id` INT(11) UNSIGNED NOT NULL,
  `taggable_type` VARCHAR(50) NOT NULL,
  `taggable_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tag_relation` (`tag_id`, `taggable_type`, `taggable_id`),
  KEY `idx_taggable` (`taggable_type`, `taggable_id`),
  CONSTRAINT `fk_tag_relations_tag` FOREIGN KEY (`tag_id`) REFERENCES `parasut_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Sync Log (Senkronizasyon Logları) Tablosu
CREATE TABLE IF NOT EXISTS `parasut_sync_log` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(100) NOT NULL,
  `sync_type` ENUM('full', 'incremental') DEFAULT 'full',
  `status` ENUM('success', 'failed', 'partial') DEFAULT 'success',
  `records_fetched` INT(11) DEFAULT 0,
  `records_saved` INT(11) DEFAULT 0,
  `records_updated` INT(11) DEFAULT 0,
  `records_skipped` INT(11) DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `duration_seconds` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
