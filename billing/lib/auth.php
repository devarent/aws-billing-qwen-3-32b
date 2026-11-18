<?php
/**
 * Autentikasi sederhana berbasis session (cocok untuk demo lokal).
 */

declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect(url('login'));
    }
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(string $email, string $password): bool
{
    $email = strtolower(trim($email));

    // Throttle: maksimal 5 percobaan per menit per sesi.
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
    if ($lockedUntil > time()) {
        return false;
    }

    $user = db_row('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        return false;
    }

    session_regenerate_id(true);
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    // Token CSRF baru setelah login (mencegah session fixation).
    unset($_SESSION['csrf_token']);
    csrf_token();

    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
