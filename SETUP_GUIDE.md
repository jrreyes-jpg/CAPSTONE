# 🚀 Edge Automation Portal - Setup Guide

## 📋 System Overview

This is a role-based project management system with three user types:
- **Admin** - Creates engineer accounts, manages the system
- **Engineer** - Works on assigned projects and tasks
- **Client** - Views their own projects

## 🗄️ Database

**Database Name:** `edge_project_asset_inventory_db`

**Tables:**
- `users` - User accounts (role: admin, engineer, client)
- `projects` - Projects assigned to clients
- `project_engineers` - Engineers assigned to projects
- `project_assets` - Assets assigned to projects
- `inventory` - Inventory items
- `assets` - Physical assets
- `tasks` - Tasks assigned to engineers
- `asset_logs` - Logs of asset movements

---

## 🔐 How to Create the First Admin Account

**IMPORTANT:** You cannot create an admin account through the Sign Up page. The first admin must be created via database.

### Step 1: Open phpMyAdmin
Go to: `http://localhost/phpmyadmin`

### Step 2: Insert Admin User
1. Click on your database: `edge_project_asset_inventory_db`
2. Find the `users` table
3. Click **Insert**
4. Fill in the fields:
   - **user_id**: Leave empty (auto-increment)
   - **full_name**: Your name (e.g., "Admin User")
   - **email**: Your email (e.g., "admin@edgeautomation.com")
   - **password**: Hash the password using this PHP:
     ```php
     <?php echo password_hash("your_password", PASSWORD_DEFAULT); ?>
     ```
   - **role**: `admin`
   - **availability_status**: `available`
   - **created_at**: Leave empty (current timestamp)

5. Click **Go** to insert

### Example SQL Command:
```sql
INSERT INTO users (full_name, email, password, role, availability_status) 
VALUES (
  'Admin User',
  'admin@edgeautomation.com',
  '$2y$10$...',  -- Use hashed password from above
  'admin',
  'available'
);
```

---

## 👥 User Roles & Features

### 1️⃣ Admin
**Access:** `http://localhost/codesamplecaps/index.php`

**Features:**
- Login with email & password
- Dashboard showing statistics
- **Create Engineer Accounts** via `/dashboards/create_engineer.php`
  - Fill in: Full Name, Email, Temporary Password
  - Engineers can then login and change their password

**Admin Panel:** After login as admin, click "Create Engineer" in the dashboard

---

### 2️⃣ Client
**Access:** `http://localhost/codesamplecaps/index.php`

**Features:**
- Sign up with name, email, password
- Login and view assigned projects
- View project details (name, description, status, dates)

**Sign Up Flow:**
1. Click "Sign Up" on login page
2. Fill in: Full Name, Email, Password, Confirm Password
3. Click "Register"
4. Redirected to login page
5. Login with your credentials

---

### 3️⃣ Engineer
**Access:** Created ONLY by Admin

**Features:**
- View assigned projects
- View assigned tasks
- Track task status and deadlines

**How Admin Creates Engineer:**
1. Login as Admin
2. Go to: `http://localhost/codesamplecaps/dashboards/create_engineer.php`
3. Fill in: Full Name, Email, Temporary Password
4. Click "Create Engineer Account"
5. Engineer can then login with provided credentials

---

## 🔑 Login Credentials (After Setup)

**Admin:**
- Email: `admin@edgeautomation.com`
- Password: Your chosen password

**Test Client (via Sign Up):**
- Email: `client@test.com`
- Password: `test123`

**Test Engineer (created by admin):**
- Email: `engineer@test.com`
- Password: `test123`

---

## 📂 File Structure

```
/index.php                          # Main login page
/auth/
  ├── register.php                 # Client sign-up
  ├── logout.php                   # Logout handler
  └── forgot.php                   # Password reset
/dashboards/
  ├── admin_dashboard.php          # Admin home
  ├── create_engineer.php          # Admin creates engineers
  ├── engineer_dashboard.php       # Engineer home
  └── client_dashboard.php         # Client home
/config/
  └── database.php                 # Database connection
```

---

## 🔗 Quick Links

| User Type | URL | Action |
|-----------|-----|--------|
| Admin | `index.php` | Login with role=admin |
| Admin | `dashboards/create_engineer.php` | Create engineers |
| Engineer | `index.php` | Login with role=engineer |
| Engineer | `dashboards/engineer_dashboard.php` | View projects/tasks |
| Client | `index.php` | Sign up or login |
| Client | `dashboards/client_dashboard.php` | View projects |

---

## ⚙️ Configuration

All database settings are in `/config/database.php`:
- Host: `127.0.0.1`
- Port: `3307` (Custom XAMPP port)
- User: `root`
- Password: (empty)
- Database: `edge_project_asset_inventory_db`

---

## ❓ Troubleshooting

**"Email not found" on login:**
- Make sure admin account was created in the database
- Check email spelling

**"Database Connection Failed":**
- Make sure MySQL is running in XAMPP
- Check database.php configuration
- Port should be 3307 (from your XAMPP setup)

**"Access Denied" error:**
- User role does not match the page you're visiting
- Login with the correct role

---

## 🎯 Next Steps

1. ✅ Create first admin account via phpMyAdmin
2. ✅ Login as admin at `/index.php`
3. ✅ Create engineer accounts via admin panel
4. ✅ Test client sign-up
5. ✅ Assign projects to engineers (via database or future feature)

---

**System Ready!** 🎉
