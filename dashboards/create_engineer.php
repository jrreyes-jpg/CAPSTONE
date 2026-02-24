<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$error = "";
$success = "";

// Create engineer account
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_engineer'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'engineer';

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $success = "Engineer account created successfully!";
                // Clear form
                $_POST = array();
            } else {
                $error = "Error creating account. Please try again.";
            }
        }
    }
}

// Get all engineers
$engineers_result = $conn->query("SELECT user_id, full_name, email, created_at FROM users WHERE role = 'engineer' ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Engineer Account - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
    body {
        background: #f5f5f5;
        font-family: 'Poppins', sans-serif;
    }
    
    .admin-container {
        display: flex;
        min-height: 100vh;
        gap: 20px;
        padding: 20px;
    }
    
    .sidebar {
        width: 250px;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        height: fit-content;
    }
    
    .sidebar a {
        display: block;
        padding: 12px;
        margin: 10px 0;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        text-align: center;
        cursor: pointer;
        transition: 0.3s;
    }
    
    .sidebar a:hover {
        background: #0056b3;
    }
    
    .main-content {
        flex: 1;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .form-section, .engineers-section {
        margin-bottom: 40px;
    }
    
    .form-section h2 {
        color: #333;
        margin-bottom: 20px;
    }
    
    input {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
        font-family: inherit;
    }
    
    button {
        background: #28a745;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 10px;
        transition: 0.3s;
    }
    
    button:hover {
        background: #218838;
    }
    
    .error-message {
        color: #d9534f;
        padding: 10px;
        background: #f2dede;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    .success-message {
        color: #3c763d;
        padding: 10px;
        background: #dff0d8;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    table th, table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    table th {
        background: #007bff;
        color: white;
    }
    
    table tr:hover {
        background: #f5f5f5;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .user-info {
        text-align: right;
    }
</style>
</head>
<body>

<div class="admin-container">
    <div class="sidebar">
        <h3>Admin Menu</h3>
        <a href="create_engineer.php">Create Engineer</a>
        <a href="../dashboards/admin_dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Create Engineer Account</h1>
            <div class="user-info">
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                <small><?php echo $_SESSION['role']; ?></small>
            </div>
        </div>
        
        <div class="form-section">
            <h2>Add New Engineer</h2>
            
            <?php if($error): ?>
                <p class="error-message"><?php echo $error; ?></p>
            <?php endif; ?>
            
            <?php if($success): ?>
                <p class="success-message"><?php echo $success; ?></p>
            <?php endif; ?>
            
            <form method="POST">
                <input type="text" name="full_name" placeholder="Full Name" required 
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                
                <input type="email" name="email" placeholder="Email Address" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                
                <input type="password" name="password" placeholder="Temporary Password" required>
                
                <button type="submit" name="create_engineer">Create Engineer Account</button>
            </form>
        </div>
        
        <div class="engineers-section">
            <h2>All Engineers (<?php echo $engineers_result->num_rows; ?>)</h2>
            
            <?php if($engineers_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($engineer = $engineers_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($engineer['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($engineer['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($engineer['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No engineers created yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
