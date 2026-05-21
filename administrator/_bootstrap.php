<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('ff_super_admin');
    session_start();
}

function admin_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_pdo(): PDO {
    return DBManager::master();
}

function admin_user(): ?array {
    if (empty($_SESSION['super_admin_id'])) {
        return null;
    }
    $stmt = admin_pdo()->prepare("SELECT * FROM ff_super_admins WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$_SESSION['super_admin_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function admin_require_login(): array {
    $user = admin_user();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function admin_flash(?string $message = null, string $type = 'success'): ?array {
    if ($message !== null) {
        $_SESSION['_admin_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['_admin_flash'] ?? null;
    unset($_SESSION['_admin_flash']);
    return $flash;
}

function admin_log(int $adminId, string $action, ?int $companyId = null, ?int $targetUserId = null, array $meta = []): void {
    try {
        $stmt = admin_pdo()->prepare("INSERT INTO ff_super_admin_log (admin_id, action, target_company_id, target_user_id, meta, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$adminId, $action, $companyId, $targetUserId, $meta ? json_encode($meta) : null, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {}
}

function tenant_pdo_for_company(int $companyId): PDO {
    return DBManager::forCompany($companyId);
}

function admin_safe_rows(PDO $pdo, string $sql, array $params = []): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function admin_safe_row(PDO $pdo, string $sql, array $params = []): ?array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function admin_safe_count(PDO $pdo, string $sql, array $params = []): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function admin_company_by_id(int $companyId): ?array {
    return admin_safe_row(admin_pdo(), "SELECT * FROM ff_companies WHERE id = ? LIMIT 1", [$companyId]);
}

function admin_company_db(int $companyId): ?array {
    return admin_safe_row(admin_pdo(), "SELECT * FROM ff_company_databases WHERE company_id = ? LIMIT 1", [$companyId]);
}
