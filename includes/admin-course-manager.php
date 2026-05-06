<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config-helper.php';
require_once __DIR__ . '/courses.php';
require_once __DIR__ . '/media.php';

function admin_home_url(): string
{
    return app_url('admin');
}

function admin_courses_url(): string
{
    return app_url('admin/courses');
}

function admin_course_url(?int $courseId = null, string $tab = 'overview', bool $new = false): string
{
    if ($new || !$courseId) {
        return app_url('admin/course/new');
    }

    $tab = in_array($tab, ['overview', 'resources', 'students', 'feedback'], true) ? $tab : 'overview';
    if ($tab === 'overview') {
        return app_url('admin/course/' . $courseId);
    }

    return app_url('admin/course/' . $courseId . '/' . $tab);
}

function admin_resource_edit_url(int $resourceId, string $kind = 'resource'): string
{
    return app_url_with_query(app_url('admin/resource/' . $resourceId . '/edit'), ['kind' => $kind]);
}

function admin_course_preview_url(int $courseId): string
{
    return course_url($courseId, true);
}

function admin_course_form_defaults(?array $course = null): array
{
    return [
        'title' => $course['title'] ?? '',
        'subject' => $course['subject'] ?? '',
        'level' => $course['level'] ?? '',
        'description' => $course['description'] ?? '',
        'thumbnail_path' => $course['thumbnail_path'] ?? '',
        'banner_path' => $course['banner_path'] ?? '',
    ];
}

function admin_resource_form_defaults(?array $resource = null, string $kind = 'resource'): array
{
    if ($kind === 'quiz') {
        return [
            'title' => $resource['title'] ?? '',
            'question' => $resource['question'] ?? '',
            'option_a' => $resource['option_a'] ?? '',
            'option_b' => $resource['option_b'] ?? '',
            'option_c' => $resource['option_c'] ?? '',
            'correct_option' => $resource['correct_option'] ?? '',
            'explanation' => $resource['explanation'] ?? '',
            'sort_order' => (string) ($resource['sort_order'] ?? 0),
        ];
    }

    return [
        'title' => $resource['title'] ?? '',
        'resource_type' => $resource['resource_type'] ?? 'note',
        'content' => $resource['content'] ?? '',
        'resource_url' => $resource['resource_url'] ?? '',
        'attachment_path' => $resource['attachment_path'] ?? '',
        'thumbnail_path' => $resource['thumbnail_path'] ?? '',
        'sort_order' => (string) ($resource['sort_order'] ?? 0),
    ];
}

function admin_store_uploaded_asset(string $field, string $folder, array $allowedMimeTypes): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => ''];
    }

    if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Upload failed. Please try again.'];
    }

    $mimeType = mime_content_type($upload['tmp_name']) ?: '';
    if (!isset($allowedMimeTypes[$mimeType])) {
        return ['path' => null, 'error' => 'Unsupported file type.'];
    }

    $uploadDirectory = dirname(__DIR__) . '/assets/uploads/' . trim($folder, '/');
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        return ['path' => null, 'error' => 'Upload folder could not be prepared.'];
    }

    $filename = trim($folder, '/') . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDirectory . '/' . $filename;

    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
        return ['path' => null, 'error' => 'File could not be saved on the server.'];
    }

    return ['path' => 'assets/uploads/' . trim($folder, '/') . '/' . $filename, 'error' => ''];
}

function admin_image_upload_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
}

function admin_document_upload_types(): array
{
    return [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt',
    ];
}

function admin_fetch_course_catalog(): array
{
    return fetch_all(
        'SELECT
            c.*,
            COUNT(DISTINCT CASE WHEN cr.resource_type <> "quiz" THEN cr.id END) AS resource_count,
            COUNT(DISTINCT qq.id) AS quiz_count,
            COUNT(DISTINCT ce.id) AS enrollment_count,
            COALESCE(AVG(rv.rating), 0) AS average_rating,
            COUNT(DISTINCT rv.id) AS review_count
         FROM courses c
         LEFT JOIN course_resources cr ON cr.course_id = c.id
         LEFT JOIN quiz_questions qq ON qq.course_id = c.id
         LEFT JOIN course_enrollments ce ON ce.course_id = c.id
         LEFT JOIN course_reviews rv ON rv.course_id = c.id
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    );
}

