<?php require_once 'db.php'; require_role(['admin', 'staff']); include 'header.php';
$users = $pdo->query('SELECT * FROM users ORDER BY full_name')->fetchAll();
?>
<div class="pagehead"><h2>Team</h2><a class="btn gray" href="profile.php">Edit my profile</a></div>
<p class="note">Everyone with an account on this system.</p>
<div class="team">
<?php foreach ($users as $u): ?>
  <a class="ucard" href="<?= (int)$u['user_id'] === (int)$_SESSION['uid'] ? 'profile.php' : '#' ?>">
    <div class="cover"<?= !empty($u['cover_photo']) ? ' style="background-image:url(\'' . photo_url($u['cover_photo']) . '\')"' : '' ?>></div>
    <?= avatar_html($u) ?>
    <b><?= h($u['full_name']) ?></b>
    <span class="note">@<?= h($u['username']) ?><?= (int)$u['user_id'] === (int)$_SESSION['uid'] ? ' (you)' : '' ?></span>
    <?php if (!empty($u['agreed_at'])): ?><small class="note" style="display:block">Agreement accepted <?= h(date('j M Y', strtotime($u['agreed_at']))) ?></small><?php endif; ?>
  </a>
<?php endforeach; ?>
</div>
<?php include 'footer.php'; ?>
