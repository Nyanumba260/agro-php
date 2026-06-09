<?php
include 'includes/config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $farm_name = sanitize($_POST['farm_name'] ?? '');
    $farm_location = sanitize($_POST['farm_location'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All required fields must be filled';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email already registered';
        } else {
            // Insert new user
            $hashed_password = hashPassword($password);
            $insert_query = "INSERT INTO users (name, email, phone, password, role, farm_name, farm_location) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('sssssss', $name, $email, $phone, $hashed_password, $role, $farm_name, $farm_location);

            if ($stmt->execute()) {
                $success = 'Account created successfully! Please login.';
                // Redirect to login after 2 seconds
                header('Refresh: 2; url=login.php');
            } else {
                $error = 'Error creating account. Please try again.';
            }
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
    <title>Register - AGROBIASHARA</title>
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

    <!-- Register Form -->
    <div class="form-container" style="max-width: 600px;">
        <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-green);">
            <i class="fas fa-user-plus"></i> Create Account
        </h2>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>I am a: *</label>
                <div style="display: flex; gap: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="radio" name="role" value="buyer" checked onchange="toggleFarmerFields()"> Buyer
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="radio" name="role" value="farmer" onchange="toggleFarmerFields()"> Farmer
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required placeholder="Your name">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required placeholder="your@email.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone *</label>
                <input type="tel" id="phone" name="phone" required placeholder="+254 7XX XXX XXX">
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required placeholder="Create password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
            </div>

            <div id="farmerFields" style="display: none;">
                <div class="form-group">
                    <label for="farm_name">Farm Name</label>
                    <input type="text" id="farm_name" name="farm_name" placeholder="Your farm name">
                </div>
                <div class="form-group">
                    <label for="farm_location">Farm Location</label>
                    <input type="text" id="farm_location" name="farm_location" placeholder="County/Region">
                </div>
            </div>

            <button type="submit" class="form-submit">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; color: var(--text-light);">
            Already have an account? <a href="login.php" style="color: var(--primary-green); font-weight: 600; text-decoration: none;">Login</a>
        </p>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 AGROBIASHARA. All rights reserved.</p>
    </footer>

    <script>
        function toggleFarmerFields() {
            const farmerFields = document.getElementById('farmerFields');
            const role = document.querySelector('input[name="role"]:checked').value;
            farmerFields.style.display = role === 'farmer' ? 'block' : 'none';
        }
    </script>
</body>
</html>
