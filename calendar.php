<?php require_once 'db.php'; require_role(['admin', 'staff', 'doctor']); include 'header.php';
$m = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$first = strtotime("$m-01");
$days = (int)date('t', $first); $offset = (int)date('w', $first);
$prev = date('Y-m', strtotime('-1 month', $first)); $next = date('Y-m', strtotime('+1 month', $first));
$doc = (int)($_GET['doctor'] ?? 0);
if (role() === 'doctor') $doc = (int)($_SESSION['doctor_id'] ?? 0) ?: -1;
$q = $doc ? "&doctor=$doc" : '';
$sql = 'SELECT a.*, p.full_name patient, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id) WHERE a.appt_date BETWEEN ? AND ?'
     . ($doc ? ' AND a.doctor_id=?' : '') . ' ORDER BY a.appt_date, a.appt_time';
$par = ["$m-01", "$m-" . str_pad($days, 2, '0', STR_PAD_LEFT)]; if ($doc) $par[] = $doc;
$st = $pdo->prepare($sql); $st->execute($par);
$by = [];
foreach ($st->fetchAll() as $r) $by[$r['appt_date']][] = $r;
$doctors = $pdo->query('SELECT doctor_id, full_name FROM doctors ORDER BY full_name')->fetchAll();
$sel = $_GET['d'] ?? (date('Y-m') === $m ? date('Y-m-d') : "$m-01");
if (substr($sel, 0, 7) !== $m || !strtotime($sel)) $sel = "$m-01";
$today = date('Y-m-d');
?>
<div class="pagehead"><h2><?= h(date('F Y', $first)) ?></h2>
  <form method="get" class="cal-nav">
    <a class="btn gray" href="?m=<?= $prev . $q ?>" aria-label="Previous month">‹</a>
    <a class="btn gray" href="?m=<?= date('Y-m') . $q ?>">Today</a>
    <a class="btn gray" href="?m=<?= $next . $q ?>" aria-label="Next month">›</a>
    <input type="hidden" name="m" value="<?= h($m) ?>">
    <?php if (role() !== 'doctor'): ?><select name="doctor" onchange="this.form.submit()" aria-label="Filter by doctor"><option value="0">All doctors</option>
      <?php foreach ($doctors as $d): ?><option value="<?= $d['doctor_id'] ?>" <?= $doc === (int)$d['doctor_id'] ? 'selected' : '' ?>><?= h($d['full_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
  </form></div>
<div class="card">
<div class="cal">
  <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w): ?><div class="cal-h"><?= $w ?></div><?php endforeach; ?>
  <?php for ($i = 0; $i < $offset; $i++): ?><div class="cal-day off"></div><?php endfor; ?>
  <?php for ($n = 1; $n <= $days; $n++):
      $date = sprintf('%s-%02d', $m, $n); $list = $by[$date] ?? []; ?>
  <a class="cal-day<?= $date === $today ? ' today' : '' ?><?= $date === $sel ? ' sel' : '' ?>" href="?m=<?= h($m) ?>&d=<?= $date . $q ?>">
    <b><?= $n ?></b><?php if ($list): ?><span class="cnt"><?= count($list) ?></span><?php endif; ?>
    <?php foreach (array_slice($list, 0, 3) as $r): ?><i class="c s-<?= strtolower($r['status']) ?>"><?= h(substr($r['appt_time'], 0, 5) . ' ' . explode(' ', $r['patient'])[0]) ?></i><?php endforeach; ?>
    <?php if (count($list) > 3): ?><span class="more">+<?= count($list) - 3 ?> more</span><?php endif; ?>
  </a>
  <?php endfor; ?>
</div></div>
<div class="card"><h3><?= h(date('l, j F Y', strtotime($sel))) ?></h3>
<?php $day = $by[$sel] ?? []; if (!$day): ?><p class="empty">No visits on this day.</p><?php else: ?>
<ul class="slots"><?php foreach ($day as $r): ?>
<li><time><?= h(substr($r['appt_time'], 0, 5)) ?></time><div><b><?= h($r['patient']) ?></b>
<small>with <?= h($r['doctor']) ?><?= $r['reason'] ? ' · ' . h($r['reason']) : '' ?> · <span class="pill s-<?= strtolower($r['status']) ?>"><?= h($r['status']) ?></span>
 · <a href="slip.php?id=<?= $r['appointment_id'] ?>" target="_blank">Slip</a><?php if (role() !== 'doctor'): ?> · <a href="appointments.php?reschedule=<?= $r['appointment_id'] ?>">Reschedule</a><?php endif; ?></small></div></li>
<?php endforeach; ?></ul><?php endif; ?>
<?php if (role() !== 'doctor'): try { $dd = avail_day(avail_load($pdo, $sel, $sel), $sel); } catch (PDOException $e) { $dd = null; } ?>
<?php if ($dd !== null): ?><p class="note" style="margin-top:14px"><b>Doctors available this day:</b> <?= $dd ? h(implode(', ', array_map(fn($d) => $d['name'] . ' (' . implode(', ', $d['blocks']) . ')', $dd))) : 'none' ?></p><?php endif; endif; ?>
<?php if (role() !== 'doctor'): ?><p><a class="btn" href="appointments.php?date=<?= h($sel) ?>">Book a visit on this day</a></p><?php endif; ?></div>
<?php include 'footer.php'; ?>
