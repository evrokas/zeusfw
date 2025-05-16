Extract from DeepSeek chat
https://chat.deepseek.com/a/chat/s/9f5258a3-f4cc-4690-9a73-dcaf91f7645b

Regarding Login Page Redirect after a session-expired page request

At the end of the document form reloading is discussed




1. Session Management & Validation

// config/security.php
session_start();

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Validate session integrity
function validate_session() {
    if (!isset($_SESSION['ip']) || $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        return false;
    }
    if (!isset($_SESSION['ua']) || $_SESSION['ua'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        return false;
    }
    return true;
}

/*******************/

2. Secure Auth Check Middleware

// middleware/auth_check.php
function require_auth() {
    session_start();
    
    // Validate session integrity first
    if (!validate_session()) {
        handle_unauthorized();
    }
    
    // Check if user is authenticated
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        handle_unauthorized();
    }
    
    // Additional security checks
    if ($_SESSION['last_activity'] < time() - 1800) { // 30 minute timeout
        handle_unauthorized();
    }
    
    $_SESSION['last_activity'] = time(); // Update activity timestamp
}

function handle_unauthorized() {
    // Store requested URL if it's a GET request
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION['return_url'] = validate_return_url($_SERVER['REQUEST_URI']);
    }
    
    // Clear sensitive session data
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
    
    header('Location: /login.php');
    exit();
}

function validate_return_url($url) {
    $parsed = parse_url($url);
    
    // Only allow relative URLs or same domain absolute URLs
    if (!isset($parsed['host']) || $parsed['host'] === $_SERVER['HTTP_HOST']) {
        // Sanitize the URL
        $clean = filter_var($url, FILTER_SANITIZE_URL);
        return $clean ?: '/'; // Default to home if invalid
    }
    
    return '/'; // Fallback to home for external URLs
}

/*******************/

3. Login Page Implementation

// login.php
session_start();

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    // Authenticate user (simplified example)
    if (authenticate_user($_POST['username'], $_POST['password'])) {
        // Regenerate session ID on login
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['last_activity'] = time();
        
        // Redirect to return URL or default page
        $return_url = $_SESSION['return_url'] ?? '/dashboard.php';
        unset($_SESSION['return_url']);
        
        // Final validation before redirect
        header('Location: ' . validate_return_url($return_url));
        exit();
    } else {
        $error = "Invalid credentials";
    }
}


/*******************/

4. Protected Page Implementation

// any protected page
require_once 'middleware/auth_check.php';
require_auth();

// Your normal page content here...


/*******************/

5. Logout Handler

// logout.php
session_start();

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login with optional return URL
header('Location: /login.php');
exit();




/*******************/
1. Password Hashing (bcrypt/Argon2)
bcrypt Implementation (PHP)
// config/password_hashing.php

// Password hashing with bcrypt
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Rehash if needed (when cost factor increases)
function needs_rehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

Argon2 Implementation (PHP 7.2+)
// config/password_hashing.php

function hash_password_argon2($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 2
    ]);
}

function verify_password_argon2($password, $hash) {
    return password_verify($password, $hash);
}

Usage in Registration/Login
// register.php
$hashedPassword = hash_password($_POST['password']);
// Store $hashedPassword in database

// login.php
$user = getUserFromDatabase($_POST['username']);
if ($user && verify_password($_POST['password'], $user['password_hash'])) {
    // Check if needs rehash
    if (needs_rehash($user['password_hash'])) {
        $newHash = hash_password($_POST['password']);
        // Update hash in database
    }
    // Proceed with login
}


/*******************/

2. Login Attempt Throttling
Database-Based Throttling
// config/login_throttling.php

function record_login_attempt($ip, $username, $success) {
    $db = get_db_connection();
    $stmt = $db->prepare(
        "INSERT INTO login_attempts 
        (ip_address, username, attempt_time, successful) 
        VALUES (?, ?, NOW(), ?)"
    );
    $stmt->execute([$ip, $username, $success ? 1 : 0]);
}

function is_login_throttled($ip, $username) {
    $db = get_db_connection();
    
    // Check recent failures from this IP
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts 
        WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE) 
        AND successful = 0"
    );
    $stmt->execute([$ip]);
    $ip_failures = $stmt->fetchColumn();
    
    // Check recent failures for this username
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts 
        WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE) 
        AND successful = 0"
    );
    $stmt->execute([$username]);
    $user_failures = $stmt->fetchColumn();
    
    // Apply throttling rules
    if ($ip_failures >= 10 || $user_failures >= 5) {
        return true;
    }
    
    return false;
}

