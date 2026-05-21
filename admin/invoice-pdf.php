<?php
/**
 * VAT-ready invoice viewer/print page.
 * Accessible as: /admin/invoice-pdf.php?id=123
 * Prints a clean A4 invoice — user can Ctrl+P to PDF.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/billing.php';

$currentUser = Auth::require();
$invoiceId   = (int)($_GET['id'] ?? 0);

// Fetch invoice (company must match current user's company)
$company = BillingService::getCompany($currentUser['id']);
$companyId = $company ? (int)$company['id'] : 0;

$invoice = DB::fetch(
    "SELECT * FROM ff_invoices WHERE id = ? AND company_id = ?",
    [$invoiceId, $companyId]
);

if (!$invoice && !($currentUser['is_super_admin'] ?? 0)) {
    http_response_code(404);
    echo '<h2>Invoice not found.</h2>';
    exit;
}
if (!$invoice) {
    $invoice = DB::fetch("SELECT * FROM ff_invoices WHERE id = ?", [$invoiceId]);
}
if (!$invoice) {
    http_response_code(404);
    echo '<h2>Invoice not found.</h2>';
    exit;
}

$lineItems = json_decode($invoice['line_items'] ?? '[]', true) ?: [];
$subtotal  = isset($invoice['subtotal']) ? (float)$invoice['subtotal'] : ((float)$invoice['amount'] - (float)($invoice['vat_amount'] ?? 0));
$vatRate   = (float)($invoice['vat_rate']   ?? $invoice['tax_rate'] ?? 0);
$vatAmount = (float)($invoice['vat_amount'] ?? $invoice['tax_amount'] ?? 0);
$total     = (float)$invoice['amount'];
$currency  = $invoice['currency'] ?? 'EUR';
$statusCls = match($invoice['status']) {
    'paid'  => 'color:#16a34a;border-color:#bbf7d0;background:#f0fdf4',
    'void'  => 'color:#9ca3af;border-color:#e5e7eb;background:#f9fafb',
    default => 'color:#d97706;border-color:#fde68a;background:#fffbeb',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= h($invoice['invoice_number']) ?> — <?= APP_NAME ?></title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Inter',sans-serif;background:#f3f4f6;color:#111827;font-size:14px;}
  .page{max-width:750px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);}
  .header{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:40px 48px 32px;}
  .header h1{font-size:28px;font-weight:800;letter-spacing:-.5px;}
  .header p{opacity:.75;font-size:13px;margin-top:4px;}
  .body{padding:40px 48px;}
  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:32px;}
  .label{font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
  .value{font-size:14px;color:#111827;line-height:1.6;}
  .status-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:99px;font-size:12px;font-weight:700;border:1.5px solid;<?= $statusCls ?>}
  table{width:100%;border-collapse:collapse;margin-bottom:24px;}
  th{text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid #e5e7eb;padding:8px 12px;}
  td{padding:12px;border-bottom:1px solid #f3f4f6;font-size:14px;}
  tr:last-child td{border-bottom:none;}
  .totals{background:#f9fafb;border-radius:12px;padding:20px 24px;margin-bottom:32px;}
  .total-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;}
  .total-row.grand{border-top:2px solid #e5e7eb;margin-top:8px;padding-top:14px;font-weight:700;font-size:18px;}
  .footer-note{background:#f0f9ff;border-radius:12px;padding:16px 20px;color:#0369a1;font-size:12px;line-height:1.6;}
  .actions{padding:16px 48px 32px;display:flex;gap:12px;}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;}
  .btn-print{background:#4f46e5;color:#fff;border:none;}
  .btn-back{background:#f3f4f6;color:#374151;border:none;}
  @media print{
    body{background:#fff;}
    .page{box-shadow:none;margin:0;border-radius:0;}
    .actions{display:none;}
  }
</style>
</head>
<body>
<div class="page">
  <div class="header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <h1><?= APP_NAME ?></h1>
        <p>Tax Invoice</p>
      </div>
      <span class="status-badge"><?= ucfirst($invoice['status']) ?></span>
    </div>
  </div>

  <div class="body">
    <div class="two-col">
      <div>
        <p class="label">Invoice Number</p>
        <p class="value" style="font-family:monospace;font-weight:700;color:#4f46e5"><?= h($invoice['invoice_number']) ?></p>
      </div>
      <div>
        <p class="label">Issue Date</p>
        <p class="value"><?= date('F j, Y', strtotime($invoice['created_at'])) ?></p>
      </div>
      <div>
        <p class="label">Billing Period</p>
        <p class="value">
          <?php if ($invoice['period_start'] && $invoice['period_end']): ?>
            <?= date('M j', strtotime($invoice['period_start'])) ?> – <?= date('M j, Y', strtotime($invoice['period_end'])) ?>
          <?php else: ?>
            <?= date('F Y', strtotime($invoice['created_at'])) ?>
          <?php endif; ?>
        </p>
      </div>
      <div>
        <p class="label">Due Date</p>
        <p class="value"><?= $invoice['due_date'] ? date('F j, Y', strtotime($invoice['due_date'])) : 'Upon receipt' ?></p>
      </div>
    </div>

    <div class="two-col" style="margin-bottom:32px">
      <div>
        <p class="label">Billed To</p>
        <p class="value">
          <strong><?= h($invoice['billing_name'] ?: ($company['name'] ?? 'Customer')) ?></strong><br>
          <?php if ($invoice['billing_address']): ?>
            <?= nl2br(h($invoice['billing_address'])) ?><br>
          <?php endif; ?>
          <?php if ($invoice['vat_number']): ?>
            VAT: <?= h($invoice['vat_number']) ?>
          <?php endif; ?>
        </p>
      </div>
      <div>
        <p class="label">Billed By</p>
        <p class="value">
          <strong><?= APP_NAME ?></strong><br>
          Plan: <span style="text-transform:capitalize;font-weight:600"><?= h($invoice['plan_slug'] ?? '—') ?></span>
        </p>
      </div>
    </div>

    <!-- Line items -->
    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th style="text-align:right">Amount (<?= h($currency) ?>)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($lineItems)): ?>
          <?php foreach ($lineItems as $item): ?>
          <tr>
            <td><?= h($item['label'] ?? '') ?></td>
            <td style="text-align:right;font-weight:500"><?= number_format((float)($item['amount'] ?? 0), 2) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td><?= ucfirst(h($invoice['plan_slug'] ?? 'Subscription')) ?> Plan</td>
            <td style="text-align:right;font-weight:500"><?= number_format($subtotal, 2) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
      <div class="total-row">
        <span style="color:#6b7280">Subtotal</span>
        <span><?= $currency ?> <?= number_format($subtotal, 2) ?></span>
      </div>
      <?php if ($vatRate > 0): ?>
      <div class="total-row">
        <span style="color:#6b7280">VAT (<?= number_format($vatRate, 0) ?>%)</span>
        <span><?= $currency ?> <?= number_format($vatAmount, 2) ?></span>
      </div>
      <?php else: ?>
      <div class="total-row">
        <span style="color:#6b7280">VAT</span>
        <span style="color:#9ca3af">—</span>
      </div>
      <?php endif; ?>
      <div class="total-row grand">
        <span>Total Due</span>
        <span style="color:#4f46e5"><?= $currency ?> <?= number_format($total, 2) ?></span>
      </div>
    </div>

    <?php if ($invoice['status'] === 'paid' && $invoice['paid_at']): ?>
    <div class="footer-note">
      ✅ <strong>Payment received</strong> on <?= date('F j, Y', strtotime($invoice['paid_at'])) ?>. Thank you for your business!
    </div>
    <?php else: ?>
    <div class="footer-note">
      💳 Please settle this invoice by the due date. Contact <a href="mailto:billing@feedbackflow.app">billing@feedbackflow.app</a> for questions.
    </div>
    <?php endif; ?>
  </div>

  <div class="actions">
    <button class="btn btn-print" onclick="window.print()">🖨 Print / Save as PDF</button>
    <a href="<?= APP_URL ?>/admin/billing.php" class="btn btn-back">← Back to Billing</a>
  </div>
</div>
</body>
</html>
