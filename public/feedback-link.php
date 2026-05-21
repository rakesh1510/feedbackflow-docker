<?php
/**
 * Universal Feedback Page
 * Used by: Email campaigns, QR codes, WhatsApp links, SMS links, direct links
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/db-manager.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$token     = sanitize($_GET['token'] ?? '');
$slug      = sanitize($_GET['slug'] ?? '');
$source    = sanitize($_GET['source'] ?? 'direct');
$preRating = (int)($_GET['r'] ?? 0);

$link = null;
$project = null;
$campaign = null;
$recipient = null;

/**
 * Resolve tenant FIRST before touching tenant tables.
 *
 * Priority:
 * 1. slug            -> project slug lookup in master mapping
 * 2. token           -> public token lookup in master mapping
 */
$resolvedCompanyId = null;

if ($slug !== '') {
    $resolvedCompanyId = DBManager::findCompanyIdByProjectSlug($slug);
}

if (!$resolvedCompanyId && $token !== '') {
    $resolvedCompanyId = DBManager::findCompanyIdByPublicToken($token);
}

if (!$resolvedCompanyId && $token !== '') {
    $resolvedCompanyId = DBManager::findCompanyIdByWidgetKey($token);
}

if ($resolvedCompanyId) {
    DB::useTenantForCompany((int)$resolvedCompanyId);
}

