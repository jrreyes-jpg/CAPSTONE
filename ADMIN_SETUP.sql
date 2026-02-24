-- 🔐 EDGE AUTOMATION - FIRST ADMIN ACCOUNT SETUP
-- 
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Click on your database: edge_project_asset_inventory_db
-- 3. Click "SQL" tab at the top
-- 4. Copy the SQL below and paste it
-- 5. Click "Go"
--
-- IMPORTANT: Change the password hash!
-- Generate a hashed password at: https://www.php.net/manual/en/function.password-hash.php
-- Or use this PHP: php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"

-- Example Admin Account with password: admin123
-- Hash: $2y$10$8q2AkL7mVxZ5bJ9pU3rH6.dKVdL7sn5kJ8mK9p2Q1R0S1T2U3V4W5

INSERT INTO users (full_name, email, password, role, availability_status) 
VALUES (
  'Administrator',
  'admin@edgeautomation.com',
  '$2y$10$8q2AkL7mVxZ5bJ9pU3rH6.dKVdL7sn5kJ8mK9p2Q1R0S1T2U3V4W5',
  'admin',
  'available'
);

-- After running this, you can login with:
-- Email: admin@edgeautomation.com
-- Password: admin123

-- 📌 To create your own admin account:
-- 1. Generate a hash for your desired password using PHP
-- 2. Replace the hash above
-- 3. Replace the email with your admin email
-- 4. Run the SQL
