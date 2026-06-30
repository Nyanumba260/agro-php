<?php
include 'includes/config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if (getUserRole() === ROLE_FARMER) {
        header('Location: farmer-dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (verifyPassword($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === ROLE_FARMER) {
                    header('Location: farmer-dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AGROBIASHARA</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <div class="logo-placeholder">
                    <i class="fas fa-leaf"></i>
                </div>
                <span>AGROBIASHARA</span>
            </a>
        </div>
    </header>

    <!-- Login Form -->
    <div class="form-container">
        <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-green);">
            <i class="fas fa-sign-in-alt"></i> Login
        </h2>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="your@email.com">
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required placeholder="Enter password">
            </div>

            <button type="submit" class="form-submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; color: var(--text-light);">
            Don't have an account? <a href="register.php" style="color: var(--primary-green); font-weight: 600; text-decoration: none;">Register here</a>
        </p>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2026 AGROBIASHARA. All rights reserved.</p>
    </footer>
</body>
</html>
