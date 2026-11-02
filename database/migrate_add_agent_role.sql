-- Migration additive : conserve les roles existants et ajoute le role agent.
ALTER TABLE users
    MODIFY role ENUM('user', 'agent', 'admin') NOT NULL DEFAULT 'user';
