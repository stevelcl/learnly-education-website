<?php

require_once __DIR__ . '/db.php';

function course_total_items(int $courseId): int
{
    $resourceTotal = (int) (fetch_one('SELECT COUNT(*) AS total FROM course_resources WHERE course_id = ? AND resource_type <> "quiz"', [$courseId])['total'] ?? 0);
    $quizTotal = (int) (fetch_one('SELECT COUNT(*) AS total FROM quiz_questions WHERE course_id = ?', [$courseId])['total'] ?? 0);
    return $resourceTotal + $quizTotal;
}

function course_completed_items(int $userId, int $courseId): int
{
    return (int) (fetch_one(
        'SELECT COUNT(*) AS total FROM course_item_progress WHERE user_id = ? AND course_id = ?',
        [$userId, $courseId]
    )['total'] ?? 0);
}

function sync_user_course_progress(int $userId, int $courseId, ?bool $saved = null): void
{
    $savedRow = fetch_one('SELECT saved FROM user_progress WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    $savedValue = $saved !== null ? (int) $saved : (int) ($savedRow['saved'] ?? 0);
    $totalItems = course_total_items($courseId);
    $completedItems = course_completed_items($userId, $courseId);
    $progressPercent = $totalItems > 0 ? (int) round(($completedItems / $totalItems) * 100) : 0;

    if ($savedValue === 0 && $completedItems === 0) {
        $deleteStmt = db()->prepare('DELETE FROM user_progress WHERE user_id = ? AND course_id = ?');
        $deleteStmt->execute([$userId, $courseId]);
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO user_progress (user_id, course_id, progress_percent, saved)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent), saved = VALUES(saved), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$userId, $courseId, $progressPercent, $savedValue]);
}

function mark_course_item_complete(int $userId, int $courseId, string $itemType, int $itemId): void
{
    if (!in_array($itemType, ['resource', 'quiz'], true)) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO course_item_progress (user_id, course_id, item_type, item_id)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$userId, $courseId, $itemType, $itemId]);
    sync_user_course_progress($userId, $courseId);
}
