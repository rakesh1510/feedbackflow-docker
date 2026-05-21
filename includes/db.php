<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/db-manager.php';

class DB {
    private static array $pdoPool = [];
    private static ?string $forcedMode = null; // master|tenant|tenant_company
    private static ?int $forcedCompanyId = null;

    public static function useMaster(): void {
        self::$forcedMode = 'master';
        self::$forcedCompanyId = null;
    }

    public static function useTenantForCompany(int $companyId): void {
        self::$forcedMode = 'tenant_company';
        self::$forcedCompanyId = $companyId;
    }

    public static function useTenantFromSession(): void {
        self::$forcedMode = 'tenant';
        self::$forcedCompanyId = null;
    }

    public static function resetContext(): void {
        self::$forcedMode = null;
        self::$forcedCompanyId = null;
    }

    public static function withMaster(callable $callback) {
        $prevMode = self::$forcedMode;
        $prevCompany = self::$forcedCompanyId;
        self::useMaster();
        try {
            return $callback();
        } finally {
            self::$forcedMode = $prevMode;
            self::$forcedCompanyId = $prevCompany;
        }
    }

    public static function withTenant(int $companyId, callable $callback) {
        $prevMode = self::$forcedMode;
        $prevCompany = self::$forcedCompanyId;
        self::useTenantForCompany($companyId);
        try {
            return $callback();
        } finally {
            self::$forcedMode = $prevMode;
            self::$forcedCompanyId = $prevCompany;
        }
    }

    public static function connect(): PDO {
        $mode = self::resolveMode();

        if ($mode === 'master') {
            return DBManager::master();
        }

        $companyId = self::resolveCompanyId();
        if (!$companyId) {
            return DBManager::master();
        }

        $key = 'tenant_' . $companyId;
        if (!isset(self::$pdoPool[$key])) {
            self::$pdoPool[$key] = DBManager::forCompany($companyId);
        }
        return self::$pdoPool[$key];
    }

    private static function resolveMode(): string {
        if (self::$forcedMode === 'master') {
            return 'master';
        }
        if (self::$forcedMode === 'tenant_company' || self::$forcedMode === 'tenant') {
            return 'tenant';
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!empty($_SESSION['super_admin_id'])) {
                return 'master';
            }
            if (!empty($_SESSION['company_id'])) {
                return 'tenant';
            }
        }

        return 'master';
    }

    private static function resolveCompanyId(): ?int {
        if (self::$forcedMode === 'tenant_company') {
            return self::$forcedCompanyId ?: null;
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['company_id'])) {
            return (int)$_SESSION['company_id'];
        }
        return null;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function fetchColumn(string $sql, array $params = []) {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function insert(string $table, array $data): int {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return (int) self::connect()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $stmt = self::query("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int {
        $stmt = self::query("DELETE FROM `$table` WHERE $where", $params);
        return $stmt->rowCount();
    }

    public static function count(string $sql, array $params = []): int {
        return (int) self::query($sql, $params)->fetchColumn();
    }
}
