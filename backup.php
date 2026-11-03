<?php require_once 'db.php'; require_role(['admin']);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    set_time_limit(300);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    log_event($pdo, 'Exported', 'database', null, 'Database backup downloaded');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="healthcenter_backup_' . date('Y-m-d_His') . '.sql"');
    echo "-- Health Center backup, " . date('c') . "\nCREATE DATABASE IF NOT EXISTS healthcenter_db CHARACTER SET utf8mb4;\nUSE healthcenter_db;\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $t) {
        $c = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `$t`;\n" . $c[1] . ";\n\n";
        $st = $pdo->query("SELECT * FROM `$t`");
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            echo "INSERT INTO `$t` VALUES (" . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r)) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}
$last = null;
try { $last = $pdo->query("SELECT logged_at, user_name FROM history_log WHERE table_name='database' ORDER BY log_id DESC LIMIT 1")->fetch(); } catch (PDOException $e) {}
include 'header.php';
?>
<h2>Backup</h2>
<p class="note">Download a copy of the whole database: patients, doctors, appointments, accounts, history and messages.</p>
<div class="card">
  <p><?= $last ? 'Last backup: <b>' . h($last['logged_at']) . '</b> by ' . h($last['user_name']) . '.' : 'No backup has been downloaded yet.' ?></p>
  <form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button>Download backup now</button></form>
</div>
<div class="card"><h3>Good to know</h3>
<ul>
<li><b>Keep it safe.</b> The file contains patient information and password hashes. Store it somewhere private, not in a shared folder.</li>
<li><b>Restore:</b> open phpMyAdmin, go to Import, choose the .sql file and run it. It replaces the current data with the backup.</li>
<li><b>Photos:</b> profile and cover photos are not in the file. Copy the <code>uploads</code> folder too.</li>
<li><b>How often:</b> download one at the end of each day, or before any big change.</li>
</ul></div>
<?php include 'footer.php'; ?>
