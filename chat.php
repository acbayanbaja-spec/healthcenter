<?php
require_once 'db.php';
require_once 'config_ai.php';
header('Content-Type: application/json; charset=utf-8');

function out($reply, $source = 'local', $code = 200) { http_response_code($code); echo json_encode(['reply' => $reply, 'source' => $source]); exit; }

if (empty($_SESSION['user']) || empty($_SESSION['uid'])) out('Please log in again.', 'local', 401);
if (!in_array(role(), ['admin', 'staff'], true)) out('The assistant is only available to staff.', 'local', 403);
if (!empty($_SESSION['must_change'])) out('Please choose a new password first.', 'local', 403);
$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf'] ?? 'x', (string)($in['csrf'] ?? ''))) out('Session expired. Reload the page.', 'local', 403);
$msg = trim(mb_substr((string)($in['message'] ?? ''), 0, 500));
if ($msg === '') out('Type a question and I will help.');

// rate limit: 20 messages per 10 minutes
$now = time();
$_SESSION['ai_hits'] = array_values(array_filter($_SESSION['ai_hits'] ?? [], fn($t) => $t > $now - 600));
if (count($_SESSION['ai_hits']) >= 20) out('You are asking quickly. Please wait a few minutes and try again.', 'local', 429);
$_SESSION['ai_hits'][] = $now;

$first = explode(' ', $_SESSION['user'])[0];

function visits_on($pdo, $day) {
    $s = $pdo->prepare("SELECT a.appt_time, p.full_name patient, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id) WHERE a.appt_date=? AND a.status='Scheduled' ORDER BY a.appt_time");
    $s->execute([$day]); $rows = $s->fetchAll();
    $label = date('l j M', strtotime($day));
    if (!$rows) return "No scheduled visits on $label. [Book a visit](appointments.php)";
    $lines = array_map(fn($r) => '• ' . substr($r['appt_time'], 0, 5) . ' — ' . $r['patient'] . ' with ' . $r['doctor'], array_slice($rows, 0, 10));
    return '**' . count($rows) . ' visit' . (count($rows) > 1 ? 's' : '') . " on $label:**\n" . implode("\n", $lines)
         . (count($rows) > 10 ? "\n…and " . (count($rows) - 10) . ' more.' : '') . "\n[Open Visits](appointments.php)";
}