// Usage in login.php
if (is_login_throttled($_SERVER['REMOTE_ADDR'], $_POST['username'])) {
    header('HTTP/1.1 429 Too Many Requests');
    die('Too many login attempts. Please try again later.');
}



/*******************/
3. Security Headers Implementation

// config/security_headers.php

function set_security_headers() {
    // Strict Transport Security (should be configured at web server level ideally)
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    
    // Content Security Policy
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://trusted.cdn.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https://trusted.cdn.com",
        "font-src 'self' https://trusted.cdn.com",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'", // Same as X-Frame-Options
    ];
    header("Content-Security-Policy: " . implode('; ', $csp));
    
    // Other headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Remove unnecessary headers
    header_remove('X-Powered-By');
    header_remove('Server');
}

// Call this function early in your application
set_security_headers();


Apache Web Server (.htaccess)

<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-src 'none'; object-src 'none'"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always unset X-Powered-By
    Header always unset Server
</IfModule>




/*******************/
Handling Form Submissions with Expired Sessions

2. Server-Side Storage (More Secure)
// Middleware to check session
function check_session() {
  session_start();
  
  if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $_SESSION['pending_post'] = [
        'data' => $_POST,
        'url' => $_SERVER['REQUEST_URI'],
        'timestamp' => time()
      ];
    }
    
    $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /login');
    exit();
  }
  
  // Clear any pending post older than 15 minutes
  if (isset($_SESSION['pending_post']) && 
      time() - $_SESSION['pending_post']['timestamp'] > 900) {
    unset($_SESSION['pending_post']);
  }
}

// After successful login
if (isset($_SESSION['pending_post'])) {
  $post_data = $_SESSION['pending_post']['data'];
  $target_url = $_SESSION['pending_post']['url'];
  unset($_SESSION['pending_post']);
  
  // Process the stored POST data
  // Could either: 
  // 1. Automatically submit with JavaScript
  // 2. Show the form again with values pre-filled
}


/*******************/
3. Hybrid Approach (Recommended)

// 1. Detect expired session on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$authenticated) {
    // Generate unique token for this submission
    $resume_token = bin2hex(random_bytes(16));
    
    // Store in database with expiration (15 minutes)
    $db->prepare("INSERT INTO pending_submissions 
                 (token, data, expires_at) 
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
       ->execute([$resume_token, json_encode($_POST)]);
    
    // Redirect to login with token
    header("Location: /login?resume=" . urlencode($resume_token));
    exit();
}

// 2. After login, check for resume token
if (isset($_GET['resume'])) {
    $token = $_GET['resume'];
    $stmt = $db->prepare("SELECT data FROM pending_submissions 
                         WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    
    if ($data = $stmt->fetch()) {
        $post_data = json_decode($data['data'], true);
        
        // Either:
        // a) Show form with values pre-filled
        // b) Auto-submit with JavaScript
        
        // Delete the token
        $db->prepare("DELETE FROM pending_submissions WHERE token = ?")
           ->execute([$token]);
    }
}




/*******************/
/*******************/
Best Practice Implementation

// In your form handler
if ($session_expired && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Filter and sanitize the POST data
    $sanitized = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, ['password', 'credit_card'])) { // Exclude sensitive fields
            $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Store in session with expiration
    $_SESSION['pending_form'] = [
        'data' => $sanitized,
        'action' => $_SERVER['REQUEST_URI'],
        'expires' => time() + 900 // 15 minutes
    ];
    
    // Redirect to login
    $_SESSION['return_url'] = '/recover-form';
    header('Location: /login');
    exit();
}

// Special route to recover form
if ($_SERVER['REQUEST_URI'] === '/recover-form' && isset($_SESSION['pending_form'])) {
    if (time() > $_SESSION['pending_form']['expires']) {
        unset($_SESSION['pending_form']);
        header('Location: ' . $_SESSION['pending_form']['action']);
        exit();
    }
    
    // Show form with values pre-filled
    echo '<form action="'.htmlspecialchars($_SESSION['pending_form']['action']).'" method="POST">';
    foreach ($_SESSION['pending_form']['data'] as $name => $value) {
        echo '<input type="hidden" name="'.htmlspecialchars($name).'" 
               value="'.htmlspecialchars($value).'">';
    }
    // Add new CSRF token
    echo '<input type="hidden" name="csrf_token" value="'.generate_csrf_token().'">';
    echo '<button type="submit">Resubmit Form</button>';
    echo '</form>';
    unset($_SESSION['pending_form']);
    exit();
}