if ($token !== '') {
    // Tenant DB query
    $recipient = DB::fetch(
        "SELECT * FROM ff_campaign_recipients WHERE token = ? LIMIT 1",
        [$token]
    );

    if ($recipient) {
        $campaign = DB::fetch(
            "SELECT * FROM ff_email_campaigns WHERE id = ? LIMIT 1",
            [$recipient['campaign_id']]
        );

        if ($campaign) {
            $project = DB::fetch(
                "SELECT * FROM ff_projects WHERE id = ? LIMIT 1",
                [$campaign['project_id']]
            );

            $source = 'email';
            $preRating = $preRating ?: (int)($recipient['pre_rating'] ?? 0);

            if (!empty($recipient['opened_at']) === false) {
                DB::update(
                    'ff_campaign_recipients',
                    ['opened_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$recipient['id']]
                );

                DB::query(
                    "UPDATE ff_email_campaigns SET open_count = COALESCE(open_count, 0) + 1 WHERE id = ?",
                    [$campaign['id']]
                );
            }
        }
    } else {
        $link = DB::fetch(
            "SELECT * FROM ff_feedback_links WHERE token = ? AND is_active = 1 LIMIT 1",
            [$token]
        );

        if ($link) {
            $project = DB::fetch(
                "SELECT * FROM ff_projects WHERE id = ? LIMIT 1",
                [$link['project_id']]
            );

            $source = $link['source'] ?? 'direct';

            DB::query(
                "UPDATE ff_feedback_links SET click_count = COALESCE(click_count, 0) + 1 WHERE id = ?",
                [$link['id']]
            );
        }
    }
} elseif ($slug !== '') {
    $project = DB::fetch(
        "SELECT * FROM ff_projects WHERE slug = ? AND is_public = 1 LIMIT 1",
        [$slug]
    );
}

if (!$project) {
    http_response_code(404);
    die('<h1 style="font-family:sans-serif;text-align:center;margin-top:100px">Feedback link not found or expired.</h1>');
}

$categories = DB::fetchAll(
    "SELECT * FROM ff_categories WHERE project_id = ? ORDER BY sort_order",
    [$project['id']]
);

$ratingQuestion = $campaign['rating_question'] ?? $link['rating_question'] ?? 'How was your experience?';
$submitted = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = sanitize($_POST['title'] ?? '');
    $desc   = sanitize($_POST['description'] ?? '');
    $catId  = (int)($_POST['category_id'] ?? 0);
    $name   = sanitize($_POST['submitter_name'] ?? '');
    $email  = sanitize($_POST['submitter_email'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if ($title === '' && $desc === '') {
        $error = 'Please write your feedback before submitting.';
    } else {
        $title = $title ?: mb_substr($desc, 0, 80);

        $fid = DB::insert('ff_feedback', [
            'project_id'      => $project['id'],
            'source'          => $source,
            'rating'          => $rating ?: null,
            'campaign_id'     => $campaign['id'] ?? null,
            'category_id'     => $catId ?: null,
            'title'           => $title,
            'description'     => $desc,
            'submitter_name'  => $name ?: ($recipient['name'] ?? null),
            'submitter_email' => $email ?: ($recipient['email'] ?? null),
            'status'          => 'new',
            'priority'        => 'medium',
            'is_public'       => 1,
        ]);

        if ($recipient) {
            DB::update(
                'ff_campaign_recipients',
                [
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'feedback_id'  => $fid,
                    'pre_rating'   => $rating ?: $preRating,
                ],
                'id = ?',
                [$recipient['id']]
            );

            DB::query(
                "UPDATE ff_email_campaigns SET submit_count = COALESCE(submit_count, 0) + 1 WHERE id = ?",
                [$campaign['id']]
            );
        }

        if ($link) {
            DB::query(
                "UPDATE ff_feedback_links SET submit_count = COALESCE(submit_count, 0) + 1 WHERE id = ?",
                [$link['id']]
            );
        }

        $submitted = true;
    }
}

$color = $project['widget_color'] ?? '#6366f1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Feedback – <?= h($project['name']) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<style>
:root{
    --brand: <?= h($color) ?>;
    --bg: #f8fafc;
    --card: #ffffff;
    --text: #111827;
    --muted: #6b7280;
    --border: #e5e7eb;
    --danger: #dc2626;
    --success: #16a34a;
}
*{box-sizing:border-box}
body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:linear-gradient(180deg,#f8fafc 0%,#eef2ff 100%);
    color:var(--text);
}
.wrap{
    max-width:760px;
    margin:40px auto;
    padding:20px;
}
.card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:24px;
    box-shadow:0 10px 35px rgba(2,6,23,.06);
    overflow:hidden;
}
.hero{
    padding:28px 28px 14px;
    background:linear-gradient(135deg, rgba(99,102,241,.08), rgba(255,255,255,1));
}
.badge{
    display:inline-block;
    background:var(--brand);
    color:#fff;
    font-size:12px;
    font-weight:700;
    padding:8px 12px;
    border-radius:999px;
    margin-bottom:12px;
}
h1{
    margin:0 0 8px;
    font-size:30px;
    line-height:1.15;
}
.sub{
    margin:0;
    color:var(--muted);
    line-height:1.6;
}
.form{
    padding:28px;
}
.grid{
    display:grid;
    gap:16px;
}
label{
    font-size:14px;
    font-weight:600;
    display:block;
    margin-bottom:8px;
}
input[type="text"],
input[type="email"],
textarea,
select{
    width:100%;
    border:1px solid var(--border);
    border-radius:14px;
    padding:14px 16px;
    font:inherit;
    background:#fff;
}
textarea{
    min-height:140px;
    resize:vertical;
}
.row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
@media (max-width: 720px){
    .row{grid-template-columns:1fr}
    .wrap{margin:18px auto;padding:12px}
    .hero,.form{padding:18px}
    h1{font-size:24px}
}
.rating{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}
.rating label{
    margin:0;
    cursor:pointer;
}
.rating input{
    display:none;
}
.star{
    border:1px solid var(--border);
    border-radius:14px;
    padding:12px 14px;
    min-width:54px;
    text-align:center;
    font-weight:700;
    background:#fff;
}
.rating input:checked + .star{
    background:var(--brand);
    color:#fff;
    border-color:var(--brand);
}
.actions{
    display:flex;
    align-items:center;
    gap:12px;
    margin-top:10px;
}
.btn{
    appearance:none;
    border:0;
    background:var(--brand);
    color:#fff;
    font:inherit;
    font-weight:700;
    padding:14px 20px;
    border-radius:14px;
    cursor:pointer;
}
.error{
    background:#fef2f2;
    color:var(--danger);
    border:1px solid #fecaca;
    padding:12px 14px;
    border-radius:14px;
    margin-bottom:16px;
}
.success{
    padding:30px 28px;
}
.successBox{
    background:#f0fdf4;
    color:#166534;
    border:1px solid #bbf7d0;
    border-radius:18px;
    padding:18px;
}
.meta{
    margin-top:16px;
    color:var(--muted);
    font-size:14px;
}
.small{
    color:var(--muted);
    font-size:13px;
}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="hero">
            <div class="badge"><?= h(strtoupper($source)) ?> FEEDBACK</div>
            <h1><?= h($ratingQuestion) ?></h1>
            <p class="sub">
                Share your feedback for <strong><?= h($project['name']) ?></strong>.
                Your response helps us improve.
            </p>
        </div>

        <?php if ($submitted): ?>
            <div class="success">
                <div class="successBox">
                    <h2 style="margin-top:0">Thank you for your feedback!</h2>
                    <p style="margin-bottom:0">Your response has been submitted successfully.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="form">
                <?php if ($error): ?>
                    <div class="error"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="post" class="grid">
                    <div>
                        <label><?= h($ratingQuestion) ?></label>
                        <div class="rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label>
                                    <input type="radio" name="rating" value="<?= $i ?>" <?= (($preRating ?: 0) === $i) ? 'checked' : '' ?>>
                                    <span class="star"><?= $i ?> ★</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <?php if (!empty($categories)): ?>
                        <div>
                            <label for="category_id">Category</label>
                            <select name="category_id" id="category_id">
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= h($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" placeholder="Short summary">
                    </div>

                    <div>
                        <label for="description">Feedback</label>
                        <textarea id="description" name="description" placeholder="Tell us more..."></textarea>
                    </div>

                    <div class="row">
                        <div>
                            <label for="submitter_name">Your name</label>
                            <input
                                type="text"
                                id="submitter_name"
                                name="submitter_name"
                                value="<?= h($recipient['name'] ?? '') ?>"
                                placeholder="Optional"
                            >
                        </div>
                        <div>
                            <label for="submitter_email">Your email</label>
                            <input
                                type="email"
                                id="submitter_email"
                                name="submitter_email"
                                value="<?= h($recipient['email'] ?? '') ?>"
                                placeholder="Optional"
                            >
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn" type="submit">Submit Feedback</button>
                    </div>

                    <div class="small">
                        By submitting, you agree that your feedback may be reviewed by the project team.
                    </div>
                </form>

                <div class="meta">
                    Project: <strong><?= h($project['name']) ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>