# 🚀 Quick Start Guide - Edge Automation Refactored

## Step 1: Update Database Schema

Open **phpMyAdmin** or your MySQL client:

```
1. Go to: http://localhost/phpmyadmin
2. Select database: edge_project_asset_inventory_db
3. Click "SQL" tab
4. Copy and paste the entire content from:
   database/migrations/2026_03_03_improved_schema.sql
5. Click "Go" to execute
```

**OR** via terminal:
```bash
cd c:\xampp\htdocs\codesamplecaps
mysql -u root -p edge_project_asset_inventory_db < database/migrations/2026_03_03_improved_schema.sql
```

> **Note:** Password is empty unless you set one. Just press Enter when prompted.

---

## Step 2: Update Gmail Configuration

### Get Your Gmail App Password:
1. Go to: https://myaccount.google.com/apppasswords
2. Sign in to your Google account
3. Select "Mail" and "Windows Computer" (or your device)
4. Google generates a **16-character password** - copy it

### Update Config:
Edit: `config/Config.php`

```php
// Line 30-35: Change these to your Gmail
$this->settings['MAIL_USERNAME'] = 'your-email@gmail.com';
$this->settings['MAIL_PASSWORD'] = 'xxxx xxxx xxxx xxxx';  // Your app password
$this->settings['MAIL_FROM_ADDRESS'] = 'your-email@gmail.com';
$this->settings['MAIL_FROM_NAME'] = 'Edge Automation';
```

---

## Step 3: Test the System

### Default Accounts:
- **Username:** admin@edge.com
- **Password:** You need to reset it first!

### Reset Admin Password:
1. Go to: http://localhost/codesamplecaps/views/auth/forgot.php
2. Enter: admin@edge.com
3. Check your Gmail inbox for reset link
4. Click link and set new password
5. Login with new password

---

## Step 4: First Login

1. Go to: http://localhost/codesamplecaps/public/login.php
2. Login with admin@edge.com and your new password
3. You'll be redirected to Admin Dashboard
4. **YOU ARE NOW A SUPER ADMIN** ✅

---

## Step 5: Create Your First User

### From Admin Dashboard:
1. Click "➕ Create Account" tab
2. Fill the form:
   - **Full Name:** John Engineer
   - **Email:** john@example.com
   - **Phone:** 1234567890 (optional)
   - **Role:** Engineer
3. Click "Create Account"
4. System sends welcome email to john@example.com
5. John goes to email, clicks reset password link
6. John sets his password
7. John can now login

---

## 📧 Testing Email (Locally)

If email isn't working locally, use **Mailtrap** for testing:

1. Go to: https://mailtrap.io (free account)
2. Create new inbox
3. Get SMTP credentials from Mailtrap
4. Update `config/Config.php`:

```php
$this->settings['MAIL_HOST'] = 'live.smtp.mailtrap.io';
$this->settings['MAIL_PORT'] = 587;
$this->settings['MAIL_USERNAME'] = 'your_mailtrap_user';
$this->settings['MAIL_PASSWORD'] = 'your_mailtrap_pass';
```

All emails will appear in Mailtrap inbox instead of going to Gmail.

---

## 🔧 Troubleshooting

### Problem: "Database Connection Failed"
**Solution:** Check `config/database.php`
- Host: `127.0.0.1` (not localhost)
- Port: `3307` (XAMPP uses this by default)
- User: `root`
- Password: (empty)
- Database: `edge_project_asset_inventory_db`

### Problem: "Can't send email"
**Solution:** Check these:
1. Gmail 2FA enabled?
2. Using "App Password" (not regular password)?
3. Credentials correct in `config/Config.php`?
4. SMTP port 587 allowed by firewall?
5. Try Mailtrap instead for local testing

### Problem: "User already exists"
**Solution:** Email is unique. Use different email.

### Problem: "Invalid or expired reset link"
**Solution:** Token expires in 60 minutes. Request new reset link.

### Problem: "Too many failed attempts"
**Solution:** Account locked for 15 minutes. Wait or create new user.

---

## 📂 Project Structure (Quick Reference)

```
Core Files You'll Modify:
├── config/Config.php ................. Email & DB settings
├── services/AuthService.php .......... Login/reset logic
├── services/EmailService.php ......... Email templates
├── repositories/UserRepository.php ... Database queries
├── controllers/UserController.php .... HTTP handlers
└── views/dashboards/admin_dashboard.php .. Admin interface
```

---

## 🎯 Common Tasks

### Create Account Programmatically:
```php
$controller = new UserController();
$result = $controller->createEngineer(
    'Bob Engineer',
    'bob@company.com',
    '0987654321',
    $_SESSION['user_id']
);
echo $result['success'] ? "✅ Created" : "❌ " . $result['error'];
```

### Verify Password Reset:
```php
$auth = new AuthService();
$result = $auth->resetPassword('token_from_email', 'newPassword123');
if ($result['success']) {
    echo "Password updated! User can now login.";
}
```

### Get All Users:
```php
$repo = new UserRepository();
$engineers = $repo->getUsersByRole('engineer');
$clients = $repo->getUsersByRole('client');
```

---

## ✅ Checklist

- [ ] Database schema imported
- [ ] Gmail app password configured
- [ ] Admin account password reset
- [ ] Admin dashboard accessed
- [ ] Test account created
- [ ] Test account email received
- [ ] Test account password reset via email
- [ ] Test login with test account
- [ ] All working ✨

---

## 📞 Next Features to Build

Based on this architecture, you can easily add:
1. ✅ ~~User authentication~~ (DONE)
2. ✅ ~~Admin account management~~ (DONE)
3. ✅ ~~Password reset via email~~ (DONE)
4. ⏳ Change password for logged-in users
5. ⏳ User profile editing
6. ⏳ Deactivate/delete users
7. ⏳ Two-factor authentication
8. ⏳ Audit logs
9. ⏳ Role-based dashboards
10. ⏳ Project assignment

---

## 🎓 Learning Tips

1. **Read ARCHITECTURE_GUIDE.md first** - Understand the layers
2. **Trace login flow** - Follow code from login.php → AuthService → UserRepository
3. **Modify existing features** - Don't build new, modify what exists
4. **Test each layer separately** - Test Repository → Service → Controller
5. **Use this pattern for everything** - Config → Repository → Service → Controller → View

---

## 💪 You're Now a Professional Developer!

Your code now follows:
- ✅ Separation of Concerns (layers)
- ✅ DRY Principle (reusable components)
- ✅ Security Best Practices (hashing, tokens, rate limiting)
- ✅ SOLID Principles (clean code)
- ✅ Professional Architecture (enterprise level)

Keep this structure for all future features.

---

**Questions?** Check ARCHITECTURE_GUIDE.md for detailed explanation of each component.

**Version:** 2.0 - Production Ready
**Last Updated:** March 3, 2026
