<?php require_once 'db.php'; require_role(['admin', 'staff']); include 'header.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete']) && role() !== 'admin') {
        $msg = 'Only an admin can delete records.';
    } elseif (isset($_POST['delete'])) {
        $o = $pdo->prepare('SELECT * FROM doctors WHERE doctor_id=?'); $o->execute([$_POST['delete']]); $o = $o->fetch();
        $pdo->prepare('DELETE FROM doctors WHERE doctor_id=?')->execute([$_POST['delete']]);
        if ($o) log_event($pdo, 'Deleted', 'doctors', $_POST['delete'], snap($o));
        $msg = 'Doctor deleted.';
    } else {
        $new = ['full_name' => $_POST['full_name'], 'specialty' => $_POST['specialty'], 'phone' => $_POST['phone'], 'schedule' => $_POST['schedule']];
        $f = array_values($new);
        if (!empty($_POST['doctor_id'])) {
            $o = $pdo->prepare('SELECT * FROM doctors WHERE doctor_id=?'); $o->execute([$_POST['doctor_id']]); $o = $o->fetch();
            $pdo->prepare('UPDATE doctors SET full_name=?,specialty=?,phone=?,schedule=? WHERE doctor_id=?')->execute([...$f, $_POST['doctor_id']]);
            log_event($pdo, 'Updated', 'doctors', $_POST['doctor_id'], changes($o, $new));
            $msg = 'Doctor updated.';
        } else {
            $pdo->prepare('INSERT INTO doctors (full_name,specialty,phone,schedule) VALUES (?,?,?,?)')->execute($f);
            log_event($pdo, 'Created', 'doctors', $pdo->lastInsertId(), snap($new, 0));
            $msg = 'Doctor added.';
        }
    }
}
$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM doctors WHERE doctor_id=?'); $s->execute([$_GET['edit']]); $edit = $s->fetch(); }
$rows = $pdo->query('SELECT * FROM doctors ORDER BY full_name')->fetchAll();
?>
<h2>Doctors</h2>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<div class="card"><h3><?= $edit ? 'Edit' : 'Add' ?> Doctor</h3>
<form method="post" class="grid">
  <input type="hidden" name="doctor_id" value="<?= h($edit['doctor_id'] ?? '') ?>">
  <input name="full_name" placeholder="Full name" value="<?= h($edit['full_name'] ?? '') ?>" required>
  <input name="specialty" placeholder="Specialty" value="<?= h($edit['specialty'] ?? '') ?>" required>
  <input name="phone" placeholder="Phone" value="<?= h($edit['phone'] ?? '') ?>">
  <input name="schedule" placeholder="Schedule (e.g. Mon-Fri 8AM-12PM)" value="<?= h($edit['schedule'] ?? '') ?>">
  <div><button>Save</button> <?php if ($edit): ?><a class="btn gray" href="doctors.php">Cancel</a><?php endif; ?></div>
</form></div>
<div class="card"><table><tr><th>Name</th><th>Specialty</th><th>Phone</th><th>Schedule</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><span class="av"><?= h(mb_substr($r['full_name'],0,1)) ?></span><?= h($r['full_name']) ?></td><td><?= h($r['specialty']) ?></td><td><?= h($r['phone']) ?></td><td><?= h($r['schedule']) ?></td>
<td><a class="btn" href="?edit=<?= $r['doctor_id'] ?>">Edit</a> <a class="btn gray" href="availability.php?doctor=<?= $r['doctor_id'] ?>">Hours</a><?php if (role() === 'admin'): ?> <a class="btn gray" href="history.php?table=doctors&id=<?= $r['doctor_id'] ?>">History</a><?php endif; ?>
<?php if (role() === 'admin'): ?><form method="post" style="display:inline" onsubmit="return confirm('Delete this doctor and their appointments?')">
<button class="red" name="delete" value="<?= $r['doctor_id'] ?>">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php include 'footer.php'; ?>
