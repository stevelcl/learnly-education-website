<?php
require_once __DIR__ . '/includes/config-helper.php';

$editingId = (int) ($_GET['edit'] ?? 0);
if ($editingId > 0) {
    header('Location: ' . app_url('admin/course/' . $editingId));
    exit;
}

header('Location: ' . app_url('admin/courses'));
exit;
