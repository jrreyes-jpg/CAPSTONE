# Senior Developer Architecture Guide
## Edge Automation System - Refactored in 2026

### 🎯 What Was Changed? (CRITICAL UNDERSTANDING)

Your old code had **inline queries** mixed with HTML and business logic. This is **WRONG** in professional development. Here's the architecture I've built:

---

## 📐 New Architecture (Layers)

```
REQUEST
   ↓
[VIEWS/CONTROLLERS] - HTTP Request Handling
   ↓
[SERVICES] - Business Logic (Auth, Email, etc)
   ↓
[REPOSITORIES] - Data Access (Database queries ONLY)
   ↓
[DATABASE] - MySQL
```

### **Layer 1: Configuration**
**File:** `config/Config.php`
- Centralized settings management
- NO hardcoded values anywhere
- SMTP, database, app settings in ONE place
- Can be overridden with environment variables

```php
$config = Config::getInstance();
$email = $config->get('MAIL_USERNAME');
```

### **Layer 2: Repositories (Data Access Object - DAO)**
**File:** `repositories/UserRepository.php`
- **ONLY database queries go here**
- No business logic, no HTML, no emails
- Controllers call these methods
- Example methods:
  - `findByEmail($email)` - Get user by email
  - `create($name, $email, ...)` - Insert new user
  - `updatePassword($userId, $hash)` - Update password
  - `recordFailedLogin($userId)` - Track login attempts

**KEY PRINCIPLE:** If you write SQL, it goes in a Repository.

### **Layer 3: Services (Business Logic)**
**Files:** 
- `services/AuthService.php` - Authentication logic
- `services/EmailService.php` - Email sending

#### **AuthService** handles:
```php
$authService = new AuthService();

// Login with security (failed attempts, lockout)
$result = $authService->login($email, $password);

// Create account (temp password sent via email)
$result = $authService->createAccount($name, $email, $role);

// Request password reset
$result = $authService->requestPasswordReset($email);

// Reset password with token
$result = $authService->resetPassword($token, $newPassword);
```

#### **EmailService** handles:
```php
$emailService = new EmailService();

// Send password reset email
$emailService->sendPasswordReset($email, $name, $token, 60);

// Send account created notification
$emailService->sendAccountCreated($email, $name, $role);
```

### **Layer 4: Controllers**
**File:** `controllers/UserController.php`
- **Receive HTTP requests**
- **Call services** (NOT direct database queries)
- **Return results to views**

Never let controllers write SQL or send emails directly!

### **Layer 5: Views**
- HTML forms and display
- Get data from controllers
- Display messages and errors
- NO database queries here!

---

## 🔐 Authentication Flow (NEW)

### **1. Login Flow**
```
User submits form → login.php
                ↓
        calls AuthService::login()
                ↓
        AuthService calls UserRepository::getLoginUser()
                ↓
        Returns user data or error
                ↓
        If success: SET SESSION and REDIRECT
        If failure: SHOW ERROR
```

### **2. Password Reset Flow**
```
User clicks "Forgot Password" → forgot.php
                ↓
        User enters email → AuthService::requestPasswordReset()
                ↓
        AuthService calls:
        - UserRepository::findByEmail()
        - UserRepository::setResetToken()
        - EmailService::sendPasswordReset()
                ↓
        Email sent with reset link
                ↓
User clicks email link → reset_password.php
                ↓
        Submits new password → AuthService::resetPassword()
                ↓
        Updates password in database
                ↓
        Success message, user can login
```

### **3. Account Creation (Super Admin)**
```
Super Admin fills form → admin_dashboard.php
                ↓
        calls UserController::createEngineer() (or other role)
                ↓
        UserController calls AuthService::createAccount()
                ↓
        AuthService:
        - Validates inputs
        - Calls UserRepository::create()
        - Calls EmailService::sendAccountCreated()
                ↓
        New user created with temp password
        Welcome email sent
        User must reset password on first login
```

---

## 📧 Email Configuration (Dynamic)

