-- =============================================================
--  Upgrade : statut des matchs + date de report
--  Migration à exécuter une seule fois sur une table matchs existante
-- =============================================================
ALTER TABLE matchs
    ADD COLUMN statut ENUM('en_cours','termine','reporte','annule')
        NOT NULL DEFAULT 'en_cours' AFTER datematch,
    ADD COLUMN datereporte DATE NULL DEFAULT NULL AFTER statut;

-- Regles metier appliquees cote applicatif (includes/functions.php) :
--   en_cours : match programme / pas encore publie (saisie possible si date passee)
--   termine  : score publie (saisie possible pour correction si date passee)
--   reporte  : la rencontre est repoussee -> datereporte OBLIGATOIRE
--   annule   : match annule, aucune saisie de score possible
-- Affichage derive automatique : "Deja joue" (date passee + score), "En cours", etc.
