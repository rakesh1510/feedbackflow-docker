<?php
/**
 * TenantProvisioner — Automatic per-company database setup.
 *
 * When a new company signs up:
 * 1. Creates a dedicated MySQL database  (ff_tenant_<id>_<slug>)
 * 2. Applies the tenant schema (db-tenant-schema.sql)
 * 3. Creates the first company admin user inside the tenant DB
 * 4. Stores encrypted connection info in the master DB
 * 5. Logs every step to ff_provisioning_log
 *
 * Requirements:
 *   - DB_ROOT_USER must have CREATE DATABASE + GRANT OPTION privileges
 *   - The tenant schema SQL must exist at: feedbackflow-complete/db-tenant-schema.sql
 */
class TenantProvisioner {

    // ── MAIN ENTRY POINT ──────────────────────────────────────────────────
    /**
     * Provision a new tenant database for a company.
     *
     * Returns an array:
     *   ['status'  => 'success'|'failed'|'skipped',
     *    'db_name' => 'ff_tenant_1_acmecorp',
     *    'error'   => '...' ]
     */
    public static function provision(
        int    $companyId,
        string $companyName,
        string $companySlug,
        int    $ownerUserId,
        array  $ownerData = []   // ['name'=>…, 'email'=>…, 'password_hash'=>…]
    ): array {
        // Already provisioned?
        if (DBManager::hasTenantDb($companyId)) {
            self::log($companyId, 'provision_skip', 'skipped', 'Tenant DB already exists.');
            return ['status' => 'skipped', 'db_name' => '', 'error' => ''];
        }

        $dbName = self::buildDbName($companyId, $companySlug);

        // Step 1: Create the database
        [$ok, $err] = self::createDatabase($dbName);
        if (!$ok) {
            self::log($companyId, 'create_database', 'failed', $err);
            DBManager::markProvisionFailed($companyId, $err);
            return ['status' => 'failed', 'db_name' => $dbName, 'error' => $err];
        }
        self::log($companyId, 'create_database', 'success', "Created: $dbName");

        // Step 2: Apply tenant schema
        [$ok, $err] = self::applySchema($dbName);
        if (!$ok) {
            self::log($companyId, 'apply_schema', 'failed', $err);
            DBManager::markProvisionFailed($companyId, $err);
            return ['status' => 'failed', 'db_name' => $dbName, 'error' => $err];
        }
        self::log($companyId, 'apply_schema', 'success', "Schema applied to $dbName");

        // Step 3: Store connection info in master DB
        try {
            DBManager::storeConnectionInfo($companyId, [
                'db_host' => DB_HOST,
                'db_port' => 3306,
                'db_name' => $dbName,
                'db_user' => DB_USER,
                'db_pass' => DB_PASS,
            ]);
            self::log($companyId, 'store_connection', 'success', "Stored in ff_company_databases");
        } catch (\Throwable $e) {
            self::log($companyId, 'store_connection', 'failed', $e->getMessage());
            return ['status' => 'failed', 'db_name' => $dbName, 'error' => $e->getMessage()];
        }

        // Step 4: Create first company admin in the tenant DB
        if ($ownerUserId && !empty($ownerData)) {
            [$ok, $err] = self::createTenantAdmin($companyId, $ownerUserId, $ownerData);
            if ($ok) {
                self::log($companyId, 'create_admin', 'success', "Owner user #$ownerUserId seeded in tenant DB");
            } else {
                self::log($companyId, 'create_admin', 'warning', $err);
                // Non-fatal — master DB still has the user
            }
        }

        self::log($companyId, 'provision_complete', 'success', "Tenant DB $dbName ready.");
        return ['status' => 'success', 'db_name' => $dbName, 'error' => ''];
    }

    // ── RUN MIGRATION ON TENANT DB (for schema updates) ──────────────────
    public static function runMigration(int $companyId, string $sql): array {
        try {
            $pdo = DBManager::forCompany($companyId);
            // Split and run each statement
            $statements = self::splitSql($sql);
            foreach ($statements as $stmt) {
                if (trim($stmt)) $pdo->exec($stmt);
            }
            self::log($companyId, 'run_migration', 'success', substr($sql, 0, 100) . '…');
            return [true, ''];
        } catch (\Throwable $e) {
            self::log($companyId, 'run_migration', 'failed', $e->getMessage());
            return [false, $e->getMessage()];
        }
    }

