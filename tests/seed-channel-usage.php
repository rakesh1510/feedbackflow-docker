<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

DB::query("USE `abdddd_company_1779095233467_256148_feedbackflow`");

$period = date('Y-m');

$columns = DB::fetchAll("SHOW COLUMNS FROM ff_billing_usage");
$fields = array_column($columns, 'Field');

function hasCol($fields, $name) {
    return in_array($name, $fields, true);
}

$row = DB::fetch("SELECT * FROM ff_billing_usage LIMIT 1");

$data = [];

if (hasCol($fields, 'period')) $data['period'] = $period;
if (hasCol($fields, 'usage_period')) $data['usage_period'] = $period;
if (hasCol($fields, 'month')) $data['month'] = $period;

if (hasCol($fields, 'emails_used')) $data['emails_used'] = 5000;
if (hasCol($fields, 'email_used')) $data['email_used'] = 5000;
if (hasCol($fields, 'email_count')) $data['email_count'] = 5000;
if (hasCol($fields, 'emails_this_month')) $data['emails_this_month'] = 5000;

if (hasCol($fields, 'whatsapp_used')) $data['whatsapp_used'] = 500;
if (hasCol($fields, 'whatsapp_count')) $data['whatsapp_count'] = 500;
if (hasCol($fields, 'whatsapp_this_month')) $data['whatsapp_this_month'] = 500;

if (hasCol($fields, 'sms_used')) $data['sms_used'] = 200;
if (hasCol($fields, 'sms_count')) $data['sms_count'] = 200;
if (hasCol($fields, 'sms_this_month')) $data['sms_this_month'] = 200;

if (hasCol($fields, 'created_at')) $data['created_at'] = date('Y-m-d H:i:s');
if (hasCol($fields, 'updated_at')) $data['updated_at'] = date('Y-m-d H:i:s');

if (!$data) {
    die("No matching usage columns found.\n");
}

if ($row) {
    $set = [];
    $params = [];
    foreach ($data as $key => $value) {
        if ($key === 'created_at') continue;
        $set[] = "`$key` = ?";
        $params[] = $value;
    }

    DB::query("UPDATE ff_billing_usage SET " . implode(', ', $set) . " LIMIT 1", $params);
    echo "Updated existing usage row.\n";
} else {
    DB::insert('ff_billing_usage', $data);
    echo "Inserted new usage row.\n";
}

echo "DONE: Email=5000, WhatsApp=500, SMS=200\n";