function admin_fetch_course(int $courseId): ?array
{
    return fetch_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
}

function admin_fetch_course_metrics(int $courseId): array
{
    return fetch_one(
        'SELECT
            COUNT(DISTINCT CASE WHEN cr.resource_type <> "quiz" THEN cr.id END) AS resource_count,
            COUNT(DISTINCT qq.id) AS quiz_count,
            COUNT(DISTINCT ce.id) AS enrollment_count,
            COALESCE(AVG(rv.rating), 0) AS average_rating,
            COUNT(DISTINCT rv.id) AS review_count
         FROM courses c
         LEFT JOIN course_resources cr ON cr.course_id = c.id
         LEFT JOIN quiz_questions qq ON qq.course_id = c.id
         LEFT JOIN course_enrollments ce ON ce.course_id = c.id
         LEFT JOIN course_reviews rv ON rv.course_id = c.id
         WHERE c.id = ?
         GROUP BY c.id',
        [$courseId]
    ) ?? [
        'resource_count' => 0,
        'quiz_count' => 0,
        'enrollment_count' => 0,
        'average_rating' => 0,
        'review_count' => 0,
    ];
}

function admin_next_course_item_order(int $courseId): int
{
    $row = fetch_one(
        'SELECT MAX(sort_value) AS max_sort
         FROM (
             SELECT sort_order AS sort_value FROM course_resources WHERE course_id = ? AND resource_type <> "quiz"
             UNION ALL
             SELECT sort_order AS sort_value FROM quiz_questions WHERE course_id = ?
         ) ordered_items',
        [$courseId, $courseId]
    );

    return max(10, (((int) ($row['max_sort'] ?? 0)) + 10));
}

function admin_fetch_course_items(int $courseId): array
{
    $resources = fetch_all(
        'SELECT id, course_id, title, resource_type, content, resource_url, attachment_path, thumbnail_path, sort_order
         FROM course_resources
         WHERE course_id = ? AND resource_type <> "quiz"
         ORDER BY sort_order, id',
        [$courseId]
    );
    $quizzes = fetch_all(
        'SELECT id, course_id, title, question, option_a, option_b, option_c, correct_option, explanation, sort_order
         FROM quiz_questions
         WHERE course_id = ?
         ORDER BY sort_order, id',
        [$courseId]
    );

    $items = [];
    foreach ($resources as $resource) {
        $items[] = [
            'kind' => 'resource',
            'id' => (int) $resource['id'],
            'course_id' => (int) $resource['course_id'],
            'resource_type' => (string) $resource['resource_type'],
            'title' => (string) $resource['title'],
            'description' => (string) $resource['content'],
            'sort_order' => max(10, (int) $resource['sort_order']),
            'meta' => $resource['resource_type'] === 'video'
                ? 'Video lesson'
                : (($resource['attachment_path'] ?? '') !== '' ? 'Note with attachment' : 'Note lesson'),
        ];
    }

    foreach ($quizzes as $index => $quiz) {
        $items[] = [
            'kind' => 'quiz',
            'id' => (int) $quiz['id'],
            'course_id' => (int) $quiz['course_id'],
            'resource_type' => 'quiz',
            'title' => trim((string) ($quiz['title'] ?? '')) !== ''
                ? (string) $quiz['title']
                : 'Quiz: ' . mb_strimwidth((string) $quiz['question'], 0, 68, '...'),
            'description' => (string) $quiz['question'],
            'sort_order' => (int) ($quiz['sort_order'] ?: (1000 + $index)),
            'meta' => 'Knowledge check',
        ];
    }

    usort($items, static function (array $left, array $right): int {
        if ($left['sort_order'] === $right['sort_order']) {
            return $left['id'] <=> $right['id'];
        }

        return $left['sort_order'] <=> $right['sort_order'];
    });

    return $items;
}

function admin_fetch_course_students(int $courseId): array
{
    return fetch_all(
        'SELECT
            u.id,
            u.name,
            u.email,
            ce.enrolled_at,
            COALESCE(up.progress_percent, 0) AS progress_percent,
            COALESCE(up.saved, 0) AS saved
         FROM course_enrollments ce
         JOIN users u ON u.id = ce.user_id
         LEFT JOIN user_progress up ON up.user_id = ce.user_id AND up.course_id = ce.course_id
         WHERE ce.course_id = ?
         ORDER BY ce.enrolled_at DESC',
        [$courseId]
    );
}

