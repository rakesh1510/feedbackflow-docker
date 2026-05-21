<?php
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function slug(string $str): string {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\-]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

function randomKey(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function formatDate(string $datetime, string $format = 'M j, Y'): string {
    return date($format, strtotime($datetime));
}

function statusBadge(string $status): string {
    $map = [
        'new'          => ['bg-blue-100 text-blue-700', 'New'],
        'under_review' => ['bg-yellow-100 text-yellow-700', 'Under Review'],
        'planned'      => ['bg-indigo-100 text-indigo-700', 'Planned'],
        'in_progress'  => ['bg-orange-100 text-orange-700', 'In Progress'],
        'done'         => ['bg-green-100 text-green-700', 'Done'],
        'declined'     => ['bg-red-100 text-red-700', 'Declined'],
        'duplicate'    => ['bg-gray-100 text-gray-600', 'Duplicate'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-gray-100 text-gray-600', ucfirst($status)];
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium $cls\">$label</span>";
}

function priorityBadge(string $priority): string {
    $map = [
        'critical' => ['bg-red-100 text-red-700', '🔴 Critical'],
        'high'     => ['bg-orange-100 text-orange-700', '🟠 High'],
        'medium'   => ['bg-yellow-100 text-yellow-700', '🟡 Medium'],
        'low'      => ['bg-gray-100 text-gray-600', '⚪ Low'],
    ];
    [$cls, $label] = $map[$priority] ?? ['bg-gray-100 text-gray-600', ucfirst($priority)];
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium $cls\">$label</span>";
}

function sentimentBadge(?string $sentiment): string {
    if (!$sentiment) return '';
    $map = [
        'positive' => ['bg-green-100 text-green-700', '😊 Positive'],
        'neutral'  => ['bg-gray-100 text-gray-600', '😐 Neutral'],
        'negative' => ['bg-red-100 text-red-700', '😟 Negative'],
    ];
    [$cls, $label] = $map[$sentiment] ?? ['bg-gray-100 text-gray-600', $sentiment];
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium $cls\">$label</span>";
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function flash(string $type, string $msg): void {
    Auth::start();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): array {
    Auth::start();
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

function csrf(): string {
    Auth::start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = randomKey(32);
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): bool {
    Auth::start();
    $token = $_POST['_csrf'] ?? '';
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function rateLimit(string $action, int $limit = 10, int $windowSeconds = 60): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $row = DB::fetch("SELECT * FROM ff_rate_limits WHERE ip = ? AND action = ?", [$ip, $action]);
    if ($row) {
        $windowStart = strtotime($row['window_start']);
        if (time() - $windowStart > $windowSeconds) {
            DB::query("UPDATE ff_rate_limits SET count = 1, window_start = NOW() WHERE ip = ? AND action = ?", [$ip, $action]);
            return true;
        }
        if ($row['count'] >= $limit) {
            return false;
        }
        DB::query("UPDATE ff_rate_limits SET count = count + 1 WHERE ip = ? AND action = ?", [$ip, $action]);
    } else {
        DB::query("INSERT INTO ff_rate_limits (ip, action) VALUES (?, ?) ON DUPLICATE KEY UPDATE count = count + 1, window_start = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) > $windowSeconds, NOW(), window_start)", [$ip, $action]);
    }
    return true;
}

function logActivity(int $projectId, ?int $userId, ?int $feedbackId, string $action, array $meta = []): void {
    DB::insert('ff_activity', [
        'project_id'  => $projectId,
        'user_id'     => $userId,
        'feedback_id' => $feedbackId,
        'action'      => $action,
        'meta'        => $meta ? json_encode($meta) : null,
    ]);
}

function getUserProjects(int $userId): array {
    return DB::fetchAll(
        "SELECT p.*, 
            (SELECT COUNT(*) FROM ff_feedback WHERE project_id = p.id) as feedback_count,
            (SELECT COUNT(*) FROM ff_feedback WHERE project_id = p.id AND status = 'new') as new_count
         FROM ff_projects p
         LEFT JOIN ff_project_members pm ON pm.project_id = p.id AND pm.user_id = ?
         WHERE p.owner_id = ? OR pm.user_id = ?
         GROUP BY p.id
         ORDER BY p.created_at DESC",
        [$userId, $userId, $userId]
    );
}

function getProject(int $projectId, int $userId): ?array {
    return DB::fetch(
        "SELECT p.* FROM ff_projects p
         LEFT JOIN ff_project_members pm ON pm.project_id = p.id AND pm.user_id = ?
         WHERE p.id = ? AND (p.owner_id = ? OR pm.user_id = ?)",
        [$userId, $projectId, $userId, $userId]
    );
}

function sanitize(string $input): string {
    return trim(strip_tags($input));
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function uploadFile(array $file, string $subdir = 'attachments'): array|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) return false;
    $filename = randomKey(20) . '.' . $ext;
    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return false;
    return [
        'filename'      => $filename,
        'original_name' => $file['name'],
        'mime_type'     => $file['type'],
        'file_size'     => $file['size'],
        'url'           => UPLOAD_URL . $subdir . '/' . $filename,
    ];
}

function getNotificationCount(int $userId): int {
    return DB::count("SELECT COUNT(*) FROM ff_notifications WHERE user_id = ? AND is_read = 0", [$userId]);
}

function sendEmail(string $to, string $subject, string $htmlBody): bool {
    if (empty(SMTP_HOST)) {
        // Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">";
        return mail($to, $subject, $htmlBody, $headers);
    }
    // PHPMailer would go here — use composer if available
    // For now, use PHP mail() as fallback
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">";
    return mail($to, $subject, $htmlBody, $headers);
}

function aiAnalyzeFeedback(array $feedback): ?array {
    if (!AI_ENABLED) return null;
    $prompt = "Analyze this product feedback:\nTitle: {$feedback['title']}\nDescription: {$feedback['description']}\n\nReturn a JSON object with:\n- sentiment: positive|neutral|negative\n- sentiment_score: 0.0-1.0\n- summary: one sentence summary\n- priority_score: 0-10\n- suggested_tags: array of 1-3 tags\n- suggested_reply: a brief empathetic reply draft";
    $response = openAiCall($prompt);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

function openAiCall(string $prompt, string $model = null): ?string {
    if (!AI_ENABLED) return null;
    $model = $model ?? OPENAI_MODEL;
    $data = json_encode([
        'model'    => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'response_format' => ['type' => 'json_object'],
    ]);
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return null;
    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? null;
}

// ─────────────────────────────────────────────────────────────────────────────
// SMTP Mailer — no external dependencies required
// ─────────────────────────────────────────────────────────────────────────────
class SMTPMailer {
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $secure; // 'tls', 'ssl', or ''
    private        $socket = null;
    public  string $lastError = '';

    public function __construct(string $host, int $port, string $user, string $pass, string $secure = 'tls') {
        $this->host   = $host;
        $this->port   = $port;
        $this->user   = $user;
        $this->pass   = $pass;
        $this->secure = strtolower($secure);
    }

    public function send(string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $htmlBody): bool {
        try {
            if (!$this->connect()) return false;
            if (!$this->auth())    { $this->disconnect(); return false; }

            $boundary = '----=_Part_' . md5(uniqid());
            $plain    = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
            $plain    = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n"
                     . "To: =?UTF-8?B?" . base64_encode($toName ?: $toEmail) . "?= <{$toEmail}>\r\n"
                     . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                     . "MIME-Version: 1.0\r\n"
                     . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n"
                     . "X-Mailer: FeedbackFlow\r\n";

            $body = "--{$boundary}\r\n"
                  . "Content-Type: text/plain; charset=UTF-8\r\n"
                  . "Content-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($plain)) . "\r\n"
                  . "--{$boundary}\r\n"
                  . "Content-Type: text/html; charset=UTF-8\r\n"
                  . "Content-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($htmlBody)) . "\r\n"
                  . "--{$boundary}--\r\n";

            if (!$this->cmd("MAIL FROM:<{$fromEmail}>", 250)) { $this->disconnect(); return false; }
            if (!$this->cmd("RCPT TO:<{$toEmail}>",    250)) { $this->disconnect(); return false; }
            if (!$this->cmd("DATA", 354))                    { $this->disconnect(); return false; }

            fwrite($this->socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $resp = $this->read();
            if ((int)$resp !== 250) {
                $this->lastError = "DATA rejected: {$resp}";
                $this->disconnect();
                return false;
            }

            $this->cmd("QUIT", 221);
            $this->disconnect();
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    private function connect(): bool {
        $prefix = $this->secure === 'ssl' ? 'ssl://' : '';
        $errno = $errstr = null;
        $this->socket = @fsockopen($prefix . $this->host, $this->port, $errno, $errstr, 15);
        if (!$this->socket) {
            $this->lastError = "Cannot connect to {$this->host}:{$this->port} — {$errstr} ({$errno})";
            return false;
        }
        stream_set_timeout($this->socket, 15);
        $this->read(); // greeting

        if (!$this->cmd("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250)) return false;

        if ($this->secure === 'tls') {
            if (!$this->cmd("STARTTLS", 220)) return false;
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = "STARTTLS failed — TLS handshake error";
                return false;
            }
            if (!$this->cmd("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250)) return false;
        }
        return true;
    }

    private function auth(): bool {
        if (!$this->cmd("AUTH LOGIN", 334)) return false;
        if (!$this->cmd(base64_encode($this->user), 334)) {
            $this->lastError = "SMTP authentication failed — wrong username";
            return false;
        }
        if (!$this->cmd(base64_encode($this->pass), 235)) {
            $this->lastError = "SMTP authentication failed — wrong password";
            return false;
        }
        return true;
    }

    private function cmd(string $cmd, int $expectedCode): bool {
        fwrite($this->socket, $cmd . "\r\n");
        $resp = $this->read();
        if ((int)$resp !== $expectedCode) {
            $this->lastError = "CMD [{$cmd}] expected {$expectedCode}, got: {$resp}";
            return false;
        }
        return true;
    }

    private function read(): string {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if ($line[3] === ' ') break; // last line of multi-line response
        }
        return trim(substr($response, 0, 3));
    }

    private function disconnect(): void {
        if ($this->socket) { @fclose($this->socket); $this->socket = null; }
    }
}

/**
 * Send an email using configured SMTP or PHP mail() as fallback.
 * Returns true on success, false on failure.
 * On failure, $error is populated.
 */
function ffSendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string &$error = ''): bool {
    $host   = defined('SMTP_HOST')   ? SMTP_HOST   : '';
    $port   = defined('SMTP_PORT')   ? SMTP_PORT   : 587;
    $user   = defined('SMTP_USER')   ? SMTP_USER   : '';
    $pass   = defined('SMTP_PASS')   ? SMTP_PASS   : '';
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    $from   = defined('SMTP_FROM')   ? SMTP_FROM   : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $fname  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'FeedbackFlow');

    if ($host && $user) {
        // Use SMTP
        $mailer = new SMTPMailer($host, (int)$port, $user, $pass, $secure);
        $ok = $mailer->send($toEmail, $toName, $from, $fname, $subject, $htmlBody);
        if (!$ok) $error = $mailer->lastError;
        return $ok;
    }

    // Fallback: PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fname} <{$from}>\r\n";
    $to = $toName ? ('"' . addslashes($toName) . '" <' . $toEmail . '>') : $toEmail;
    $ok = @mail($to, $subject, $htmlBody, $headers);
    if (!$ok) $error = 'PHP mail() failed. Configure SMTP in config.php for reliable delivery.';
    return $ok;
}

// ─────────────────────────────────────────────────────────────────────────────
// AI COPILOT — Intent detection, clustering, insights, auto-reply
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Keyword-based intent detection fallback (no OpenAI required)
 */
function aiDetectIntentKeywords(string $text): string {
    $text = strtolower($text);
    $intents = [
        'bug'         => ['bug','crash','error','broken','doesn\'t work','not working','fail','issue','problem','exception','freeze','hang','loop','missing','disappeared','404','500','can\'t','cannot','won\'t','stopped'],
        'performance' => ['slow','fast','loading','speed','lag','response','timeout','performance','heavy','memory','ram','cpu','latency','delay','wait'],
        'pricing'     => ['expensive','price','cost','cheap','billing','invoice','subscription','plan','trial','refund','charge','fee','payment','upsell'],
        'feature'     => ['would like','please add','wish','want','need','request','missing','add','feature','suggest','proposal','idea','could you','can you add','integrate','support for'],
        'ux'          => ['confusing','hard to use','difficult','unclear','ux','ui','design','navigate','menu','layout','intuitive','complicated','ugly','clean','simple','flow','button','click'],
        'praise'      => ['great','love','excellent','amazing','perfect','awesome','best','fantastic','thank','happy','helpful','brilliant','wonderful','superb','good job','nice work'],
    ];
    $scores = [];
    foreach ($intents as $intent => $keywords) {
        $scores[$intent] = 0;
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) $scores[$intent]++;
        }
    }
    arsort($scores);
    $top = array_key_first($scores);
    return ($scores[$top] > 0) ? $top : 'other';
}

/**
 * Use OpenAI to detect intent and generate a concise cluster label for feedback text.
 * Falls back to keyword matching if AI is disabled.
 */
function aiAnalyzeFeedbackItem(array $fb): array {
    $text  = trim(($fb['title'] ?? '') . ' ' . ($fb['description'] ?? ''));
    $intent = aiDetectIntentKeywords($text);
    $reply = '';

    if (!AI_ENABLED) {
        return ['intent' => $intent, 'cluster_label' => '', 'reply' => ''];
    }

    $prompt = 'Analyze this user feedback and return ONLY a JSON object with keys: ' .
              '"intent" (one of: bug, feature, ux, pricing, performance, praise, other), ' .
              '"cluster_label" (3-6 word topic summary like "Checkout button not working"), ' .
              '"reply" (a warm 1-2 sentence reply from the product team acknowledging the feedback). ' .
              'Feedback: ' . mb_substr($text, 0, 400);

    $result = callOpenAI($prompt, 200);
    if ($result) {
        $cleaned = preg_replace('/```json|```/', '', $result);
        $json = json_decode(trim($cleaned), true);
        if ($json) {
            $intent = $json['intent'] ?? $intent;
            $reply  = $json['reply']  ?? '';
            return ['intent' => $intent, 'cluster_label' => $json['cluster_label'] ?? '', 'reply' => $reply];
        }
    }
    return ['intent' => $intent, 'cluster_label' => '', 'reply' => ''];
}

/**
 * Run full AI analysis on all recent feedback for a project.
 * Updates ff_feedback.ai_intent, builds ff_ai_clusters, generates ff_ai_insights.
 */
function runAICopilotAnalysis(int $projectId): array {
    $feedback = DB::fetchAll(
        "SELECT id, title, description, ai_sentiment, ai_intent, created_at FROM ff_feedback
         WHERE project_id = ? ORDER BY created_at DESC LIMIT 200",
        [$projectId]
    );

    if (empty($feedback)) return ['clustered' => 0, 'insights' => 0];

    // Step 1: Detect intent for unanalyzed feedback
    $allIntents = []; // feedback_id => intent
    $allLabels  = []; // feedback_id => cluster_label
    $allReplies = []; // feedback_id => reply

    if (AI_ENABLED && count($feedback) > 0) {
        // Batch via OpenAI — send up to 30 items at once
        $chunks = array_chunk($feedback, 20);
        foreach ($chunks as $chunk) {
            $list = '';
            foreach ($chunk as $i => $fb) {
                $text = mb_substr(trim(($fb['title'] ?? '') . ' ' . ($fb['description'] ?? '')), 0, 200);
                $list .= ($i + 1) . '. [ID:' . $fb['id'] . '] ' . $text . "\n";
            }
            $prompt = 'For each feedback item below, return ONLY a JSON array. Each element: ' .
                      '{"id": <number>, "intent": "<bug|feature|ux|pricing|performance|praise|other>", ' .
                      '"cluster_label": "<3-6 word topic>", "reply": "<1-2 sentence warm reply>"}' .
                      "\n\nFeedback items:\n" . $list;

            $result = callOpenAI($prompt, 1500);
            if ($result) {
                $cleaned = preg_replace('/```json|```/', '', $result);
                $json = json_decode(trim($cleaned), true);
                if (is_array($json)) {
                    foreach ($json as $item) {
                        $fid = $item['id'] ?? null;
                        if ($fid) {
                            $allIntents[$fid] = $item['intent']       ?? 'other';
                            $allLabels[$fid]  = $item['cluster_label'] ?? '';
                            $allReplies[$fid] = $item['reply']         ?? '';
                        }
                    }
                }
            }
        }
    }

    // Update each feedback item's intent (and reply if generated)
    foreach ($feedback as $fb) {
        $fid    = $fb['id'];
        $intent = $allIntents[$fid] ?? aiDetectIntentKeywords(($fb['title'] ?? '') . ' ' . ($fb['description'] ?? ''));
        $reply  = $allReplies[$fid] ?? '';
        $allIntents[$fid] = $intent;
        $update = ['ai_intent' => $intent];
        if ($reply) $update['ai_reply'] = $reply;
        DB::update('ff_feedback', $update, 'id = ?', [$fid]);
    }

    // Step 2: Build clusters
    // Delete old clusters for this project (fresh re-cluster)
    DB::query("DELETE FROM ff_cluster_feedback WHERE cluster_id IN (SELECT id FROM ff_ai_clusters WHERE project_id = ?)", [$projectId]);
    DB::query("DELETE FROM ff_ai_clusters WHERE project_id = ?", [$projectId]);

    // Group feedback by intent + cluster_label
    $groups = []; // key => [intent, label, feedback_ids, sentiments]
    foreach ($feedback as $fb) {
        $fid    = $fb['id'];
        $intent = $allIntents[$fid] ?? 'other';
        $label  = $allLabels[$fid]  ?? '';
        // If no label from AI, group purely by intent
        $key = $label ? md5($label) : $intent;
        if (!isset($groups[$key])) {
            $groups[$key] = ['intent' => $intent, 'label' => $label ?: ucfirst($intent) . ' feedback', 'feedback_ids' => [], 'sentiments' => []];
        }
        $groups[$key]['feedback_ids'][] = $fid;
        $groups[$key]['sentiments'][]   = $fb['ai_sentiment'] ?? 'neutral';
    }

    // Step 3: Compute trends (this week vs last week count per cluster label)
    $thisWeekStart = date('Y-m-d H:i:s', strtotime('monday this week'));
    $lastWeekStart = date('Y-m-d H:i:s', strtotime('monday last week'));
    $lastWeekEnd   = date('Y-m-d H:i:s', strtotime('sunday last week'));

    $clusterCount = 0;
    foreach ($groups as $group) {
        if (empty($group['feedback_ids'])) continue;
        $fids       = $group['feedback_ids'];
        $sentiments = $group['sentiments'];
        $negCount   = count(array_filter($sentiments, fn($s) => $s === 'negative'));
        $posCount   = count(array_filter($sentiments, fn($s) => $s === 'positive'));
        $total      = count($sentiments);
        $avgSent    = $negCount > $total * 0.5 ? 'negative' : ($posCount > $total * 0.5 ? 'positive' : 'neutral');

        // Severity based on count + sentiment
        $severity = 'low';
        if ($total >= 10 && $negCount > 3) $severity = 'critical';
        elseif ($total >= 5 || $negCount > 2) $severity = 'high';
        elseif ($total >= 2) $severity = 'medium';

        // Trend: compare this week vs last week
        $placeholders = implode(',', array_fill(0, count($fids), '?'));
        $thisWeek = (int)DB::count(
            "SELECT COUNT(*) FROM ff_feedback WHERE id IN ($placeholders) AND created_at >= ?",
            array_merge($fids, [$thisWeekStart])
        );
        $lastWeek = (int)DB::count(
            "SELECT COUNT(*) FROM ff_feedback WHERE id IN ($placeholders) AND created_at BETWEEN ? AND ?",
            array_merge($fids, [$lastWeekStart, $lastWeekEnd])
        );
        $trend    = 'stable';
        $trendPct = 0;
        if ($lastWeek > 0) {
            $trendPct = (int)(($thisWeek - $lastWeek) / $lastWeek * 100);
            if ($trendPct > 20)  $trend = 'rising';
            if ($trendPct < -20) $trend = 'falling';
        } elseif ($thisWeek > 0) {
            $trend = 'rising'; $trendPct = 100;
        }

        // Action suggestion
        $actions = [
            'bug'         => 'Create a bug ticket and assign to engineering',
            'feature'     => 'Add to product roadmap for review',
            'ux'          => 'Schedule a UX review session',
            'pricing'     => 'Review pricing page and share with sales team',
            'performance' => 'Profile and optimize the affected flow',
            'praise'      => 'Share with the team and reply to thank users',
            'other'       => 'Review and categorize manually',
        ];
        $suggestedAction = $actions[$group['intent']] ?? 'Review and categorize manually';

        $clusterId = DB::insert('ff_ai_clusters', [
            'project_id'       => $projectId,
            'title'            => mb_substr($group['label'], 0, 250),
            'intent'           => $group['intent'],
            'severity'         => $severity,
            'feedback_count'   => $total,
            'avg_sentiment'    => $avgSent,
            'trend'            => $trend,
            'trend_pct'        => abs($trendPct),
            'suggested_action' => $suggestedAction,
        ]);

        foreach ($fids as $fid) {
            DB::query("INSERT IGNORE INTO ff_cluster_feedback (cluster_id, feedback_id) VALUES (?, ?)", [$clusterId, $fid]);
        }
        $clusterCount++;
    }

    // Step 4: Generate insights
    DB::query("DELETE FROM ff_ai_insights WHERE project_id = ?", [$projectId]);
    $insightCount = generateAIInsights($projectId);

    return ['clustered' => count($feedback), 'clusters' => $clusterCount, 'insights' => $insightCount];
}

/**
 * Generate high-level CEO insights from cluster data + sentiment trends.
 */
function generateAIInsights(int $projectId): int {
    $count = 0;

    // Rising critical clusters
    $rising = DB::fetchAll(
        "SELECT * FROM ff_ai_clusters WHERE project_id = ? AND trend = 'rising' AND severity IN ('critical','high') ORDER BY feedback_count DESC LIMIT 3",
        [$projectId]
    );
    foreach ($rising as $cl) {
        DB::insert('ff_ai_insights', [
            'project_id' => $projectId,
            'type'       => 'trending',
            'icon'       => '🚨',
            'title'      => $cl['title'] . ' is trending up',
            'body'       => $cl['feedback_count'] . ' reports this week, ' . ($cl['trend_pct'] > 0 ? '+' . $cl['trend_pct'] . '% vs last week.' : 'newly emerging.') . ' Severity: ' . strtoupper($cl['severity']) . '.',
            'metric'     => ($cl['trend_pct'] > 0 ? '+' : '') . $cl['trend_pct'] . '% this week',
        ]);
        $count++;
    }

    // Sentiment shift (this week vs last week)
    $thisWeekNeg  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment = 'negative' AND created_at >= ?", [$projectId, date('Y-m-d', strtotime('monday this week'))]);
    $lastWeekNeg  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment = 'negative' AND created_at BETWEEN ? AND ?", [$projectId, date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))]);
    $thisWeekPos  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment = 'positive' AND created_at >= ?", [$projectId, date('Y-m-d', strtotime('monday this week'))]);
    $lastWeekPos  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment = 'positive' AND created_at BETWEEN ? AND ?", [$projectId, date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))]);

    if ($lastWeekNeg > 0 && $thisWeekNeg > $lastWeekNeg) {
        $pct = (int)(($thisWeekNeg - $lastWeekNeg) / $lastWeekNeg * 100);
        DB::insert('ff_ai_insights', ['project_id' => $projectId, 'type' => 'sentiment', 'icon' => '⚠️', 'title' => 'Negative sentiment increased by ' . $pct . '%', 'body' => 'This week: ' . $thisWeekNeg . ' negative vs ' . $lastWeekNeg . ' last week. Review top issues and consider proactive communication.', 'metric' => '+' . $pct . '% negative']);
        $count++;
    }
    if ($lastWeekPos > 0 && $thisWeekPos > $lastWeekPos) {
        $pct = (int)(($thisWeekPos - $lastWeekPos) / $lastWeekPos * 100);
        DB::insert('ff_ai_insights', ['project_id' => $projectId, 'type' => 'praise', 'icon' => '🎉', 'title' => 'Positive sentiment up ' . $pct . '% this week', 'body' => 'This week: ' . $thisWeekPos . ' positive vs ' . $lastWeekPos . ' last week. Users are responding well!', 'metric' => '+' . $pct . '% positive']);
        $count++;
    }

    // Feature request cluster
    $topFeature = DB::fetch("SELECT * FROM ff_ai_clusters WHERE project_id = ? AND intent = 'feature' ORDER BY feedback_count DESC LIMIT 1", [$projectId]);
    if ($topFeature) {
        DB::insert('ff_ai_insights', ['project_id' => $projectId, 'type' => 'trending', 'icon' => '💡', 'title' => 'Top feature request: ' . $topFeature['title'], 'body' => $topFeature['feedback_count'] . ' users requesting this. Consider adding it to the roadmap.', 'metric' => $topFeature['feedback_count'] . ' requests']);
        $count++;
    }

    // Release impact — compare sentiment 7 days before vs after each changelog
    $releases = DB::fetchAll("SELECT * FROM ff_changelog WHERE project_id = ? AND published_at IS NOT NULL AND published_at <= NOW() ORDER BY published_at DESC LIMIT 3", [$projectId]);
    foreach ($releases as $rel) {
        $before = $rel['published_at'];
        $beforeStart = date('Y-m-d H:i:s', strtotime($before . ' -7 days'));
        $afterEnd    = date('Y-m-d H:i:s', strtotime($before . ' +7 days'));
        $negBefore = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='negative' AND created_at BETWEEN ? AND ?", [$projectId, $beforeStart, $before]);
        $negAfter  = (int)DB::count("SELECT COUNT(*) FROM ff_feedback WHERE project_id = ? AND ai_sentiment='negative' AND created_at BETWEEN ? AND ?", [$projectId, $before, $afterEnd]);
        if ($negBefore > 2 && $negAfter < $negBefore) {
            $drop = $negBefore - $negAfter;
            $pct  = (int)($drop / $negBefore * 100);
            DB::insert('ff_ai_insights', ['project_id' => $projectId, 'type' => 'release_impact', 'icon' => '📉', 'title' => 'Release "' . mb_substr($rel['title'], 0, 40) . '" reduced complaints by ' . $pct . '%', 'body' => 'Negative feedback dropped from ' . $negBefore . ' to ' . $negAfter . ' after this release. Great impact!', 'metric' => '-' . $pct . '% complaints']);
            $count++;
        }
    }

    return $count;
}

/**
 * Generate an AI reply for a single feedback item.
 */
function aiGenerateReply(array $fb, string $projectName = ''): string {
    $text = trim(($fb['title'] ?? '') . '. ' . ($fb['description'] ?? ''));
    $name = $fb['submitter_name'] ? ('Hi ' . explode(' ', $fb['submitter_name'])[0] . ', ') : '';

    if (!AI_ENABLED) {
        $templates = [
            'bug'         => "{$name}Thank you for reporting this issue. Our team is investigating and will work on a fix as soon as possible.",
            'feature'     => "{$name}Thanks for the suggestion! We've logged this feature request and will consider it for a future release.",
            'praise'      => "{$name}Thank you so much — feedback like this means a lot to our team. We'll keep working hard!",
            'performance' => "{$name}Thanks for letting us know. We're actively working on improving performance and will address this.",
            'pricing'     => "{$name}Thank you for your feedback. Our team will review your pricing concerns.",
            'ux'          => "{$name}Thanks for pointing this out. We're always working to improve the user experience based on feedback like yours.",
            'other'       => "{$name}Thank you for your feedback! We've received it and our team will review it.",
        ];
        return $templates[$fb['ai_intent'] ?? 'other'] ?? $templates['other'];
    }

    $prompt = 'Write a warm, professional 2-sentence reply from the product team of "' . $projectName . '" to this user feedback. ' .
              'Address their specific concern. Be empathetic and specific. ' .
              'Feedback: ' . mb_substr($text, 0, 300);
    return callOpenAI($prompt, 150) ?? "Thank you for your feedback! We've received it and our team will review it shortly.";
}

function triggerWebhooks(int $projectId, string $event, array $payload): void {
    $webhooks = DB::fetchAll(
        "SELECT * FROM ff_webhooks WHERE project_id = ? AND is_active = 1 AND events LIKE ?",
        [$projectId, "%$event%"]
    );
    foreach ($webhooks as $wh) {
        $body = json_encode(['event' => $event, 'data' => $payload, 'timestamp' => time()]);
        $sig = $wh['secret'] ? hash_hmac('sha256', $body, $wh['secret']) : '';
        $ch = curl_init($wh['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-FeedbackFlow-Signature: ' . $sig,
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
        DB::query("UPDATE ff_webhooks SET last_triggered = NOW() WHERE id = ?", [$wh['id']]);
    }
}

/**
 * Output CSRF hidden input field
 */
function csrfInput(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf(), ENT_QUOTES) . '">';
}

/**
 * Log an audit event to ff_audit_logs
 */
function logAudit(array $user, string $action, string $resourceType = null, int $resourceId = null, array $oldValues = null, array $newValues = null): void {
    try {
        DB::insert('ff_audit_logs', [
            'user_id'       => $user['id'] ?? null,
            'user_name'     => $user['name'] ?? null,
            'user_email'    => $user['email'] ?? null,
            'action'        => $action,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'old_values'    => $oldValues ? json_encode($oldValues) : null,
            'new_values'    => $newValues ? json_encode($newValues) : null,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (\Throwable $e) {
        // Fail silently — audit logging should never break the application
        if (DEBUG_MODE) error_log("logAudit failed: " . $e->getMessage());
    }
}

/**
 * Translation helper — Module 19 (Multi-Language)
 */
function __(string $key, array $replace = [], string $lang = null): string {
    static $strings = [];
    $lang = $lang ?? ($_SESSION['lang'] ?? 'en');
    if (!isset($strings[$lang])) {
        $file = dirname(__DIR__) . '/lang/' . preg_replace('/[^a-z\-]/', '', $lang) . '.php';
        $strings[$lang] = file_exists($file) ? (require $file) : [];
    }
    $val = $strings[$lang][$key] ?? $strings['en'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $val = str_replace(':' . $k, $v, $val);
    }
    return $val;
}

/**
 * Dispatch a background job to the ff_jobs queue
 */
function dispatchJob(string $type, array $payload = [], int $delaySeconds = 0): int {
    return DB::insert('ff_jobs', [
        'type'         => $type,
        'payload'      => json_encode($payload),
        'status'       => 'pending',
        'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
    ]);
}

/**
 * Check if an email/phone is in the suppression list
 */
function isSuppressed(string $value): bool {
    return (bool)DB::fetch("SELECT id FROM ff_suppression WHERE value = ? AND 1=1", [$value]);
}

/**
 * Get notification count for a user (unread)
 */
function getUnreadNotificationCount(int $userId): int {
    return DB::count("SELECT COUNT(*) FROM ff_notifications WHERE user_id = ? AND is_read = 0", [$userId]);
}

/**
 * Create a notification for a user
 */
function createNotification(int $userId, string $type, string $message, int $projectId = null, int $feedbackId = null): void {
    DB::insert('ff_notifications', [
        'user_id'     => $userId,
        'project_id'  => $projectId,
        'feedback_id' => $feedbackId,
        'type'        => $type,
        'message'     => $message,
        'is_read'     => 0,
    ]);
}

/**
 * Increment usage counter for a company
 */
function trackUsage(int $companyId, string $metric, int $amount = 1): void {
    $month = date('Y-m');
    DB::query("INSERT INTO ff_usage (company_id, year_month, $metric) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $metric = $metric + ?", [$companyId, $month, $amount, $amount]);
}

/**
 * Format bytes to human-readable size
 */
function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}
