<?php
require_once __DIR__ . '/includes/config-helper.php';

header('Location: ' . app_url_with_query(app_url('admin/courses'), ['notice' => 'resources_in_courses']));
exit;
