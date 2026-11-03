<?php require_once 'db.php'; require_role(['admin', 'staff']); include 'header.php';
$msg = '';
$hasOpt = (bool)$pdo->query("SHOW COLUMNS FROM patients LIKE 'sms_optin'")->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete']) && role() !== 'admin') {
        $msg = 'Only an admin can delete records.';
    } elseif (isset($_POST['delete'])) {
        $o = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $o->execute([$_POST['delete']]); $o = $o->fetch();
        $pdo->prepare('DELETE FROM patients WHERE patient_id=?')->execute([$_POST['delete']]);
        if ($o) log_event($pdo, 'Deleted', 'patients', $_POST['delete'], snap($o));
        $msg = 'Patient deleted.';
    } else {
        $new = ['full_name' => $_POST['full_name'], 'birth_date' => $_POST['birth_date'], 'gender' => $_POST['gender'], 'phone' => $_POST['phone'], 'address' => $_POST['address']];
        if ($hasOpt) $new['sms_optin'] = !empty($_POST['sms_optin']) ? 1 : 0;
        $cols = array_keys($new); $vals = array_values($new);
        if (!empty($_POST['patient_id'])) {
            $o = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $o->execute([$_POST['patient_id']]); $o = $o->fetch();
            $pdo->prepare('UPDATE patients SET ' . implode(',', array_map(fn($c) => "$c=?", $cols)) . ' WHERE patient_id=?')->execute([...$vals, $_POST['patient_id']]);
            log_event($pdo, 'Updated', 'patients', $_POST['patient_id'], changes($o, $new));
            $msg = 'Patient updated.';
        } else {
            $pdo->prepare('INSERT INTO patients (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
            log_event($pdo, 'Created', 'patients', $pdo->lastInsertId(), snap($new, 0));
            $msg = 'Patient added.';
        }
    }
}
$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $s->execute([$_GET['edit']]); $edit = $s->fetch(); }
$q = trim($_GET['q'] ?? '');
$s = $pdo->prepare('SELECT * FROM patients WHERE full_name LIKE ? OR phone LIKE ? ORDER BY full_name');
$s->execute(["%$q%", "%$q%"]); $rows = $s->fetchAll();
?>
<div class="pagehead"><h2>Patients</h2><a class="btn" href="register_patient.php">Register walk-in patient</a></div>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<div class="card"><h3><?= $edit ? 'Edit' : 'Add' ?> Patient</h3>
<form method="post" class="grid">
  <input type="hidden" name="patient_id" value="<?= h($edit['patient_id'] ?? '') ?>">
  <input name="full_name" placeholder="Full name" value="<?= h($edit['full_name'] ?? '') ?>" required>
  <input type="date" name="birth_date" value="<?= h($edit['birth_date'] ?? '') ?>" required>
  <select name="gender"><?php foreach (['Male', 'Female', 'Other'] as $g): ?>
    <option <?= ($edit['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select>
  <input name="phone" placeholder="Phone" value="<?= h($edit['phone'] ?? '') ?>">
  <input name="address" placeholder="Address" value="<?= h($edit['address'] ?? '') ?>">
  <?php if ($hasOpt): ?><label class="agree"><input type="checkbox" name="sms_optin" value="1" <?= ($edit['sms_optin'] ?? 1) ? 'checked' : '' ?>> <span>Patient agrees to appointment texts</span></label><?php endif; ?>
  <div><button>Save</button> <?php if ($edit): ?><a class="btn gray" href="patients.php">Cancel</a><?php endif; ?></div>
</form></div>
<div class="card">
<form method="get" style="margin-bottom:10px"><input name="q" placeholder="Search name or phone…" value="<?= h($q) ?>"></form>
<table><tr><th>Name</th><th>Birth date</th><th>Gender</th><th>Phone</th><th>Address</th><?php if ($hasOpt): ?><th>Texts</th><?php endif; ?><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><span class="av"><?= h(mb_substr($r['full_name'], 0, 1)) ?></span><a href="patient.php?id=<?= $r['patient_id'] ?>"><?= h($r['full_name']) ?></a></td><td><?= h($r['birth_date']) ?></td><td><?= h($r['gender']) ?></td><td><?= h($r['phone']) ?></td><td><?= h($r['address']) ?></td>
<?php if ($hasOpt): ?><td><span class="pill <?= $r['sms_optin'] ? 's-completed' : 's-other' ?>"><?= $r['sms_optin'] ? 'On' : 'Off' ?></span></td><?php endif; ?>
<td><a class="btn" href="?edit=<?= $r['patient_id'] ?>">Edit</a> <?php if (role() === 'admin'): ?><a class="btn gray" href="history.php?table=patients&id=<?= $r['patient_id'] ?>">History</a><?php endif; ?>
<?php if (role() === 'admin'): ?><form method="post" style="display:inline" onsubmit="return confirm('Delete this patient and their appointments?')">
<button class="red" name="delete" value="<?= $r['patient_id'] ?>">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php include 'footer.php'; ?>
