# 🏗️ System Architecture Diagram

## Overall Application Flow

```
┌─────────────────────────────────────────────────────────────┐
│                      BROWSER / CLIENT                        │
│                   (User Interface, HTML)                     │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                      VIEW LAYER                              │
│  public/login.php, views/auth/forgot.php, admin_dashboard   │
│           (Display HTML, No Business Logic)                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                    CONTROLLER LAYER                          │
│         controllers/UserController.php                       │
│    (Receive requests, Call services, Return results)        │
└──────────────────────────┬──────────────────────────────────┘
                           │
                ┌──────────┼──────────┐
                ↓          ↓          ↓
    ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
    │ AuthService  │  │EmailService  │  │UserService?  │
    │ - login()    │  │ - send()     │  │ - create()   │
    │ - register() │  │ - template() │  │ - delete()   │
    │ - reset()    │  └──────────────┘  └──────────────┘
    └──────────────┘
                │
                ↓
    ┌──────────────────────────────────┐
    │      SERVICE LAYER               │
    │  (Business Logic, Validation)    │
    └──────────────┬───────────────────┘
                   │
                ┌──┴─────────────────────┐
                ↓                        ↓
    ┌──────────────────────┐  ┌──────────────────┐
    │ UserRepository       │  │ ProjectRepository│
    │ - findByEmail()      │  │ - findById()     │
    │ - create()           │  │ - create()       │
    │ - updatePassword()   │  │ - getAll()       │
    │ - recordFailedLogin()│  └──────────────────┘
    └──────────────┬───────┘
                   │
       ┌───────────┼───────────┐
       ↓           ↓           ↓
    ┌─────────┐┌─────────┐┌──────────────┐
    │ Config  ││Database ││File System   │
    │ Service ││ queries ││(for uploads) │
    └─────────┘└────┬────┘└──────────────┘
                    │
                    ↓
            ┌──────────────────┐
            │   MySQL Database │
            │                  │
            │ ├─ users         │
            │ ├─ projects      │
            │ ├─ tasks         │
            │ ├─ inventory     │
            │ └─ audit_logs    │
            └──────────────────┘
```

---

## Login Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ User enters email & password                                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ↓
            ┌────────────────────┐
            │ login.php (View)   │
            │                    │
            │ · Validate input   │
            │ · Call service     │
            └────────────┬───────┘
                         │
                         ↓
            ┌────────────────────────────┐
            │ UserController::login()     │
            │                             │
            │ return $this->authService  │
            │   ->login($email, $pass)   │
            └────────────┬────────────────┘
                         │
                         ↓
            ┌────────────────────────────────┐
            │ AuthService::login()            │
            │                                │
            │ 1. Validate input             │
            │ 2. Get user record            │
            │ 3. Check failed attempts      │
            │ 4. Verify password            │
            │ 5. Reset attempts             │
            │ 6. Return result              │
            └────────────┬───────────────────┘
                         │
        ┌────────────────┴────────────────┐
        ↓                                 ↓
    ┌─────────────────┐        ┌─────────────────┐
    │ UserRepository  │        │ UserRepository  │
    │ getLoginUser()  │        │ resetFailedLogin│
    │                 │        │                 │
    │ Query: SELECT * │        │ UPDATE failed   │
    │   FROM users    │        │   attempts = 0  │
    │   WHERE email=? │        └─────────────────┘
    └────────┬────────┘
             │
             ↓
        ┌──────────────┐
        │ MySQL Query  │
        └────────┬─────┘
                 │
                 ↓
        ┌──────────────────┐
        │ Return user data │
        │ or NULL          │
        └────────┬─────────┘
                 │
        ┌────────┴────────┐
        │                 │
        ↓                 ↓
    ┌─ Success ─┐    ┌─ Failed ─┐
    │           │    │          │
    │ Set session   Record   │
    │ Redirect   attempt    │
    │ dashboard  error msg  │
    └───────────┘    └──────────┘
