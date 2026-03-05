<?php
/**
 * Super Admin Dashboard
 * 
 * Super Admin controls:
 * - Create accounts for engineers, manpower engineers, and clients
 * - View all users
 * - Manage user status
 * - Change passwords (via email reset)
 * 
 * All business logic in AuthService/UserController
 */


require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../controllers/UserController.php';

// Verify super admin access
require_role('super_admin');

$userController = new UserController();
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'dashboard';

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (empty($fullName) || empty($email) || empty($role)) {
        $error = 'Full name, email, and role are required.';
    } else {
        // Call controller to create account
        $result = $userController->{'create' . ucfirst($role)}($fullName, $email, $phone, $_SESSION['user_id']);

        if ($result['success']) {
            $message = $result['message'] ?? 'Account created successfully!';
            $activeTab = 'users';
        } else {
            $error = $result['error'] ?? 'Failed to create account.';
        }
    }
}

// Get users by role
$engineers = $userController->getEngineers();
$manpower = $userController->getManpower();
$clients = $userController->getClients();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Edge Automation</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
    <?php include __DIR__ . '/../components/sidebar_super_admin.php'; ?>
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <a href="/codesamplecaps/views/auth/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'dashboard' ? 'display: block;' : 'display: none;'; ?>">
                <h2 style="margin-bottom: 20px;">System Overview</h2>
<div class="stats">
    <div class="stat-card">
        <h3>Engineers</h3>
        <div class="number counter" data-target="<?php echo count($engineers); ?>">0</div>
    </div>
    <div class="stat-card">
        <h3>Manpower</h3>
        <div class="number counter" data-target="<?php echo count($manpower); ?>">0</div>
    </div>
    <div class="stat-card">
        <h3>Clients</h3>
        <div class="number counter" data-target="<?php echo count($clients); ?>">0</div>
    </div>
    <div class="stat-card">
        <h3>Total Users</h3>
        <div class="number counter" data-target="<?php echo count($engineers) + count($manpower) + count($clients) + 1; ?>">0</div>
    </div>
</div>            </div>

            <!-- Create Account Tab -->
            <div id="create" class="tab-content <?php echo $activeTab === 'create' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'create' ? 'display: block;' : 'display: none;'; ?>">
                <div class="form-section">
                    <h2>Create New Account</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone">
                            </div>
                            <div class="form-group">
                                <label for="role">Role *</label>
                                <select id="role" name="role" required>
                                    <option value="">Select a role</option>
                                    <option value="engineer">Engineer</option>
                                    <option value="manpower">Manpower Engineer</option>
                                    <option value="client">Client</option>
                                </select>
                            </div>
                        </div>

                        <p style="color: #666; font-size: 13px; margin-bottom: 20px;">
                            ℹ️ A temporary password will be generated and sent to the email address.
                            The user must reset their password on first login.
                        </p>

                        <button type="submit" name="create_account" class="submit-btn">Create Account</button>
                    </form>
                </div>
            </div>

            <!-- View Users Tab -->
            <div id="users" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'users' ? 'display: block;' : 'display: none;'; ?>">
                <!-- Engineers -->
                <h2 style="margin-top: 20px; margin-bottom: 15px;">Engineers</h2>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($engineers)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No engineers</td></tr>
                            <?php else: ?>
                                <?php foreach($engineers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td><span class="status-badge status-active">Active</span></td>
                                        <td>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Manpower -->
                <h2 style="margin-top: 30px; margin-bottom: 15px;">Manpower Engineers</h2>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($manpower)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No manpower engineers</td></tr>
                            <?php else: ?>
                                <?php foreach($manpower as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td><span class="status-badge status-active">Active</span></td>
                                        <td>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Clients -->
                <h2 style="margin-top: 30px; margin-bottom: 15px;">Clients</h2>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No clients</td></tr>
                            <?php else: ?>
                                <?php foreach($clients as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td><span class="status-badge status-active">Active</span></td>
                                        <td>
                                            <button class="action-btn edit">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'profile' ? 'display: block;' : 'display: none;'; ?>">
                <div class="form-section">
                    <h2>My Profile</h2>
                    <p style="margin-bottom: 20px; color: #666;">
                        Profile management coming soon...
                    </p>
                </div>
            </div>
        </main>
    </div>

    <script src="/codesamplecaps/public/assets/js/script.js"></script>
</body>
</html>
