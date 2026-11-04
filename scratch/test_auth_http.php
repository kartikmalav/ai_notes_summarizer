<?php
$baseUrl = 'http://localhost/ai_notes_summarizer/';
$cookieFile = __DIR__ . '/cookie_jar.txt';
if (file_exists($cookieFile)) @unlink($cookieFile);

echo "=== 1. TEST SIGNUP PAGE (GET) ===\n";
$ch = curl_init($baseUrl . 'signup.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Signup GET HTTP Code: $code\n";

echo "\n=== 2. TEST REGISTRATION (POST) ===\n";
$testEmail = 'audit_user_' . time() . '@audit.local';
$testPass = 'auditPass123!';
$ch = curl_init($baseUrl . 'signup.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'full_name' => 'Audit Tester',
    'email' => $testEmail,
    'password' => $testPass,
    'course' => 'BCA 3rd Sem'
]));
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Signup POST HTTP Code: $code (Expected 302 redirect)\n";

require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, full_name, email, password_hash FROM users WHERE email = ?");
$stmt->execute([$testEmail]);
$createdUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($createdUser) {
    echo "User created in MySQL! ID: {$createdUser['id']}, Hash Prefix: " . substr($createdUser['password_hash'], 0, 7) . "\n";
    $verifyPass = password_verify($testPass, $createdUser['password_hash']);
    echo "Password verify check: " . ($verifyPass ? "PASS" : "FAIL") . "\n";
} else {
    echo "FAILED to create user in database.\n";
    exit(1);
}

echo "\n=== 3. TEST LOGIN (POST) ===\n";
$ch = curl_init($baseUrl . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'email' => $testEmail,
    'password' => $testPass
]));
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Login POST HTTP Code: $code (Expected 302 redirect)\n";

echo "\n=== 4. TEST AUTHENTICATED DASHBOARD (GET) ===\n";
$ch = curl_init($baseUrl . 'dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Dashboard GET HTTP Code: $code\n";
$hasWelcome = strpos($res, 'Audit Tester') !== false || strpos($res, 'Welcome') !== false;
echo "Contains user greeting: " . ($hasWelcome ? "YES" : "NO") . "\n";

echo "\n=== 5. TEST MY-PDFS PAGE (GET) ===\n";
$ch = curl_init($baseUrl . 'my-pdfs.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "My PDFs GET HTTP Code: $code\n";

echo "\n=== 6. TEST LOGOUT (GET) ===\n";
$ch = curl_init($baseUrl . 'logout.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Logout GET HTTP Code: $code (Expected 302)\n";

echo "\n=== 7. TEST DASHBOARD AFTER LOGOUT ===\n";
$ch = curl_init($baseUrl . 'dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Dashboard after logout HTTP Code: $code (Expected 302 redirect to login.php)\n";

// CLEANUP AUDIT USER
$delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$delStmt->execute([$createdUser['id']]);
echo "\nAudit test user cleaned up cleanly. Existing records preserved.\n";
if (file_exists($cookieFile)) @unlink($cookieFile);

