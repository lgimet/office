CREATE TABLE IF NOT EXISTS company_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    legal_name VARCHAR(190) NOT NULL,
    trading_name VARCHAR(190) DEFAULT NULL,
    legal_form VARCHAR(80) DEFAULT NULL,
    share_capital VARCHAR(100) DEFAULT NULL,
    address_line1 VARCHAR(190) DEFAULT NULL,
    address_line2 VARCHAR(190) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    country VARCHAR(120) NOT NULL DEFAULT 'France',
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    website VARCHAR(190) DEFAULT NULL,
    siret VARCHAR(32) DEFAULT NULL,
    siren VARCHAR(16) DEFAULT NULL,
    vat_number VARCHAR(64) DEFAULT NULL,
    ape_code VARCHAR(16) DEFAULT NULL,
    rcs_city VARCHAR(120) DEFAULT NULL,
    bank_name VARCHAR(120) DEFAULT NULL,
    iban VARCHAR(64) DEFAULT NULL,
    bic VARCHAR(16) DEFAULT NULL,
    default_currency CHAR(3) NOT NULL DEFAULT 'EUR',
    default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    default_payment_terms VARCHAR(255) DEFAULT NULL,
    default_payment_method VARCHAR(100) DEFAULT NULL,
    invoice_footer TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO company_settings (
    id,
    legal_name,
    country,
    default_currency,
    default_tax_rate
) VALUES (
    1,
    '',
    'France',
    'EUR',
    20.00
);
