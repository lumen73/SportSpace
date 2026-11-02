-- Migration unique pour l'ancien schema SportSpace (MyISAM, identifiants INT).
-- Sauvegarder la base et executer les requetes de controle ci-dessous avant
-- toute modification. Les deux comptages doivent retourner 0.

SELECT COUNT(*) AS orphan_match_rows
FROM matchs AS m
LEFT JOIN equipe AS e1 ON e1.idequipe = m.idequipe1
LEFT JOIN equipe AS e2 ON e2.idequipe = m.idequipe2
WHERE e1.idequipe IS NULL OR e2.idequipe IS NULL;

SELECT COUNT(*) AS scores_to_review
FROM matchs
WHERE score IS NOT NULL
  AND TRIM(score) <> ''
  AND (
      CHAR_LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(score), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''), ':', '-')) > 7
      OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(score), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''), ':', '-') NOT REGEXP '^[0-9]{1,3}-[0-9]{1,3}$'
  );

SELECT COUNT(*) AS status_conflicts
FROM matchs
WHERE (score IS NOT NULL AND TRIM(score) <> '' AND statut IN ('reporte', 'annule'))
   OR (statut = 'termine' AND (score IS NULL OR TRIM(score) = ''))
   OR (statut = 'reporte' AND (datereporte IS NULL OR datereporte <= datematch))
   OR (statut <> 'reporte' AND datereporte IS NOT NULL);

-- Normalise les espaces/anciennes variantes, et convertit les chaines vides.
UPDATE matchs
SET score = NULL
WHERE score IS NOT NULL AND TRIM(score) = '';

UPDATE matchs
SET score = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(score), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), ''), ':', '-')
WHERE score IS NOT NULL;

-- Les anciennes versions laissaient parfois le statut en_cours apres saisie.
UPDATE matchs
SET statut = 'termine'
WHERE score IS NOT NULL AND score <> '' AND statut = 'en_cours';

ALTER TABLE equipe
    ENGINE = InnoDB,
    MODIFY idequipe BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY photodrapeau VARCHAR(255) NULL;

ALTER TABLE matchs
    ENGINE = InnoDB,
    MODIFY idmatch BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY idequipe1 BIGINT UNSIGNED NOT NULL,
    MODIFY idequipe2 BIGINT UNSIGNED NOT NULL,
    MODIFY score VARCHAR(7) NULL DEFAULT NULL,
    ADD KEY idx_matchs_datematch (datematch);

ALTER TABLE matchs
    ADD CONSTRAINT fk_matchs_equipe1 FOREIGN KEY (idequipe1)
        REFERENCES equipe (idequipe) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_matchs_equipe2 FOREIGN KEY (idequipe2)
        REFERENCES equipe (idequipe) ON UPDATE CASCADE ON DELETE RESTRICT;
