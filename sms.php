<?php
// ---- Phone (SMS) notifications ----
function normalize_phone($raw) {
    $p = preg_replace('/[^\d+]/', '', (string)$raw);
    if ($p === '') return null;
    if ($p[0] === '+') { $digits = substr($p, 1); }
    elseif ($p[0] === '0') { return normalize_phone(SMS_COUNTRY_CODE . substr($p, 1)); }
    else { $cc = ltrim(SMS_COUNTRY_CODE, '+'); $digits = strpos($p, $cc) === 0 ? $p : $cc . $p; }
    return (strlen($digits) >= 10 && strlen($digits) <= 15) ? '+' . $digits : null;
}
function sms_pill($s) { return ['Sent' => 's-completed', 'Failed' => 's-cancelled'][$s] ?? 's-other'; }

function sms_text($type, $a) {
    $first = explode(' ', $a['patient'])[0];
    $when = date('D j M', strtotime($a['appt_date'])) . ' at ' . date('g:i A', strtotime($a['appt_time']));
    $doc = $a['doctor'];
    switch ($type) {
        case 'Booked': return "Hi $first, your appointment at " . CLINIC_NAME . " is confirmed: $when with $doc. Please arrive 10 minutes early.";
        case 'Reminder-24h': return "Reminder: $first, you have an appointment at " . CLINIC_NAME . " on $when with $doc.";
        case 'Reminder-2h': return "Reminder: $first, your appointment at " . CLINIC_NAME . " is in about 2 hours ($when, $doc).";
        case 'Rescheduled': return "Hi $first, your appointment at " . CLINIC_NAME . " has been moved to $when with $doc.";
        case 'Cancelled': return "Hi $first, your appointment at " . CLINIC_NAME . " on $when with $doc was cancelled. Please contact us to rebook.";
    }
    return '';
}

