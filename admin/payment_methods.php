<?php
require_once '../config.php';
requireStaffOrAdmin();
if (!canAccess('payment_methods')) {
    header('Location: /admin/dashboard.php');
    exit;
}

$paymentMethods = readJSON(PAYMENT_METHODS_FILE);
if (empty($paymentMethods)) {
    $paymentMethods = [
        'paypal' => ['enabled' => true, 'name' => 'PayPal', 'instructions' => 'Send payment to the PayPal account provided by admin. Include your transaction ID or payment screenshot.'],
        'chime' => ['enabled' => true, 'name' => 'Chime', 'instructions' => 'Send payment to the Chime account provided by admin. Include your transaction ID or payment screenshot.'],
        'paygate' => [
            'enabled' => false,
            'name' => 'PayGate.to',
            'instructions' => 'Instant payment via card, Apple Pay, Google Pay, or PayPal. You will be redirected to PayGate.to to complete payment.',
            'payout_address' => '',
            'use_hosted_checkout' => false,
            'checkout_domain' => '',
            'wallet_api_url' => '',
            'api_endpoint' => '',
            'success_url' => '',
            'cancel_url' => '',
            'webhook_secret' => ''
        ]
    ];
}
if (!isset($paymentMethods['paygate']) || !is_array($paymentMethods['paygate'])) {
    $paymentMethods['paygate'] = [
        'enabled' => false,
        'name' => 'PayGate.to',
        'instructions' => 'Instant payment via card, Apple Pay, Google Pay, or PayPal. You will be redirected to PayGate.to to complete payment.',
        'payout_address' => '',
        'use_hosted_checkout' => false,
        'checkout_domain' => '',
        'wallet_api_url' => '',
        'api_endpoint' => '',
        'success_url' => '',
        'cancel_url' => '',
        'webhook_secret' => ''
    ];
}
$paymentMethods['paygate'] = array_merge([
    'enabled' => false,
    'name' => 'PayGate.to',
    'instructions' => 'Instant payment via card, Apple Pay, Google Pay, or PayPal. You will be redirected to PayGate.to to complete payment.',
    'payout_address' => '',
    'use_hosted_checkout' => false,
    'checkout_domain' => '',
    'wallet_api_url' => '',
    'api_endpoint' => '',
    'success_url' => '',
    'cancel_url' => '',
    'webhook_secret' => ''
], $paymentMethods['paygate'] ?? []);

$success = '';
$error = '';

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/../uploads/qr_codes';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Handle QR code deletion
if (isset($_GET['delete_qr']) && isset($_GET['method'])) {
    $method = $_GET['method'];
    if (isset($paymentMethods[$method]['qr_code'])) {
        $qrPath = __DIR__ . '/../' . $paymentMethods[$method]['qr_code'];
        if (file_exists($qrPath)) {
            unlink($qrPath);
        }
        unset($paymentMethods[$method]['qr_code']);
        writeJSON(PAYMENT_METHODS_FILE, $paymentMethods);
        $success = 'QR code deleted successfully!';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_methods'])) {
        $updatedMethods = [];
        
        foreach (['paypal', 'chime'] as $method) {
            $updatedMethods[$method] = [
                'enabled' => isset($_POST[$method . '_enabled']),
                'name' => trim($_POST[$method . '_name'] ?? ''),
                'instructions' => trim($_POST[$method . '_instructions'] ?? ''),
                'qr_code' => $paymentMethods[$method]['qr_code'] ?? null
            ];
            
            if (isset($_FILES[$method . '_qr']) && $_FILES[$method . '_qr']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$method . '_qr'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($file['type'], $allowedTypes)) {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $method . '_qr_' . time() . '.' . $extension;
                    $filepath = $uploadsDir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        if (isset($paymentMethods[$method]['qr_code'])) {
                            $oldPath = __DIR__ . '/../' . $paymentMethods[$method]['qr_code'];
                            if (file_exists($oldPath)) unlink($oldPath);
                        }
                        $updatedMethods[$method]['qr_code'] = 'uploads/qr_codes/' . $filename;
                    } else {
                        $error = 'Failed to upload QR code for ' . ucfirst($method);
                    }
                } else {
                    $error = 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.';
                }
            }
        }

        $pg = $paymentMethods['paygate'] ?? [];
        $updatedMethods['paygate'] = [
            'enabled' => isset($_POST['paygate_enabled']),
            'name' => trim($_POST['paygate_name'] ?? 'PayGate.to'),
            'instructions' => trim($_POST['paygate_instructions'] ?? ''),
            'payout_address' => trim($_POST['paygate_payout_address'] ?? ''),
            'use_hosted_checkout' => isset($_POST['paygate_use_hosted_checkout']),
            'checkout_domain' => trim($_POST['paygate_checkout_domain'] ?? ''),
            'wallet_api_url' => trim($_POST['paygate_wallet_api_url'] ?? ''),
            'api_endpoint' => trim($_POST['paygate_api_endpoint'] ?? ''),
            'success_url' => trim($_POST['paygate_success_url'] ?? ''),
            'cancel_url' => trim($_POST['paygate_cancel_url'] ?? ''),
            'webhook_secret' => trim($_POST['paygate_webhook_secret'] ?? '')
        ];
        
        writeJSON(PAYMENT_METHODS_FILE, $updatedMethods);
        $paymentMethods = $updatedMethods;
        if (empty($error)) {
            $success = 'Payment methods updated successfully!';
        }
    }
}

