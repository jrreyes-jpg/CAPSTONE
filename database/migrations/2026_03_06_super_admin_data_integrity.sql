-- Data integrity recommendations for users table
-- Run on your real DB after backup.

-- 1) Normalize old role value 'foremen' -> 'foreman'
UPDATE users SET role = 'foreman' WHERE role = 'foremen';

-- 2) Fill missing role safely (manual review first if needed)
-- Example fallback (choose one policy):
-- UPDATE users SET role = 'client' WHERE role IS NULL OR role = '';

-- 3) Remove invalid phone values before adding constraints (manual cleanup)
-- SELECT id, full_name, phone FROM users WHERE phone IS NOT NULL AND phone <> '' AND phone NOT REGEXP '^09[0-9]{9}$';

-- 4) Add uniqueness constraints (prevents duplicate name/email/phone)
ALTER TABLE users
    ADD UNIQUE KEY uq_users_email (email),
    ADD UNIQUE KEY uq_users_phone (phone),
    ADD UNIQUE KEY uq_users_full_name (full_name);

-- 5) Add role/status/phone checks (MariaDB/MySQL 8+)
ALTER TABLE users
    MODIFY role VARCHAR(30) NOT NULL,
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active',
    ADD CONSTRAINT chk_users_role CHECK (role IN ('super_admin','engineer','foreman','client')),
    ADD CONSTRAINT chk_users_status CHECK (status IN ('active','inactive')),
    ADD CONSTRAINT chk_users_phone CHECK (phone IS NULL OR phone = '' OR phone REGEXP '^09[0-9]{9}$');

-- SECURITY NOTE:
-- Passwords should stay hashed. Do NOT store/reveal plaintext passwords.
-- If admin needs access, implement reset-password flow instead of showing saved passwords.
