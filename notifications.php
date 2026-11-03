<?php require_once 'db.php'; require_role(['admin', 'staff']); include 'header.php';
$msg = ''; $err = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['run'])) { $msg = 'Reminders checked. ' . run_reminders($pdo) . ' sent or recorded.'; }
        elseif (isset($_POST['resend'])) { [$st, $e] = sms_deliver($pdo, (int)$_POST['resend']); $msg = "Resend result: $st" . ($e ? " ($e)" : ''); }
        elseif (isset($_POST['announce']) && role() === 'admin') {
            $text = trim(mb_substr($_POST['text'] ?? '', 0, 140));
            $which = ($_POST['audience'] ?? '') === 'upcoming' ? 'upcoming' : 'all';
            if ($text === '') { $err = 'Write the announcement first.'; }
            else {
                set_time_limit(120);
                $list = array_slice(sms_audience($pdo, $which), 0, 200); $tally = [];
                foreach ($list as $r) {
                    $nid = sms_record($pdo, null, $r['patient_id'], $r['phone'], 'Announcement', CLINIC_NAME . ': ' . $text);
                    [$st] = sms_deliver($pdo, $nid); $tally[$st] = ($tally[$st] ?? 0) + 1;
                }
                log_event($pdo, 'Created', 'notifications', null, 'Announcement to ' . count($list) . " patients ($which): $text");
                $msg = 'Announcement processed for ' . count($list) . ' patients. ' . implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($tally), $tally));
            }
        }
        elseif (isset($_POST['test'])) {
            $nid = sms_record($pdo, null, null, $_POST['phone'], 'Test', 'This is a test message from ' . CLINIC_NAME . '.');
            [$st, $e] = sms_deliver($pdo, $nid); $msg = "Test message: $st" . ($e ? " ($e)" : '');
        }
    }
    $soon = $pdo->query("SELECT a.*, p.full_name patient, p.phone, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id)
        WHERE a.status='Scheduled' AND TIMESTAMP(a.appt_date,a.appt_time) BETWEEN NOW() AND NOW() + INTERVAL 24 HOUR ORDER BY a.appt_date, a.appt_time")->fetchAll();
    $nAll = count(sms_audience($pdo, 'all')); $nUp = count(sms_audience($pdo, 'upcoming'));
    $outbox = $pdo->query("SELECT n.*, p.full_name patient FROM notifications n LEFT JOIN patients p USING(patient_id) ORDER BY n.notif_id DESC LIMIT 100")->fetchAll();
} catch (PDOException $e) { $soon = $outbox = []; $nAll = $nUp = 0; $err = 'The notifications table is missing. Run migrate_notifications.sql in phpMyAdmin.'; }
?>
<div class="pagehead"><h2>Alerts</h2>
  <form method="post"><button name="run" value="1">Send due reminders now</button></form></div>
<p class="note">Patients get a text when a visit is booked or cancelled, 24 hours before, and 2 hours before. Texts never include the reason for the visit.</p>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<?php if (SMS_PROVIDER === 'simulate'): ?><div class="msg err">Simulation mode: messages are recorded below but not delivered to phones. Set <b>SMS_PROVIDER</b> in <b>config_sms.php</b> to send real texts.</div>
<?php else: ?><div class="msg ok">Sending through <b><?= h(SMS_PROVIDER) ?></b>.</div><?php endif; ?>

<div class="card"><h3>Starting in the next 24 hours</h3>
<?php if (!$soon): ?><p class="empty">No visits are coming up in the next 24 hours.</p><?php else: ?>
<ul class="slots"><?php foreach ($soon as $a): ?>
<li><time><?= h(substr($a['appt_time'], 0, 5)) ?></time><div><b><?= h($a['patient']) ?></b>
<small>with <?= h($a['doctor']) ?> · <?= normalize_phone($a['phone']) ? h(normalize_phone($a['phone'])) : 'no valid phone number' ?></small></div></li>
<?php endforeach; ?></ul><?php endif; ?></div>

<?php if (role() === 'admin'): ?>
<div class="card"><h3>Send an announcement</h3>
<form method="post" class="grid" onsubmit="var o=this.audience.selectedOptions[0];return confirm('Send this text to '+o.dataset.n+' patients?')">
  <select name="audience" aria-label="Who should get it"><option value="upcoming" data-n="<?= (int)$nUp ?>">Patients with upcoming visits (<?= (int)$nUp ?>)</option><option value="all" data-n="<?= (int)$nAll ?>">All patients with texts on (<?= (int)$nAll ?>)</option></select>
  <input name="text" maxlength="140" placeholder="Message (up to 140 characters, sent with the clinic name)" required>
  <div><button name="announce" value="1">Send announcement</button></div>
</form><p class="note">Sends to a maximum of 200 patients at a time. Patients who turned texts off are never included.</p></div>
<?php endif; ?>

<div class="card"><h3>Send a test text</h3>
<form method="post" class="grid"><input name="phone" placeholder="Phone number, e.g. 0917 123 4567" required><div><button name="test" value="1">Send test</button></div></form></div>

<div class="card"><h3>Message log</h3>
<table><tr><th>When</th><th>Patient</th><th>Phone</th><th>Type</th><th>Message</th><th>Status</th><th></th></tr>
<?php foreach ($outbox as $n): ?>
<tr><td><?= h($n['created_at']) ?></td><td><?= h($n['patient'] ?? '—') ?></td><td><?= h($n['phone']) ?></td><td><?= h($n['type']) ?></td>
<td class="det"><?= h($n['message']) ?><?= $n['error'] ? '<br><small style="color:#b3263d">' . h($n['error']) . '</small>' : '' ?></td>
<td><span class="pill <?= sms_pill($n['status']) ?>"><?= h($n['status']) ?></span></td>
<td><?php if (in_array($n['status'], ['Failed', 'Pending'])): ?><form method="post"><button name="resend" value="<?= $n['notif_id'] ?>">Resend</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$outbox) echo '<tr><td colspan="7">No messages yet.</td></tr>'; ?></table></div>
<?php include 'footer.php'; ?>