```

---

## Account Creation Flow

```
┌──────────────────────────────┐
│ Super Admin fills form:       │
│ - Name: John Engineer         │
│ - Email: john@company.com     │
│ - Role: engineer              │
└──────────────┬────────────────┘
               │
               ↓
    ┌─────────────────────────────┐
    │ admin_dashboard.php (View)  │
    │                             │
    │ calls UserController        │
    │   ->createEngineer()        │
    └──────────────┬──────────────┘
                   │
                   ↓
    ┌──────────────────────────────────┐
    │ UserController::createEngineer()  │
    │                                  │
    │ require_role('super_admin')      │
    │ return $this->authService       │
    │   ->createAccount(...)          │
    └──────────────┬───────────────────┘
                   │
                   ↓
    ┌──────────────────────────────────┐
    │ AuthService::createAccount()      │
    │                                  │
    │ 1. Validate input               │
    │ 2. Check email exists           │
    │ 3. Generate temp password       │
    │ 4. Hash password (bcrypt)       │
    │ 5. Call UserRepository::create()│
    │ 6. Send welcome email           │
    │ 7. Return result                │
    └──────────────┬───────────────────┘
                   │
        ┌──────────┼──────────┐
        │          │          │
        ↓          ↓          ↓
    ┌─────────┐┌──────────┐┌────────────┐
    │UserRepo ││UserRepo  ││EmailService│
    │ create()││ emailExis││ send       │
    │         ││ts()      ││            │
    └────┬────┘└────┬─────┘└──────┬─────┘
         │          │             │
         ↓          ↓             ↓
    ┌────────────────────────────────────┐
    │ Database Operations                │
    │                                    │
    │ ✓ INSERT into users                │
    │ ✓ VERIFY email unique              │
    │ ✓ SEND email via SMTP              │
    └────────────┬───────────────────────┘
                 │
                 ↓
        ┌────────────────────┐
        │ Admin Dashboard    │
        │                    │
        │ Show success msg   │
        │ Display new user   │
        │ in Users list      │
        └────────────────────┘
                 │
                 ↓
        ┌────────────────────┐
        │ New User Inbox     │
        │                    │
        │ Receives:          │
        │ Welcome email      │
        │ + reset link       │
        └────────────────────┘
```

---

## Password Reset Flow

```
┌────────────────────────────────────┐
│ User (forgot password)              │
│ Goes to forgot_password page        │
└──────────────┬─────────────────────┘
               │
               ↓
    ┌──────────────────────────┐
    │ forgot.php (View)        │
    │ Enter email              │
    └──────────────┬───────────┘
                   │
                   ↓
    ┌──────────────────────────┐
    │ AuthService::            │
    │ requestPasswordReset()   │
    │                          │
    │ 1. Find user by email   │
    │ 2. Generate token      │
    │ 3. Set expiry (60 min) │
    │ 4. Save in database    │
    │ 5. Send email          │
    │ 6. Return success      │
    └──────────────┬──────────┘
                   │
        ┌──────────┼──────────┐
        │          │          │
        ↓          ↓          ↓
    ┌───────────┐┌────────────┐┌──────────┐
    │UserRepository        │EmailService│
    │· findByEmail()       │· send      │
    │· setResetToken()     │  reset()   │
    └────┬────────┘└────┬──────┘└────┬────┘
         │               │            │
         ↓               ↓            ↓
    ┌──────────────────────────────────────┐
    │ Email Sent to User                   │
    │                                      │
    │ Subject: Password Reset Request      │
    │ Content:                             │
    │ - Link with unique token             │
    │ - Expires in 60 minutes              │
    │ - Security warning                   │
    └──────────────┬───────────────────────┘
                   │
                   ↓
    ┌──────────────────────────────────┐
    │ User Clicks Reset Link            │
    │ (from email)                      │
    └──────────────┬────────────────────┘
                   │
                   ↓
    ┌───────────────────────────────────┐
    │ reset_password.php (View)         │
    │                                   │
    │ Form validates token              │
    │ - Shows password strength meter   │
    │ - Get confirm password            │
    │ - Submit new password             │
    └──────────────┬──────────────────┘
                   │
                   ↓
    ┌───────────────────────────────────┐
    │ AuthService::resetPassword()      │
    │                                   │
    │ 1. Find user by token             │
    │ 2. Verify token not expired       │
    │ 3. Verify token not used before   │
    │ 4. Hash new password              │
    │ 5. Update user password           │
    │ 6. Mark token as used             │
    │ 7. Return success                 │
    └──────────────┬──────────────────┘
                   │
        ┌──────────┼──────────┐
        │          │          │
        ↓          ↓          ↓
    ┌──────────────────────────────┐
    │ UserRepository              │
    │ · findByResetToken()        │
    │ · updatePassword()          │
    └──────────────┬──────────────┘
                   │
                   ↓
    ┌──────────────────────────────┐
    │ Database Updated             │
    │                              │
    │ ✓ Password hash changed      │
    │ ✓ Token marked as used       │
    │ ✓ User can login now         │
    └──────────────┬───────────────┘
                   │
                   ↓
    ┌──────────────────────────────┐
    │ User Receives Success Msg    │
    │                              │
    │ "Password reset successfully │
    │  You can now login"          │
    │ Link to login page           │
    └──────────────────────────────┘
