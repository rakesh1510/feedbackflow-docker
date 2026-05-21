<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db-manager.php';

class TenantProvisioner
{
    public static function provision(
        int $companyId,
        string $companyName,
        string $slug,
        array $ownerData = []
    ): array {
        $master = DBManager::master();

        try {
            $dbName = DBManager::provisionCompanyDatabase($companyId, $slug);
            $tenant = DBManager::tenantByDbName($dbName);

            if (empty($ownerData['email']) || empty($ownerData['password'])) {
                throw new RuntimeException('Owner data is required for tenant provisioning.');
            }

            $hasCompanyId = false;
            try {
                $col = $tenant->query("SHOW COLUMNS FROM ff_users LIKE 'company_id'")->fetch();
                $hasCompanyId = (bool)$col;
            } catch (Throwable $e) {
                $hasCompanyId = false;
            }

            $columns = [];
            foreach ($tenant->query("SHOW COLUMNS FROM ff_users")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[$col['Field']] = true;
            }

            $data = [];
            if ($hasCompanyId && isset($columns['company_id'])) $data['company_id'] = $companyId;
            if (isset($columns['name'])) $data['name'] = $ownerData['name'] ?? $companyName;
            if (isset($columns['email'])) $data['email'] = strtolower($ownerData['email']);
            if (isset($columns['password'])) $data['password'] = $ownerData['password'];
            if (isset($columns['role'])) $data['role'] = 'owner';
            if (isset($columns['is_active'])) $data['is_active'] = 1;
            if (isset($columns['email_verified'])) $data['email_verified'] = 1;
            if (isset($columns['status'])) $data['status'] = 'active';
            if (isset($columns['created_at'])) $data['created_at'] = date('Y-m-d H:i:s');
            if (isset($columns['updated_at'])) $data['updated_at'] = date('Y-m-d H:i:s');

            $colSql = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $tenant->prepare("INSERT INTO ff_users ($colSql) VALUES ($placeholders)");
            $stmt->execute(array_values($data));
            $tenantUserId = (int)$tenant->lastInsertId();

            $stmt = $master->prepare("UPDATE ff_companies SET is_active = 1 WHERE id = ?");
            $stmt->execute([$companyId]);

            try {
                $stmt = $master->prepare("
                    INSERT INTO ff_provisioning_log
                    (company_id, action, status, detail, created_at)
                    VALUES (?, 'tenant_provision', 'success', ?, NOW())
                ");
                $stmt->execute([$companyId, "Tenant DB provisioned: {$dbName} with full schema"]);
            } catch (Throwable $e) {
            }

            return ['db_name' => $dbName, 'tenant_user_id' => $tenantUserId];

        } catch (Throwable $e) {
            DBManager::markProvisionFailed($companyId, $e->getMessage());

            try {
                $stmt = $master->prepare("UPDATE ff_companies SET is_active = 0 WHERE id = ?");
                $stmt->execute([$companyId]);
            } catch (Throwable $inner) {
            }

            try {
                $stmt = $master->prepare("
                    INSERT INTO ff_provisioning_log
                    (company_id, action, status, detail, created_at)
                    VALUES (?, 'tenant_provision', 'failed', ?, NOW())
                ");
                $stmt->execute([$companyId, substr($e->getMessage(), 0, 1000)]);
            } catch (Throwable $inner) {
            }

            throw $e;
        }
    }
}
