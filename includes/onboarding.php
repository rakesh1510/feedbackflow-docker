<?php
// TenantProvisioner is loaded lazily so onboarding.php can work
// even if db-manager.php is not yet available (e.g. during install).

/**
 * OnboardingService — manages the two-track onboarding flows:
 *
 * Flow 1 (Company Signup):  register → select_plan → setup_project → complete
 * Flow 2 (Invited User):    accept_invite → active (no plan/company steps)
 *
 * State is stored in the PHP session during the multi-step signup.
 * All actions are logged to ff_onboarding_log.
 */
class OnboardingService {

    const SESSION_KEY = '_ff_onboarding';

    public static function start(int $userId, int $companyId): void {
        Auth::start();
        $_SESSION[self::SESSION_KEY] = [
            'user_id'    => $userId,
            'company_id' => $companyId,
            'step'       => 'select_plan',
            'started_at' => time(),
        ];
    }

    public static function getState(): ?array {
        Auth::start();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function setState(array $data): void {
        Auth::start();
        $_SESSION[self::SESSION_KEY] = array_merge(
            $_SESSION[self::SESSION_KEY] ?? [],
            $data
        );
    }

    public static function clearState(): void {
        Auth::start();
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function isInProgress(): bool {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function guardAdmin(int $userId): void {
        try {
            $company = DB::fetch(
                "SELECT c.* FROM ff_companies c
                 JOIN ff_users u ON u.company_id = c.id
                 WHERE u.id = ? AND c.onboarding_complete = 0",
                [$userId]
            );
            if ($company) {
                $plan = $company['plan'] ?? '';
                if (!$plan || $plan === '') {
                    redirect(APP_URL . '/onboarding/select-plan.php');
                } else {
                    redirect(APP_URL . '/onboarding/setup.php');
                }
            }
        } catch (Throwable $e) {
        }
    }

    public static function checkDuplicate(string $name, string $email): ?array {
        $nameLower = strtolower(trim($name));

        $byName = DB::withMaster(fn() => DB::fetch(
            "SELECT * FROM ff_companies WHERE LOWER(name) = ?",
            [$nameLower]
        ));
        if ($byName) return $byName;

        $nameSlug = preg_replace('/[^a-z0-9]/', '', $nameLower);
        $all = DB::withMaster(fn() => DB::fetchAll("SELECT * FROM ff_companies WHERE is_active = 1 LIMIT 500"));
        foreach ($all as $c) {
            $cSlug = preg_replace('/[^a-z0-9]/', '', strtolower($c['name']));
            if ($nameSlug && $cSlug && $nameSlug === $cSlug) return $c;
            if (strlen($nameSlug) > 4 && strlen($cSlug) > 4) {
                $maxLen = max(strlen($nameSlug), strlen($cSlug));
                $dist   = levenshtein($nameSlug, $cSlug);
                if ($maxLen > 0 && (1 - $dist / $maxLen) >= 0.85) return $c;
            }
        }

        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        $publicDomains = ['gmail.com','yahoo.com','hotmail.com','outlook.com','icloud.com','aol.com','live.com'];
        if (!in_array($domain, $publicDomains, true)) {
            $byDomain = DB::withMaster(fn() => DB::fetch(
                "SELECT * FROM ff_companies WHERE LOWER(email) LIKE ?",
                ['%@' . $domain]
            ));
            if ($byDomain) return $byDomain;
        }

        return null;
    }

    /**
     * Create a new company and provision its dedicated tenant database.
     * Returns the new company ID.
     */
    public static function createCompany(string $companyName, string $email, array $ownerData = []): array {
        $baseSlug = preg_replace('/[^a-z0-9\-]/', '-', strtolower(trim($companyName)));
        $baseSlug = preg_replace('/-+/', '-', trim($baseSlug, '-')) ?: 'company';
        $slug = $baseSlug;
        $i = 1;

        while (DB::withMaster(fn() => DB::fetch("SELECT id FROM ff_companies WHERE slug = ?", [$slug]))) {
            $slug = $baseSlug . '-' . $i++;
        }

        $companyId = DB::withMaster(fn() => DB::insert('ff_companies', [
            'name'                => $companyName,
            'slug'                => $slug,
            'email'               => $email,
            'plan'                => '',
            'onboarding_complete' => 0,
            'signup_ip'           => $_SERVER['REMOTE_ADDR'] ?? null,
            'is_active'           => 1,
        ]));

        try {
            $tenantFile = __DIR__ . '/tenant_provisioner.php';
            if (!class_exists('TenantProvisioner') && file_exists($tenantFile)) {
                require_once $tenantFile;
            }

            if (!class_exists('TenantProvisioner')) {
                throw new RuntimeException('TenantProvisioner class not found.');
            }

            $provisioned = TenantProvisioner::provision(
                $companyId,
                $companyName,
                $slug,
                $ownerData
            );

            try {
                DB::withMaster(fn() => DB::query(
                    "INSERT IGNORE INTO ff_billing_usage (company_id, year_month) VALUES (?, ?)",
                    [$companyId, date('Y-m')]
                ));
            } catch (Throwable $e) { }

            return [
                'company_id' => $companyId,
                'db_name' => $provisioned['db_name'] ?? ($slug . '_feedbackflow'),
                'tenant_user_id' => (int)($provisioned['tenant_user_id'] ?? 0),
            ];

        } catch (Throwable $e) {
            DB::withMaster(fn() => DB::update('ff_companies', [
                'is_active' => 0
            ], 'id = ?', [$companyId]));

            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('Tenant provisioning failed: ' . $e->getMessage());
            }

            throw $e;
        }
    }

    public static function activatePlan(int $companyId, string $planSlug, string $cycle = 'monthly'): bool {
        $plan = DB::withMaster(fn() => DB::fetch("SELECT * FROM ff_billing_plans WHERE slug = ? AND is_active = 1", [$planSlug]));
        if (!$plan) return false;

        $updateData = [
            'plan'          => $planSlug,
            'billing_cycle' => $cycle,
        ];

        if ((float)($plan['price_monthly'] ?? 0) > 0) {
            try {
                $updateData['trial_ends_at'] = date('Y-m-d H:i:s', strtotime('+14 days'));
            } catch (Throwable $e) { }
        }

        try {
            DB::withMaster(fn() => DB::update('ff_companies', $updateData, 'id = ?', [$companyId]));
        } catch (Throwable $e) {
            DB::withMaster(fn() => DB::update('ff_companies', ['plan' => $planSlug], 'id = ?', [$companyId]));
        }

        try {
            if ((float)($plan['price_monthly'] ?? 0) > 0) {
                DB::withMaster(fn() => DB::query(
                    "INSERT IGNORE INTO ff_billing_usage (company_id, year_month) VALUES (?, ?)",
                    [$companyId, date('Y-m')]
                ));
            }
        } catch (Throwable $e) { }

        return true;
    }

    public static function createFirstProject(
        int    $userId,
        string $projectName,
        string $projectDesc = '',
        array  $channels    = []
    ): int {
        $baseSlug = preg_replace('/[^a-z0-9\-]/', '-', strtolower(trim($projectName)));
        $baseSlug = trim(preg_replace('/-+/', '-', $baseSlug), '-') ?: 'project';
        $slug = $baseSlug;
        $i = 1;
        while (DB::fetch("SELECT id FROM ff_projects WHERE slug = ?", [$slug])) {
            $slug = $baseSlug . '-' . $i++;
        }

        $widgetKey = bin2hex(random_bytes(16));

        $projectId = DB::insert('ff_projects', [
            'name'              => $projectName,
            'slug'              => $slug,
            'description'       => $projectDesc,
            'owner_id'          => $userId,
            'is_public'         => 1,
            'allow_anonymous'   => 1,
            'widget_key'        => $widgetKey,
            'widget_color'      => '#6366f1',
        ]);

        if ($projectId && !empty($channels)) {
            try {
                foreach ($channels as $ch) {
                    DB::insert('ff_project_channels', [
                        'project_id' => $projectId,
                        'channel'    => $ch,
                        'is_active'  => 1,
                    ]);
                }
            } catch (Throwable $e) { }
        }

        return $projectId;
    }

    public static function complete(int $companyId, int $userId): void {
        try {
            DB::withMaster(fn() => DB::update('ff_companies', ['onboarding_complete' => 1], 'id = ?', [$companyId]));
        } catch (Throwable $e) { }

        self::log($userId, $companyId, 'onboarding_complete', [], 'company_signup');
        self::clearState();
    }

    public static function log(
        int    $userId,
        ?int   $companyId,
        string $action,
        array  $meta = [],
        string $flow = 'other'
    ): void {
        try {
            DB::insert('ff_onboarding_log', [
                'user_id'    => $userId,
                'company_id' => $companyId,
                'action'     => $action,
                'flow'       => $flow,
                'meta'       => $meta ? json_encode($meta) : null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (Throwable $e) {
        }
    }
}
