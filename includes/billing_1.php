<?php
/**
 * BillingService — DB-driven pricing, limits, add-ons, invoices
 * Effective limit = plan_base + (addon_qty × units_per_qty) + admin_override
 */
class BillingService {

    // ------------------------------------------------------------------ //
    // PLAN HELPERS
    // ------------------------------------------------------------------ //

    public static function getPlan(string $slug): ?array {
        return DB::withMaster(fn() => DB::fetch("SELECT * FROM ff_billing_plans WHERE slug = ? AND is_active = 1", [$slug]));
    }

    public static function getAllPlans(): array {
        return DB::withMaster(fn() => DB::fetchAll("SELECT * FROM ff_billing_plans WHERE is_active = 1 ORDER BY sort_order"));
    }

    /** Return the active company row (incl. plan slug) for a user. */
    public static function getCompany(int $userId): ?array {
        $companyId = (int)($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0) {
            return null;
        }
        return DB::withMaster(fn() => DB::fetch(
            "SELECT * FROM ff_companies WHERE id = ?",
            [$companyId]
        ));
    }

    /** Return company row directly by id. */
    public static function getCompanyById(int $companyId): ?array {
        return DB::withMaster(fn() => DB::fetch("SELECT * FROM ff_companies WHERE id = ?", [$companyId]));
    }

    // ------------------------------------------------------------------ //
    // EFFECTIVE LIMITS
    // Effective = plan_base + addon contribution + admin override
    // ------------------------------------------------------------------ //

    /**
     * Returns the effective limit value for a single resource.
     * -1 means unlimited (either plan or override).
     */
    public static function getEffective(int $companyId, string $resource): int {
        $limits = self::getEffectiveLimits($companyId);
        return $limits[$resource] ?? 0;
    }

    /**
     * Full map of effective limits for a company.
     * Numeric fields: plan_base + addon_units + (admin_override replaces if set)
     * Boolean fields: 1 if plan allows OR addon enables OR admin override = 1
     */
    public static function getEffectiveLimits(int $companyId): array {
        $company = self::getCompanyById($companyId);
        if (!$company) return self::emptyLimits();

        $plan = self::getPlan($company['plan'] ?? 'starter');
        if (!$plan) $plan = self::getPlan('starter');
        if (!$plan) return self::emptyLimits();

        // --- Base from plan ---
        $limits = [
            'max_projects'       => (int)$plan['max_projects'],
            'max_users'          => (int)$plan['max_users'],
            'max_feedback_per_month' => (int)$plan['max_feedback_per_month'],
            'max_campaigns_per_month'=> (int)$plan['max_campaigns_per_month'],
            'max_emails'         => (int)$plan['max_emails'],
            'max_whatsapp'       => (int)$plan['max_whatsapp'],
            'max_sms'            => (int)$plan['max_sms'],
            'allow_ai'           => (int)$plan['allow_ai'],
            'allow_white_label'  => (int)$plan['allow_white_label'],
            'allow_api'          => (int)$plan['allow_api'],
            'allow_export'       => (int)$plan['allow_export'],
            'allow_automations'  => (int)$plan['allow_automations'],
            'allow_audit_logs'   => (int)$plan['allow_audit_logs'],
            'allow_sso'          => (int)$plan['allow_sso'],
            'plan_name'          => $plan['name'],
            'plan_slug'          => $plan['slug'],
        ];

        // --- Add add-on contributions (only if plan limit ≠ -1/unlimited) ---
        $addons = self::getCompanyAddons($companyId);
        foreach ($addons as $a) {
            $res = $a['resource'];
            if ($res === null || !array_key_exists($res, $limits)) continue;

            if ($a['type'] === 'boolean') {
                // Boolean add-on enables the feature
                $limits[$res] = 1;
            } else {
                // Quantity add-on: add units only if not already unlimited
                if ($limits[$res] !== -1) {
                    $limits[$res] += (int)$a['quantity'] * (int)$a['units_per_qty'];
                }
            }
        }

        // --- Admin overrides (override replaces the computed value) ---
        try {
            $overrides = DB::withMaster(fn() => DB::fetchAll(
                "SELECT resource, override_value FROM ff_admin_overrides WHERE company_id = ?",
                [$companyId]
            ));
            foreach ($overrides as $o) {
                $limits[$o['resource']] = (int)$o['override_value'];
            }
        } catch (\Throwable $e) {
            // ff_admin_overrides table not yet created — skip overrides
        }

        return $limits;
    }

    private static function emptyLimits(): array {
        return [
            'max_projects' => 0, 'max_users' => 0,
            'max_feedback_per_month' => 0, 'max_campaigns_per_month' => 0,
            'max_emails' => 0, 'max_whatsapp' => 0, 'max_sms' => 0,
            'allow_ai' => 0, 'allow_white_label' => 0, 'allow_api' => 0,
            'allow_export' => 0, 'allow_automations' => 0,
            'allow_audit_logs' => 0, 'allow_sso' => 0,
            'plan_name' => 'Starter', 'plan_slug' => 'starter',
        ];
    }

