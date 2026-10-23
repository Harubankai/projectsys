<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$f = ['farmer_id' => '', 'full_name' => '', 'address' => '', 'contact_number' => '', 'gender' => '', 'date_registered' => date('Y-m-d'), 'status' => 'active'];
if ($id) {
    $st = db()->prepare('SELECT * FROM farmers WHERE id = ?'); $st->execute([$id]);
    $f = $st->fetch();
    if (!$f) { flash('danger', 'Farmer not found.'); redirect('farmers/index.php'); }
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['farmer_id','full_name','address','contact_number','gender','date_registered'] as $k) $f[$k] = trim($_POST[$k] ?? '');
    if (!$id && $f['farmer_id'] === '') $f['farmer_id'] = next_farmer_id();

    // Only admin can set status; staff submissions go to 'pending'
    $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
    if ($id) {
        // editing: admin can approve/change status
        $f['status'] = $isAdmin && in_array($_POST['status'] ?? '', ['pending','active'], true) ? $_POST['status'] : $f['status'];
    } else {
        // new farmer: admin → active, staff → pending
        $f['status'] = $isAdmin ? 'active' : 'pending';
    }

    if (!preg_match('/^[A-Za-z0-9-]{3,30}$/', $f['farmer_id'])) $errors['farmer_id'] = 'Use 3-30 letters, numbers, or hyphens.';
    if ($f['full_name'] === '' || mb_strlen($f['full_name']) > 150) $errors['full_name'] = 'Enter the farmer\'s full name (up to 150 characters).';
    if ($f['address'] === '' || mb_strlen($f['address']) > 255) $errors['address'] = 'Enter an address (up to 255 characters).';
    if ($f['contact_number'] === '') {
        $errors['contact_number'] = 'Contact number is required.';
    } elseif (strlen($f['contact_number']) > 11) {
        $errors['contact_number'] = 'Contact number must not exceed 11 digits.';
    } elseif (!preg_match('/^09\d{9}$/', $f['contact_number'])) {
        $errors['contact_number'] = 'Enter a valid 11-digit mobile number starting with 09 (e.g., 09171234567).';
    }
    if (!in_array($f['gender'], ['Male','Female','Other'], true)) $errors['gender'] = 'Select a gender.';
    $d = DateTime::createFromFormat('Y-m-d', $f['date_registered']);
    if (!$d || $d->format('Y-m-d') !== $f['date_registered'] || $f['date_registered'] > date('Y-m-d')) $errors['date_registered'] = 'Enter a valid date that is not in the future.';

    if (!isset($errors['farmer_id'])) {
        $st = db()->prepare('SELECT id FROM farmers WHERE farmer_id = ? AND id <> ?'); $st->execute([$f['farmer_id'], $id]);
        if ($st->fetch()) $errors['farmer_id'] = 'This farmer ID is already in use.';
    }
    if (!$errors) {
        $vals = [$f['farmer_id'], $f['full_name'], $f['address'], $f['contact_number'], $f['gender'], $f['date_registered'], $f['status']];
        if ($id) {
            db()->prepare('UPDATE farmers SET farmer_id=?, full_name=?, address=?, contact_number=?, gender=?, date_registered=?, status=? WHERE id=?')->execute([...$vals, $id]);
            log_activity('Updated farmer', 'farmer', $id);
            flash('success', 'Farmer record updated.');
        } else {
            db()->prepare('INSERT INTO farmers (farmer_id, full_name, address, contact_number, gender, date_registered, status) VALUES (?,?,?,?,?,?,?)')->execute($vals);
            $id = (int)db()->lastInsertId();
            log_activity('Created farmer', 'farmer', $id, $f['farmer_id']);
            if ($f['status'] === 'pending') {
                flash('success', 'Farmer added and is pending admin approval.');
            } else {
                flash('success', 'Farmer added and activated.');
            }
        }
        redirect('farmers/view.php?id=' . $id);
    }
}
$editing = (bool)$id;
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$pageTitle = $editing ? 'Edit farmer' : 'Add farmer'; $active = 'farmers';
require __DIR__ . '/../includes/header.php';
function err($errors, $k) { return isset($errors[$k]) ? '<small class="field-err">' . e($errors[$k]) . '</small>' : ''; }
?>
<div class="page-head"><h1><?= e($pageTitle) ?></h1></div>
<section class="panel">
<form method="post" novalidate class="form-grid">
    <?= csrf_field() ?>
    <div><label for="farmer_id">Farmer ID</label>
        <input id="farmer_id" name="farmer_id" value="<?= e($f['farmer_id']) ?>" placeholder="Leave blank to generate" maxlength="30"><?= err($errors,'farmer_id') ?></div>
    <div><label for="date_registered">Date registered</label>
        <input type="date" id="date_registered" name="date_registered" value="<?= e($f['date_registered']) ?>" max="<?= date('Y-m-d') ?>" required><?= err($errors,'date_registered') ?></div>
    <div class="full"><label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($f['full_name']) ?>" maxlength="150" required><?= err($errors,'full_name') ?></div>
    <div class="full"><label for="address">Address</label>
        <input id="address" name="address" value="<?= e($f['address']) ?>" maxlength="255" required><?= err($errors,'address') ?></div>
    <div><label for="contact_number">Contact number</label>
        <input type="tel" id="contact_number" name="contact_number" value="<?= e($f['contact_number']) ?>" placeholder="09171234567" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" title="Enter an 11-digit mobile number (e.g. 09171234567)" required><?= err($errors,'contact_number') ?></div>
    <div><label for="gender">Gender</label>
        <select id="gender" name="gender" required><option value="">Select</option>
        <?php foreach (['Male','Female','Other'] as $g): ?><option <?= $f['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select><?= err($errors,'gender') ?></div>

    <?php if ($isAdmin && $editing): ?>
    <div class="full">
        <label for="farmer_status">Approval Status</label>
        <div style="display:flex;align-items:center;gap:16px;margin-top:4px;">
            <select id="farmer_status" name="status" style="width:auto;min-width:160px;">
                <option value="pending" <?= ($f['status'] ?? 'active') === 'pending' ? 'selected' : '' ?>>Pending (not yet approved)</option>
                <option value="active" <?= ($f['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (approved)</option>
            </select>
            <?php if (($f['status'] ?? 'active') === 'pending'): ?>
            <span class="badge badge-warn" style="font-size:.9rem;">Awaiting admin approval</span>
            <?php else: ?>
            <span class="badge badge-ok" style="font-size:.9rem;">Approved</span>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!$editing): ?>
    <div class="full">
        <div class="alert alert-info" style="background:var(--g50);border:1px solid var(--g100);border-radius:8px;padding:12px 16px;color:var(--g900);display:flex;gap:10px;align-items:center;">
            <i class="fa-solid fa-circle-info" style="color:var(--g600);"></i>
            <?php if ($isAdmin): ?>
            <span>This farmer will be <strong>immediately active</strong> since you are an admin.</span>
            <?php else: ?>
            <span>This farmer will be <strong>pending admin approval</strong> before being fully activated in the system.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="full form-actions">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Save changes' : 'Add farmer' ?></button>
        <a class="btn btn-light" href="<?= url('farmers/index.php') ?>">Cancel</a>
    </div>
</form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
