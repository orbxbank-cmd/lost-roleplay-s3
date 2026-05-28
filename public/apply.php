<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';
\Core\Session::start();

$db = \Core\Database::getInstance();
$config = require __DIR__ . '/../config/app.php';
$error = '';
$success = false;
$statusResult = null;

// Check status by ingame name
if (isset($_GET['check'])) {
    $name = trim($_GET['check']);
    if ($name) {
        $app = $db->fetch("SELECT status, created_at FROM shop_staff_applications WHERE ingame_name = ? ORDER BY created_at DESC LIMIT 1", [$name]);
        if ($app) {
            $statusResult = $app;
        } else {
            $error = 'No application found for this name';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ingameName = trim($_POST['ingame_name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $country = trim($_POST['country'] ?? '');
    $playHours = (int)($_POST['play_hours'] ?? 0);
    $experience = trim($_POST['experience'] ?? '');
    $whyStaff = trim($_POST['why_staff'] ?? '');
    $strengths = trim($_POST['strengths'] ?? '');
    $weaknesses = trim($_POST['weaknesses'] ?? '');
    $discord = trim($_POST['discord'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');

    if (empty($ingameName) || empty($whyStaff) || $age < 10) {
        $error = 'Please fill all required fields';
    } else {
        try {
            $db->insert('staff_applications', [
                'ingame_name' => $ingameName,
                'age' => $age,
                'country' => $country ?: null,
                'play_hours' => $playHours ?: null,
                'experience' => $experience ?: null,
                'why_staff' => $whyStaff,
                'strengths' => $strengths ?: null,
                'weaknesses' => $weaknesses ?: null,
                'discord' => $discord ?: null,
                'whatsapp' => $whatsapp ?: null,
                'status' => 'pending',
            ]);
            \Core\Logger::info('New staff application', ['name' => $ingameName]);
            $success = true;
        } catch (\Exception $e) {
            $error = 'An error occurred. Please try again.';
            \Core\Logger::error('Application error', ['message' => $e->getMessage()]);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <h2 class="section-title"><i class="fas fa-clipboard-list title-accent"></i> Staff Application</h2>
        <p class="section-subtitle">Want to join the Lost Roleplay team? Fill out the form below</p>

        <!-- Status Check -->
        <div style="max-width: 500px; margin: 0 auto 2rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; text-align: center;">
            <h3 style="font-family: var(--header-font); font-size: 1rem; margin-bottom: 0.8rem;"><i class="fas fa-search"></i> Check Your Application Status</h3>
            <form method="GET" style="display: flex; gap: 0.5rem;">
                <input type="text" name="check" class="form-control" placeholder="Enter your in-game name" required style="flex:1;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            </form>
            <?php if ($statusResult): ?>
                <div style="margin-top: 1rem; padding: 0.8rem; border-radius: var(--radius); background: var(--bg-dark);">
                    <strong>Status:</strong>
                    <?php if ($statusResult['status'] === 'accepted'): ?>
                        <span class="status status-confirmed"><i class="fas fa-check-circle"></i> Accepted</span>
                        <p style="color: var(--success); margin-top: 0.3rem; font-size: 0.85rem;">Congratulations! Contact us on WhatsApp to proceed.</p>
                    <?php elseif ($statusResult['status'] === 'rejected'): ?>
                        <span class="status status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                        <p style="color: var(--danger); margin-top: 0.3rem; font-size: 0.85rem;">Unfortunately, your application was not accepted. You can re-apply later.</p>
                    <?php else: ?>
                        <span class="status status-pending"><i class="fas fa-clock"></i> Pending</span>
                        <p style="color: var(--text-muted); margin-top: 0.3rem; font-size: 0.85rem;">Your application is being reviewed. Check back later.</p>
                    <?php endif; ?>
                    <small style="color: var(--text-muted);">Submitted: <?= date('Y-m-d', strtotime($statusResult['created_at'])) ?></small>
                </div>
            <?php elseif ($error && !$success): ?>
                <div class="alert alert-danger" style="margin-top: 0.8rem;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>

        <hr class="gta-divider">

        <?php if ($success): ?>
            <div style="max-width: 600px; margin: 0 auto; text-align: center; padding: 3rem 0;">
                <div style="font-size: 3.5rem; margin-bottom: 1rem; color: var(--success);"><i class="fas fa-check-circle"></i></div>
                <h2 style="margin-bottom: 0.5rem; font-family: var(--header-font);">Application Submitted!</h2>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">We will review your application and contact you soon. Check your status above using your in-game name.</p>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        <?php else: ?>
            <?php if ($error && !$statusResult): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div style="max-width: 700px; margin: 0 auto;">
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label><i class="fas fa-gamepad"></i> In-game Name *</label>
                            <input type="text" name="ingame_name" class="form-control" required placeholder="Your character name">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Age *</label>
                            <input type="number" name="age" class="form-control" required min="10" placeholder="Your age">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Country</label>
                            <input type="text" name="country" class="form-control" placeholder="e.g. Morocco">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Hours Played / Day</label>
                            <input type="number" name="play_hours" class="form-control" placeholder="Hours per day">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-history"></i> Previous Experience</label>
                        <textarea name="experience" class="form-control" placeholder="Have you been staff before? On which servers?"></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-question-circle"></i> Why do you want to be a staff? *</label>
                        <textarea name="why_staff" class="form-control" required placeholder="Explain why you want to join the team..." rows="4"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label><i class="fas fa-plus-circle" style="color: var(--success);"></i> Your Strengths</label>
                            <textarea name="strengths" class="form-control" placeholder="What are you good at?" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-minus-circle" style="color: var(--danger);"></i> Your Weaknesses</label>
                            <textarea name="weaknesses" class="form-control" placeholder="What do you need to improve?" rows="3"></textarea>
                        </div>
                    </div>

                    <hr class="gta-divider">

                    <h3 style="margin-bottom: 1rem; font-family: var(--header-font);"><i class="fas fa-address-card"></i> Contact Info</h3>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label><i class="fab fa-discord"></i> Discord (User#Tag)</label>
                            <input type="text" name="discord" class="form-control" placeholder="e.g. User#1234">
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                            <input type="text" name="whatsapp" class="form-control" placeholder="e.g. +212XXXXXXXXX">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold btn-lg" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
