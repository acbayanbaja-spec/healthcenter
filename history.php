<?php require_once 'db.php'; require_role(['admin']); include 'header.php';
$f = array_intersect_key($_GET, array_flip(['table','action','id','from','to','q']));
$missing = false;
try { $rows = history_rows($pdo, $f); } catch (PDOException $e) { $rows = []; $missing = true; }
$qs = http_build_query(array_filter($f, 'strlen'));
$pill = ['Created' => 's-completed', 'Updated' => 's-scheduled', 'Deleted' => 's-cancelled'];
?>
<div class="pagehead"><h2>History</h2>
  <a class="btn" href="export_word.php?report=history<?= $qs ? '&' . h($qs) : '' ?>">Download Word report</a></div>
<p class="note">Every create, update, delete, login, and export is recorded here with who did it and when. History starts from the day this feature was installed.</p>
<?php if ($missing): ?><div class="msg err">The history table is missing. Run <b>migrate_history.sql</b> in phpMyAdmin.</div><?php endif; ?>
<?php if (!empty($f['table']) && !empty($f['id'])): ?>
<div class="msg ok">Showing the full history of <b><?= h($f['table']) ?> #<?= (int)$f['id'] ?></b>. <a href="history.php">Show all history</a></div>
<?php endif; ?>
<div class="card">
<form class="filters" method="get">
  <input type="hidden" name="id" value="<?= h($f['id'] ?? '') ?>">
  <select name="table"><option value="">All records</option>
    <?php foreach (['patients','doctors','appointments','users','reports'] as $t): ?><option <?= ($f['table'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
  <select name="action"><option value="">All actions</option>
    <?php foreach (['Created','Updated','Deleted','Viewed','Searched','Login','Logout','Registered','Exported'] as $t): ?><option <?= ($f['action'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= h($f['from'] ?? '') ?>" title="From">
  <input type="date" name="to" value="<?= h($f['to'] ?? '') ?>" title="To">
  <input name="q" placeholder="Search details or user" value="<?= h($f['q'] ?? '') ?>">
  <button>Filter</button>
</form></div>
<div class="card">
<table><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['logged_at']) ?></td><td><?= h($r['user_name']) ?></td>
<td><span class="pill <?= $pill[$r['action']] ?? 's-other' ?>"><?= h($r['action']) ?></span></td>
<td><?php if ($r['record_id']): ?><a href="history.php?table=<?= h($r['table_name']) ?>&id=<?= (int)$r['record_id'] ?>"><?= h($r['table_name']) ?> #<?= (int)$r['record_id'] ?></a><?php else: ?><?= h($r['table_name']) ?><?php endif; ?></td>
<td class="det"><?= h($r['details']) ?></td></tr>
<?php endforeach; if (!$rows && !$missing): ?><tr><td colspan="5">No history matches these filters.</td></tr><?php endif; ?>
</table>
<?php if (count($rows) >= 500): ?><p class="note">Showing the latest 500 entries. Narrow the filters to see older ones.</p><?php endif; ?>
</div>
<?php include 'footer.php'; ?>