function ai_local($pdo, $m, $first) {
    $t = strtolower($m);
    if (preg_match('/\b(emergency|chest pain|can\'?t breathe|cannot breathe|unconscious|severe bleeding|overdose|suicid)/', $t))
        return "If someone may be in danger, call your local emergency number now. I can't give medical advice, and this system isn't for emergencies.";
    if (preg_match('/^(hi|hello|hey|good (morning|afternoon|evening))\b/', $t)) return "Hi $first! Ask me about today's visits, finding a patient, or how to do something in the system.";
    if (preg_match('/\b(thanks|thank you)\b/', $t)) return "You're welcome!";

    if (preg_match('/\btoday\b/', $t) && preg_match('/appointment|visit|schedule|booked|patient/', $t)) return visits_on($pdo, date('Y-m-d'));
    if (preg_match('/\btomorrow\b/', $t) && preg_match('/appointment|visit|schedule|booked|patient/', $t)) return visits_on($pdo, date('Y-m-d', strtotime('+1 day')));

    if (preg_match('/\b(upcoming|next)\b.*(appointment|visit)/', $t)) {
        $s = $pdo->prepare("SELECT a.appt_date, a.appt_time, p.full_name patient, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id) WHERE a.status='Scheduled' AND TIMESTAMP(a.appt_date,a.appt_time) >= ? ORDER BY a.appt_date, a.appt_time LIMIT 5");
        $s->execute([date('Y-m-d H:i:s')]); $rows = $s->fetchAll();
        if (!$rows) return 'There are no upcoming scheduled visits. [Book a visit](appointments.php)';
        return "**Next visits:**\n" . implode("\n", array_map(fn($r) => '• ' . date('D j M', strtotime($r['appt_date'])) . ' ' . substr($r['appt_time'], 0, 5) . ' — ' . $r['patient'] . ' with ' . $r['doctor'], $rows));
    }
    if (preg_match('/how many (patients|doctors|appointments|visits)/', $t, $x)) {
        $tbl = ['patients' => 'patients', 'doctors' => 'doctors', 'appointments' => 'appointments', 'visits' => 'appointments'][$x[1]];
        return 'There are **' . $pdo->query("SELECT COUNT(*) FROM $tbl")->fetchColumn() . "** $x[1] on record.";
    }
    if (preg_match('/cancell?ation rate|how many cancel/', $t)) {
        $tot = (int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn(); $c = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Cancelled'")->fetchColumn();
        return $tot ? "**$c of $tot** visits were cancelled (" . round($c / $tot * 100) . '%). [See Analytics](analytics.php)' : 'There are no visits yet.';
    }
    if (preg_match('/\b(?:find|search|look ?up)\s+(?:for\s+)?(?:a\s+)?(?:patient\s+)?(.{2,40})$/', $t, $x)) {
        $name = trim($x[1]);
        if (in_array($name, ['patient', 'patients', 'a patient'])) return 'Tell me who to look for, like **find patient Maria** or **find 0917…**.';
        $s = $pdo->prepare('SELECT full_name, phone FROM patients WHERE full_name LIKE ? OR phone LIKE ? ORDER BY full_name LIMIT 6');
        $s->execute(["%$name%", "%$name%"]); $rows = $s->fetchAll();
        log_event($pdo, 'Searched', 'patients', null, "Assistant patient search: $name");
        if (!$rows) return "I couldn't find a patient matching \"$name\". [Add a patient](patients.php)";
        return "**Patients matching \"$name\":**\n" . implode("\n", array_map(fn($r) => '• ' . $r['full_name'] . ($r['phone'] ? ' · ' . $r['phone'] : ''), $rows)) . "\n[Open in Patients](patients.php?q=" . rawurlencode($name) . ')';
    }
    if (preg_match('/\b(doctors?|dr)\b.*(available|schedule|on duty|who|list)/', $t)) {
        $rows = $pdo->query('SELECT full_name, specialty, schedule FROM doctors ORDER BY full_name LIMIT 10')->fetchAll();
        if (!$rows) return 'No doctors are on record yet. [Add a doctor](doctors.php)';
        return "**Doctors:**\n" . implode("\n", array_map(fn($r) => '• ' . $r['full_name'] . ' (' . $r['specialty'] . ')' . ($r['schedule'] ? ' — ' . $r['schedule'] : ''), $rows)) . "\n[Open Doctors](doctors.php)";
    }

    $howto = [
        ['/availab|working hours|day off|leave\\b/', 'Set each doctor\'s weekly hours and days off in [Doctors](doctors.php) with the **Hours** button. Patients only see a doctor as available on working days, outside days off, while time slots are open.'],
        ['/backup|restore|export.*database/', 'Admins can download a full database backup from [Backup](backup.php). To restore, import the .sql file in phpMyAdmin. Copy the uploads folder too, since photos are stored there.'],
        ['/walk.?in|register.*patient|patient.*(account|login)/', 'Open [Register](register_patient.php) to create a login for a walk-in patient. Pick an existing patient record or enter new details, tick the User Agreement box, and give them the username and temporary password.'],
        ['/(create|add|new).*(account|user)|\\brole/', 'Admins create staff, doctor and patient accounts in [Users](users.php). Staff create walk-in patient accounts in [Register](register_patient.php).'],
        ['/calendar|month view/', 'Open [Calendar](calendar.php) for a month view. Click a day to see its visits, filter by doctor, and book on that day.'],
        ['/print|slip|\bpdf\b/', 'Use **Slip** on a visit in [Visits](appointments.php) to open a printable appointment slip, then choose Print or Save as PDF.'],
        ['/dark|theme|light mode/', 'Use the moon/sun button in the top bar to switch between light and dark mode. It is remembered in this browser.'],
        ['/announce|broadcast|bulk|everyone/', 'In [Alerts](notifications.php), use **Send an announcement** to text every patient with texts on, or only patients with upcoming visits.'],
        ['/patient.*(record|detail|page)|visit history/', 'Click a patient\'s name in [Patients](patients.php) to see their details, visit history, slips and a Word export of their record history.'],
        ['/book|make an? appointment|schedule an? (visit|appointment)/', "Open [Visits](appointments.php), choose the patient, doctor, date and time, then select **Book visit**. A doctor can't be booked twice at the same time. Or just tell me **book a visit** and I'll walk you through it."],
        ['/cancel|resched|move (a|the) (visit|appointment)/', "In [Visits](appointments.php) use **Reschedule** to move a visit, or set the status to **Cancelled** (an upcoming visit also texts the patient). I can do either for you: say **reschedule a visit** or **cancel a visit**."],
        ['/(add|new|register).*patient|patient.*(add|new)/', 'Open [Patients](patients.php), fill in the form at the top, and select **Save**. Use the search box to find or edit someone later.'],
        ['/(add|new).*doctor/', 'Open [Doctors](doctors.php), enter the name, specialty, phone and schedule, then select **Save**.'],
        ['/report|export|word|download/', 'Use **Download Word report** on [Analytics](analytics.php) for the charts, or on [History](history.php) for the change log (it respects your filters).'],
        ['/history|audit|who changed|log\b/', '[History](history.php) shows every create, update, delete, login and export. Each patient, doctor and visit also has a **History** button for its own timeline.'],
        ['/sms|text|remind|notif|alert|bell|opt/', 'Texts go out when a visit is booked, rescheduled or cancelled and 24 hours and 2 hours before. Untick "Patient agrees to appointment texts" on a patient to stop their texts. See the message log and resend failures in [Alerts](notifications.php). Provider settings live in config_sms.php.'],
        ['/photo|cover|avatar|picture|profile/', 'Click your name in the top bar to open your profile, then upload a profile photo or cover photo (JPG, PNG or WebP). Everyone is listed on [Team](team.php).'],
        ['/password/', 'Open [My profile](profile.php) and use **Change password**. You need your current password.'],
        ['/agreement|terms/', 'The [User Agreement](terms.php) is shown at registration, and the acceptance time is saved with each account.'],
        ['/analytic|statistic|chart|rate/', '[Analytics](analytics.php) shows completion and cancellation rates, visits per doctor and month, patient groups, and top reasons for visits.'],
        ['/log ?out|sign out/', 'Select **Log out** at the bottom of the side menu, especially on shared computers.'],
        ['/guide|tutorial|how (do|to)|help/', 'The [Guide](tutorial.php) walks through the system step by step. You can also ask me things like **today\'s visits** or **find patient Maria**.'],
    ];
    foreach ($howto as [$re, $ans]) if (preg_match($re, $t)) return $ans;
    return null;
}

function ai_llm($pdo, $history, $msg) {
    $pat = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    $doc = (int)$pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status='Scheduled' AND appt_date=?"); $s->execute([date('Y-m-d')]); $today = (int)$s->fetchColumn();
    $system = "You are Pulse, the assistant inside the Health Center Information and Appointment Management System. Help staff use the system and answer general questions in under 120 words.\n"
      . "Pages: Home (index.php), Patients (patients.php), Doctors (doctors.php), Visits/appointments (appointments.php), Analytics with Word report (analytics.php), History/audit log (history.php), Alerts and text messages (notifications.php), Team (team.php), My profile (profile.php), Guide (tutorial.php).\n"
      . "Rules: You cannot see patient or appointment records, so never invent names, times or details; send people to the right page. Do not diagnose or give treatment advice; suggest seeing a clinician. For emergencies, tell them to call the local emergency number. Link pages with markdown like [Visits](appointments.php), using only the pages above. Plain text otherwise.\n"
      . "Current totals: $pat patients, $doc doctors, $today visits scheduled today. Today is " . date('l j F Y') . '.';
    $messages = [];
    foreach (array_slice($history, -6) as $h) {
        if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && is_string($h['content'] ?? null)) $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['content'], 0, 600)];
    }
    while ($messages && $messages[0]['role'] !== 'user') array_shift($messages);
    $messages[] = ['role' => 'user', 'content' => $msg];
    if (!function_exists('curl_init')) return [false, 'cURL is not enabled in PHP.'];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . ANTHROPIC_API_KEY, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode(['model' => AI_MODEL, 'max_tokens' => 400, 'system' => $system, 'messages' => $messages])]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) return [false, $err];
    $j = json_decode($res, true);
    if (isset($j['content'][0]['text'])) return [true, trim($j['content'][0]['text'])];
    return [false, $j['error']['message'] ?? 'Unexpected response'];
}

