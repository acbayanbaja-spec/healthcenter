<?php
// ---- Appointment actions shared by the Visits page and the Pulse assistant ----
function appt_label($pdo, $id) {
    $s = $pdo->prepare('SELECT a.*, p.full_name patient, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id) WHERE a.appointment_id=?');
    $s->execute([$id]); $r = $s->fetch();
    return $r ? "Patient: {$r['patient']}; Doctor: {$r['doctor']}; {$r['appt_date']} " . substr($r['appt_time'], 0, 5) . "; Status: {$r['status']}" . ($r['reason'] ? "; Reason: {$r['reason']}" : '') : '';
}
function appt_when_ok($date, $time) { $ts = strtotime("$date $time"); return $ts && $ts > time(); }
function appt_err($e) { return (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) ? 'That doctor is already booked at that date and time.' : 'Database error. Check that the patient and doctor still exist.'; }

// each returns [ok, message, id?]
function book_appointment($pdo, $pid, $did, $date, $time, $reason, $sms = true, $via = '') {
    if (!appt_when_ok($date, $time)) return [false, 'Choose a date and time in the future.', null];
    try {
        $pdo->prepare('INSERT INTO appointments (patient_id,doctor_id,appt_date,appt_time,reason) VALUES (?,?,?,?,?)')->execute([$pid, $did, $date, $time, $reason]);
        $id = (int)$pdo->lastInsertId();
        log_event($pdo, 'Created', 'appointments', $id, appt_label($pdo, $id) . $via);
        $m = 'Appointment booked.';
        if ($sms) $m .= ' ' . notify_appointment($pdo, $id, 'Booked');
        return [true, $m, $id];
    } catch (PDOException $e) { return [false, appt_err($e), null]; }
}

function reschedule_appointment($pdo, $id, $did, $date, $time, $reason, $sms = true, $via = '') {
    if (!appt_when_ok($date, $time)) return [false, 'Choose a date and time in the future.', $id];
    $before = appt_label($pdo, $id);
    if ($before === '') return [false, 'Appointment not found.', $id];
    try {
        $pdo->prepare("UPDATE appointments SET doctor_id=?, appt_date=?, appt_time=?, reason=?, status='Scheduled' WHERE appointment_id=?")->execute([$did, $date, $time, $reason, $id]);
    } catch (PDOException $e) { return [false, appt_err($e), $id]; }
    $after = appt_label($pdo, $id);
    if ($before === $after) return [true, 'Nothing changed.', $id];
    log_event($pdo, 'Updated', 'appointments', $id, "Rescheduled: $before → $after" . $via);
    $m = 'Visit rescheduled. ' . notify_appointment($pdo, $id, 'Rescheduled', $sms ? null : 'Text not requested by staff');
    return [true, trim($m), $id];
}

function set_status($pdo, $id, $new, $via = '') {
    if (!in_array($new, ['Scheduled', 'Completed', 'Cancelled'], true)) return 'Invalid status.';
    $o = $pdo->prepare('SELECT status FROM appointments WHERE appointment_id=?'); $o->execute([$id]); $old = $o->fetchColumn();
    if ($old === false) return 'Appointment not found.';
    $wasUpcoming = appt_is_upcoming($pdo, $id);
    $pdo->prepare('UPDATE appointments SET status=? WHERE appointment_id=?')->execute([$new, $id]);
    log_event($pdo, 'Updated', 'appointments', $id, "status: $old → $new; " . appt_label($pdo, $id) . $via);
    $m = 'Status updated.';
    if ($new === 'Cancelled' && $wasUpcoming) $m .= ' ' . notify_appointment($pdo, $id, 'Cancelled');
    return $m;
}

function delete_appointment($pdo, $id) {
    $lbl = appt_label($pdo, $id);
    $sms = appt_is_upcoming($pdo, $id) ? notify_appointment($pdo, $id, 'Cancelled') : '';
    $pdo->prepare('DELETE FROM appointments WHERE appointment_id=?')->execute([$id]);
    log_event($pdo, 'Deleted', 'appointments', $id, $lbl);
    return trim('Appointment deleted. ' . $sms);
}

// ---- Doctor availability (weekly hours minus days off minus booked slots) ----
const SLOT_MINUTES = 30;
function avail_load($pdo, $from, $to) {
    $a = $pdo->query('SELECT d.doctor_id, d.full_name, d.specialty, v.weekday, v.start_time, v.end_time FROM doctors d JOIN doctor_availability v ON v.doctor_id = d.doctor_id ORDER BY d.full_name, v.start_time')->fetchAll();
    $s = $pdo->prepare('SELECT doctor_id, off_date FROM doctor_days_off WHERE off_date BETWEEN ? AND ?'); $s->execute([$from, $to]);
    $off = []; foreach ($s->fetchAll() as $r) $off[$r['doctor_id']][$r['off_date']] = true;
    $s = $pdo->prepare('SELECT doctor_id, appt_date, appt_time FROM appointments WHERE appt_date BETWEEN ? AND ?'); $s->execute([$from, $to]);
    $booked = []; foreach ($s->fetchAll() as $r) $booked[$r['doctor_id']][$r['appt_date']][] = strtotime($r['appt_date'] . ' ' . $r['appt_time']);
    return [$a, $off, $booked];
}
// Doctors with at least one open slot on $date: [doctor_id => ['name','specialty','blocks'=>[..],'open'=>n]]
function avail_day($data, $date) {
    [$a, $off, $booked] = $data;
    $w = (int)date('w', strtotime($date)); $slot = SLOT_MINUTES * 60; $out = [];
    foreach ($a as $r) {
        $did = $r['doctor_id'];
        if ((int)$r['weekday'] !== $w || !empty($off[$did][$date])) continue;
        $s = strtotime("$date {$r['start_time']}"); $e = strtotime("$date {$r['end_time']}"); $open = 0;
        for ($t = $s; $t + $slot <= $e; $t += $slot) {
            if ($t < time()) continue;
            $taken = false;
            foreach ($booked[$did][$date] ?? [] as $bt) if ($bt >= $t && $bt < $t + $slot) { $taken = true; break; }
            if (!$taken) $open++;
        }
        if (!isset($out[$did])) $out[$did] = ['name' => $r['full_name'], 'specialty' => $r['specialty'], 'blocks' => [], 'open' => 0];
        $out[$did]['blocks'][] = substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5);
        $out[$did]['open'] += $open;
    }
    return array_filter($out, fn($d) => $d['open'] > 0);
}
