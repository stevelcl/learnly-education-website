<?php
session_start();
require_once __DIR__ . '/includes/cart.php';
clear_cart();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
