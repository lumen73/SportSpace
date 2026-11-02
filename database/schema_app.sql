-- Schéma des équipes et des matchs pour une installation neuve.
-- Exécuter également schema_users.sql pour activer les comptes.

CREATE TABLE IF NOT EXISTS equipe (
    idequipe BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomequipe VARCHAR(120) NOT NULL,
    photodrapeau VARCHAR(255) NULL,
    PRIMARY KEY (idequipe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matchs (
    idmatch BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idequipe1 BIGINT UNSIGNED NOT NULL,
    idequipe2 BIGINT UNSIGNED NOT NULL,
    datematch DATE NOT NULL,
    statut ENUM('en_cours', 'termine', 'reporte', 'annule') NOT NULL DEFAULT 'en_cours',
    datereporte DATE NULL DEFAULT NULL,
    score VARCHAR(7) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idmatch),
    KEY idx_matchs_datematch (datematch),
    KEY idx_matchs_idequipe1 (idequipe1),
    KEY idx_matchs_idequipe2 (idequipe2),
    CONSTRAINT fk_matchs_equipe1 FOREIGN KEY (idequipe1)
        REFERENCES equipe (idequipe) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matchs_equipe2 FOREIGN KEY (idequipe2)
        REFERENCES equipe (idequipe) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
