<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/progress.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user = require_login();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$itemType = (string) ($_POST['item_type'] ?? '');
$itemId = (int) ($_POST['item_id'] ?? 0);

if ($courseId <= 0 || $itemId <= 0 || $itemType !== 'resource') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid progress request.']);
    exit;
}

$isEnrolled = is_course_enrolled((int) $user['id'], $courseId);
if (!$isEnrolled) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Enrollment required.']);
    exit;
}

$resource = fetch_one(
    'SELECT id FROM course_resources WHERE id = ? AND course_id = ? AND resource_type <> "quiz"',
    [$itemId, $courseId]
);

if (!$resource) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Lesson not found.']);
    exit;
}

mark_course_item_complete((int) $user['id'], $courseId, 'resource', $itemId);

$totalItems = course_total_items($courseId);
$completedItems = course_completed_items((int) $user['id'], $courseId);
$progressPercent = $totalItems > 0 ? (int) round(($completedItems / $totalItems) * 100) : 0;

echo json_encode([
    'ok' => true,
    'completed_items' => $completedItems,
    'total_items' => $totalItems,
    'progress_percent' => $progressPercent,
]);
