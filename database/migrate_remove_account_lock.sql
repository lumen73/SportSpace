-- Back up the database before running this migration.
-- Run once on databases created with the old schema_users.sql.
ALTER TABLE users
    DROP INDEX idx_users_locked_until,
    DROP COLUMN failed_attempts,
    DROP COLUMN locked_until;
