<?php
require_once dirname(__DIR__) . '/config.php';

class DBManager {

    private static array $pool = [];
    private static ?PDO $rootPdo = null;

    public static function master(): PDO {
        if (empty(self::$pool['master'])) {
            self::$pool['master'] = self::buildPdo(
                MASTER_DB_HOST,
                MASTER_DB_PORT,
                MASTER_DB_NAME,
                MASTER_DB_USER,
                MASTER_DB_PASS
            );
        }
        return self::$pool['master'];
    }

    public static function forCompany(int $companyId): PDO {
        $key = "tenant_{$companyId}";
        if (!empty(self::$pool[$key])) {
            return self::$pool[$key];
        }

        $info = self::getConnectionInfo($companyId);
        if (!$info) {
            throw new RuntimeException("No tenant database found for company #{$companyId}. Has it been provisioned?");
        }

        self::$pool[$key] = self::buildPdo(
            $info['db_host'],
            (int)($info['db_port'] ?? 3306),
            $info['db_name'],
            $info['db_user'],
            self::decrypt($info['db_pass_enc'])
        );

        return self::$pool[$key];
    }

    public static function tenantByDbName(string $dbName): PDO {
        return self::buildPdo(DB_HOST, 3306, $dbName, DB_USER, DB_PASS);
    }

