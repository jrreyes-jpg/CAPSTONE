-- Super Admin data-integrity migration for users table
-- IMPORTANT: backup database first before running.

START TRANSACTION;

-- 1) Normalize legacy role value
UPDATE users
SET role = 'foreman'
WHERE role = 'foremen';

-- 2) Fill missing role safely
-- Chosen policy: fallback role = client
UPDATE users
SET role = 'client'
WHERE role IS NULL OR TRIM(role) = '';

-- 3) Remove invalid phone values before adding constraints
-- Keep only PH mobile format 09xxxxxxxxx; set invalid values to NULL
UPDATE users
SET phone = NULL
WHERE phone IS NOT NULL
  AND TRIM(phone) <> ''
  AND phone NOT REGEXP '^09[0-9]{9}$';

-- 4) Resolve duplicate data first (so unique constraints won't fail)
-- Keep the smallest id record, sanitize the rest

-- 4.a duplicate emails
UPDATE users u
JOIN (
    SELECT email, MIN(id) AS keep_id
    FROM users
    WHERE email IS NOT NULL AND TRIM(email) <> ''
    GROUP BY email
    HAVING COUNT(*) > 1
) d ON u.email = d.email AND u.id <> d.keep_id
SET u.email = CONCAT('dup+', u.id, '@invalid.local');

-- 4.b duplicate phones (after invalid phones were nulled)
UPDATE users u
JOIN (
    SELECT phone, MIN(id) AS keep_id
    FROM users
    WHERE phone IS NOT NULL AND TRIM(phone) <> ''
    GROUP BY phone
    HAVING COUNT(*) > 1
) d ON u.phone = d.phone AND u.id <> d.keep_id
SET u.phone = NULL;

-- 4.c duplicate full_name
UPDATE users u
JOIN (
    SELECT full_name, MIN(id) AS keep_id
    FROM users
    WHERE full_name IS NOT NULL AND TRIM(full_name) <> ''
    GROUP BY full_name
    HAVING COUNT(*) > 1
) d ON u.full_name = d.full_name AND u.id <> d.keep_id
SET u.full_name = CONCAT(u.full_name, ' #', u.id);

-- 5) Add uniqueness constraints (prevents duplicate name/email/phone)
ALTER TABLE users
    ADD UNIQUE KEY uq_users_email (email),
    ADD UNIQUE KEY uq_users_phone (phone),
    ADD UNIQUE KEY uq_users_full_name (full_name);

-- 6) Enforce role/status/phone structure
ALTER TABLE users
    MODIFY role VARCHAR(30) NOT NULL,
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active',
    ADD CONSTRAINT chk_users_role CHECK (role IN ('super_admin','engineer','foreman','client')),
    ADD CONSTRAINT chk_users_status CHECK (status IN ('active','inactive')),
    ADD CONSTRAINT chk_users_phone CHECK (phone IS NULL OR phone = '' OR phone REGEXP '^09[0-9]{9}$');

COMMIT;

-- Verification queries (run after COMMIT)
-- SELECT id, full_name, email, phone, role, status FROM users ORDER BY id DESC;
-- SELECT role, COUNT(*) FROM users GROUP BY role;
-- SELECT email, COUNT(*) c FROM users GROUP BY email HAVING c > 1;
-- SELECT phone, COUNT(*) c FROM users WHERE phone IS NOT NULL AND phone <> '' GROUP BY phone HAVING c > 1;
-- SELECT full_name, COUNT(*) c FROM users GROUP BY full_name HAVING c > 1;
