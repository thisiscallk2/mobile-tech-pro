-- =====================================================================
--  MobileTech Pro  --  Database schema
--  Web-based integrated management system for mobile phone retailers
--  (POS + Inventory + Repair tracking + CRM)
--
--  Engine : InnoDB      Charset : utf8mb4 / utf8mb4_unicode_ci
--  DBMS   : MySQL 5.7+ / 8.0
--
--  NOTE ON LOW-STOCK NAMING:
--  The requested "low_stock_threshold" is served by inventory.reorder_level.
--  A product is "low stock" when inventory.quantity <= inventory.reorder_level.
--  We keep the name reorder_level (industry-standard term) and document it here
--  rather than adding a duplicate column with the same meaning.
-- =====================================================================

DROP DATABASE IF EXISTS mobiletech_pro;
CREATE DATABASE mobiletech_pro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE mobiletech_pro;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  users  --  staff accounts (admin / sales_staff / technician)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(120) NOT NULL,
    phone         VARCHAR(30)      NULL,
    user_type     ENUM('admin','sales_staff','technician') NOT NULL DEFAULT 'sales_staff',
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME         NULL,
    permissions   VARCHAR(255)     NULL,   -- comma-separated module keys: sales,inventory,repairs,reports
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  password_resets  --  self-service password reset tokens
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(150) NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token),
    KEY idx_password_resets_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  categories  --  product categories (phones, accessories, parts...)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  brands  --  device / product manufacturers
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS brands;
CREATE TABLE brands (
    brand_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (brand_id),
    UNIQUE KEY uq_brands_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  suppliers  --  where stock is purchased from
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS suppliers;
CREATE TABLE suppliers (
    supplier_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120) NOT NULL,
    contact_person VARCHAR(120)     NULL,
    phone          VARCHAR(30)      NULL,
    email          VARCHAR(150)     NULL,
    address        VARCHAR(255)     NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (supplier_id),
    KEY idx_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  products  --  catalogue items (sellable goods)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS products;
CREATE TABLE products (
    product_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku           VARCHAR(60)  NOT NULL,
    name          VARCHAR(160) NOT NULL,
    description   TEXT             NULL,
    category_id   INT UNSIGNED     NULL,
    brand_id      INT UNSIGNED     NULL,
    supplier_id   INT UNSIGNED     NULL,
    cost_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path    VARCHAR(255)     NULL,           -- product photo upload
    active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_brand (brand_id),
    KEY idx_products_supplier (supplier_id),
    KEY idx_products_name (name),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (category_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_brand FOREIGN KEY (brand_id)
        REFERENCES brands (brand_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id)
        REFERENCES suppliers (supplier_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  inventory  --  stock levels per product
--  reorder_level == low-stock threshold (see header note)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS inventory;
CREATE TABLE inventory (
    inventory_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id    INT UNSIGNED NOT NULL,
    quantity      INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,          -- low_stock_threshold
    location      VARCHAR(80)      NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (inventory_id),
    UNIQUE KEY uq_inventory_product (product_id),
    KEY idx_inventory_reorder (quantity, reorder_level),
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  customers  --  CRM records
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
    customer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name   VARCHAR(120) NOT NULL,
    email       VARCHAR(150)     NULL,
    phone       VARCHAR(30)      NULL,
    address     VARCHAR(255)     NULL,
    notes       TEXT             NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    KEY idx_customers_phone (phone),
    KEY idx_customers_email (email),
    KEY idx_customers_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  coupons  --  discount codes applied at POS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
    coupon_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(40)  NOT NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valid_from    DATE             NULL,
    valid_to      DATE             NULL,
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (coupon_id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_active (active, valid_from, valid_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  sales  --  POS transactions (invoice header)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS sales;
CREATE TABLE sales (
    sale_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_no     VARCHAR(40)  NOT NULL,
    customer_id    INT UNSIGNED     NULL,
    user_id        INT UNSIGNED     NULL,          -- staff who processed the sale
    coupon_id      INT UNSIGNED     NULL,
    subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','card','mobile','other') NOT NULL DEFAULT 'cash',
    status         ENUM('completed','refunded','void') NOT NULL DEFAULT 'completed',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sale_id),
    UNIQUE KEY uq_sales_invoice (invoice_no),
    KEY idx_sales_customer (customer_id),
    KEY idx_sales_user (user_id),
    KEY idx_sales_created (created_at),
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_coupon FOREIGN KEY (coupon_id)
        REFERENCES coupons (coupon_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  sale_items  --  invoice line items
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS sale_items;
CREATE TABLE sale_items (
    sale_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id      INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED     NULL,
    quantity     INT NOT NULL DEFAULT 1,
    unit_price   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (sale_item_id),
    KEY idx_sale_items_sale (sale_id),
    KEY idx_sale_items_product (product_id),
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id)
        REFERENCES sales (sale_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  payments  --  payment details per POS transaction
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    payment_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id          INT UNSIGNED NOT NULL,
    payment_method   ENUM('cash','card','bank_transfer','mobile') NOT NULL DEFAULT 'cash',
    amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_tendered  DECIMAL(10,2) NULL,
    change_due       DECIMAL(10,2) NULL,
    reference        VARCHAR(100) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY idx_payments_sale (sale_id),
    CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id)
        REFERENCES sales (sale_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  receipts  --  printed receipts per sale
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS receipts;
CREATE TABLE receipts (
    receipt_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id          INT UNSIGNED NOT NULL,
    receipt_number   VARCHAR(40) NOT NULL,
    printed_at       DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt_id),
    UNIQUE KEY uq_receipts_number (receipt_number),
    KEY idx_receipts_sale (sale_id),
    CONSTRAINT fk_receipts_sale FOREIGN KEY (sale_id)
        REFERENCES sales (sale_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  repair_jobs  --  repair tracking tickets
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS repair_jobs;
CREATE TABLE repair_jobs (
    repair_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_no      VARCHAR(40)  NOT NULL,
    customer_id    INT UNSIGNED     NULL,
    technician_id  INT UNSIGNED     NULL,          -- assigned user (technician)
    device_brand   VARCHAR(80)      NULL,
    device_model   VARCHAR(120)     NULL,
    imei           VARCHAR(40)      NULL,
    urgency        ENUM('low','normal','urgent') NOT NULL DEFAULT 'normal',
    reported_issue TEXT             NULL,
    diagnosis      TEXT             NULL,
    status         ENUM('received','diagnosing','in_progress','completed','delivered','cancelled')
                        NOT NULL DEFAULT 'received',
    estimated_cost DECIMAL(10,2)    NULL,
    final_cost     DECIMAL(10,2)    NULL,
    due_date       DATETIME         NULL,
    received_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at   TIMESTAMP        NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (repair_id),
    UNIQUE KEY uq_repair_ticket (ticket_no),
    KEY idx_repair_customer (customer_id),
    KEY idx_repair_technician (technician_id),
    KEY idx_repair_status (status),
    KEY idx_repair_urgency (urgency),
    CONSTRAINT fk_repair_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_repair_technician FOREIGN KEY (technician_id)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  repair_updates  --  timeline notes / status changes per ticket
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS repair_updates;
CREATE TABLE repair_updates (
    update_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    repair_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED     NULL,
    status      ENUM('received','diagnosing','in_progress','completed','delivered','cancelled') NULL,
    note        TEXT             NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (update_id),
    KEY idx_repair_updates_repair (repair_id),
    CONSTRAINT fk_repair_updates_repair FOREIGN KEY (repair_id)
        REFERENCES repair_jobs (repair_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_repair_updates_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  notifications  --  customer messages (SMS / email)
--  Used later for the "notify customer" action on completed repairs.
--  Either customer_id or repair_id (or both) may be set.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     INT UNSIGNED     NULL,
    repair_id       INT UNSIGNED     NULL,
    message         TEXT         NOT NULL,
    channel         ENUM('sms','email') NOT NULL DEFAULT 'sms',
    status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    sent_at         TIMESTAMP        NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notifications_customer (customer_id),
    KEY idx_notifications_repair (repair_id),
    KEY idx_notifications_status (status),
    CONSTRAINT fk_notifications_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_repair FOREIGN KEY (repair_id)
        REFERENCES repair_jobs (repair_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  TRIGGERS
-- =====================================================================

-- NOTE: product + inventory rows are created together in an explicit
-- application-level transaction (see App\Models\Product::create), so no
-- auto-insert trigger is used here.

-- Keep sale_items.line_total consistent (quantity * unit_price) on insert.
DROP TRIGGER IF EXISTS trg_sale_items_before_insert;
DELIMITER $$
CREATE TRIGGER trg_sale_items_before_insert
BEFORE INSERT ON sale_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$
DELIMITER ;

-- Keep sale_items.line_total consistent on update.
DROP TRIGGER IF EXISTS trg_sale_items_before_update;
DELIMITER $$
CREATE TRIGGER trg_sale_items_before_update
BEFORE UPDATE ON sale_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$
DELIMITER ;

-- =====================================================================
--  SEED DATA
--  Passwords (bcrypt via password_hash, PASSWORD_DEFAULT):
--    admin      / admin123
--    sales      / sales123
--    tech       / tech123
-- =====================================================================

INSERT INTO users (username, email, password_hash, full_name, phone, user_type, permissions) VALUES
('admin', 'admin@mobiletechpro.test', '$2y$10$YJ4XsWl2GATw.sWj.7p.9OshweVV1Ri.el4ZANUMptIYunw6a4WfC', 'System Administrator', '0100000001', 'admin', 'sales,inventory,repairs,reports'),
('sales', 'sales@mobiletechpro.test', '$2y$10$scmAb2/A2z66oGzKwQ2Xbeljw.8mCfMwYMIdxT.g96pxZu9j3zeza', 'Sales Staff',          '0100000002', 'sales_staff', 'sales,inventory'),
('tech',  'tech@mobiletechpro.test',  '$2y$10$i54t/.WQfKTH.yCoH.zC3urZJqL8nhxUuJlX3wzpKmcmvOA8EAfYC', 'Repair Technician',    '0100000003', 'technician', 'repairs');
 
INSERT INTO categories (name) VALUES
('Smartphones'), ('Accessories'), ('Spare Parts'), ('Tablets'), ('Wearables');

INSERT INTO brands (name) VALUES
('Apple'), ('Samsung'), ('Xiaomi'), ('Huawei'), ('Oppo'), ('Generic');

INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('TechDistro Ltd', 'Kyaw Kyaw', '0111222333', 'sales@techdistro.test', 'No. 12, Yangon'),
('MobileParts Co', 'Su Su', '0144556677', 'orders@mobileparts.test', 'No. 88, Mandalay'),
('AccessoryHub', 'John Lin', '0199887766', 'hello@accessoryhub.test', 'No. 5, Naypyidaw');

-- Sample products; inventory rows are inserted explicitly below.
INSERT INTO products (sku, name, description, category_id, brand_id, supplier_id, cost_price, selling_price) VALUES
('IPH-13-128', 'iPhone 13 128GB', 'Apple iPhone 13, 128GB', 1, 1, 1, 550.00, 699.00),
('SAM-A54-256', 'Samsung Galaxy A54 256GB', 'Samsung Galaxy A54, 256GB', 1, 2, 1, 300.00, 399.00),
('ACC-USBC-2M', 'USB-C Cable 2m', 'Braided USB-C charging cable', 2, 6, 3, 2.00, 7.50),
('PRT-IP13-SCR', 'iPhone 13 Screen', 'OEM replacement screen for iPhone 13', 3, 1, 2, 60.00, 120.00);

INSERT INTO inventory (product_id, quantity, reorder_level, location) VALUES
(1, 12, 3,  'Shelf A1'),
(2, 20, 5,  'Shelf A2'),
(3, 3,  10, 'Drawer B1'),   -- low stock example
(4, 8,  4,  'Drawer C3');

INSERT INTO coupons (code, discount_type, value, valid_from, valid_to, active) VALUES
('WELCOME10', 'percent', 10.00, '2026-01-01', '2026-12-31', 1),
('FLAT20',    'fixed',   20.00, '2026-01-01', '2026-12-31', 1);

INSERT INTO customers (full_name, email, phone, address) VALUES
('Walk-in Customer', NULL, NULL, NULL),
('Aung Aung', 'aung@example.test', '0912345678', 'Yangon');

-- Sample repair jobs (technician_id = 3 = tech / tech123)
INSERT INTO repair_jobs (ticket_no, customer_id, technician_id, device_brand, device_model, imei, urgency, reported_issue, diagnosis, status, estimated_cost, due_date) VALUES
('JOB-2404', 2, 3, 'Samsung', 'Galaxy A54', '350123456789012', 'urgent', 'Screen cracked, touch not working', 'LCD panel needs replacement', 'received', 85.00, '2026-07-28 18:00:00'),
('JOB-2405', 2, 3, 'Apple', 'iPhone 13', '352987654321098', 'normal', 'Battery drains quickly', 'Battery health at 72%, needs replacement', 'diagnosing', 45.00, '2026-07-30 18:00:00'),
('JOB-2406', 2, 3, 'Xiaomi', 'Redmi Note 12', '869123456789012', 'low', 'Speaker not working', 'Speaker module replacement', 'in_progress', 25.00, '2026-08-02 18:00:00'),
('JOB-2407', 2, 3, 'Huawei', 'P40 Pro', '867987654321012', 'urgent', 'Water damage, won''t turn on', 'Board-level repair, corrosion cleanup', 'in_progress', 120.00, '2026-07-27 14:00:00'),
('JOB-2408', 2, 3, 'Oppo', 'Reno 8', '865555555555012', 'normal', 'Charging port loose', 'USB-C port replacement', 'completed', 30.00, '2026-07-29 18:00:00');
 