$pendingCounts = ['payments' => 0, 'withdrawals' => 0, 'game_requests' => 0, 'account_requests' => 0];
$adminPageTitle = 'Payment Methods';
$adminCurrentPage = 'payment_methods';
$adminPageSubtitle = 'Configure PayPal, Chime & PayGate.to for user deposits';
require __DIR__ . '/_header.php';
?>

<?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

<div class="card">
    <h3 class="admin-section-title"><i class="fas fa-credit-card"></i> Configure Payment Methods</h3>
    <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Manage which payment methods are available to users for deposits. You can enable/disable methods and customize their instructions.
            </p>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <?php foreach (['paypal', 'chime'] as $method): 
                    $config = $paymentMethods[$method] ?? ['enabled' => true, 'name' => ucfirst($method), 'instructions' => ''];
                ?>
                    <div class="card" style="margin-bottom: 24px; background: var(--bg-secondary);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h4 style="color: var(--text-primary); font-size: 18px; font-weight: 600;">
                                <?= htmlspecialchars(ucfirst($method)) ?>
                            </h4>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="<?= $method ?>_enabled" 
                                       <?= ($config['enabled'] ?? false) ? 'checked' : '' ?> 
                                       style="width: 18px; height: 18px; cursor: pointer;">
                                <span style="color: var(--text-secondary); font-size: 14px;">Enabled</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Display Name</label>
                            <input type="text" name="<?= $method ?>_name" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($config['name'] ?? ucfirst($method)) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>Instructions</label>
                            <textarea name="<?= $method ?>_instructions" 
                                      class="form-control" 
                                      rows="3" 
                                      required><?= htmlspecialchars($config['instructions'] ?? '') ?></textarea>
                            <small style="color: var(--text-muted); margin-top: 5px; display: block;">
                                This text will be shown to users when they select this payment method.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>QR Code (for deposit page)</label>
                            <?php if (isset($config['qr_code']) && !empty($config['qr_code'])): ?>
                                <div style="margin-bottom: 12px;">
                                    <img src="/<?= htmlspecialchars($config['qr_code']) ?>" 
                                         alt="QR Code" 
                                         style="max-width: 200px; max-height: 200px; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px; background: var(--bg-card);">
                                    <div style="margin-top: 8px;">
                                        <a href="?delete_qr=1&method=<?= $method ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this QR code?')">
                                            <i class="fas fa-trash"></i> Delete QR Code
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" 
                                   name="<?= $method ?>_qr" 
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   class="form-control">
                            <small style="color: var(--text-muted); margin-top: 5px; display: block;">
                                Upload a QR code image (JPEG, PNG, GIF, or WebP). This will be shown on the deposit page for users to scan.
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php $pg = $paymentMethods['paygate'] ?? []; ?>
                <div class="card" style="margin-bottom: 24px; background: var(--bg-secondary);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <h4 style="color: var(--text-primary); font-size: 18px; font-weight: 600;">
                            PayGate.to <span style="font-size: 12px; font-weight: 400; color: var(--text-muted);">(No signup, no API key – <a href="https://documenter.getpostman.com/view/14826208/2sA3Bj9aBi" target="_blank" rel="noopener">API docs</a>)</span>
                        </h4>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="paygate_enabled" <?= ($pg['enabled'] ?? false) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                            <span style="color: var(--text-secondary); font-size: 14px;">Enabled</span>
                        </label>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                        PayGate.to is <strong>open access</strong>: no account, no API keys. Your USDC Polygon wallet address is your identity. Create one e.g. with <a href="https://trustwallet.com" target="_blank" rel="noopener">Trust Wallet</a> (SWIFT option).
                    </p>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="paygate_name" class="form-control" value="<?= htmlspecialchars($pg['name'] ?? 'PayGate.to') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Instructions (shown to user)</label>
                        <textarea name="paygate_instructions" class="form-control" rows="2"><?= htmlspecialchars($pg['instructions'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Your USDC Polygon Wallet Address *</label>
                        <input type="text" name="paygate_payout_address" class="form-control" value="<?= htmlspecialchars($pg['payout_address'] ?? '') ?>" placeholder="0x... (where you receive instant payouts)">
                        <small style="color: var(--danger); display: block; margin-top: 4px;">Required when PayGate.to is enabled.</small>
                        <small style="color: var(--text-muted);">This is the only required setting. Do NOT share your private key — only this public address.</small>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="paygate_use_hosted_checkout" <?= ($pg['use_hosted_checkout'] ?? false) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                            <span>Use multi-provider hosted checkout (GET redirect)</span>
                        </label>
                        <small style="color: var(--text-muted); display: block; margin-top: 4px;">Redirects to <code>checkout.paygate.to/pay.php</code> with encrypted address, amount, email, currency. Requires GET <code>api.paygate.to/control/wallet.php</code> to get encrypted address from your payout wallet.</small>
                    </div>
                    <div class="form-group">
                        <label>Checkout domain (optional – white-label)</label>
                        <input type="text" name="paygate_checkout_domain" class="form-control" value="<?= htmlspecialchars($pg['checkout_domain'] ?? '') ?>" placeholder="checkout.example.com">
                        <small style="color: var(--text-muted);">Rebrand the hosted checkout page with your own subdomain. Leave blank for default checkout.paygate.to.</small>
                    </div>
                    <div class="form-group">
                        <label>Wallet API URL (optional – for hosted checkout only)</label>
                        <input type="url" name="paygate_wallet_api_url" class="form-control" value="<?= htmlspecialchars($pg['wallet_api_url'] ?? '') ?>" placeholder="https://api.paygate.to/control/wallet.php">
                        <small style="color: var(--text-muted);">If encrypted address fails, set the full URL to GET for the encrypted address (e.g. <code>https://paygate.to/control/wallet.php</code>). Leave blank to try api.paygate.to and paygate.to.</small>
                    </div>
                    <div class="form-group">
                        <label>Create payment API endpoint (optional – for POST flow only)</label>
                        <input type="url" name="paygate_api_endpoint" class="form-control" value="<?= htmlspecialchars($pg['api_endpoint'] ?? '') ?>" placeholder="https://paygate.to/api/crypto/payment/create">
                        <small style="color: var(--text-muted);">If you get <strong>404 Not Found</strong>, check the <a href="https://documenter.getpostman.com/view/14826208/2sA3Bj9aBi" target="_blank" rel="noopener">PayGate.to API docs</a> for the current create-payment URL and paste it here. Leave blank to use the default.</small>
                    </div>
                    <div class="form-group">
                        <label>Success URL (after payment)</label>
                        <input type="url" name="paygate_success_url" class="form-control" value="<?= htmlspecialchars($pg['success_url'] ?? '') ?>" placeholder="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yoursite.com') . '/deposit.php?paygate=success') ?>">
                    </div>
                    <div class="form-group">
                        <label>Cancel URL (if user cancels)</label>
                        <input type="url" name="paygate_cancel_url" class="form-control" value="<?= htmlspecialchars($pg['cancel_url'] ?? '') ?>" placeholder="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yoursite.com') . '/deposit.php?paygate=cancel') ?>">
                    </div>
                    <div class="form-group">
                        <label>Webhook Secret (optional – callback verification)</label>
                        <input type="text" name="paygate_webhook_secret" class="form-control" value="<?= htmlspecialchars($pg['webhook_secret'] ?? '') ?>" placeholder="If PayGate.to signs callbacks" autocomplete="off">
                        <small style="color: var(--text-muted);">Callback URL (PayGate calls this when payment is done): <code><?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yoursite.com') . '/api/paygate_callback.php') ?></code></small>
                        <?php
                        $isLocalhost = (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1'));
                        if ($isLocalhost): ?>
                        <p style="margin-top: 10px; padding: 10px; background: rgba(251, 191, 36, 0.15); border-radius: 8px; color: var(--warning); font-size: 13px;">
                            <i class="fas fa-info-circle"></i> <strong>Testing on localhost:</strong> PayGate.to cannot reach <code>localhost</code> to confirm payments. The payment link may still work, but your balance won’t update automatically until you use a <strong>public URL</strong> (e.g. <a href="https://ngrok.com" target="_blank" rel="noopener">ngrok</a>) or deploy to a live server.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
        <button type="submit" name="update_methods" class="btn btn-block">Save Changes</button>
    </form>
</div>

<?php require __DIR__ . '/_footer.php'; ?>