    public static function hasTenantDb(int $companyId): bool {
        try {
            $stmt = self::master()->prepare(
                "SELECT id FROM ff_company_databases WHERE company_id = ? AND db_status = 'active' LIMIT 1"
            );
            $stmt->execute([$companyId]);
            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function markProvisionFailed(int $companyId, string $error): void {
        try {
            $stmt = self::master()->prepare("SELECT id FROM ff_company_databases WHERE company_id = ? LIMIT 1");
            $stmt->execute([$companyId]);
            $exists = $stmt->fetch();

            if ($exists) {
                $stmt = self::master()->prepare("
                    UPDATE ff_company_databases
                    SET db_status = 'failed',
                        error_msg = ?,
                        provisioned_at = NOW()
                    WHERE company_id = ?
                ");
                $stmt->execute([substr($error, 0, 500), $companyId]);
            } else {
                $stmt = self::master()->prepare("
                    INSERT INTO ff_company_databases
                    (company_id, db_host, db_name, db_status, error_msg, provisioned_at)
                    VALUES (?, ?, '', 'failed', ?, NOW())
                ");
                $stmt->execute([$companyId, DB_HOST, substr($error, 0, 500)]);
            }
        } catch (Throwable $e) {
        }
    }

    public static function storeConnectionInfo(int $companyId, array $info): void {
        $encPass = self::encrypt($info['db_pass']);

        $stmt = self::master()->prepare("
            INSERT INTO ff_company_databases
                (company_id, db_host, db_port, db_name, db_user, db_pass_enc, db_status, provisioned_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'active', NOW())
            ON DUPLICATE KEY UPDATE
                db_host = VALUES(db_host),
                db_port = VALUES(db_port),
                db_name = VALUES(db_name),
                db_user = VALUES(db_user),
                db_pass_enc = VALUES(db_pass_enc),
                db_status = 'active',
                provisioned_at = NOW()
        ");

        $stmt->execute([
            $companyId,
            $info['db_host'],
            $info['db_port'] ?? 3306,
            $info['db_name'],
            $info['db_user'],
            $encPass,
        ]);
    }

    public static function rootConnection(): PDO {
        if (!self::$rootPdo) {
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            self::$rootPdo = new PDO($dsn, DB_ROOT_USER, DB_ROOT_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$rootPdo;
    }

    public static function createTenantDatabase(string $dbName): void {
        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        if (!$safeDbName) {
            throw new RuntimeException("Invalid database name");
        }

        $sql = "CREATE DATABASE IF NOT EXISTS `$safeDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
        self::rootConnection()->exec($sql);
    }

    public static function runTenantSchema(string $dbName, string $schemaFile): void {
        if (!file_exists($schemaFile)) {
            throw new RuntimeException("Tenant schema file not found: " . $schemaFile);
        }

        $pdo = self::buildPdo(DB_HOST, 3306, $dbName, DB_USER, DB_PASS);
        $sql = file_get_contents($schemaFile);

        if ($sql === false) {
            throw new RuntimeException("Unable to read tenant schema file");
        }

        $pdo->exec($sql);
    }

    public static function provisionCompanyDatabase(int $companyId, string $companySlug): string {
        $dbName = strtolower($companySlug) . '_feedbackflow';
        $dbName = preg_replace('/[^a-z0-9_]/', '_', $dbName);

        self::createTenantDatabase($dbName);

        $schemaFile = dirname(__DIR__) . '/db-tenant-schema-full.sql';
        if (!file_exists($schemaFile)) {
            $schemaFile = dirname(__DIR__) . '/db-tenant-schema.sql';
        }
        self::runTenantSchema($dbName, $schemaFile);

        self::storeConnectionInfo($companyId, [
            'db_host' => DB_HOST,
            'db_port' => 3306,
            'db_name' => $dbName,
            'db_user' => DB_USER,
            'db_pass' => DB_PASS,
        ]);

        return $dbName;
    }

    public static function tenantQuery(int $companyId, string $sql, array $params = []): PDOStatement {
        $pdo = self::forCompany($companyId);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function tenantFetch(int $companyId, string $sql, array $params = []): ?array {
        $result = self::tenantQuery($companyId, $sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function tenantFetchAll(int $companyId, string $sql, array $params = []): array {
        return self::tenantQuery($companyId, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function tenantInsert(int $companyId, string $table, array $data): int {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $pdo = self::forCompany($companyId);
        $stmt = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($placeholders)");
        $stmt->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }

    public static function tenantCount(int $companyId, string $sql, array $params = []): int {
        return (int) self::tenantQuery($companyId, $sql, $params)->fetchColumn();
    }

    public static function logSuperAdminAction(
        int $adminId,
        string $action,
        ?int $targetCompanyId = null,
        ?int $targetUserId = null,
        array $meta = []
    ): void {
        try {
            $stmt = self::master()->prepare("
                INSERT INTO ff_super_admin_log
                    (admin_id, action, target_company_id, target_user_id, meta, ip, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $adminId,
                $action,
                $targetCompanyId,
                $targetUserId,
                $meta ? json_encode($meta) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            if (DEBUG_MODE) {
                error_log("DBManager::logSuperAdminAction: " . $e->getMessage());
            }
        }
    }

    public static function encrypt(string $plaintext): string {
        $iv = random_bytes(16);
        $enc = openssl_encrypt($plaintext, 'AES-256-CBC', APP_ENCRYPT_KEY, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function decrypt(string $ciphertext): string {
        $raw = base64_decode($ciphertext);
        $iv = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        return openssl_decrypt($enc, 'AES-256-CBC', APP_ENCRYPT_KEY, OPENSSL_RAW_DATA, $iv) ?: '';
    }

    public static function listCompanyDatabases(): array {
        try {
            $stmt = self::master()->query("
                SELECT cd.*, c.name AS company_name, c.plan, c.is_active
                FROM ff_company_databases cd
                LEFT JOIN ff_companies c ON c.id = cd.company_id
                ORDER BY cd.provisioned_at DESC
            ");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getProvisioningLogs(int $companyId, int $limit = 50): array {
        try {
            $stmt = self::master()->prepare("
                SELECT * FROM ff_provisioning_log
                WHERE company_id = ?
                ORDER BY created_at DESC
                LIMIT $limit
            ");
            $stmt->execute([$companyId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function findCompanyIdByInviteToken(string $token): ?int {
        foreach (self::listCompanyDatabases() as $db) {
            try {
                $pdo = self::forCompany((int)$db['company_id']);
                $stmt = $pdo->prepare("SELECT company_id FROM ff_users WHERE invite_token = ? LIMIT 1");
                $stmt->execute([$token]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return (int)($row['company_id'] ?? $db['company_id']);
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    public static function findCompanyIdByFeedbackId(int $feedbackId): ?int {
        foreach (self::listCompanyDatabases() as $db) {
            try {
                $pdo = self::forCompany((int)$db['company_id']);
                $stmt = $pdo->prepare("SELECT p.owner_id FROM ff_feedback f JOIN ff_projects p ON p.id = f.project_id WHERE f.id = ? LIMIT 1");
                $stmt->execute([$feedbackId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $ownerId = (int)($row['owner_id'] ?? 0);
                    if ($ownerId > 0) {
                        $u = $pdo->prepare("SELECT company_id FROM ff_users WHERE id = ? LIMIT 1");
                        $u->execute([$ownerId]);
                        $user = $u->fetch(PDO::FETCH_ASSOC);
                        if (!empty($user['company_id'])) {
                            return (int)$user['company_id'];
                        }
                    }
                    return (int)$db['company_id'];
                }
            } catch (Throwable $e) {
            }
        }
        return null;
    }

    public static function findCompanyIdByPublicToken(string $token): ?int {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return DB::withMaster(function () use ($token) {
            $row = DB::fetch(
                "SELECT company_id
                 FROM ff_public_token_map
                 WHERE token = ?
                 LIMIT 1",
                [$token]
            );

            return $row ? (int)$row['company_id'] : null;
        });
    }

    public static function registerPublicToken(int $companyId, string $token, string $tokenType, ?int $projectId = null, ?int $entityId = null): void {
        $token = trim($token);
        if ($companyId <= 0 || $token === '' || $tokenType === '') {
            return;
        }

        DB::withMaster(function () use ($companyId, $token, $tokenType, $projectId, $entityId) {
            $existing = DB::fetch(
                "SELECT id FROM ff_public_token_map WHERE token = ? LIMIT 1",
                [$token]
            );

            if ($existing) {
                DB::update(
                    'ff_public_token_map',
                    [
                        'company_id' => $companyId,
                        'token_type' => $tokenType,
                        'project_id' => $projectId,
                        'entity_id'  => $entityId,
                    ],
                    'id = ?',
                    [$existing['id']]
                );
            } else {
                DB::insert('ff_public_token_map', [
                    'company_id' => $companyId,
                    'token'      => $token,
                    'token_type' => $tokenType,
                    'project_id' => $projectId,
                    'entity_id'  => $entityId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        });
    }

//    public static function findCompanyIdByProjectSlug(string $slug): ?int {
//        $slug = trim($slug);
//        if ($slug === '') {
//            return null;
//        }
//
//        return DB::withMaster(function () use ($slug) {
//            $row = DB::fetch(
//                "SELECT company_id
//                 FROM ff_public_token_map
//                 WHERE token = ? AND token_type = 'project_slug'
//                 LIMIT 1",
//                [$slug]
//            );
//
//            return $row ? (int)$row['company_id'] : null;
//        });
//    }

    
    public static function findCompanyIdByProjectSlug(string $slug): ?int
{
    $slug = trim($slug);

    if ($slug === '') {
        return null;
    }

    foreach (self::listCompanyDatabases() as $db) {

        try {

            $companyId = (int)$db['company_id'];

            $pdo = self::forCompany($companyId);

            $stmt = $pdo->prepare("
                SELECT id
                FROM ff_projects
                WHERE slug = ?
                LIMIT 1
            ");

            $stmt->execute([$slug]);

            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($project) {
                return $companyId;
            }

        } catch (Throwable $e) {

            continue;
        }
    }

    return null;
}
    public static function findCompanyIdByWidgetKey(string $widgetKey): ?int {
        $widgetKey = trim($widgetKey);
        if ($widgetKey === '') {
            return null;
        }

        return DB::withMaster(function () use ($widgetKey) {
            $row = DB::fetch(
                "SELECT company_id
                 FROM ff_public_token_map
                 WHERE token = ? AND token_type = 'widget_key'
                 LIMIT 1",
                [$widgetKey]
            );

            return $row ? (int)$row['company_id'] : null;
        });
    }

    private static function getConnectionInfo(int $companyId): ?array {
        try {
            $stmt = self::master()->prepare("
                SELECT * FROM ff_company_databases
                WHERE company_id = ? AND db_status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$companyId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function buildPdo(
        string $host,
        int $port,
        string $dbname,
        string $user,
        string $pass
    ): PDO {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);
    }
}
