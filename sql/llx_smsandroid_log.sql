-- ============================================================================
-- Table: llx_smsandroid_log
-- Description: Historique des SMS envoyés via le module SMS Android
-- Compatible: Dolibarr 19+, MySQL 5.7+, MariaDB 10.2+
-- /volume1/web/dolibarr_test/htdocs/custom/smsandroid/sql/llx_smsandroid_log.sql.php
-- ============================================================================

CREATE TABLE IF NOT EXISTS llx_smsandroid_log (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    date_creation   DATETIME DEFAULT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Données SMS
    phone           VARCHAR(30) NOT NULL COMMENT 'Numéro de téléphone (format international)',
    message         TEXT NOT NULL COMMENT 'Contenu du SMS',
    
    -- Statut API
    status          VARCHAR(20) DEFAULT 'Pending' COMMENT 'Pending/Sent/Failed',
    api_response    TEXT DEFAULT NULL COMMENT 'Réponse JSON brute de l''API Android',
    error_message   VARCHAR(255) DEFAULT NULL COMMENT 'Message d''erreur si échec',
    
    -- Métadonnées Dolibarr
    fk_user_author  INTEGER DEFAULT NULL COMMENT 'Lien vers llx_user (auteur)',
    fk_user_modif   INTEGER DEFAULT NULL COMMENT 'Lien vers llx_user (dernière modif)',
    entity          INTEGER DEFAULT 1 NOT NULL COMMENT 'Multi-company support',
    
    -- Index pour performances
    INDEX idx_smsandroid_phone (phone),
    INDEX idx_smsandroid_status (status),
    INDEX idx_smsandroid_date (date_creation),
    INDEX idx_smsandroid_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

