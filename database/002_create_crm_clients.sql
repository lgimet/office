CREATE TABLE IF NOT EXISTS client_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY client_types_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_type_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) DEFAULT NULL,
    contact_first_name VARCHAR(100) DEFAULT NULL,
    contact_last_name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address_line1 VARCHAR(190) DEFAULT NULL,
    address_line2 VARCHAR(190) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    country VARCHAR(120) NOT NULL DEFAULT 'France',
    siret VARCHAR(32) DEFAULT NULL,
    vat_number VARCHAR(64) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY clients_client_type_id_index (client_type_id),
    KEY clients_company_name_index (company_name),
    CONSTRAINT clients_client_type_id_foreign
        FOREIGN KEY (client_type_id) REFERENCES client_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO client_types (name, slug, position)
SELECT 'SpotCard', 'spotcard', 10
WHERE NOT EXISTS (SELECT 1 FROM client_types WHERE slug = 'spotcard');
INSERT INTO client_types (name, slug, position)
SELECT 'ORepas', 'orepas', 20
WHERE NOT EXISTS (SELECT 1 FROM client_types WHERE slug = 'orepas');
INSERT INTO client_types (name, slug, position)
SELECT 'Autre', 'autre', 30
WHERE NOT EXISTS (SELECT 1 FROM client_types WHERE slug = 'autre');
