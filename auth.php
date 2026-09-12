<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
    session_start();
}
function loggedIn(): bool { return isset($_SESSION['user_id']); }
function requireLogin(bool $json=false): void {
    if (loggedIn()) return;
    if ($json) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error'=>'Authentication required.']); exit; }
    header('Location: login.php'); exit;
}
function csrfToken(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verifyCsrf(string $token): void { if (!hash_equals($_SESSION['csrf'] ?? '', $token)) { http_response_code(403); throw new RuntimeException('Invalid security token.'); } }