```

---

## Layer Responsibilities

```
┌────────────────────────────────────────────┐
│               VIEW LAYER                    │
│  (public/login.php, views/auth/*.php)      │
│                                            │
│ ✓ Display HTML                             │
│ ✓ Show validation messages                 │
│ ✓ Receive HTTP requests                    │
│ ✓ Call controllers/services                │
│                                            │
│ ✗ NO database queries                      │
│ ✗ NO business logic                        │
│ ✗ NO email sending                         │
└────────────────────────────────────────────┘
                      ↓
┌────────────────────────────────────────────┐
│            CONTROLLER LAYER                 │
│   (controllers/UserController.php)         │
│                                            │
│ ✓ Receive HTTP requests                    │
│ ✓ Validate HTTP parameters                 │
│ ✓ Call appropriate service methods         │
│ ✓ Return results to view                   │
│                                            │
│ ✗ NO database queries                      │
│ ✗ NO business logic                        │
│ ✗ NO file operations                       │
└────────────────────────────────────────────┘
                      ↓
┌────────────────────────────────────────────┐
│             SERVICE LAYER                   │
│  (services/AuthService.php, etc.)          │
│                                            │
│ ✓ Business logic (login, register, etc)   │
│ ✓ Validate business rules                  │
│ ✓ Coordinate multiple operations           │
│ ✓ Call repository/email methods            │
│ ✓ Error handling                           │
│                                            │
│ ✗ NO direct database queries               │
│ ✗ NO HTML or view logic                    │
│ ✗ NOT directly called from views           │
└────────────────────────────────────────────┘
                      ↓
┌────────────────────────────────────────────┐
│         REPOSITORY LAYER (DAO)              │
│  (repositories/UserRepository.php)         │
│                                            │
│ ✓ Database queries ONLY                    │
│ ✓ CRUD operations                          │
│ ✓ Prepared statements (safe)               │
│ ✓ Return raw data                          │
│                                            │
│ ✗ NO business logic                        │
│ ✗ NO validation (just fail/success)        │
│ ✗ NO email sending                         │
│ ✗ NO HTML generation                       │
└────────────────────────────────────────────┘
                      ↓
┌────────────────────────────────────────────┐
│           INFRASTRUCTURE LAYER              │
│  (Config, Database Connection, SMTP)       │
│                                            │
│ ✓ Configuration management                 │
│ ✓ Database connections                     │
│ ✓ External services (email)                │
│ ✓ File systems                             │
│                                            │
│ ✗ NO business logic                        │
│ ✗ NO HTTP handling                         │
└────────────────────────────────────────────┘
```

---

## Service Dependencies

```
UserController
    ↓
    ├─→ AuthService
    │       ├─→ UserRepository
    │       │   ├─→ Config (DB settings)
    │       │   └─→ Database
    │       │
    │       └─→ EmailService
    │           ├─→ Config (SMTP settings)
    │           └─→ PHPMailer (external)
    │
    └─→ UserRepository
        ├─→ Config (DB settings)
        └─→ Database
```

---

## Security Layers

```
┌─────────────────────────────────────┐
│         INPUT VALIDATION             │
│  Views validate before sending       │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│       SERVICE VALIDATION             │
│  Services validate business logic    │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│      REPOSITORY PROTECTION           │
│  Prepared statements (no SQL inject) │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│     PASSWORD HASHING (bcrypt)        │
│  Never store plain passwords         │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│    TOKEN EXPIRY & ONE-TIME USE       │
│  Reset tokens can't be reused        │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│   FAILED LOGIN TRACKING & LOCKOUT    │
│  10 attempts = 15 min lockout        │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│     SESSION MANAGEMENT               │
│  Secure session handling             │
└─────────────────────────────────────┘
```

---

## Configuration Hierarchy

```
┌─────────────────────────────────────┐
│   Environment Variables              │
│   (highest priority)                 │
│   Example: DB_HOST=prod.db.com       │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│   Config Service (Config.php)        │
│   Loads from environment or defaults │
│                                     │
│   $config->get('DB_HOST')           │
│   $config->get('MAIL_USERNAME')     │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│   Controllers/Services               │
│   Use config, never hardcode values  │
│                                     │
│   $dbHost = $config->get('DB_HOST') │
└──────────────┬──────────────────────┘
               │
               ↓
┌─────────────────────────────────────┐
│   NO Hardcoded Values Anywhere!      │
│   (lowest priority - NOT USED!)      │
└─────────────────────────────────────┘
```

---

## Database Schema Relationships

```
┌──────────────────┐
│    users         │
├──────────────────┤
│ id (PK)          │
│ email (UNIQUE)   │
│ password         │
│ role             │
│ created_by (FK)  │──→ users.id (self-reference)
│ status           │
│ reset_token      │
│ token_expiry     │
└──────────────────┘
        │
        │ (1:N)
        │
        ↓
┌──────────────────────┐
│ password_reset_tokens│
├──────────────────────┤
│ id (PK)              │
│ user_id (FK)         │──→ users.id
│ token (UNIQUE)       │
│ expires_at           │
│ used                 │
│ used_at              │
└──────────────────────┘
        
┌──────────────────┐
│   projects       │
├──────────────────┤
│ id (PK)          │
│ project_name     │
│ client_id (FK)   │──→ users.id
│ created_by (FK)  │──→ users.id
│ status           │
│ created_at       │
└──────────────────┘
        │
        │ (1:N)
        │
        ↓
┌──────────────────────┐
│ project_assignments  │
├──────────────────────┤
│ id (PK)              │
│ project_id (FK)      │──→ projects.id
│ engineer_id (FK)     │──→ users.id
│ assigned_by (FK)     │──→ users.id
│ assigned_at          │
└──────────────────────┘
        
┌──────────────────┐
│      tasks       │
├──────────────────┤
│ id (PK)          │
│ project_id (FK)  │──→ projects.id
│ assigned_to (FK) │──→ users.id
│ task_name        │
│ status           │
│ created_at       │
└──────────────────┘
```

---

## Request/Response Cycle

```
START
  │
  ↓
User submits form (POST request)
  │
  ↓
┌─────────────────────────┐
│ View Layer              │
│ (login.php)             │
│ · Receive POST data     │
│ · Basic validation      │
└────────────┬────────────┘
             │
             ↓
┌──────────────────────────────────┐
│ Controller                        │
│ (UserController)                 │
│ · receive($email, $password)     │
│ · call service method            │
└────────────┬─────────────────────┘
             │
             ↓
┌──────────────────────────────────┐
│ Service                           │
│ (AuthService::login)             │
│ · validate                        │
│ · call repository                │
│ · handle business logic          │
│ · return result                  │
└────────────┬─────────────────────┘
             │
             ↓
┌──────────────────────────────────┐
│ Repository                        │
│ (UserRepository::getLoginUser)   │
│ · execute SQL                     │
│ · return data or null            │
└────────────┬─────────────────────┘
             │
             ↓
        Database executes query
             │
             ↓
    Return result set
             │
             ↓ (back up the stack)
┌──────────────────────────────────┐
│ Repository returns data           │
└────────────┬─────────────────────┘
             │
             ↓
┌──────────────────────────────────┐
│ Service processes result          │
│ · verify password                 │
│ · reset failed attempts          │
│ · return success/error           │
└────────────┬─────────────────────┘
             │
             ↓
┌──────────────────────────────────┐
│ Controller passes to view         │
└────────────┬─────────────────────┘
             │
             ↓
┌──────────────────────────────────┐
│ View displays result              │
│ · Show success message            │
│ · Or show error                   │
│ · Render HTML                     │
└────────────┬─────────────────────┘
             │
             ↓
         HTTP Response sent to browser
             │
             ↓
END
```

---

## Summary

This architecture ensures:
- **Clear Separation** - Each layer has one responsibility
- **Maintainability** - Easy to modify and test
- **Security** - Multiple layers of protection
- **Scalability** - Easy to add new features
- **Professional** - Industry-standard patterns

---

**Version:** 1.0
**Created:** March 3, 2026
**Status:** ✅ Complete
