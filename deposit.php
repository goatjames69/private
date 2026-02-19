<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (empty(trim($user['email'] ?? ''))) {
    header('Location: /profile.php?add_email=1');
    exit;
}
$success = '';
$error = isset($_GET['error']) ? trim($_GET['error']) : '';
if ($error === 'amount') $error = 'Minimum amount is $1.';
if ($error === 'login') $error = 'Please log in to continue.';

// Load payment methods configuration
$paymentMethodsConfig = readJSON(PAYMENT_METHODS_FILE);
if (empty($paymentMethodsConfig)) {
    $paymentMethodsConfig = [
        'paypal' => ['enabled' => true, 'name' => 'PayPal', 'instructions' => 'Send payment to the PayPal account provided by admin.'],
        'chime' => ['enabled' => true, 'name' => 'Chime', 'instructions' => 'Send payment to the Chime account provided by admin.']
    ];
}

$enabledMethods = [];
foreach ($paymentMethodsConfig as $key => $config) {
    if ($config['enabled'] ?? false) {
        $enabledMethods[$key] = $config;
    }
}

// Card / PayGate deposit logs for this user (simple list on deposit page)
$paygateTxLog = [];
if (defined('PAYGATETX_FILE') && file_exists(PAYGATETX_FILE)) {
    $paygateTxLog = json_decode(file_get_contents(PAYGATETX_FILE), true);
}
if (!is_array($paygateTxLog)) $paygateTxLog = [];
$userEmail = trim($user['email'] ?? '');
$userPaygateTx = array_values(array_filter($paygateTxLog, function($tx) use ($userEmail) {
    return strcasecmp(trim($tx['email'] ?? ''), $userEmail) === 0;
}));
$userPaygateTx = array_reverse($userPaygateTx);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? '';
    $payment_info = trim($_POST['payment_info'] ?? '');

    if ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } elseif (!isset($enabledMethods[$method])) {
        $error = 'Invalid payment method';
    } elseif ($method === 'paygate') {
        header('Location: /api/paygate_create.php?amount=' . urlencode($amount) . '&redirect=1');
        exit;
    } elseif (empty($payment_info)) {
        $error = 'Payment information is required';
    } else {
        $payments = readJSON(PAYMENTS_FILE);
        $payment = [
            'id' => generateId(),
            'user_id' => $user['id'],
            'amount' => $amount,
            'method' => $method,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s'),
            'payment_info' => $payment_info
        ];
        $payments[] = $payment;
        writeJSON(PAYMENTS_FILE, $payments);
        $success = 'Deposit request submitted successfully! Admin will review and approve it.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Deposit - JAMES GAMEROOM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user-dashboard.css">
    <link rel="stylesheet" href="assets/css/realtime.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="user-dashboard">
    <div class="ud-container">
        <header class="ud-header">
            <h1><i class="fas fa-wallet"></i> Deposit Funds</h1>
            <p class="ud-greeting">Add funds to your account</p>
        </header>

        <div class="ud-balance-card">
            <div class="ud-balance-label">Current Balance</div>
            <div class="ud-balance-amount">$<?= number_format($user['balance'], 2) ?></div>
            <div class="ud-balance-actions">
                <a href="/dashboard.php" class="btn btn-block">Back to Dashboard</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-credit-card"></i> Payment Methods</h3>
            <form method="POST" action="" id="depositForm">
                <div class="form-group" id="amountGroup">
                    <label>Deposit Amount ($) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
                </div>
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="method" class="form-control" id="paymentMethod" required>
                        <option value="">Select Method</option>
                        <?php foreach ($enabledMethods as $key => $config): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($config['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="qrCodeGroup" style="display: none;">
                    <label>QR Code</label>
                    <div id="qrCodeDisplay" style="text-align: center; margin-bottom: 12px;">
                        <img id="qrCodeImage" src="" alt="QR Code" style="max-width: 250px; max-height: 250px; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; background: var(--bg-card);">
                    </div>
                    <small style="color: var(--text-secondary); display: block; text-align: center;">Scan this QR code to send payment</small>
                </div>
                <div class="form-group" id="paymentInfoGroup" style="display: none;">
                    <label id="paymentInfoLabel">Payment Information *</label>
                    <textarea name="payment_info" class="form-control" id="paymentInfo" placeholder="Enter transaction ID or payment details..."></textarea>
                    <small style="color: var(--text-secondary); margin-top: 5px; display: block;"><span id="paymentInfoHint"></span></small>
                </div>
                <div class="form-group" id="paygateNote" style="display: none;">
                    <p style="color: var(--text-secondary); margin: 0;"><i class="fas fa-external-link-alt"></i> You will be redirected to PayGate.to to pay securely with card, Apple Pay, Google Pay, or PayPal.</p>
                </div>
                <div class="form-group" id="paygateProceedGroup" style="display: none;">
                    <p style="color: var(--text-secondary); margin-bottom: 12px;">Enter amount and email on the next page. No deposit amount needed here.</p>
                    <a href="/cards_deposit.html" class="btn btn-block" style="text-align: center; text-decoration: none;"><i class="fas fa-credit-card"></i> Proceed to Card / Apple Pay</a>
                </div>
                <button type="submit" id="depositSubmitBtn" class="btn btn-block"><span class="btn-text">Submit Deposit Request</span></button>
            </form>
        </section>

        <section class="ud-card">
            <h3 class="ud-card-title"><i class="fas fa-info-circle"></i> Payment Instructions</h3>
            <div style="color: var(--text-secondary); font-size: 0.9rem;">
                <?php foreach ($enabledMethods as $key => $config): ?>
                    <p style="margin-bottom: 12px;">
                        <strong><?= htmlspecialchars($config['name']) ?>:</strong>
                        <?= htmlspecialchars($config['instructions'] ?? 'Include your transaction ID or payment screenshot. Admin will verify and approve.') ?>
                    </p>
                <?php endforeach; ?>
                <p style="margin-top: 15px; color: var(--accent-primary); font-weight: 500;">
                    <i class="fas fa-exclamation-triangle"></i> All deposits require admin approval. Funds will be added to your balance once approved.
                </p>
            </div>
        </section>

        <section class="ud-card paygate-logs-card">
            <h3 class="ud-card-title"><i class="fas fa-list"></i> Card / PayGate Logs</h3>
            <?php if (empty($userPaygateTx)): ?>
                <p class="paygate-logs-empty">No card deposits yet.</p>
            <?php else: ?>
                <div class="paygate-logs-wrap">
                    <table class="ud-table paygate-logs-table">
                        <thead>
                            <tr>
                                <th class="paygate-th-date">Date</th>
                                <th class="paygate-th-status">Status</th>
                                <th class="paygate-th-amount">Amount</th>
                                <th class="paygate-th-link">Action</th>
                                <th class="paygate-th-tracking">Tracking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userPaygateTx as $tx):
                                $tracking = $tx['tracking_id'] ?? '—';
                            ?>
                            <tr>
                                <td class="paygate-td-date"><?= htmlspecialchars($tx['server_time'] ?? '—') ?></td>
                                <?php $st = strtolower($tx['status'] ?? 'pending'); $badgeClass = ($st === 'approved') ? 'badge-approved' : (($st === 'rejected') ? 'badge-rejected' : 'badge-pending'); ?>
                                <td class="paygate-td-status"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($tx['status'] ?? 'Pending')) ?></span></td>
                                <td class="paygate-td-amount">$<?= htmlspecialchars(number_format((float)($tx['amount'] ?? 0), 2)) ?></td>
                                <td class="paygate-td-link"><?php if (!empty($tx['link'])): ?><a href="<?= htmlspecialchars($tx['link']) ?>" target="_blank" rel="noopener" class="paygate-link-btn"><i class="fas fa-external-link-alt"></i> Open</a><?php else: ?>—<?php endif; ?></td>
                                <td class="paygate-td-tracking" title="<?= htmlspecialchars($tracking) ?>"><?= htmlspecialchars($tracking) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <nav class="ud-nav">
        <a href="/dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="/deposit.php" class="active"><i class="fas fa-wallet"></i> Deposit</a>
        <a href="/games.php"><i class="fas fa-gamepad"></i> Games</a>
        <a href="/leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="/support.php"><i class="fas fa-headset"></i> Support</a>
    </nav>

    <script>
    (function() {
        var paymentMethods = <?= json_encode($enabledMethods) ?>;
        var paymentMethod = document.getElementById('paymentMethod');
        var infoGroup = document.getElementById('paymentInfoGroup');
        var qrGroup = document.getElementById('qrCodeGroup');
        var qrImage = document.getElementById('qrCodeImage');
        var infoLabel = document.getElementById('paymentInfoLabel');
        var infoHint = document.getElementById('paymentInfoHint');
        var infoField = document.getElementById('paymentInfo');
        var submitBtn = document.getElementById('depositSubmitBtn');

        var amountGroup = document.getElementById('amountGroup');
        var paygateProceedGroup = document.getElementById('paygateProceedGroup');
        function updateMethodUI(method) {
            var isPaygate = method === 'paygate';
            var paygateNote = document.getElementById('paygateNote');
            if (paygateNote) paygateNote.style.display = 'none';
            if (paygateProceedGroup) paygateProceedGroup.style.display = isPaygate ? 'block' : 'none';
            if (amountGroup) amountGroup.style.display = isPaygate ? 'none' : 'block';
            if (submitBtn) submitBtn.style.display = isPaygate ? 'none' : 'block';
            if (isPaygate) {
                infoGroup.style.display = 'none';
                qrGroup.style.display = 'none';
                infoField.removeAttribute('required');
                infoField.value = '';
            } else if (method && paymentMethods[method]) {
                var config = paymentMethods[method];
                infoGroup.style.display = 'block';
                infoLabel.textContent = config.name + ' Payment Information *';
                infoHint.textContent = config.instructions || 'Enter transaction ID or payment details';
                infoField.setAttribute('required', 'required');
                infoField.placeholder = 'Enter transaction ID or payment details...';
                if (config.qr_code) {
                    qrImage.src = '/' + config.qr_code;
                    qrGroup.style.display = 'block';
                } else {
                    qrGroup.style.display = 'none';
                }
            } else {
                infoGroup.style.display = 'none';
                qrGroup.style.display = 'none';
                infoField.removeAttribute('required');
            }
        }

        if (paymentMethod) {
            paymentMethod.addEventListener('change', function() { updateMethodUI(this.value); });
            updateMethodUI(paymentMethod.value);
        }

        var form = document.getElementById('depositForm');
        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                var method = paymentMethod ? paymentMethod.value : '';
                if (method === 'paygate') {
                    e.preventDefault();
                    window.location.href = '/cards_deposit.html';
                    return;
                }
            });
        }
    })();
    </script>
    <script src="assets/js/toasts.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
