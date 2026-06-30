<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'agrobiashara');

// Start session
session_start();

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Define user roles
define('ROLE_BUYER', 'buyer');
define('ROLE_FARMER', 'farmer');
define('ROLE_ADMIN', 'admin');

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function getUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Redirect to specific role
function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        header('Location: index.php');
        exit;
    }
}

// Sanitize input
function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(trim($input));
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// M-Pesa configuration
// Paste your Daraja credentials here
// For local testing, use sandbox and a public callback URL via ngrok

define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: (isset($_ENV['MPESA_CONSUMER_KEY']) ? $_ENV['MPESA_CONSUMER_KEY'] : 'ejHQkvAr0GqxpGjNRdxg3A84tJbnj2mk5vANmAGnP7PsgSjC'));
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: (isset($_ENV['MPESA_CONSUMER_SECRET']) ? $_ENV['MPESA_CONSUMER_SECRET'] : 'o5N8FZm5e2P191tcgViQeCuXOYPzDYVt2wvIP8JMhDS3Vz9soOoEmGhaQGn8E7PC'));
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: (isset($_ENV['MPESA_SHORTCODE']) ? $_ENV['MPESA_SHORTCODE'] : '174379'));
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: (isset($_ENV['MPESA_PASSKEY']) ? $_ENV['MPESA_PASSKEY'] : ''));
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: (isset($_ENV['MPESA_CALLBACK_URL']) ? $_ENV['MPESA_CALLBACK_URL'] : 'https://dashboard.ngrok.com/early-access'));
define('MPESA_ENV', getenv('MPESA_ENV') ?: (isset($_ENV['MPESA_ENV']) ? $_ENV['MPESA_ENV'] : 'sandbox'));
?>
