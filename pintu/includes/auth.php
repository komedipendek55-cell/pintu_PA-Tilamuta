<?php
/** Auth helpers */

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];
    $_SESSION['login_at'] = time();

    $stmt = Database::pdo()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([(int)$user['id']]);
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function find_user_by_username(string $username): ?array {
    $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return $u ?: null;
}