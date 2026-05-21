<?php
/**
 * UserManagement — Company-scoped user invite, role, and status management.
 *
 * Roles that can invite: owner, admin
 * Roles that cannot:     manager, member, viewer
 *
 * Invite flow:
 *   1. invite()          → creates ff_users row with status='invited', sends token email
 *   2. acceptInvite()    → validates token, sets name+password, status='active', logs in
 */
class UserManagement {

    // ------------------------------------------------------------------ //
    // QUERY HELPERS
    // ------------------------------------------------------------------ //

    /** All users belonging to a company (ordered: owner first, then by name). */
    public static function getCompanyUsers(int $companyId): array {
        return DB::fetchAll(
            "SELECT u.id, u.name, u.email, u.role, u.is_active,
                    u.last_login, u.created_at, u.invited_by,
                    COALESCE(u.status, IF(u.is_active=1,'active','disabled')) AS status,
                    inv.name AS invited_by_name
             FROM ff_users u
             LEFT JOIN ff_users inv ON inv.id = u.invited_by
             WHERE u.company_id = ?
             ORDER BY FIELD(u.role,'owner','admin','manager','member','viewer') ASC, u.name ASC",
            [$companyId]
        );
    }

    /** Active (non-invited, non-disabled) user count for a company. */
    public static function activeCount(int $companyId): int {
        try {
            return DB::count(
                "SELECT COUNT(*) FROM ff_users
                 WHERE company_id = ? AND is_active = 1
                 AND COALESCE(status,'active') != 'invited'",
                [$companyId]
            );
        } catch (\Throwable $e) {
            return DB::count(
                "SELECT COUNT(*) FROM ff_users WHERE company_id = ? AND is_active = 1",
                [$companyId]
            );
        }
    }

    // ------------------------------------------------------------------ //
    // PERMISSIONS
    // ------------------------------------------------------------------ //

    /** Returns true if $user can invite / manage other company users. */
    public static function canInvite(array $user): bool {
        return in_array($user['role'], ['owner', 'admin'], true);
    }

    /** Returns true if $actor can change role / status of $target (cannot touch owner). */
    public static function canManage(array $actor, array $target): bool {
        if ($target['role'] === 'owner') return false;
        return in_array($actor['role'], ['owner', 'admin'], true);
    }

    // ------------------------------------------------------------------ //
    // LIMIT CHECK
    // ------------------------------------------------------------------ //

    /**
     * Returns ['ok' => bool, 'used' => int, 'limit' => int, 'msg' => string]
     * 'limit' = -1 means unlimited.
     */
    public static function checkLimit(int $companyId): array {
        $limits = [];
        try {
            $limits = BillingService::getEffectiveLimits($companyId);
        } catch (\Throwable $e) { }

        $max  = isset($limits['max_users']) ? (int)$limits['max_users'] : 0;
        $used = self::activeCount($companyId);

        if ($max === -1) {
            return ['ok' => true, 'used' => $used, 'limit' => -1, 'msg' => ''];
        }
        if ($max > 0 && $used >= $max) {
            return [
                'ok'    => false,
                'used'  => $used,
                'limit' => $max,
                'msg'   => "User limit reached ({$used}/{$max}). Upgrade your plan or add the Extra Users add-on.",
            ];
        }
        return ['ok' => true, 'used' => $used, 'limit' => $max, 'msg' => ''];
    }

    // ------------------------------------------------------------------ //
    // INVITE
    // ------------------------------------------------------------------ //

    /**
     * Invite a new user to the company.
     * Returns true on success, sets $error on failure.
     */
    public static function invite(
        int    $companyId,
        int    $invitedById,
        string $email,
        string $role,
        string $companyName,
        string &$error = ''
    ): bool {
        // Sanitise role — only admin and member allowed via invite
        $allowedRoles = ['admin', 'manager', 'member', 'viewer'];
        if (!in_array($role, $allowedRoles, true)) $role = 'member';

        // Check email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
            return false;
        }

        // Limit check
        $check = self::checkLimit($companyId);
        if (!$check['ok']) {
            $error = $check['msg'];
            return false;
        }

        // Is user already in this company?
        $existing = DB::fetch(
            "SELECT id, company_id, status FROM ff_users WHERE email = ?",
            [$email]
        );

        if ($existing) {
            if ((int)($existing['company_id'] ?? 0) === $companyId) {
                $error = 'This email is already a member of your company.';
                return false;
            }
            // User exists in another company or no company — cannot re-invite
            $error = 'This email is already registered in the system.';
            return false;
        }