    /**
     * Check if company has exceeded a numeric limit.
     * Returns false if unlimited (-1).
     */
    public static function exceeded(int $companyId, string $resource, int $currentUsage): bool {
        $limit = self::getEffective($companyId, $resource);
        if ($limit === -1) return false;
        return $currentUsage >= $limit;
    }

    /** Usage percentage (0–100+). 0 for unlimited. */
    public static function usagePercent(int $limit, int $used): int {
        if ($limit <= 0) return 0;
        return (int) round(($used / $limit) * 100);
    }

    // ------------------------------------------------------------------ //
    // USAGE STATS
    // ------------------------------------------------------------------ //

    public static function getUsage(int $companyId): array {
        $monthStart = date('Y-m-01 00:00:00');

        $safe = function (callable $fn): int {
            try { return (int)$fn(); } catch (\Throwable $e) { return 0; }
        };

        return [
            'projects' => $safe(function () use ($companyId) {
                return DB::count(
                    "SELECT COUNT(*) FROM ff_projects WHERE owner_id IN
                     (SELECT id FROM ff_users WHERE company_id = ?)",
                    [$companyId]
                );
            }),
            'users' => $safe(function () use ($companyId) {
                return DB::count(
                    "SELECT COUNT(*) FROM ff_users WHERE company_id = ? AND is_active = 1",
                    [$companyId]
                );
            }),
            'feedback' => $safe(function () use ($companyId, $monthStart) {
                return DB::count(
                    "SELECT COUNT(*) FROM ff_feedback f
                     JOIN ff_projects p ON p.id = f.project_id
                     WHERE p.owner_id IN (SELECT id FROM ff_users WHERE company_id = ?)
                     AND f.created_at >= ?",
                    [$companyId, $monthStart]
                );
            }),
            'campaigns' => $safe(function () use ($companyId, $monthStart) {
                return DB::count(
                    "SELECT COUNT(*) FROM ff_campaigns
                     WHERE company_id = ? AND created_at >= ?",
                    [$companyId, $monthStart]
                );
            }),
            'emails' => $safe(function () use ($companyId, $monthStart) {
                return DB::count(
                    "SELECT COUNT(*) FROM ff_campaign_sends cs
                     JOIN ff_campaigns c ON c.id = cs.campaign_id
                     WHERE c.company_id = ? AND cs.sent_at >= ?",
                    [$companyId, $monthStart]
                );
            }),
            'whatsapp' => 0,
            'sms' => $safe(function () use ($companyId, $monthStart) {
                return DB::count(
                    "SELECT COUNT(*) FROM ff_sms_log
                     WHERE company_id = ? AND sent_at >= ?",
                    [$companyId, $monthStart]
                );
            }),
        ];
    }

    // ------------------------------------------------------------------ //
    // ADD-ONS
    // ------------------------------------------------------------------ //

    /** All available add-ons from catalog */
    public static function getAvailableAddons(): array {
        try {
            return DB::fetchAll(
                "SELECT * FROM ff_addons WHERE is_active = 1 ORDER BY sort_order"
            );
        } catch (\Throwable $e) {
            return []; // ff_addons table not yet created
        }
    }

    /** Add-ons purchased by a company (with addon catalog detail) */
    public static function getCompanyAddons(int $companyId): array {
        try {
            return DB::fetchAll(
                "SELECT ca.quantity, ca.activated_at, ca.expires_at,
                        a.id as addon_id, a.slug, a.name, a.description, a.type,
                        a.resource, a.unit_label, a.units_per_qty, a.price_per_qty,
                        a.icon, a.min_qty, a.max_qty
                 FROM ff_company_addons ca
                 JOIN ff_addons a ON a.id = ca.addon_id
                 WHERE ca.company_id = ?
                 ORDER BY a.sort_order",
                [$companyId]
            );
        } catch (\Throwable $e) {
            return []; // ff_company_addons / ff_addons table not yet created
        }
    }

