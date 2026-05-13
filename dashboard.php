<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config-helper.php';
require_login();
header('Location: ' . app_url('profile.php'));
exit;