function admin_fetch_course_feedback(int $courseId): array
{
    return fetch_all(
        'SELECT rv.rating, rv.comment, rv.updated_at, u.name, u.email
         FROM course_reviews rv
         JOIN users u ON u.id = rv.user_id
         WHERE rv.course_id = ?
         ORDER BY rv.updated_at DESC',
        [$courseId]
    );
}

function admin_save_course(array $data, array &$formValues, ?int $courseId = null): array
{
    $title = trim($data['title'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $level = trim($data['level'] ?? '');
    $description = trim($data['description'] ?? '');
    $thumbnailPath = trim($data['existing_thumbnail_path'] ?? '');
    $bannerPath = trim($data['existing_banner_path'] ?? '');

    $thumbUpload = admin_store_uploaded_asset('thumbnail_image', 'courses', admin_image_upload_types());
    if ($thumbUpload['error'] !== '') {
        return ['ok' => false, 'error' => 'Thumbnail: ' . $thumbUpload['error']];
    }
    if ($thumbUpload['path']) {
        $thumbnailPath = $thumbUpload['path'];
    }

    $bannerUpload = admin_store_uploaded_asset('banner_image', 'course-banners', admin_image_upload_types());
    if ($bannerUpload['error'] !== '') {
        return ['ok' => false, 'error' => 'Banner: ' . $bannerUpload['error']];
    }
    if ($bannerUpload['path']) {
        $bannerPath = $bannerUpload['path'];
    }

    $formValues = [
        'title' => $title,
        'subject' => $subject,
        'level' => $level,
        'description' => $description,
        'thumbnail_path' => $thumbnailPath,
        'banner_path' => $bannerPath,
    ];

    if ($title === '' || $subject === '' || $level === '' || $description === '') {
        return ['ok' => false, 'error' => 'Please complete all course fields.'];
    }

    if ($courseId) {
        $stmt = db()->prepare(
            'UPDATE courses
             SET title = ?, subject = ?, level = ?, description = ?, thumbnail_path = ?, banner_path = ?
             WHERE id = ?'
        );
        $stmt->execute([$title, $subject, $level, $description, $thumbnailPath, $bannerPath, $courseId]);
        return ['ok' => true, 'id' => $courseId];
    }

    $stmt = db()->prepare(
        'INSERT INTO courses (title, subject, level, description, thumbnail_path, banner_path)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $subject, $level, $description, $thumbnailPath, $bannerPath]);

    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

function admin_create_course_item(int $courseId, array $post): array
{
    $type = $post['resource_type'] ?? 'note';
    $sortOrder = admin_next_course_item_order($courseId);

    if ($type === 'note') {
        $title = trim($post['note_title'] ?? '');
        $content = trim($post['note_content'] ?? '');
        $attachmentPath = '';
        $upload = admin_store_uploaded_asset('note_attachment', 'course-notes', admin_document_upload_types());
        if ($upload['error'] !== '') {
            return ['ok' => false, 'error' => 'Attachment: ' . $upload['error']];
        }
        if ($upload['path']) {
            $attachmentPath = $upload['path'];
        }

        if ($title === '' || $content === '') {
            return ['ok' => false, 'error' => 'Notes need a title and content.'];
        }

        $stmt = db()->prepare(
            'INSERT INTO course_resources (course_id, title, resource_type, content, attachment_path, sort_order)
             VALUES (?, ?, "note", ?, ?, ?)'
        );
        $stmt->execute([$courseId, $title, $content, $attachmentPath, $sortOrder]);
        return ['ok' => true];
    }

    if ($type === 'video') {
        $title = trim($post['video_title'] ?? '');
        $description = trim($post['video_description'] ?? '');
        $videoUrl = video_embed_src(trim($post['video_url'] ?? ''));
        $thumbnailPath = trim($post['existing_video_thumbnail_path'] ?? '');
        $upload = admin_store_uploaded_asset('video_thumbnail', 'course-videos', admin_image_upload_types());
        if ($upload['error'] !== '') {
            return ['ok' => false, 'error' => 'Thumbnail: ' . $upload['error']];
        }
        if ($upload['path']) {
            $thumbnailPath = $upload['path'];
        }

        if ($title === '' || $videoUrl === '') {
            return ['ok' => false, 'error' => 'Videos need a title and a valid URL.'];
        }

        $stmt = db()->prepare(
            'INSERT INTO course_resources (course_id, title, resource_type, content, resource_url, thumbnail_path, sort_order)
             VALUES (?, ?, "video", ?, ?, ?, ?)'
        );
        $stmt->execute([$courseId, $title, $description !== '' ? $description : 'Video lesson resource.', $videoUrl, $thumbnailPath, $sortOrder]);
        return ['ok' => true];
    }

    $title = trim($post['quiz_title'] ?? '');
    $question = trim($post['quiz_question'] ?? '');
    $optionA = trim($post['option_a'] ?? '');
    $optionB = trim($post['option_b'] ?? '');
    $optionC = trim($post['option_c'] ?? '');
    $correct = trim($post['correct_option'] ?? '');
    $explanation = trim($post['quiz_explanation'] ?? '');

    if ($question === '' || $optionA === '' || $optionB === '' || $optionC === '' || !in_array($correct, ['A', 'B', 'C'], true)) {
        return ['ok' => false, 'error' => 'Quiz questions need a prompt, three options, and a correct answer.'];
    }

    $stmt = db()->prepare(
        'INSERT INTO quiz_questions (course_id, title, question, option_a, option_b, option_c, correct_option, explanation, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$courseId, $title, $question, $optionA, $optionB, $optionC, $correct, $explanation, $sortOrder]);
    return ['ok' => true];
}

function admin_delete_course_item(string $kind, int $itemId): void
{
    if ($kind === 'quiz') {
        $stmt = db()->prepare('DELETE FROM quiz_questions WHERE id = ?');
        $stmt->execute([$itemId]);
        return;
    }

    $stmt = db()->prepare('DELETE FROM course_resources WHERE id = ?');
    $stmt->execute([$itemId]);
}

function admin_update_course_item_order(int $courseId, array $orderedTokens): void
{
    $nextOrder = 10;
    foreach ($orderedTokens as $token) {
        [$kind, $id] = array_pad(explode(':', (string) $token, 2), 2, null);
        $itemId = (int) $id;
        if ($itemId <= 0) {
            continue;
        }

        if ($kind === 'quiz') {
            $stmt = db()->prepare('UPDATE quiz_questions SET sort_order = ? WHERE id = ? AND course_id = ?');
            $stmt->execute([$nextOrder, $itemId, $courseId]);
        } elseif ($kind === 'resource') {
            $stmt = db()->prepare('UPDATE course_resources SET sort_order = ? WHERE id = ? AND course_id = ?');
            $stmt->execute([$nextOrder, $itemId, $courseId]);
        }

        $nextOrder += 10;
    }
}

function admin_fetch_editable_item(int $itemId, string $kind): ?array
{
    if ($kind === 'quiz') {
        return fetch_one('SELECT * FROM quiz_questions WHERE id = ?', [$itemId]);
    }

    return fetch_one('SELECT * FROM course_resources WHERE id = ?', [$itemId]);
}

function admin_update_resource_item(int $itemId, array $post, array &$formValues): array
{
    $resource = fetch_one('SELECT * FROM course_resources WHERE id = ?', [$itemId]);
    if (!$resource) {
        return ['ok' => false, 'error' => 'Resource not found.'];
    }

    $title = trim($post['title'] ?? '');
    $content = trim($post['content'] ?? '');
    $sortOrder = max(1, (int) ($post['sort_order'] ?? 1));
    $resourceUrl = trim($post['resource_url'] ?? '');
    $attachmentPath = trim($post['existing_attachment_path'] ?? '');
    $thumbnailPath = trim($post['existing_thumbnail_path'] ?? '');

    if ($resource['resource_type'] === 'note') {
        $attachmentUpload = admin_store_uploaded_asset('attachment_file', 'course-notes', admin_document_upload_types());
        if ($attachmentUpload['error'] !== '') {
            return ['ok' => false, 'error' => 'Attachment: ' . $attachmentUpload['error']];
        }
        if ($attachmentUpload['path']) {
            $attachmentPath = $attachmentUpload['path'];
        }

        $formValues = [
            'title' => $title,
            'resource_type' => 'note',
            'content' => $content,
            'resource_url' => '',
            'attachment_path' => $attachmentPath,
            'thumbnail_path' => '',
            'sort_order' => (string) $sortOrder,
        ];

        if ($title === '' || $content === '') {
            return ['ok' => false, 'error' => 'Notes need a title and content.'];
        }

        $stmt = db()->prepare(
            'UPDATE course_resources
             SET title = ?, content = ?, attachment_path = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([$title, $content, $attachmentPath, $sortOrder, $itemId]);
        return ['ok' => true, 'course_id' => (int) $resource['course_id']];
    }

    $thumbnailUpload = admin_store_uploaded_asset('thumbnail_image', 'course-videos', admin_image_upload_types());
    if ($thumbnailUpload['error'] !== '') {
        return ['ok' => false, 'error' => 'Thumbnail: ' . $thumbnailUpload['error']];
    }
    if ($thumbnailUpload['path']) {
        $thumbnailPath = $thumbnailUpload['path'];
    }

    $normalizedUrl = video_embed_src($resourceUrl);
    $formValues = [
        'title' => $title,
        'resource_type' => 'video',
        'content' => $content,
        'resource_url' => $resourceUrl,
        'attachment_path' => '',
        'thumbnail_path' => $thumbnailPath,
        'sort_order' => (string) $sortOrder,
    ];

    if ($title === '' || $normalizedUrl === '') {
        return ['ok' => false, 'error' => 'Videos need a title and a valid URL.'];
    }

    $stmt = db()->prepare(
        'UPDATE course_resources
         SET title = ?, content = ?, resource_url = ?, thumbnail_path = ?, sort_order = ?
         WHERE id = ?'
    );
    $stmt->execute([$title, $content !== '' ? $content : 'Video lesson resource.', $normalizedUrl, $thumbnailPath, $sortOrder, $itemId]);
    return ['ok' => true, 'course_id' => (int) $resource['course_id']];
}

function admin_update_quiz_item(int $itemId, array $post, array &$formValues): array
{
    $quiz = fetch_one('SELECT * FROM quiz_questions WHERE id = ?', [$itemId]);
    if (!$quiz) {
        return ['ok' => false, 'error' => 'Quiz question not found.'];
    }

    $title = trim($post['title'] ?? '');
    $question = trim($post['question'] ?? '');
    $optionA = trim($post['option_a'] ?? '');
    $optionB = trim($post['option_b'] ?? '');
    $optionC = trim($post['option_c'] ?? '');
    $correct = trim($post['correct_option'] ?? '');
    $explanation = trim($post['explanation'] ?? '');
    $sortOrder = max(1, (int) ($post['sort_order'] ?? 1));

    $formValues = [
        'title' => $title,
        'question' => $question,
        'option_a' => $optionA,
        'option_b' => $optionB,
        'option_c' => $optionC,
        'correct_option' => $correct,
        'explanation' => $explanation,
        'sort_order' => (string) $sortOrder,
    ];

    if ($question === '' || $optionA === '' || $optionB === '' || $optionC === '' || !in_array($correct, ['A', 'B', 'C'], true)) {
        return ['ok' => false, 'error' => 'Quiz questions need a prompt, three options, and a correct answer.'];
    }

    $stmt = db()->prepare(
        'UPDATE quiz_questions
         SET title = ?, question = ?, option_a = ?, option_b = ?, option_c = ?, correct_option = ?, explanation = ?, sort_order = ?
         WHERE id = ?'
    );
    $stmt->execute([$title, $question, $optionA, $optionB, $optionC, $correct, $explanation, $sortOrder, $itemId]);
    return ['ok' => true, 'course_id' => (int) $quiz['course_id']];
}

function admin_course_nav_items(int $courseId): array
{
    return [
        'overview' => ['label' => 'Overview', 'href' => admin_course_url($courseId, 'overview')],
        'resources' => ['label' => 'Resources', 'href' => admin_course_url($courseId, 'resources')],
        'students' => ['label' => 'Students', 'href' => admin_course_url($courseId, 'students')],
        'feedback' => ['label' => 'Feedback', 'href' => admin_course_url($courseId, 'feedback')],
    ];
}

