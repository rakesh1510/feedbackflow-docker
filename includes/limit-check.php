<?php
/**
 * Limit Enforcement Helpers
 *
 * Include this file in any admin page that needs to gate access
 * based on the company's effective plan limits.
 *
 * Usage:
 *   require_once '../includes/limit-check.php';
 *   LimitCheck::gateFeature($companyId, 'allow_ai', 'AI Copilot');
 *   LimitCheck::gateNumeric($companyId, 'max_projects', $currentProjectCount, 'project');
 */

require_once __DIR__ . '/billing.php';

class LimitCheck {

    /**
     * Gate a boolean feature (allow_ai, allow_white_label, etc.)
     * Redirects with flash error if the feature is not available.
     */
    public static function gateFeature(
        int    $companyId,
        string $resource,
        string $featureName,
        string $redirectUrl = ''
    ): void {
        $limits = BillingService::getEffectiveLimits($companyId);
        if (empty($limits[$resource])) {
            $next = BillingService::getNextPlan($limits['plan_slug'] ?? 'starter');
            $upgradeText = $next
                ? "Upgrade to {$next['name']} to unlock {$featureName}."
                : "Upgrade your plan to unlock {$featureName}.";
            if (function_exists('flash')) {
                flash('error', "🔒 {$featureName} is not available on your current plan. {$upgradeText}");
            }
            $url = $redirectUrl ?: (defined('APP_URL') ? APP_URL . '/admin/billing.php' : '/admin/billing.php');
            header("Location: $url");
            exit;
        }
    }

    /**
     * Gate a numeric limit (max_projects, max_users, max_emails, etc.)
     * Returns true if under the limit, false if at/over.
     * Optionally redirects with a flash message.
     */
    public static function gateNumeric(
        int    $companyId,
        string $resource,
        int    $currentUsage,
        string $resourceName  = 'resource',
        bool   $redirect      = false,
        string $redirectUrl   = ''
    ): bool {
        $limit = BillingService::getEffective($companyId, $resource);
        if ($limit === -1) return true;  // unlimited
        if ($currentUsage < $limit) return true;

        $next = BillingService::getEffectiveLimits($companyId);
        $nextPlan = BillingService::getNextPlan($next['plan_slug'] ?? 'starter');
        $upgradeText = $nextPlan
            ? "Upgrade to {$nextPlan['name']} or buy an add-on to increase your limit."
            : "Buy an add-on to increase your limit.";

        if ($redirect) {
            if (function_exists('flash')) {
                flash('error', "🔒 You've reached your {$resourceName} limit ({$limit}). {$upgradeText}");
            }
            $url = $redirectUrl ?: (defined('APP_URL') ? APP_URL . '/admin/billing.php' : '/admin/billing.php');
            header("Location: $url");
            exit;
        }

        return false;
    }

    /**
     * Return a JSON 402 response for API endpoints hitting a limit.
     */
    public static function apiGate(int $companyId, string $resource, int $currentUsage): void {
        $limit = BillingService::getEffective($companyId, $resource);
        if ($limit === -1 || $currentUsage < $limit) return;

        http_response_code(402);
        header('Content-Type: application/json');
        echo json_encode([
            'error'   => 'plan_limit_exceeded',
            'message' => "You have reached your {$resource} limit ({$limit}). Upgrade your plan or add more capacity.",
            'limit'   => $limit,
            'used'    => $currentUsage,
        ]);
        exit;
    }

    /**
     * Convenience: check if a feature is enabled for the company.
     */
    public static function hasFeature(int $companyId, string $resource): bool {
        return BillingService::getEffective($companyId, $resource) === 1;
    }

    /**
     * Inline upgrade banner HTML for embedding in admin pages near locked features.
     */
    public static function upgradeBanner(int $companyId, string $featureName): string {
        $limits   = BillingService::getEffectiveLimits($companyId);
        $nextPlan = BillingService::getNextPlan($limits['plan_slug'] ?? 'starter');
        $url      = defined('APP_URL') ? APP_URL . '/admin/billing.php' : '/admin/billing.php';

        $price = $nextPlan ? '€' . number_format((float)$nextPlan['price_monthly'], 0) . '/mo' : '';
        $name  = $nextPlan ? h($nextPlan['name']) : 'a higher plan';

        return '<div class="rounded-xl bg-indigo-50 border border-indigo-100 p-4 flex items-start gap-3">'
             . '<div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">'
             . '<i class="fas fa-lock text-indigo-500 text-sm"></i></div>'
             . '<div><p class="text-sm font-semibold text-indigo-900">🔒 ' . h($featureName) . ' requires an upgrade</p>'
             . '<p class="text-xs text-indigo-600 mt-0.5">Available on ' . $name . ' (' . $price . ') and above.</p>'
             . '<a href="' . $url . '" class="inline-block mt-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-lg transition">Upgrade now</a>'
             . '</div></div>';
    }
}