    // ── EXPORT TENANT DATA ────────────────────────────────────────────────
    /**
     * Returns an array of company data for export.
     * Super admin can download this as JSON.
     */
    public static function exportData(int $companyId): array {
        $data = ['company_id' => $companyId, 'exported_at' => date('c'), 'tables' => []];

        $tables = [
            'ff_users'     => "SELECT id, name, email, role, is_active, created_at FROM ff_users",
            'ff_projects'  => "SELECT id, name, slug, description, is_public, created_at FROM ff_projects",
            'ff_feedback'  => "SELECT id, project_id, type, content, status, created_at FROM ff_feedback LIMIT 1000",
            'ff_campaigns' => "SELECT id, name, type, status, created_at FROM ff_campaigns LIMIT 500",
        ];

        foreach ($tables as $table => $sql) {
            try {
                $rows = DBManager::tenantFetchAll($companyId, $sql);
                $data['tables'][$table] = $rows;
            } catch (\Throwable $e) {
                $data['tables'][$table] = ['error' => $e->getMessage()];
            }
        }

        return $data;
    }

    // ── LOGGING ───────────────────────────────────────────────────────────
    public static function log(
        int    $companyId,
        string $action,
        string $status,
        string $detail = '',
        ?int   $createdBy = null
    ): void {
        try {
            $master = DBManager::master();
            $stmt   = $master->prepare(
                "INSERT INTO ff_provisioning_log
                   (company_id, action, status, detail, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $companyId,
                $action,
                $status,
                substr($detail, 0, 1000),
                $createdBy,
            ]);
        } catch (\Throwable $e) {
            // Log table may not exist yet — silently fail
            if (DEBUG_MODE) error_log('TenantProvisioner::log failed: ' . $e->getMessage());
        }
    }

    // ── PRIVATE HELPERS ───────────────────────────────────────────────────

    private static function buildDbName(int $companyId, string $slug): string {
        $safeslug = preg_replace('/[^a-z0-9_]/', '_', strtolower($slug));
        $name     = DB_TENANT_PREFIX . $companyId . '_' . $safeslug;
        // MySQL DB name limit: 64 chars
        return substr($name, 0, 64);
    }

    private static function createDatabase(string $dbName): array {
        try {
            $pdo = DBManager::rootConnection();
            // Create the database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Grant the app user access to this database (idempotent)
            // Escape the values safely
            $dbUser = addslashes(DB_USER);
            $dbHost = addslashes(DB_HOST);
            $dbPass = addslashes(DB_PASS);
            try {
                $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'$dbHost'");
                $pdo->exec("FLUSH PRIVILEGES");
            } catch (\Throwable $e) {
                // GRANT may fail if DB_ROOT_USER === DB_USER and lacks GRANT OPTION.
                // Non-fatal — the user may already have access.
            }

            return [true, ''];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    private static function applySchema(string $dbName): array {
        $schemaFile = dirname(__DIR__) . '/db-tenant-schema.sql';
        if (!file_exists($schemaFile)) {
            return [false, "Tenant schema file not found: $schemaFile"];
        }

        try {
            $pdo = DBManager::rootConnection();
            $pdo->exec("USE `$dbName`");

            $sql  = file_get_contents($schemaFile);
            $stmts = self::splitSql($sql);
            foreach ($stmts as $stmt) {
                if (trim($stmt)) $pdo->exec($stmt);
            }
            return [true, ''];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    private static function createTenantAdmin(int $companyId, int $userId, array $data): array {
        try {
            // The owner user was already created in the MASTER DB by the signup form.
            // Here we mirror them into the tenant DB so the tenant DB is self-contained.
            DBManager::tenantInsert($companyId, 'ff_users', [
                'id'             => $userId,
                'name'           => $data['name']          ?? 'Admin',
                'email'          => $data['email']         ?? '',
                'password'       => $data['password_hash'] ?? '',
                'role'           => 'owner',
                'is_active'      => 1,
                'status'         => 'active',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
            return [true, ''];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * Split a SQL file into individual statements, handling
     * DELIMITER changes (for stored procedures etc.).
     */
    private static function splitSql(string $sql): array {
        // Strip single-line comments
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
        // Strip block comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $statements = [];
        $current    = '';
        $delimiter  = ';';

        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);
            if (stripos($trimmed, 'DELIMITER') === 0) {
                $parts     = preg_split('/\s+/', $trimmed);
                $delimiter = $parts[1] ?? ';';
                continue;
            }
            $current .= $line . "\n";
            if (substr(rtrim($current), -strlen($delimiter)) === $delimiter) {
                $statements[] = trim(substr(rtrim($current), 0, -strlen($delimiter)));
                $current      = '';
            }
        }
        if (trim($current)) $statements[] = trim($current);

        return array_filter($statements, fn($s) => trim($s) !== '');
    }
}
