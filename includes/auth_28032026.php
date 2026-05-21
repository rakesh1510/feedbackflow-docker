<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db-manager.php';
require_once __DIR__ . '/functions.php';

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('ff_session');
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(string $email, string $password): bool {
        self::start();
        $master = DBManager::master();
        $stmt = $master->query("SELECT company_id, db_name FROM ff_company_databases WHERE db_status = 'active'");
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($companies as $company) {
            try {
                $tenant = DBManager::forCompany((int)$company['company_id']);
                $userStmt = $tenant->prepare("SELECT * FROM ff_users WHERE email = ? AND is_active = 1 LIMIT 1");
                $userStmt->execute([strtolower($email)]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($password, $user['password'])) {
                    self::setSession($user, (int)$company['company_id']);
                    $upd = $tenant->prepare("UPDATE ff_users SET last_login = NOW() WHERE id = ?");
                    $upd->execute([$user['id']]);
                    return true;
                }
            } catch (Throwable $e) {}
        }
        return false;
    }

    public static function register(string $name, string $email, string $password): int|false {
        throw new RuntimeException('Use company signup flow from index.php for tenant registration.');
    }

    public static function logout(): void {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    public static function user(): ?array {
        self::start();
        if (empty($_SESSION['user_id']) || empty($_SESSION['company_id'])) {
            return null;
        }
        DB::useTenantForCompany((int)$_SESSION['company_id']);
        $user = DB::fetch("SELECT * FROM ff_users WHERE id = ? AND is_active = 1", [$_SESSION['user_id']]);
        DB::resetContext();
        return $user ?: null;
    }

    public static function check(): bool {
        return self::user() !== null;
    }

    public static function require(): array {
        $user = self::user();
        if (!$user) {
            header('Location: ' . APP_URL . '/index.php?page=login');
            exit;
        }
        return $user;
    }

    public static function isAdmin(array $user): bool {
        return in_array($user['role'], ['owner', 'admin']);
    }

    public static function canManageProject(array $user, int $projectId): bool {
        if (in_array($user['role'], ['owner', 'admin'])) return true;
        $member = DB::fetch(
            "SELECT role FROM ff_project_members WHERE project_id = ? AND user_id = ?",
            [$projectId, $user['id']]
        );
        return $member && in_array($member['role'], ['admin', 'manager']);
    }

    private static function setSession(array $user, int $companyId = 0): void {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'] ?? null;
        if ($companyId > 0) {
            $_SESSION['company_id'] = $companyId;
        }
    }
}
