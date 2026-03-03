**Project Restructure Overview**

- Goal: Replace legacy `admin` role with `super_admin`, add `foreman`, unify assignments and tasks, and refactor to controller-based architecture.

**New Folder Layout (top-level)**
- config/           : configuration and middleware
- controllers/      : HTTP controllers (business logic)
- models/           : DB models / query wrappers
- views/            : templates (no SQL in views)
- public/           : web root (index, assets, login)
- database/migrations/: SQL migrations

**Key Schema Changes (migration included)**
- `users` now: id, full_name, email (unique), password (hashed), role ENUM('super_admin','engineer','foreman','client'), status ENUM('active','inactive'), phone, created_at, updated_at
- `project_assignments`: id, project_id, user_id, role_in_project ENUM('engineer','foreman'), assigned_at
- `tasks`: id, project_id, assigned_to, created_by, title, description, status ENUM('pending','ongoing','completed','delayed'), priority ENUM('low','medium','high'), deadline, created_at, updated_at
- Optional: `foreman_profiles`

**Security & Architecture**
- Use prepared statements in controllers/models.
- Use `config/auth_middleware.php` to centralize role checks.
- Remove public self-registration; only `super_admin` can create accounts.

**Next recommended steps**
- Implement controllers for users, projects, tasks.
- Replace inline SQL from views to `models/` or `controllers/` using prepared statements.
- Update all controllers/views to use `users.id` (migration may rename old `user_id` to `id`).
- Run migration in safe/dev environment first and verify data mapping.

