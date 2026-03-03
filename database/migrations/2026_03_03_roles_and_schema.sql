-- Migration: Restructure roles and core schema
-- Date: 2026-03-03
-- Note: Run in a maintenance window. Backup DB before applying.

START TRANSACTION;

-- 1) Create new users table with normalized schema
CREATE TABLE IF NOT EXISTS users_new (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','engineer','foreman','client') NOT NULL DEFAULT 'client',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  phone VARCHAR(50) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  failed_attempts INT DEFAULT 0,
  last_failed_login DATETIME DEFAULT NULL
);

-- 2) Migrate data from legacy `users` table if exists
-- This attempts to map `user_id` -> `id` and to convert 'admin' role -> 'super_admin'
SET @has_old := (SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'users' AND table_schema = DATABASE());
-- Only perform migration if old users table exists
-- NOTE: If your existing schema differs, review this section before running.
-- Use INSERT ... SELECT with careful mapping
INSERT INTO users_new (full_name, email, password, role, status, phone, created_at, updated_at, failed_attempts, last_failed_login)
SELECT
  COALESCE(full_name, CONCAT(first_name,' ',last_name), email) AS full_name,
  email,
  password,
  CASE WHEN role = 'admin' THEN 'super_admin' ELSE role END AS role,
  CASE WHEN status IS NULL THEN 'active' ELSE status END AS status,
  COALESCE(phone, NULL) AS phone,
  COALESCE(created_at, NOW()),
  COALESCE(updated_at, NOW()),
  COALESCE(failed_attempts, 0),
  last_failed_login
FROM users
WHERE @has_old = 1;

-- 3) Replace users table atomically (only if users table exists)
-- Keep a backup copy just in case
-- Rename old users to users_old, then rename users_new to users
IF @has_old = 1 THEN
  RENAME TABLE users TO users_old, users_new TO users;
END IF;

-- 4) Create project_assignments table
CREATE TABLE IF NOT EXISTS project_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role_in_project ENUM('engineer','foreman') NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pa_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5) Create/modify tasks table (safe create if not exists)
CREATE TABLE IF NOT EXISTS tasks_new (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  assigned_to INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('pending','ongoing','completed','delayed') NOT NULL DEFAULT 'pending',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  deadline DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- If there is an existing `tasks` table, try to migrate into tasks_new (best-effort)
SET @has_tasks := (SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'tasks' AND table_schema = DATABASE());
INSERT INTO tasks_new (project_id, assigned_to, created_by, title, description, status, priority, deadline, created_at, updated_at)
SELECT
  project_id,
  assigned_to,
  created_by,
  title,
  description,
  CASE WHEN status IN ('pending','ongoing','completed','delayed') THEN status ELSE 'pending' END,
  CASE WHEN priority IN ('low','medium','high') THEN priority ELSE 'medium' END,
  deadline,
  COALESCE(created_at, NOW()),
  COALESCE(updated_at, NOW())
FROM tasks
WHERE @has_tasks = 1;

IF @has_tasks = 1 THEN
  RENAME TABLE tasks TO tasks_old, tasks_new TO tasks;
END IF;

-- 6) Optional: foreman_profiles table
CREATE TABLE IF NOT EXISTS foreman_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  specialization VARCHAR(255) DEFAULT NULL,
  availability_status ENUM('available','assigned','on_leave') DEFAULT 'available',
  max_projects INT DEFAULT 3,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_foreman_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

COMMIT;

-- Notes:
-- - Review `projects` and `tasks` PK/column names; adjust FK references if your schema uses different primary key names (e.g., project_id).
-- - This migration attempts to be safe but must be reviewed in each environment before running.