        // Generate secure token (48 hex chars)
        $token   = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));

        // Create user row with status = invited
        $userId = DB::insert('ff_users', [
            'company_id'     => $companyId,
            'name'           => explode('@', $email)[0],
            'email'          => $email,
            'password'       => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'role'           => $role,
            'is_active'      => 0,
            'email_verified' => 0,
            'status'         => 'invited',
            'invite_token'   => $token,
            'invite_expires' => $expires,
            'invited_by'     => $invitedById,
        ]);

        if (!$userId) {
            $error = 'Failed to create user record.';
            return false;
        }

        // Fetch inviter name
        $inviter = DB::fetch("SELECT name FROM ff_users WHERE id = ?", [$invitedById]);
        $inviterName = $inviter['name'] ?? 'Your team';

        // Send invite email
        $html = self::buildInviteEmail($token, $companyName, $inviterName, $role);
        $mailError = '';
        $sent = ffSendMail($email, '', "You've been invited to {$companyName} on " . APP_NAME, $html, $mailError);

        if (!$sent) {
            // Don't delete the record — admin can resend later
            $error = "User created but email delivery failed: {$mailError}. Use Resend Invite.";
            return true; // partial success
        }

        return true;
    }

    /**
     * Resend the invite email to an already-invited user.
     */
    public static function resendInvite(int $userId, string $companyName, string &$error = ''): bool {
        $user = DB::fetch("SELECT * FROM ff_users WHERE id = ?", [$userId]);
        if (!$user) { $error = 'User not found.'; return false; }

        $status = $user['status'] ?? ($user['is_active'] ? 'active' : 'invited');
        if ($status !== 'invited') {
            $error = 'This user has already accepted their invite.';
            return false;
        }

        // Re-generate token and extend expiry
        $token   = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));

        DB::update('ff_users', [
            'invite_token'   => $token,
            'invite_expires' => $expires,
        ], 'id = ?', [$userId]);

        $inviter = DB::fetch("SELECT name FROM ff_users WHERE id = ?", [$user['invited_by'] ?? 0]);
        $html = self::buildInviteEmail($token, $companyName, $inviter['name'] ?? 'Your team', $user['role']);
        $mailError = '';
        $ok = ffSendMail($user['email'], $user['name'], "Invite reminder — {$companyName}", $html, $mailError);

        if (!$ok) { $error = "Email failed: {$mailError}"; return false; }
        return true;
    }

    // ------------------------------------------------------------------ //
    // ACCEPT INVITE
    // ------------------------------------------------------------------ //

    /**
     * Accept an invite by token.
     * Returns the user array on success, null on failure.
     */
    public static function acceptInvite(
        string $token,
        string $name,
        string $password,
        string &$error = ''
    ): ?array {
        if (strlen($token) < 16) { $error = 'Invalid token.'; return null; }

        $user = null;
        try {
            $user = DB::fetch(
                "SELECT * FROM ff_users WHERE invite_token = ? AND status = 'invited'",
                [$token]
            );
        } catch (\Throwable $e) {
            // status column may not exist yet — try without
            $user = DB::fetch(
                "SELECT * FROM ff_users WHERE invite_token = ? AND is_active = 0",
                [$token]
            );
        }

        if (!$user) { $error = 'Invalid or expired invite link.'; return null; }

        // Check expiry
        try {
            if ($user['invite_expires'] && strtotime($user['invite_expires']) < time()) {
                $error = 'This invite link has expired. Ask your admin to resend it.';
                return null;
            }
        } catch (\Throwable $e) { }

        $name = trim($name);
        if (strlen($name) < 2) { $error = 'Please enter your full name.'; return null; }
        if (strlen($password) < 8) { $error = 'Password must be at least 8 characters.'; return null; }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $updateData = [
            'name'           => $name,
            'password'       => $hash,
            'is_active'      => 1,
            'email_verified' => 1,
            'invite_token'   => null,
            'invite_expires' => null,
            'last_login'     => date('Y-m-d H:i:s'),
        ];

        // Add status if column exists
        try {
            DB::query("SELECT status FROM ff_users WHERE id = ? LIMIT 1", [$user['id']]);
            $updateData['status'] = 'active';
        } catch (\Throwable $e) { }

        DB::update('ff_users', $updateData, 'id = ?', [$user['id']]);

        return DB::fetch("SELECT * FROM ff_users WHERE id = ?", [$user['id']]);
    }

    // ------------------------------------------------------------------ //
    // ROLE / STATUS MANAGEMENT
    // ------------------------------------------------------------------ //

    /** Change a user's role within the company. */
    public static function changeRole(int $companyId, int $userId, string $role, array $actor, string &$error = ''): bool {
        $allowed = ['admin', 'manager', 'member', 'viewer'];
        if (!in_array($role, $allowed, true)) { $error = 'Invalid role.'; return false; }

        $target = DB::fetch("SELECT * FROM ff_users WHERE id = ? AND company_id = ?", [$userId, $companyId]);
        if (!$target) { $error = 'User not found in your company.'; return false; }
        if (!self::canManage($actor, $target)) { $error = 'You cannot change this user\'s role.'; return false; }

        DB::update('ff_users', ['role' => $role], 'id = ?', [$userId]);
        return true;
    }

    /** Deactivate (disable) a user. */
    public static function deactivate(int $companyId, int $userId, array $actor, string &$error = ''): bool {
        $target = DB::fetch("SELECT * FROM ff_users WHERE id = ? AND company_id = ?", [$userId, $companyId]);
        if (!$target) { $error = 'User not found.'; return false; }
        if (!self::canManage($actor, $target)) { $error = 'You cannot deactivate this user.'; return false; }
        if ((int)$actor['id'] === $userId) { $error = 'You cannot deactivate yourself.'; return false; }

        $updateData = ['is_active' => 0];
        try {
            DB::query("SELECT status FROM ff_users WHERE id = ? LIMIT 1", [$userId]);
            $updateData['status'] = 'disabled';
        } catch (\Throwable $e) { }

        DB::update('ff_users', $updateData, 'id = ?', [$userId]);
        return true;
    }

    /** Reactivate a previously disabled user. */
    public static function reactivate(int $companyId, int $userId, array $actor, string &$error = ''): bool {
        // Re-check limit before reactivating
        $check = self::checkLimit($companyId);
        if (!$check['ok']) { $error = $check['msg']; return false; }

        $target = DB::fetch("SELECT * FROM ff_users WHERE id = ? AND company_id = ?", [$userId, $companyId]);
        if (!$target) { $error = 'User not found.'; return false; }
        if (!self::canManage($actor, $target)) { $error = 'You cannot reactivate this user.'; return false; }

        $updateData = ['is_active' => 1];
        try {
            DB::query("SELECT status FROM ff_users WHERE id = ? LIMIT 1", [$userId]);
            $updateData['status'] = 'active';
        } catch (\Throwable $e) { }

        DB::update('ff_users', $updateData, 'id = ?', [$userId]);
        return true;
    }

    /** Remove a user from the company entirely (hard delete — use with caution). */
    public static function removeFromCompany(int $companyId, int $userId, array $actor, string &$error = ''): bool {
        $target = DB::fetch("SELECT * FROM ff_users WHERE id = ? AND company_id = ?", [$userId, $companyId]);
        if (!$target) { $error = 'User not found.'; return false; }
        if (!self::canManage($actor, $target)) { $error = 'Permission denied.'; return false; }
        if ((int)$actor['id'] === $userId) { $error = 'You cannot remove yourself.'; return false; }

        // Nullify company_id rather than deleting the account
        $updateData = ['company_id' => null, 'is_active' => 0];
        try {
            DB::query("SELECT status FROM ff_users WHERE id = ? LIMIT 1", [$userId]);
            $updateData['status'] = 'disabled';
        } catch (\Throwable $e) { }

        DB::update('ff_users', $updateData, 'id = ?', [$userId]);
        return true;
    }

    // ------------------------------------------------------------------ //
    // EMAIL TEMPLATE
    // ------------------------------------------------------------------ //

    private static function buildInviteEmail(string $token, string $companyName, string $inviterName, string $role): string {
        $url      = APP_URL . '/accept-invite.php?token=' . urlencode($token);
        $appName  = APP_NAME;
        $roleLabel = ucfirst($role);

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f3f4f6; margin:0; padding:40px 20px; }
  .card { background:#fff; max-width:520px; margin:0 auto; border-radius:16px; padding:40px; box-shadow:0 4px 24px rgba(0,0,0,.08); }
  .logo { font-size:22px; font-weight:700; color:#6366f1; margin-bottom:32px; }
  h2 { font-size:20px; color:#111827; margin:0 0 16px; }
  p  { color:#4b5563; line-height:1.6; margin:0 0 16px; }
  .btn { display:inline-block; background:#6366f1; color:#fff !important; text-decoration:none;
         font-weight:600; font-size:15px; padding:14px 32px; border-radius:12px; margin:16px 0; }
  .meta { background:#f9fafb; border-radius:10px; padding:14px 18px; font-size:13px; color:#6b7280; margin-top:24px; }
  .footer { text-align:center; font-size:12px; color:#9ca3af; margin-top:32px; }
</style></head>
<body>
<div class="card">
  <div class="logo">📣 {$appName}</div>
  <h2>You've been invited to join {$companyName}</h2>
  <p><strong>{$inviterName}</strong> has invited you to collaborate on <strong>{$companyName}</strong> as a <strong>{$roleLabel}</strong>.</p>
  <p>Click the button below to accept your invitation, set your password, and get started:</p>
  <a href="{$url}" class="btn">Accept Invitation →</a>
  <div class="meta">
    <strong>Link expires in 72 hours.</strong><br>
    If you didn't expect this invitation, you can safely ignore this email.
  </div>
  <div class="footer">{$appName} &middot; Sent by {$inviterName}</div>
</div>
</body>
</html>
HTML;
    }
}