    /**
     * Add or update an add-on for a company.
     * quantity = 0 removes the add-on.
     */
    public static function setAddon(int $companyId, int $addonId, int $quantity): bool {
        $addon = DB::fetch("SELECT * FROM ff_addons WHERE id = ? AND is_active = 1", [$addonId]);
        if (!$addon) return false;

        if ($quantity <= 0) {
            DB::delete('ff_company_addons', 'company_id = ? AND addon_id = ?', [$companyId, $addonId]);
            return true;
        }

        $quantity = min($quantity, (int)$addon['max_qty']);
        $quantity = max($quantity, (int)$addon['min_qty']);

        $existing = DB::fetch(
            "SELECT id FROM ff_company_addons WHERE company_id = ? AND addon_id = ?",
            [$companyId, $addonId]
        );

        if ($existing) {
            DB::update('ff_company_addons', ['quantity' => $quantity], 'company_id = ? AND addon_id = ?', [$companyId, $addonId]);
        } else {
            DB::insert('ff_company_addons', [
                'company_id'   => $companyId,
                'addon_id'     => $addonId,
                'quantity'     => $quantity,
                'activated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return true;
    }

    /** Monthly cost of all active add-ons for a company */
    public static function addonsMonthlyTotal(int $companyId): float {
        $addons = self::getCompanyAddons($companyId);
        $total = 0.0;
        foreach ($addons as $a) {
            $total += (float)$a['price_per_qty'] * (int)$a['quantity'];
        }
        return $total;
    }

    // ------------------------------------------------------------------ //
    // PLAN UPGRADE / CHANGE
    // ------------------------------------------------------------------ //

    public static function changePlan(int $companyId, string $planSlug, string $cycle = 'monthly'): bool {
        $plan = self::getPlan($planSlug);
        if (!$plan) return false;

        $expires = ($cycle === 'yearly')
            ? date('Y-m-d H:i:s', strtotime('+1 year'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));

        DB::update('ff_companies', [
            'plan'           => $planSlug,
            'billing_cycle'  => $cycle,
            'plan_expires_at'=> $expires,
        ], 'id = ?', [$companyId]);

        return true;
    }

    // ------------------------------------------------------------------ //
    // INVOICES
    // ------------------------------------------------------------------ //

    public static function generateInvoiceNumber(): string {
        $prefix = 'FF-' . date('Ym') . '-';
        $last   = DB::fetch(
            "SELECT invoice_number FROM ff_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );
        $seq = $last ? ((int)substr($last['invoice_number'], -4) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a VAT-ready invoice record.
     *
     * @param int    $companyId
     * @param string $planSlug     which plan this invoice is for
     * @param float  $subtotal     net amount before VAT
     * @param float  $vatRate      e.g. 20.0 for 20%
     * @param array  $lineItems    [['label'=>'...','amount'=>float], ...]
     * @param string $cycle        monthly | yearly
     */
    public static function createInvoice(
        int    $companyId,
        string $planSlug,
        float  $subtotal,
        float  $vatRate    = 0.0,
        array  $lineItems  = [],
        string $cycle      = 'monthly'
    ): int {
        $company   = self::getCompanyById($companyId);
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $total     = round($subtotal + $vatAmount, 2);
        $now       = date('Y-m-d H:i:s');

        return DB::insert('ff_invoices', [
            'company_id'      => $companyId,
            'invoice_number'  => self::generateInvoiceNumber(),
            'amount'          => $total,
            'subtotal'        => $subtotal,
            'vat_rate'        => $vatRate,
            'vat_amount'      => $vatAmount,
            'tax_amount'      => $vatAmount,
            'tax_rate'        => $vatRate,
            'currency'        => 'EUR',
            'status'          => 'paid',
            'plan_slug'       => $planSlug,
            'billing_name'    => $company['billing_name'] ?? $company['name'] ?? '',
            'billing_address' => $company['billing_address'] ?? '',
            'vat_number'      => $company['vat_number'] ?? '',
            'line_items'      => json_encode($lineItems),
            'period_start'    => date('Y-m-01'),
            'period_end'      => ($cycle === 'yearly') ? date('Y-m-d', strtotime('+1 year -1 day')) : date('Y-m-t'),
            'due_date'        => date('Y-m-d'),
            'paid_at'         => $now,
            'created_at'      => $now,
        ]);
    }

    // ------------------------------------------------------------------ //
    // UPGRADE PROMPT HELPER
    // ------------------------------------------------------------------ //

    /** Returns array of resources currently at ≥ $threshold % of limit */
    public static function upgradePrompts(int $companyId, array $usage, int $threshold = 80): array {
        $limits  = self::getEffectiveLimits($companyId);
        $prompts = [];
        $numeric = [
            'max_projects'           => ['label' => 'Projects',           'used_key' => 'projects'],
            'max_users'              => ['label' => 'Team Members',        'used_key' => 'users'],
            'max_feedback_per_month' => ['label' => 'Feedback this month', 'used_key' => 'feedback'],
            'max_emails'             => ['label' => 'Emails this month',   'used_key' => 'emails'],
            'max_whatsapp'           => ['label' => 'WhatsApp messages',   'used_key' => 'whatsapp'],
            'max_sms'                => ['label' => 'SMS messages',        'used_key' => 'sms'],
        ];
        foreach ($numeric as $key => $meta) {
            $limit = $limits[$key] ?? 0;
            if ($limit === -1 || $limit === 0) continue;
            $used = $usage[$meta['used_key']] ?? 0;
            $pct  = self::usagePercent($limit, $used);
            if ($pct >= $threshold) {
                $prompts[] = ['resource' => $key, 'label' => $meta['label'], 'pct' => $pct, 'used' => $used, 'limit' => $limit];
            }
        }
        return $prompts;
    }

    // ------------------------------------------------------------------ //
    // NEXT PLAN HELPER
    // ------------------------------------------------------------------ //

    public static function getNextPlan(string $currentSlug): ?array {
        $all = self::getAllPlans();
        $found = false;
        foreach ($all as $plan) {
            if ($found) return $plan;
            if ($plan['slug'] === $currentSlug) $found = true;
        }
        return null;
    }
}
