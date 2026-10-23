<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_perm('manage_users'); // admin only
csrf_check();
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $st = db()->prepare('UPDATE farmers SET status = ? WHERE id = ?');
    $st->execute(['active', $id]);
    if ($st->rowCount()) {
        log_activity('Approved farmer', 'farmer', $id);
        flash('success', 'Farmer approved and now active.');
    } else {
        flash('danger', 'Farmer not found or already active.');
    }
}
redirect('farmers/index.php');
