<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$search = trim($_GET['q'] ?? '');
$progressFilter = $_GET['progress'] ?? 'all';
$savedFilter = $_GET['saved'] ?? '';
$sort = $_GET['sort'] ?? 'recent';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

$noticeMap = [
    'reset' => 'Learner progress reset.',
    'completed' => 'Course marked complete for the learner.',
    'removed' => 'Enrollment removed.',
    'archived' => 'Progress record archived.',
    'bulk_removed' => 'Selected enrollments removed.',
    'bulk_archived' => 'Selected records archived.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

function analytics_base_query(array &$params, string $search, string $progressFilter, string $savedFilter): string
{
    $showArchived = $progressFilter === 'archived';
    $where = ['u.deleted_at IS NULL', 'u.account_status <> "deleted"'];

    if ($showArchived) {
        $where[] = '(ce.archived_at IS NOT NULL OR up_archived.id IS NOT NULL)';
    } else {
        $where[] = 'ce.archived_at IS NULL';
    }

    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.title LIKE ? OR c.subject LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($savedFilter === 'saved') {
        $where[] = 'COALESCE(up.saved, up_archived.saved, 0) = 1';
    }

    if (!$showArchived && $progressFilter === 'completed') {
        $where[] = 'COALESCE(up.progress_percent, 0) >= 100';
    } elseif (!$showArchived && $progressFilter === 'in_progress') {
        $where[] = 'COALESCE(up.progress_percent, 0) BETWEEN 1 AND 99';
    } elseif (!$showArchived && $progressFilter === 'not_started') {
        $where[] = 'COALESCE(up.progress_percent, 0) = 0';
    } elseif (!$showArchived && $progressFilter === 'low') {
        $where[] = 'COALESCE(up.progress_percent, 0) BETWEEN 1 AND 19';
    }

    return ' FROM course_enrollments ce
        JOIN users u ON u.id = ce.user_id
        JOIN courses c ON c.id = ce.course_id
        LEFT JOIN user_progress up ON up.user_id = ce.user_id AND up.course_id = ce.course_id AND up.archived_at IS NULL
        LEFT JOIN user_progress up_archived ON up_archived.user_id = ce.user_id AND up_archived.course_id = ce.course_id AND up_archived.archived_at IS NOT NULL
        LEFT JOIN (
            SELECT
                user_id,
                course_id,
                COUNT(*) AS completed_items,
                SUM(CASE WHEN item_type = "quiz" THEN 1 ELSE 0 END) AS completed_quizzes,
                MAX(completed_at) AS last_completed_at
            FROM course_item_progress
            GROUP BY user_id, course_id
        ) cp ON cp.user_id = ce.user_id AND cp.course_id = ce.course_id
        LEFT JOIN (
            SELECT grouped.course_id, COUNT(*) AS total_items
            FROM (
                SELECT course_id, id FROM course_resources WHERE resource_type <> "quiz"
                UNION ALL
                SELECT course_id, id FROM quiz_questions
            ) grouped
            GROUP BY grouped.course_id
        ) ct ON ct.course_id = ce.course_id
        LEFT JOIN course_reviews crv ON crv.user_id = ce.user_id AND crv.course_id = ce.course_id
        WHERE ' . implode(' AND ', $where);
}

function analytics_progress_state(int $progress): string
{
    if ($progress >= 100) {
        return 'completed';
    }
    if ($progress <= 0) {
        return 'not_started';
    }
    if ($progress < 20) {
        return 'low';
    }

    return 'in_progress';
}

function analytics_last_activity(?string $updatedAt, ?string $enrolledAt): string
{
    return (string) ($updatedAt ?: $enrolledAt ?: '');
}

function analytics_risk_state(int $progress, string $lastActivity): string
{
    $inactiveThreshold = new DateTimeImmutable('-14 days');
    $activityDate = $lastActivity !== '' ? new DateTimeImmutable($lastActivity) : null;

    if ($progress > 0 && $progress < 20) {
        return 'at_risk';
    }
    if ($activityDate instanceof DateTimeImmutable && $activityDate < $inactiveThreshold && $progress < 100) {
        return 'inactive';
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $selectedTokens = array_values(array_filter(array_map('trim', (array) ($_POST['selected'] ?? []))));
    $redirectBase = app_url_with_query(app_url('admin/analytics'), [
        'q' => $search,
        'progress' => $progressFilter,
        'saved' => $savedFilter,
        'sort' => $sort,
        'page' => $page,
        'per_page' => $perPage,
    ]);

    $parseSelection = static function (string $token): array {
        [$userId, $courseId] = array_pad(explode(':', $token, 2), 2, '0');
        return [(int) $userId, (int) $courseId];
    };

    $resetProgress = static function (int $userId, int $courseId): void {
        db()->prepare('DELETE FROM course_item_progress WHERE user_id = ? AND course_id = ?')->execute([$userId, $courseId]);
        $saved = (int) (fetch_one('SELECT saved FROM user_progress WHERE user_id = ? AND course_id = ?', [$userId, $courseId])['saved'] ?? 0);
        db()->prepare(
            'INSERT INTO user_progress (user_id, course_id, progress_percent, saved, archived_at)
             VALUES (?, ?, 0, ?, NULL)
             ON DUPLICATE KEY UPDATE progress_percent = 0, saved = VALUES(saved), archived_at = NULL, updated_at = CURRENT_TIMESTAMP'
        )->execute([$userId, $courseId, $saved]);
    };

    $markComplete = static function (int $userId, int $courseId): void {
        $resources = fetch_all('SELECT id FROM course_resources WHERE course_id = ? AND resource_type <> "quiz"', [$courseId]);
        $quizzes = fetch_all('SELECT id FROM quiz_questions WHERE course_id = ?', [$courseId]);
        $stmt = db()->prepare(
            'INSERT INTO course_item_progress (user_id, course_id, item_type, item_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP'
        );
        foreach ($resources as $resource) {
            $stmt->execute([$userId, $courseId, 'resource', (int) $resource['id']]);
        }
        foreach ($quizzes as $quiz) {
            $stmt->execute([$userId, $courseId, 'quiz', (int) $quiz['id']]);
        }
        $saved = (int) (fetch_one('SELECT saved FROM user_progress WHERE user_id = ? AND course_id = ?', [$userId, $courseId])['saved'] ?? 0);
        db()->prepare(
            'INSERT INTO user_progress (user_id, course_id, progress_percent, saved, archived_at)
             VALUES (?, ?, 100, ?, NULL)
             ON DUPLICATE KEY UPDATE progress_percent = 100, saved = VALUES(saved), archived_at = NULL, updated_at = CURRENT_TIMESTAMP'
        )->execute([$userId, $courseId, $saved]);
    };

    $removeEnrollment = static function (int $userId, int $courseId): void {
        db()->prepare('DELETE FROM course_item_progress WHERE user_id = ? AND course_id = ?')->execute([$userId, $courseId]);
        db()->prepare('DELETE FROM user_progress WHERE user_id = ? AND course_id = ?')->execute([$userId, $courseId]);
        db()->prepare('DELETE FROM course_enrollments WHERE user_id = ? AND course_id = ?')->execute([$userId, $courseId]);
    };

    $archiveRecord = static function (int $userId, int $courseId): void {
        db()->prepare('UPDATE course_enrollments SET archived_at = NOW() WHERE user_id = ? AND course_id = ?')->execute([$userId, $courseId]);
        db()->prepare(
            'INSERT INTO user_progress (user_id, course_id, progress_percent, saved, archived_at)
             VALUES (?, ?, 0, 0, NOW())
             ON DUPLICATE KEY UPDATE archived_at = NOW(), updated_at = CURRENT_TIMESTAMP'
        )->execute([$userId, $courseId]);
    };

    if ($action === 'bulk_remove' || $action === 'bulk_archive') {
        foreach ($selectedTokens as $token) {
            [$targetUserId, $targetCourseId] = $parseSelection($token);
            if ($targetUserId <= 0 || $targetCourseId <= 0) {
                continue;
            }
            if ($action === 'bulk_remove') {
                $removeEnrollment($targetUserId, $targetCourseId);
            } else {
                $archiveRecord($targetUserId, $targetCourseId);
            }
        }
        header('Location: ' . $redirectBase . '&notice=' . ($action === 'bulk_remove' ? 'bulk_removed' : 'bulk_archived'));
        exit;
    }

    $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
    $targetCourseId = (int) ($_POST['target_course_id'] ?? 0);
    if ($targetUserId > 0 && $targetCourseId > 0) {
        if ($action === 'reset') {
            $resetProgress($targetUserId, $targetCourseId);
            header('Location: ' . $redirectBase . '&notice=reset');
            exit;
        }
        if ($action === 'mark_complete') {
            $markComplete($targetUserId, $targetCourseId);
            header('Location: ' . $redirectBase . '&notice=completed');
            exit;
        }
        if ($action === 'remove_enrollment') {
            $removeEnrollment($targetUserId, $targetCourseId);
            header('Location: ' . $redirectBase . '&notice=removed');
            exit;
        }
        if ($action === 'archive') {
            $archiveRecord($targetUserId, $targetCourseId);
            header('Location: ' . $redirectBase . '&notice=archived');
            exit;
        }
    }
}

$summaryParams = [];
$summaryBaseSql = analytics_base_query($summaryParams, '', 'all', '');
$summaryRows = fetch_all(
    'SELECT
        ce.user_id,
        ce.course_id,
        c.title,
        COALESCE(up.saved, 0) AS saved,
        COALESCE(up.progress_percent, 0) AS progress_percent,
        ce.enrolled_at,
        up.updated_at
     ' . $summaryBaseSql,
    $summaryParams
);

$summary = [
    'total_enrollments' => count($summaryRows),
    'active_learners' => 0,
    'average_completion' => 0,
    'saved_courses' => 0,
    'incomplete_enrollments' => 0,
    'at_risk' => 0,
    'highest_course' => 'None yet',
    'lowest_course' => 'None yet',
];
$courseProgressBuckets = [];
$progressSum = 0;
$activeLearnerIds = [];

foreach ($summaryRows as $row) {
    $progress = (int) $row['progress_percent'];
    $progressSum += $progress;
    if ($progress > 0) {
        $activeLearnerIds[(int) $row['user_id']] = true;
    }
    if (!empty($row['saved'])) {
        $summary['saved_courses']++;
    }
    if ($progress < 100) {
        $summary['incomplete_enrollments']++;
    }
    $risk = analytics_risk_state($progress, analytics_last_activity($row['updated_at'] ?? null, $row['enrolled_at'] ?? null));
    if ($risk !== '') {
        $summary['at_risk']++;
    }
    $courseProgressBuckets[$row['title']][] = $progress;
}

$summary['active_learners'] = count($activeLearnerIds);

if ($summary['total_enrollments'] > 0) {
    $summary['average_completion'] = (int) round($progressSum / $summary['total_enrollments']);
}
if ($courseProgressBuckets) {
    uasort($courseProgressBuckets, static fn(array $left, array $right): int => (array_sum($right) / max(1, count($right))) <=> (array_sum($left) / max(1, count($left))));
    $keys = array_keys($courseProgressBuckets);
    $summary['highest_course'] = $keys[0] ?? 'None yet';
    $summary['lowest_course'] = $keys[count($keys) - 1] ?? 'None yet';
}

$params = [];
$baseSql = analytics_base_query($params, $search, $progressFilter, $savedFilter);

$sortSql = match ($sort) {
    'highest' => 'ORDER BY COALESCE(up.progress_percent, 0) DESC, analytics_last_activity DESC',
    'lowest' => 'ORDER BY COALESCE(up.progress_percent, 0) ASC, analytics_last_activity DESC',
    'most_enrolled' => 'ORDER BY course_enrollment_count DESC, analytics_last_activity DESC',
    default => 'ORDER BY analytics_last_activity DESC',
};

$countRow = fetch_one(
    'SELECT COUNT(DISTINCT CONCAT(ce.user_id, ":", ce.course_id)) AS total
     ' . $baseSql,
    $params
);
$totalRows = (int) ($countRow['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = fetch_all(
    'SELECT DISTINCT
        ce.user_id,
        ce.course_id,
        u.name,
        u.email,
        c.title,
        c.subject,
        c.level,
        ce.enrolled_at,
        ce.archived_at AS enrollment_archived_at,
        COALESCE(up.saved, up_archived.saved, 0) AS saved,
        COALESCE(up.progress_percent, up_archived.progress_percent, 0) AS progress_percent,
        COALESCE(up.updated_at, up_archived.updated_at) AS updated_at,
        COALESCE(up.archived_at, up_archived.archived_at) AS archived_at,
        COALESCE(cp.completed_items, 0) AS completed_items,
        COALESCE(cp.completed_quizzes, 0) AS completed_quizzes,
        COALESCE(ct.total_items, 0) AS total_items,
        (
            SELECT COUNT(*)
            FROM quiz_questions qq
            WHERE qq.course_id = ce.course_id
        ) AS total_quizzes,
        crv.rating AS submitted_rating,
        crv.comment AS submitted_comment,
        crv.updated_at AS feedback_updated_at,
        (
            SELECT COUNT(*)
            FROM course_enrollments ce2
            WHERE ce2.course_id = ce.course_id AND ce2.archived_at IS NULL
        ) AS course_enrollment_count,
        COALESCE(up.updated_at, ce.enrolled_at) AS analytics_last_activity
     ' . $baseSql . '
     ' . $sortSql . '
     LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
    $params
);

if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'], true)) {
    $filename = 'learnly-progress-' . date('Ymd-His');
    if ($_GET['export'] === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo "Student\tEmail\tCourse\tProgress\tCompleted Steps\tSaved\tLast Activity\n";
        foreach ($rows as $row) {
            echo implode("\t", [
                $row['name'],
                $row['email'],
                $row['title'],
                $row['progress_percent'] . '%',
                $row['completed_items'] . '/' . $row['total_items'],
                !empty($row['saved']) ? 'Yes' : 'No',
                analytics_last_activity($row['updated_at'] ?? null, $row['enrolled_at'] ?? null),
            ]) . "\n";
        }
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $handle = fopen('php://output', 'w');
    fputcsv($handle, ['Student', 'Email', 'Course', 'Progress', 'Completed Steps', 'Saved', 'Last Activity']);
    foreach ($rows as $row) {
        fputcsv($handle, [
            $row['name'],
            $row['email'],
            $row['title'],
            $row['progress_percent'] . '%',
            $row['completed_items'] . '/' . $row['total_items'],
            !empty($row['saved']) ? 'Yes' : 'No',
            analytics_last_activity($row['updated_at'] ?? null, $row['enrolled_at'] ?? null),
        ]);
    }
    fclose($handle);
    exit;
}

admin_render_start([
    'title' => 'Progress Analytics',
    'page_title' => 'Progress Analytics',
    'page_subtitle' => 'Monitor learner health, intervene early, and manage enrollments directly from the analytics view.',
    'active_nav' => 'analytics',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Progress Analytics'],
    ],
    'notice' => $message,
    'user' => $user,
]);
?>

<section class="admin-stats-row">
    <article class="panel admin-stat-card"><strong><?= $summary['total_enrollments'] ?></strong><span class="muted">Total Enrollments</span></article>
    <article class="panel admin-stat-card"><strong><?= $summary['active_learners'] ?></strong><span class="muted">Active Learners</span></article>
    <article class="panel admin-stat-card"><strong><?= $summary['average_completion'] ?>%</strong><span class="muted">Average Completion</span></article>
    <article class="panel admin-stat-card"><strong><?= $summary['saved_courses'] ?></strong><span class="muted">Saved Courses</span></article>
    <article class="panel admin-stat-card"><strong><?= $summary['at_risk'] ?></strong><span class="muted">Students at Risk</span></article>
</section>

<section class="admin-content-grid">
    <article class="panel">
        <div class="admin-mini-list">
            <div class="admin-mini-row"><strong>Highest completion</strong><span class="muted"><?= htmlspecialchars($summary['highest_course']) ?></span></div>
            <div class="admin-mini-row"><strong>Lowest completion</strong><span class="muted"><?= htmlspecialchars($summary['lowest_course']) ?></span></div>
            <div class="admin-mini-row"><strong>Incomplete enrollments</strong><span class="muted"><?= $summary['incomplete_enrollments'] ?></span></div>
        </div>
    </article>
    <article class="panel">
        <div class="actions">
            <a class="button ghost small" href="<?= htmlspecialchars(app_url_with_query(app_url('admin/analytics'), ['q' => $search, 'progress' => $progressFilter, 'saved' => $savedFilter, 'sort' => $sort, 'per_page' => $perPage, 'export' => 'csv'])) ?>">Export CSV</a>
            <a class="button ghost small" href="<?= htmlspecialchars(app_url_with_query(app_url('admin/analytics'), ['q' => $search, 'progress' => $progressFilter, 'saved' => $savedFilter, 'sort' => $sort, 'per_page' => $perPage, 'export' => 'excel'])) ?>">Export Excel</a>
            <button type="button" class="button ghost small" onclick="window.print()">Print Report</button>
        </div>
    </article>
</section>

<section class="panel">
    <form class="admin-filter-bar analytics-filter-bar" method="get">
        <label>Search
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student, email, or course">
        </label>
        <label>Progress
            <select name="progress">
                <option value="all" <?= $progressFilter === 'all' ? 'selected' : '' ?>>All progress</option>
                <option value="completed" <?= $progressFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="in_progress" <?= $progressFilter === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                <option value="not_started" <?= $progressFilter === 'not_started' ? 'selected' : '' ?>>Not started</option>
                <option value="low" <?= $progressFilter === 'low' ? 'selected' : '' ?>>Low progress</option>
                <option value="archived" <?= $progressFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </label>
        <label>Saved
            <select name="saved">
                <option value="" <?= $savedFilter === '' ? 'selected' : '' ?>>All</option>
                <option value="saved" <?= $savedFilter === 'saved' ? 'selected' : '' ?>>Saved only</option>
            </select>
        </label>
        <label>Sort
            <select name="sort">
                <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recently updated</option>
                <option value="highest" <?= $sort === 'highest' ? 'selected' : '' ?>>Highest completion</option>
                <option value="lowest" <?= $sort === 'lowest' ? 'selected' : '' ?>>Lowest completion</option>
                <option value="most_enrolled" <?= $sort === 'most_enrolled' ? 'selected' : '' ?>>Most enrolled</option>
            </select>
        </label>
        <label>Per page
            <select name="per_page">
                <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
            </select>
        </label>
        <div class="form-actions">
            <button type="submit">Apply</button>
            <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/analytics')) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="panel admin-data-table analytics-table-shell">
    <?php if (!$rows): ?>
        <div class="admin-empty-state"><strong>No progress records found.</strong><span>Try adjusting the filters or wait for new learner activity.</span></div>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="admin-bulk-bar">
                <div class="actions">
                    <button type="submit" class="button ghost small" name="action" value="bulk_archive" data-confirm="Archive the selected progress records?">Archive Selected</button>
                    <button type="submit" class="button danger small" name="action" value="bulk_remove" data-confirm="Remove the selected enrollments? This clears progress and access.">Remove Selected</button>
                </div>
            </div>
            <table class="admin-compact-table admin-analytics-table">
                <colgroup>
                    <col class="col-select">
                    <col class="col-student">
                    <col class="col-course">
                    <col class="col-progress">
                    <col class="col-steps">
                    <col class="col-status">
                    <col class="col-saved">
                    <col class="col-activity">
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th><input type="checkbox" data-select-all></th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Progress</th>
                        <th>Completed Steps</th>
                        <th>Enrollment Status</th>
                        <th>Saved</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $progressPercent = (int) $row['progress_percent'];
                        $progressState = analytics_progress_state($progressPercent);
                        $lastActivity = analytics_last_activity($row['updated_at'] ?? null, $row['enrolled_at'] ?? null);
                        $riskState = analytics_risk_state($progressPercent, $lastActivity);
                        $selectionValue = (int) $row['user_id'] . ':' . (int) $row['course_id'];
                        $detailPayload = [
                            'name' => (string) $row['name'],
                            'email' => (string) $row['email'],
                            'title' => (string) $row['title'],
                            'subject' => (string) $row['subject'],
                            'progress' => $progressPercent . '%',
                            'completed_steps' => (int) $row['completed_items'] . ' / ' . (int) $row['total_items'],
                            'quiz_checks' => (int) $row['completed_quizzes'] . ' / ' . (int) $row['total_quizzes'],
                            'status' => ucwords(str_replace('_', ' ', $progressState)),
                            'saved' => !empty($row['saved']) ? 'Saved' : 'No',
                            'last_activity' => $lastActivity,
                            'enrolled_at' => (string) $row['enrolled_at'],
                            'rating' => $row['submitted_rating'] ? (int) $row['submitted_rating'] . '/5' : 'No rating yet',
                            'comment' => (string) ($row['submitted_comment'] ?: 'No written feedback submitted.'),
                            'risk' => $riskState === 'at_risk' ? 'At Risk' : ($riskState === 'inactive' ? 'Inactive' : 'Healthy'),
                            'course_url' => course_url((int) $row['course_id'], true),
                            'user_id' => (int) $row['user_id'],
                            'course_id' => (int) $row['course_id'],
                        ];
                        ?>
                        <tr class="analytics-main-row">
                            <td data-label="Select"><input type="checkbox" name="selected[]" value="<?= htmlspecialchars($selectionValue) ?>"></td>
                            <td data-label="Student">
                                <strong><?= htmlspecialchars($row['name']) ?></strong><br>
                                <span class="muted"><?= htmlspecialchars($row['email']) ?></span>
                                <?php if ($riskState !== ''): ?>
                                    <div><span class="status-pill status-<?= $riskState === 'at_risk' ? 'cancelled' : 'pending' ?>"><?= $riskState === 'at_risk' ? 'At Risk' : 'Inactive' ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Course">
                                <div class="analytics-course-cell">
                                    <strong><?= htmlspecialchars($row['title']) ?></strong>
                                    <span class="muted"><?= htmlspecialchars($row['subject']) ?></span>
                                </div>
                            </td>
                            <td data-label="Progress">
                                <div class="admin-progress-cell">
                                    <strong><?= $progressPercent ?>%</strong>
                                    <div class="progress slim"><span class="progress-<?= htmlspecialchars($progressState) ?>" style="width: <?= $progressPercent ?>%"></span></div>
                                </div>
                            </td>
                            <td data-label="Completed Steps"><?= (int) $row['completed_items'] ?> / <?= (int) $row['total_items'] ?></td>
                            <td data-label="Enrollment Status"><span class="status-pill status-<?= htmlspecialchars($progressState) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $progressState))) ?></span></td>
                            <td data-label="Saved"><?= !empty($row['saved']) ? 'Saved' : 'No' ?></td>
                            <td data-label="Last Activity"><?= htmlspecialchars($lastActivity) ?></td>
                            <td data-label="Actions">
                                <button
                                    type="button"
                                    class="button ghost small analytics-detail-toggle"
                                    data-analytics-open='<?= htmlspecialchars(json_encode($detailPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>'
                                >View Details</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</section>

<div class="analytics-detail-modal" data-analytics-modal hidden>
    <div class="analytics-detail-dialog">
        <div class="analytics-detail-dialog-head">
            <div>
                <p class="eyebrow">Learner Progress</p>
                <h2 data-analytics-modal-title>Student</h2>
                <p class="muted" data-analytics-modal-email>Email</p>
            </div>
            <button type="button" class="button ghost small" data-analytics-close>Close</button>
        </div>
        <div class="analytics-detail-grid">
            <section class="analytics-detail-section">
                <h3>Student Information</h3>
                <p><strong data-analytics-modal-name>Student</strong></p>
                <p class="muted" data-analytics-modal-email-copy>Email</p>
                <p><strong>Saved status:</strong> <span data-analytics-modal-saved>No</span></p>
            </section>
            <section class="analytics-detail-section">
                <h3>Enrollment Details</h3>
                <p><strong>Course:</strong> <span data-analytics-modal-course>Course</span></p>
                <p><strong>Subject:</strong> <span data-analytics-modal-subject>Subject</span></p>
                <p><strong>Enrolled:</strong> <span data-analytics-modal-enrolled>--</span></p>
            </section>
            <section class="analytics-detail-section">
                <h3>Progress Snapshot</h3>
                <p><strong>Progress:</strong> <span data-analytics-modal-progress>0%</span></p>
                <p><strong>Completed steps:</strong> <span data-analytics-modal-steps>0 / 0</span></p>
                <p><strong>Status:</strong> <span data-analytics-modal-status>Not started</span></p>
            </section>
            <section class="analytics-detail-section">
                <h3>Quiz Attempts</h3>
                <p><strong>Quiz checks:</strong> <span data-analytics-modal-quizzes>0 / 0</span></p>
                <p><strong>Last activity:</strong> <span data-analytics-modal-activity>--</span></p>
                <p><strong>Learner health:</strong> <span data-analytics-modal-risk>Healthy</span></p>
            </section>
            <section class="analytics-detail-section">
                <h3>Course Rating</h3>
                <p><strong>Rating:</strong> <span data-analytics-modal-rating>No rating yet</span></p>
                <p class="muted" data-analytics-modal-comment>No written feedback submitted.</p>
            </section>
            <section class="analytics-detail-section analytics-detail-actions-card">
                <h3>Admin Actions</h3>
                <div class="admin-table-actions analytics-detail-actions">
                    <a class="button ghost small" href="#" data-analytics-modal-course-link>View Course</a>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="target_user_id" value="0" data-analytics-target-user>
                        <input type="hidden" name="target_course_id" value="0" data-analytics-target-course>
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="button ghost small" data-confirm="Reset this learner's progress for the course?">Reset Progress</button>
                    </form>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="target_user_id" value="0" data-analytics-target-user>
                        <input type="hidden" name="target_course_id" value="0" data-analytics-target-course>
                        <input type="hidden" name="action" value="mark_complete">
                        <button type="submit" class="button ghost small" data-confirm="Mark this course complete for the learner?">Mark Complete</button>
                    </form>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="target_user_id" value="0" data-analytics-target-user>
                        <input type="hidden" name="target_course_id" value="0" data-analytics-target-course>
                        <input type="hidden" name="action" value="archive">
                        <button type="submit" class="button ghost small" data-confirm="Archive this progress record?">Archive Record</button>
                    </form>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="target_user_id" value="0" data-analytics-target-user>
                        <input type="hidden" name="target_course_id" value="0" data-analytics-target-course>
                        <input type="hidden" name="action" value="remove_enrollment">
                        <button type="submit" class="button danger small" data-confirm="Remove this learner from the course? Their saved progress and access will be cleared.">Remove Enrollment</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <?php $baseParams = ['q' => $search, 'progress' => $progressFilter, 'saved' => $savedFilter, 'sort' => $sort, 'per_page' => $perPage]; ?>
    <div class="pagination-bar">
        <a class="button ghost small <?= $page <= 1 ? 'disabled-link' : '' ?>" href="<?= $page <= 1 ? '#' : htmlspecialchars(app_url('admin/analytics') . '?' . http_build_query(array_merge($baseParams, ['page' => $page - 1]))) ?>">Previous</a>
        <div class="pagination-pages">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="pagination-page <?= $i === $page ? 'active' : '' ?>" href="<?= htmlspecialchars(app_url('admin/analytics') . '?' . http_build_query(array_merge($baseParams, ['page' => $i]))) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <a class="button ghost small <?= $page >= $totalPages ? 'disabled-link' : '' ?>" href="<?= $page >= $totalPages ? '#' : htmlspecialchars(app_url('admin/analytics') . '?' . http_build_query(array_merge($baseParams, ['page' => $page + 1]))) ?>">Next</a>
    </div>
<?php endif; ?>

<?php admin_render_end(); ?>
