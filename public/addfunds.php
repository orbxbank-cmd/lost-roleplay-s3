<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAuth();

$db = \Core\Database::getInstance();
$config = require __DIR__ . '/../config/app.php';
$user = \Core\Auth::user();

$paymentMethods = $db->fetchAll("SELECT * FROM shop_payment_methods WHERE is_active = 1 ORDER BY sort_order");
$coinPackages = $config['coin_packages'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = (int)($_POST['package_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $proofFile = null;

    $package = null;
    foreach ($coinPackages as $p) {
        if (($p['id'] ?? 0) === $packageId) {
            $package = $p;
            break;
        }
    }

    $selectedCoins = $package ? $package['coins'] : 0;

    if (!$package) {
        $error = 'Invalid package selected.';
    } elseif (empty($paymentMethod)) {
        $error = 'Please select a payment method.';
    } elseif (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a payment proof image.';
    }

    if (!$error) {
        $uploadDir = __DIR__ . '/../uploads/proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) {
            $error = 'Allowed formats: jpg, png, gif, webp';
        } else {
            $proofFile = 'funds_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDir . $proofFile);

            $db->insert('coin_purchases', [
                'user_id' => $user['id'],
                'coins' => $package['coins'],
                'amount_mad' => $package['price'],
                'payment_method' => $paymentMethod,
                'proof_file' => $proofFile,
                'status' => 'pending',
            ]);

            \Core\Logger::info('Add funds request', ['user' => $user['username'], 'coins' => $package['coins'], 'amount' => $package['price']]);
            $success = 'Your request has been submitted. An admin will confirm your payment soon.';
        }
    }
}

$purchases = $db->fetchAll("SELECT * FROM shop_coin_purchases WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$user['id']]);

require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-coins"></i> Add Funds</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Purchase coins to use in the shop</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
            <h3 style="margin-bottom: 1rem;">Choose a Package</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <?php foreach ($coinPackages as $pkg): ?>
                    <div class="payment-method" onclick="selectPackage(this, <?= $pkg['id'] ?>, <?= $pkg['coins'] ?>, <?= $pkg['price'] ?>)" style="cursor:pointer;">
                        <div class="pm-icon">🪙</div>
                        <div class="pm-name"><?= $pkg['label'] ?></div>
                        <div style="font-weight: 700; color: var(--accent); font-size: 1.2rem;"><?= $pkg['price'] ?> dh</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" id="funds-form" style="margin-top: 2rem;">
                <input type="hidden" name="package_id" id="selected-package" value="">

                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="">Select payment method</option>
                        <?php foreach ($paymentMethods as $pm): ?>
                            <option value="<?= htmlspecialchars($pm['code']) ?>"><?= htmlspecialchars($pm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Payment Proof (Screenshot) *</label>
                    <input type="file" name="proof_file" class="form-control" accept="image/*" required>
                    <small style="color: var(--text-muted);">Upload a screenshot of your payment</small>
                </div>

                <div id="package-preview" style="display:none; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
                    <strong>Selected:</strong> <span id="preview-label"></span><br>
                    <strong>Amount:</strong> <span id="preview-price"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Submit Request</button>
            </form>
        </div>

        <div>
            <h3 style="margin-bottom: 1rem;">Payment Instructions</h3>
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem;">
                <p>Send the amount to one of these numbers, then upload the proof:</p>
                <ul style="margin-top: 1rem; line-height: 2;">
                    <?php foreach ($paymentMethods as $pm): ?>
                        <li><strong><?= htmlspecialchars($pm['name']) ?>:</strong> <?= htmlspecialchars($config['contact_phone']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!empty($purchases)): ?>
                <h3 style="margin-bottom: 1rem;">Your Requests</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Coins</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $p): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--accent);">+<?= $p['coins'] ?></td>
                                    <td><?= number_format($p['amount_mad'], 0) ?> dh</td>
                                    <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                    <td>
                                        <span class="status status-<?= $p['status'] ?>">
                                            <?= $p['status'] === 'pending' ? 'Pending' : ($p['status'] === 'confirmed' ? 'Confirmed' : 'Rejected') ?>
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let selectedPackage = null;
function selectPackage(el, id, coins, price) {
    document.querySelectorAll('.payment-method').forEach(pm => pm.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-package').value = id;
    document.getElementById('package-preview').style.display = 'block';
    document.getElementById('preview-label').textContent = coins.toLocaleString() + ' Coins';
    document.getElementById('preview-price').textContent = price + ' dh';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