**NO MORE HARDCODED CREDENTIALS!**

### How to Change Email Settings:

**Option 1: Update Config File** (Quick)
```php
// Edit config/Config.php
$this->settings['MAIL_HOST'] = 'smtp.gmail.com';
$this->settings['MAIL_USERNAME'] = 'your-email@gmail.com';
$this->settings['MAIL_PASSWORD'] = 'your-app-password';
```

**Option 2: Use Environment Variables** (Professional)
```php
// In your server environment, set:
setenv('MAIL_HOST', 'smtp.gmail.com');
setenv('MAIL_USERNAME', 'your-email@gmail.com');
setenv('MAIL_PASSWORD', 'your-app-password');

// Config.php automatically loads from environment
```

### Gmail Setup (Common):
1. Enable 2-factor authentication on Gmail
2. Generate "App Password" (not your regular password)
3. Use the App Password in config
4. Done! It's secure and doesn't require 2FA verification each time

---

## 🗄️ Database Schema (NEW)

### Key Changes:

1. **Consolidated Users Table**
   - Only ONE `users` table (removed `users_new`)
   - Fields: `id`, `full_name`, `email`, `password`, `role`, `status`, etc.
   - Roles: `super_admin`, `engineer`, `manpower`, `client`

2. **Password Reset Tokens Table**
   - `password_reset_tokens` table for secure password resets
   - Each token expires after 60 minutes
   - Marked as "used" after reset to prevent reuse

3. **Audit Logs Table** (for future)
   - Tracks all important actions
   - Who did what, when, and what changed

### Run This to Update Database:
```sql
-- Import the schema file:
Source /path/to/database/migrations/2026_03_03_improved_schema.sql
```

---

## 👥 User Management (Admin Dashboard)

Super Admin can now:

### 1. Create Accounts
- Role: Engineer, Manpower, or Client
- System sends welcome email with temp password
- User resets password on first login via email link

### 2. View Users
- See all users by role
- Display contact information
- Quick actions (Edit, etc - to be implemented)

### 3. Admin Dashboard
- Overview statistics
- Navigation between sections
- Easy account creation form

### **How to Manage**:
```
1. Login as Super Admin
2. Go to Admin Dashboard
3. Click "Create Account" tab
4. Fill form (Name, Email, Phone, Role)
5. Click "Create Account"
6. System sends email to new user
7. User clicks reset password link
8. User sets their own password
9. User can now login
```

---

## 🔒 Security Features Implemented

### 1. Failed Login Tracking
- Tracks failed attempts per user
- Locks account after 10 failed attempts
- Lockout period: 15 minutes
- Resets on successful login

**Code:**
```php
// In UserRepository:
recordFailedLogin($userId)      // Increment counter
resetFailedAttempts($userId)    // Reset counter
```

### 2. Password Hashing
- Uses PHP's `password_hash()` (bcrypt)
- Automatically salted
- Unhackable without brute force

```php
password_hash($password, PASSWORD_DEFAULT);
password_verify($inputPassword, $hashFromDB);
```

### 3. Reset Tokens
- One-time use tokens
- 60-minute expiry
- Cryptographically secure
- Unique per reset request

### 4. Email Security
- No account creation verification needed (user created by admin)
- Password reset requires email access
- Account status can be activated/deactivated

---

## 📝 File Structure Explanation

```
📁 codesamplecaps/
├── 📁 config/
│   ├── Config.php              ← Central configuration
│   ├── database.php            ← DB connection (uses Config)
│   └── auth_middleware.php    ← Role checking
│
├── 📁 repositories/
│   └── UserRepository.php      ← All user database queries
│
├── 📁 services/
│   ├── AuthService.php         ← Login, register, password reset logic
│   └── EmailService.php        ← Email sending (SMTP)
│
├── 📁 controllers/
│   └── UserController.php      ← HTTP request handlers
│
├── 📁 views/
│   ├── 📁 auth/
│   │   ├── forgot.php          ← Password reset request
│   │   ├── reset_password.php  ← Password reset form
│   │   └── logout.php
│   │
│   └── 📁 dashboards/
│       ├── admin_dashboard.php ← Super admin controls
│       ├── engineer_dashboard.php
│       ├── client_dashboard.php
│       └── foreman_dashboard.php
│
├── 📁 public/
│   └── login.php               ← Login page (uses AuthService)
│
└── 📁 database/
    └── 📁 migrations/
        └── 2026_03_03_improved_schema.sql ← Database schema
```