function http_post($url, $fields, $userpwd = null) {
    if (!function_exists('curl_init')) return [0, 'The PHP cURL extension is not enabled'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    if ($userpwd) curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return [$code, $body === false ? $err : $body];
}
// returns [ok, error, simulated]
function sms_send($to, $body) {
    switch (SMS_PROVIDER) {
        case 'twilio':
            [$code, $res] = http_post('https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json',
                ['To' => $to, 'From' => TWILIO_FROM, 'Body' => $body], TWILIO_SID . ':' . TWILIO_TOKEN);
            if ($code === 201) return [true, null, false];
            $j = json_decode($res, true); return [false, $j['message'] ?? (string)$res, false];
        case 'semaphore':
            $f = ['apikey' => SEMAPHORE_KEY, 'number' => ltrim($to, '+'), 'message' => $body];
            if (SEMAPHORE_SENDER) $f['sendername'] = SEMAPHORE_SENDER;
            [$code, $res] = http_post('https://api.semaphore.co/api/v4/messages', $f);
            $j = json_decode($res, true);
            if ($code === 200 && is_array($j) && isset($j[0]['message_id'])) return [true, null, false];
            return [false, is_array($j) ? json_encode($j) : (string)$res, false];
        default:
            return [true, null, true];
    }
}

function sms_record($pdo, $apptId, $patientId, $phone, $type, $message, $skip = null) {
    $norm = normalize_phone($phone);
    $pdo->prepare('INSERT INTO notifications (created_at,appointment_id,patient_id,phone,type,message,status,provider,error) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([date('Y-m-d H:i:s'), $apptId, $patientId, $norm ?: (string)$phone, $type, $message,
                   ($norm && !$skip) ? 'Pending' : 'Skipped', SMS_PROVIDER, $skip ?: ($norm ? null : 'No valid phone number')]);
    return (int)$pdo->lastInsertId();
}
function sms_deliver($pdo, $nid) {
    $s = $pdo->prepare('SELECT * FROM notifications WHERE notif_id=?'); $s->execute([$nid]); $n = $s->fetch();
    if (!$n) return ['Failed', 'Message not found'];
    if ($n['status'] === 'Skipped') return ['Skipped', $n['error']];
    [$ok, $err, $sim] = sms_send($n['phone'], $n['message']);
    $status = $ok ? ($sim ? 'Simulated' : 'Sent') : 'Failed';
    $pdo->prepare('UPDATE notifications SET status=?, error=?, provider=?, sent_at=? WHERE notif_id=?')
        ->execute([$status, $ok ? null : substr((string)$err, 0, 250), SMS_PROVIDER, $ok ? date('Y-m-d H:i:s') : null, $nid]);
    return [$status, $err];
}
// Queue + send one text for an appointment. Returns a short message for the screen.
function notify_appointment($pdo, $id, $type, $skip = null) {
    try {
        $s = $pdo->prepare('SELECT a.*, p.full_name patient, p.phone, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id) WHERE a.appointment_id=?');
        $s->execute([$id]); $a = $s->fetch();
        if (!$a) return '';
        if ($skip === null) {
            try { $o = $pdo->prepare('SELECT sms_optin FROM patients WHERE patient_id=?'); $o->execute([$a['patient_id']]); $opt = $o->fetchColumn(); if ($opt !== false && (int)$opt === 0) $skip = 'Patient opted out of texts'; } catch (PDOException $e) {}
        }
        $nid = sms_record($pdo, $id, $a['patient_id'], $a['phone'], $type, sms_text($type, $a), $skip);
        [$st, $err] = sms_deliver($pdo, $nid);
        if ($st === 'Sent') return 'Text message sent.';
        if ($st === 'Simulated') return 'Text message recorded (simulation mode, nothing was delivered).';
        if ($st === 'Skipped') return 'Text skipped: ' . strtolower($err) . '.';
        return 'Text message failed: ' . $err;
    } catch (PDOException $e) { return 'Text not sent: run migrate_notifications.sql.'; }
}
function appt_is_upcoming($pdo, $id) {
    $s = $pdo->prepare("SELECT status, appt_date, appt_time FROM appointments WHERE appointment_id=?"); $s->execute([$id]); $a = $s->fetch();
    return $a && $a['status'] === 'Scheduled' && strtotime($a['appt_date'] . ' ' . $a['appt_time']) > time();
}
// Sends the 24-hour and 2-hour reminders that are due. Returns how many were processed.
function run_reminders($pdo) {
    $n = 0;
    $rows = $pdo->query("SELECT appointment_id, appt_date, appt_time FROM appointments WHERE status='Scheduled' AND appt_date BETWEEN CURDATE() - INTERVAL 1 DAY AND CURDATE() + INTERVAL 2 DAY")->fetchAll();
    foreach ($rows as $r) {
        $ts = strtotime($r['appt_date'] . ' ' . $r['appt_time']); $diff = $ts - time();
        if ($diff <= 0 || $diff > 86400) continue;
        $type = $diff <= 7200 ? 'Reminder-2h' : 'Reminder-24h';
        $window = $type === 'Reminder-2h' ? 7200 : 86400;
        $m = $pdo->prepare("SELECT created_at FROM notifications WHERE appointment_id=? AND type IN ('Booked','Rescheduled') ORDER BY notif_id DESC LIMIT 1"); $m->execute([$r['appointment_id']]);
        $booked = $m->fetchColumn();
        $c = $pdo->prepare('SELECT 1 FROM notifications WHERE appointment_id=? AND type=? AND created_at >= ? LIMIT 1'); $c->execute([$r['appointment_id'], $type, $booked ?: '1970-01-01 00:00:00']);
        if ($c->fetch()) continue;
        if ($booked && ($ts - strtotime($booked)) < $window) continue;   // booked recently: confirmation already covers it
        notify_appointment($pdo, $r['appointment_id'], $type); $n++;
    }
    return $n;
}
// Runs reminders at most every 10 minutes while anyone is using the site.
function maybe_run_reminders($pdo) {
    $f = sys_get_temp_dir() . '/healthcenter_reminders.lock';
    if (is_file($f) && time() - filemtime($f) < 600) return;
    touch($f);
    try { run_reminders($pdo); } catch (Throwable $e) {}
}

// Patients who can be texted: valid phone and not opted out. $which: 'all' or 'upcoming' (has a future scheduled visit).
function sms_audience($pdo, $which) {
    $opt = '';
    try { if ($pdo->query("SHOW COLUMNS FROM patients LIKE 'sms_optin'")->fetch()) $opt = ' AND p.sms_optin=1'; } catch (PDOException $e) {}
    $sql = "SELECT p.patient_id, p.phone FROM patients p WHERE p.phone<>''$opt"
         . ($which === 'upcoming' ? " AND p.patient_id IN (SELECT patient_id FROM appointments WHERE status='Scheduled' AND TIMESTAMP(appt_date,appt_time) > NOW())" : '');
    $out = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) { $n = normalize_phone($r['phone']); if ($n) $out[] = ['patient_id' => $r['patient_id'], 'phone' => $n]; }
    return $out;
}