function pf_date($s) { $ts = strtotime(strtolower(trim($s))); if (!$ts) return null; $d = date('Y-m-d', $ts); return ($d < date('Y-m-d') || $ts > strtotime('+1 year')) ? null : $d; }
function pf_time($s) { $t = strtolower(trim($s)); if (!preg_match('/^\d{1,2}(:\d{2})?\s*(am|pm)?$/', $t) || !preg_match('/(am|pm|:)/', $t)) return null; $ts = strtotime($t); return $ts ? date('H:i', $ts) : null; }
function pf_when($d, $t) { return date('D j M', strtotime($d)) . ' at ' . date('g:i A', strtotime($t)); }
function pf_list($rows, $fn) { $o = []; foreach ($rows as $i => $r) $o[] = '**' . ($i + 1) . '.** ' . $fn($r); return implode("\n", $o); }

// Step-by-step book / reschedule / cancel flows. Names stay on this server and never go to the AI provider.
function pulse_flow($pdo, $m, $first) {
    $t = strtolower(trim($m));
    $f = $_SESSION['pulse'] ?? null;
    if ($f && time() - $f['ts'] > 600) { unset($_SESSION['pulse']); $f = null; }
    if ($f && preg_match('/^(stop|quit|exit|abort|never ?mind|nevermind)$/', $t)) { unset($_SESSION['pulse']); return 'Okay, I stopped. Nothing was changed.'; }
    if (!$f) {
        if (preg_match('/^(how|what|can|where|why|who|when)\b/', $t)) return null;
        $kind = null;
        if (preg_match('/^(please )?reschedule\b|^(move|change) (a|the|my) (visit|appointment)/', $t)) $kind = 'resched';
        elseif (preg_match('/^(please )?cancel\b/', $t)) $kind = 'cancel';
        elseif (preg_match('/^(please )?(book|schedule)\b.*(visit|appointment)|^(please )?book\b|^(new|add|make)( a| an)? (visit|appointment)/', $t)) $kind = 'book';
        if (!$kind) return null;
        $_SESSION['pulse'] = ['kind' => $kind, 'step' => 'patient', 'ts' => time()];
        return 'Sure. Who is the patient? Type a name or phone number. (Say **stop** any time.)';
    }
    $f['ts'] = time(); $k = $f['kind'];
    $save = function () use (&$f) { $_SESSION['pulse'] = $f; };
    $end = function () { unset($_SESSION['pulse']); };

    if ($f['step'] === 'patient' || $f['step'] === 'pick_patient') {
        $p = null;
        if ($f['step'] === 'pick_patient' && ctype_digit($t) && isset($f['cands'][(int)$t - 1])) $p = $f['cands'][(int)$t - 1];
        else {
            $s = $pdo->prepare('SELECT patient_id, full_name FROM patients WHERE full_name LIKE ? OR phone LIKE ? ORDER BY full_name LIMIT 6');
            $s->execute(["%$m%", "%$m%"]); $rows = $s->fetchAll();
            if (!$rows) { $f['step'] = 'patient'; $save(); return "I couldn't find a patient matching \"$m\". Try another name or phone number, or say **stop**."; }
            if (count($rows) > 1) { $f['step'] = 'pick_patient'; $f['cands'] = $rows; $save(); return "Which one?\n" . pf_list($rows, fn($r) => $r['full_name']) . "\nReply with a number."; }
            $p = $rows[0];
        }
        $f['pid'] = (int)$p['patient_id']; $f['pname'] = $p['full_name']; unset($f['cands']);
        if ($k === 'book') {
            $docs = $pdo->query('SELECT doctor_id, full_name, specialty FROM doctors ORDER BY full_name LIMIT 10')->fetchAll();
            if (!$docs) { $end(); return 'There are no doctors yet. [Add a doctor](doctors.php) first.'; }
            $f['step'] = 'doctor'; $f['dcands'] = $docs; $save();
            return "Booking for **{$p['full_name']}**. Which doctor?\n" . pf_list($docs, fn($r) => $r['full_name'] . ' (' . $r['specialty'] . ')') . "\nReply with a number or name.";
        }
        $s = $pdo->prepare("SELECT appointment_id, doctor_id, appt_date, appt_time, reason, (SELECT full_name FROM doctors d WHERE d.doctor_id=a.doctor_id) doctor FROM appointments a WHERE patient_id=? AND status='Scheduled' AND TIMESTAMP(appt_date,appt_time) > ? ORDER BY appt_date, appt_time LIMIT 6");
        $s->execute([$f['pid'], date('Y-m-d H:i:s')]); $vis = $s->fetchAll();
        if (!$vis) { $end(); return "{$p['full_name']} has no upcoming scheduled visits."; }
        $f['step'] = 'pick_visit'; $f['vis'] = $vis; $save();
        return "**{$p['full_name']}'s upcoming visits:**\n" . pf_list($vis, fn($r) => pf_when($r['appt_date'], $r['appt_time']) . ' with ' . $r['doctor']) . "\nWhich one? Reply with a number.";
    }
    if ($f['step'] === 'pick_visit') {
        if (!ctype_digit($t) || !isset($f['vis'][(int)$t - 1])) return 'Please reply with one of the numbers, or say **stop**.';
        $v = $f['vis'][(int)$t - 1]; $f['vid'] = (int)$v['appointment_id']; $f['did'] = (int)$v['doctor_id']; $f['reason'] = $v['reason']; $f['vlabel'] = pf_when($v['appt_date'], $v['appt_time']) . ' with ' . $v['doctor'];
        if ($k === 'cancel') { $f['step'] = 'confirm'; $save(); return "**Cancel this visit?**\n{$f['pname']} — {$f['vlabel']}\nThe patient will get a text. Reply **yes** to confirm or **no** to stop."; }
        $f['step'] = 'date'; $save(); return 'What new date? (for example **tomorrow**, **monday**, or **2026-11-03**)';
    }
    if ($f['step'] === 'doctor') {
        $d = null;
        if (ctype_digit($t) && isset($f['dcands'][(int)$t - 1])) $d = $f['dcands'][(int)$t - 1];
        else foreach ($f['dcands'] as $c) if (stripos($c['full_name'], $m) !== false) { $d = $c; break; }
        if (!$d) return 'I did not catch that doctor. Reply with a number or a name, or say **stop**.';
        $f['did'] = (int)$d['doctor_id']; $f['dname'] = $d['full_name']; unset($f['dcands']); $f['step'] = 'date'; $save();
        return 'What date? (for example **tomorrow**, **monday**, or **2026-11-03**)';
    }
    if ($f['step'] === 'date') {
        $d = pf_date($m); if (!$d) return "I couldn't use that date. Try **tomorrow**, **monday**, or **2026-11-03** (it must be today or later).";
        $f['date'] = $d; $f['step'] = 'time'; $save(); return 'What time? (for example **9am** or **14:30**)';
    }
    if ($f['step'] === 'time') {
        $tm = pf_time($m); if (!$tm) return 'Use a time like **9am**, **2:30pm** or **14:30**.';
        if (strtotime($f['date'] . ' ' . $tm) <= time()) return 'That time has already passed. Pick a later time.';
        $f['time'] = $tm;
        if ($k === 'book') { $f['step'] = 'reason'; $save(); return 'What is the reason for the visit? (or say **skip**)'; }
        $f['step'] = 'confirm'; $save();
        return "**Reschedule this visit?**\n{$f['pname']} — {$f['vlabel']}\n**New time:** " . pf_when($f['date'], $f['time']) . "\nThe patient will get a text. Reply **yes** to confirm or **no** to stop.";
    }
    if ($f['step'] === 'reason') {
        $f['reason'] = $t === 'skip' ? '' : mb_substr($m, 0, 200); $f['step'] = 'confirm'; $save();
        return "**Book this visit?**\nPatient: {$f['pname']}\nDoctor: {$f['dname']}\nWhen: " . pf_when($f['date'], $f['time']) . ($f['reason'] ? "\nReason: {$f['reason']}" : '') . "\nThe patient will get a text confirmation. Reply **yes** to confirm or **no** to stop.";
    }
    if ($f['step'] === 'confirm') {
        if (preg_match('/^(n|no|nope)$/', $t)) { $end(); return 'Okay, nothing was changed.'; }
        if (!preg_match('/^(y|yes|yep|yeah|confirm|ok|okay|sure|do it)$/', $t)) return 'Please reply **yes** to confirm or **no** to stop.';
        if ($k === 'cancel') { $r = set_status($pdo, $f['vid'], 'Cancelled', ' (via Pulse)'); $end(); return "Done. $r [Open Visits](appointments.php)"; }
        if ($k === 'resched') {
            [$ok, $r] = reschedule_appointment($pdo, $f['vid'], $f['did'], $f['date'], $f['time'], (string)$f['reason'], true, ' (via Pulse)');
            if ($ok) { $end(); return "Done. $r [Open Visits](appointments.php)"; }
            $f['step'] = 'time'; $save(); return "$r Try another time (for example **3pm**), or say **stop**.";
        }
        [$ok, $r] = book_appointment($pdo, $f['pid'], $f['did'], $f['date'], $f['time'], (string)$f['reason'], true, ' (via Pulse)');
        if ($ok) { $end(); return "Done. $r [Open Visits](appointments.php)"; }
        $f['step'] = 'time'; $save(); return "$r Try another time (for example **3pm**), or say **stop**.";
    }
    $end(); return null;
}

try { $flow = pulse_flow($pdo, $msg, $first); } catch (PDOException $e) { unset($_SESSION['pulse']); out('I could not finish that because of a database error. Nothing was changed.'); }
if ($flow !== null) out($flow);

try { $local = ai_local($pdo, $msg, $first); } catch (PDOException $e) { out('I could not read the database right now.'); }
if ($local !== null) out($local);

if (AI_PROVIDER === 'anthropic' && ANTHROPIC_API_KEY !== '') {
    [$ok, $text] = ai_llm($pdo, is_array($in['history'] ?? null) ? $in['history'] : [], $msg);
    if ($ok) out($text, 'ai');
    out("I couldn't reach the AI service ($text). I can still answer questions about visits, patients, doctors and how to use the pages.");
}
out("I can answer questions about **today's visits**, **upcoming visits**, **find patient Maria**, **doctors' schedules**, and how to use each page. Try **how do I book a visit?**" . (AI_PROVIDER === 'local' ? "\nFor open-ended questions, connect an AI provider in config_ai.php." : ''));