---

## 🚀 Next Steps for You

### 1. Update Your Database
```sql
-- Run this to create new tables with improved schema
mysql -u root edge_project_asset_inventory_db < database/migrations/2026_03_03_improved_schema.sql
```

### 2. Configure SMTP
Edit `config/Config.php` and set your Gmail credentials:
```php
$this->settings['MAIL_USERNAME'] = 'your-email@gmail.com';
$this->settings['MAIL_PASSWORD'] = 'your-app-password';
```

### 3. Test the System
- Login with admin@edge.com (you'll need to reset password)
- Create new accounts from Admin Dashboard
- Test password reset flow
- Verify emails are being sent

### 4. Future Development
- Implement edit user functionality
- Add change password for logged-in users
- Add user deactivation/deletion
- Implement audit logs
- Add two-factor authentication
- Implement role-based dashboards

---

## 💡 Key Takeaways (Senior Developer Lessons)

### DO ✅
- Put database queries in **Repositories**
- Put business logic in **Services**
- Put HTTP handling in **Controllers**
- Put HTML in **Views**
- Centralize configuration in **Config**
- Use dependency injection pattern
- Validate all inputs
- Hash passwords with bcrypt
- Use prepared statements (prevents SQL injection)
- Track and limit failed login attempts
- Send sensitive links via email, not SMS

### DON'T ❌
- Don't write SQL in views or controllers
- Don't hardcode configuration values
- Don't mix business logic with HTML
- Don't trust user input
- Don't store passwords in plain text
- Don't send passwords via email
- Don't use global variables unnecessarily
- Don't repeat code - use services and functions

---

## 🎓 Learning Path

1. **Understand the layers** - Read through Services → Repositories → Models
2. **Trace a request** - Follow login from view → controller → service → repository
3. **Modify a feature** - Try adding a "change password" feature using the existing pattern
4. **Add new features** - Create new services for new functionality
5. **Implement professionally** - Always follow this architecture

---

## 📞 Quick Reference

### To Create an Account (Programmatically):
```php
$userController = new UserController();
$result = $userController->createEngineer(
    'John Doe',
    'john@email.com',
    '1234567890',
    $_SESSION['user_id']  // who created it
);

if ($result['success']) {
    echo "Account created! User ID: " . $result['userId'];
    // Email already sent automatically
} else {
    echo "Error: " . $result['error'];
}
```

### To Verify Login:
```php
$authService = new AuthService();
$result = $authService->login('user@email.com', 'password123');

if ($result['success']) {
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['role'] = $result['user']['role'];
    // Redirect to dashboard
} else {
    // Show error: $result['error']
}
```

### To Send Custom Email:
```php
$emailService = new EmailService();
$success = $emailService->sendPasswordReset(
    'user@email.com',
    'User Name',
    'reset_token_here',
    60  // expires in 60 minutes
);
```

---

## Summary

✨ **What you now have:**
- Clean, professional architecture
- Secure authentication with email verification
- Dynamic, centralized configuration
- Scalable code structure
- Easy to add new features
- Proper separation of concerns
- Industry-standard patterns

🎯 **Your system is now:**
- More secure (failed login tracking, token expiry)
- More maintainable (clear layer separation)
- More professional (industry standards)
- More scalable (easy to add features)
- More testable (each layer can be tested independently)

---

**Created:** March 3, 2026
**Version:** 2.0 (Refactored)
**Author:** Architecture implemented for professional coding standards
