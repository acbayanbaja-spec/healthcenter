<?php
require_once 'db.php';
if (!empty($_SESSION['user'])) log_event($pdo, 'Logout', 'users', null, 'Signed out');
session_destroy(); header('Location: login.php');
