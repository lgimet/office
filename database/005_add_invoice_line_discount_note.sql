ALTER TABLE invoice_lines
    ADD COLUMN discount_note VARCHAR(255) DEFAULT NULL AFTER discount_value;
