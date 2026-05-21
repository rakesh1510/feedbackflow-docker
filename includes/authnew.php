<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-manager.php';

class Auth {

    public static function login($email, $password) {
        $master = DBManager::master();

        $stmt = $master->query("SELECT company_id, db_name FROM ff_company_databases WHERE db_status='active'");
        $companies = $stmt->fetchAll();

        foreach ($companies as $c) {
            try {
                $tenant = DBManager::tenantByDbName($c['db_name']);
                $userStmt = $tenant->prepare("SELECT * FROM ff_users WHERE email = ? LIMIT 1");
                $userStmt->execute([$email]);
                $user = $userStmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['company_id'] = $c['company_id'];
                    return true;
                }
            } catch (Throwable $e) {}
        }

        return false;
    }

    public static function user() {
        if (empty($_SESSION['user_id']) || empty($_SESSION['company_id'])) return null;

        return DBManager::tenantFetch(
            $_SESSION['company_id'],
            "SELECT * FROM ff_users WHERE id = ? AND is_active = 1",
            [$_SESSION['user_id']]
        );
    }

    public static function check() {
        return self::user() !== null;
    }

    public static function require() {
        $user = self::user();
        if (!$user) {
            header("Location: /index.php");
            exit;
        }
        return $user;
    }

    public static function logout() {
        session_destroy();
    }
}
