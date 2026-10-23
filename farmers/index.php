<?php
$pageTitle = 'Farmers'; $active = 'farmers';
require __DIR__ . '/../includes/header.php';
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status_filter'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 10;
$conditions = [];
$params = [];
if ($q !== '') { $conditions[] = '(full_name LIKE ? OR farmer_id LIKE ?)'; $params = ["%$q%", "%$q%"]; }
if (in_array($statusFilter, ['pending','active'], true)) { $conditions[] = 'status = ?'; $params[] = $statusFilter; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$st = db()->prepare("SELECT COUNT(*) FROM farmers $where"); $st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$st = db()->prepare("SELECT * FROM farmers $where ORDER BY full_name LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();
$pendingCount = (int)db()->query("SELECT COUNT(*) FROM farmers WHERE status='pending'")->fetchColumn();
?>
<div class="page-head row">
    <div><h1>Farmers</h1><p class="muted"><?= $total ?> record<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?></p></div>
    <a class="btn btn-primary" href="<?= url('farmers/form.php') ?>"><i class="fa-solid fa-plus"></i> Add farmer</a>
</div>

<?php if ($isAdmin && $pendingCount > 0): ?>
<div style="background:#fef9ec;border:1px solid #f6d860;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
    <i class="fa-solid fa-clock" style="color:#b7791f;font-size:1.2rem;"></i>
    <div><strong style="color:#7d5a0a;"><?= $pendingCount ?> farmer<?= $pendingCount > 1 ? 's' : '' ?> awaiting approval.</strong>
    <span style="color:#856404;"> Review and approve them.</span></div>
    <a class="btn btn-sm" href="?status_filter=pending" style="margin-left:auto;background:#f6d860;border:1px solid #d4a908;color:#7d5a0a;font-weight:700;"><i class="fa-solid fa-list-check"></i> View pending</a>
</div>
<?php endif; ?>

<section class="panel">
    <form method="get" class="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or farmer ID">
        <?php if ($isAdmin): ?>
        <select name="status_filter" style="width:auto;min-width:140px;" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
        </select>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($q !== '' || $statusFilter !== ''): ?><a class="btn btn-light" href="index.php">Clear</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?>
        <p class="empty"><?= $q !== '' ? 'No farmers match your search. Try a different name or ID.' : 'No farmers registered yet.' ?></p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Farmer ID</th><th>Full name</th><th>Address</th><th>Contact</th><th>Gender</th><th>Registered</th><th>Status</th><th class="right">Actions</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['farmer_id']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['address']) ?></td>
                <td><?= e($r['contact_number']) ?></td><td><?= e($r['gender']) ?></td>
                <td><?= e(date('M d, Y', strtotime($r['date_registered']))) ?></td>
                <td><?= ($r['status'] ?? 'active') === 'pending' ? '<span class="badge badge-warn">Pending</span>' : '<span class="badge badge-ok">Active</span>' ?></td>
                <td class="right actions">
                    <a class="icon-btn" title="View" href="view.php?id=<?= $r['id'] ?>"><i class="fa-solid fa-eye"></i></a>
                    <a class="icon-btn" title="Edit" href="form.php?id=<?= $r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
<?php if ($isAdmin && ($r['status'] ?? 'active') === 'pending'): ?>
                    <form method="post" action="approve.php" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="icon-btn" title="Approve farmer" style="color:#2a8a52;border-color:#2a8a52;"><i class="fa-solid fa-circle-check"></i></button></form>
<?php endif; ?>
<?php if (can('delete')): ?>
                    <button class="icon-btn danger" title="Delete" type="button" data-delete="<?= $r['id'] ?>" data-name="<?= e($r['full_name']) ?>"><i class="fa-solid fa-trash"></i></button>
<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php if ($pages > 1): ?><div class="pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="<?= $i === $page ? 'cur' : '' ?>" href="?q=<?= urlencode($q) ?>&status_filter=<?= urlencode($statusFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?></div><?php endif; ?>
    <?php endif; ?>
</section>

<div class="modal" id="deleteModal" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="dm-title">
        <h3 id="dm-title">Delete this farmer?</h3>
        <p>You are about to delete <strong id="dm-name"></strong>. This cannot be undone.</p>
        <form method="post" action="delete.php">
            <?= csrf_field() ?><input type="hidden" name="id" id="dm-id">
            <div class="modal-actions">
                <button type="button" class="btn btn-light" id="dm-cancel">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete farmer</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
