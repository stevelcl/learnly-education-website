<?php

function course_url(int $courseId, bool $adminPreview = false): string
{
    $url = 'course.php?id=' . $courseId;
    if ($adminPreview) {
        $url .= '&admin_preview=1';
    }
    return $url;
}

function learn_url(int $courseId, bool $adminPreview = false): string
{
    $url = 'learn/' . $courseId;
    if ($adminPreview) {
        $url .= '?admin_preview=1';
    }
    return $url;
}

function course_star_text(float $rating): string
{
    $stars = max(1, min(5, (int) round($rating)));
    return str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
}

function course_badges(array $course): array
{
    $badges = [];
    $rating = (float) ($course['average_rating'] ?? 0);
    $enrolled = (int) ($course['enrollment_count'] ?? 0);
    $reviews = (int) ($course['review_count'] ?? 0);

    if ($enrolled >= 8 || $reviews >= 4) {
        $badges[] = 'Popular';
    }

    if ($rating >= 4.3 || ($reviews >= 2 && $rating >= 4.0)) {
        $badges[] = 'Recommended';
    }

    return $badges;
}

function course_curriculum_items(array $resources, array $questions): array
{
    $items = [];
    foreach ($resources as $resource) {
        $items[] = [
            'type' => 'resource',
            'id' => (int) $resource['id'],
            'title' => (string) $resource['title'],
            'label' => ucfirst((string) $resource['resource_type']),
            'content' => (string) $resource['content'],
            'resource_url' => (string) ($resource['resource_url'] ?? ''),
        ];
    }

    foreach ($questions as $index => $question) {
        $items[] = [
            'type' => 'quiz',
            'id' => (int) $question['id'],
            'title' => 'Quiz Checkpoint ' . ($index + 1),
            'label' => 'Quiz',
            'question' => (string) $question['question'],
            'option_a' => (string) $question['option_a'],
            'option_b' => (string) $question['option_b'],
            'option_c' => (string) $question['option_c'],
            'correct_option' => (string) $question['correct_option'],
        ];
    }

    return $items;
}

function course_learning_points(array $course, array $resources, array $questions): array
{
    $points = [];
    foreach ($resources as $resource) {
        $text = trim((string) $resource['title']);
        if ($text !== '') {
            $points[] = 'Work through ' . strtolower($text) . ' with guided explanation.';
        }
        if (count($points) >= 4) {
            break;
        }
    }

    if (count($points) < 4 && count($questions) > 0) {
        $points[] = 'Check understanding with ' . count($questions) . ' quiz prompt' . (count($questions) === 1 ? '' : 's') . '.';
    }

    if (count($points) < 4) {
        $points[] = 'Build practical understanding in ' . strtolower((string) $course['subject']) . '.';
    }

    return array_slice(array_values(array_unique($points)), 0, 4);
}

function course_skill_points(array $course, array $resources): array
{
    $skills = [];
    foreach ($resources as $resource) {
        $title = trim((string) $resource['title']);
        if ($title !== '') {
            $skills[] = $title;
        }
        if (count($skills) >= 4) {
            break;
        }
    }

    if (count($skills) < 4) {
        $skills[] = (string) $course['subject'] . ' fundamentals';
    }
    if (count($skills) < 4) {
        $skills[] = 'Independent study workflow';
    }

    return array_slice(array_values(array_unique($skills)), 0, 4);
}

function course_audience_points(array $course): array
{
    $level = strtolower((string) ($course['level'] ?? ''));
    return [
        'Students starting ' . strtolower((string) $course['subject']) . ' coursework.',
        'Learners who want a guided path instead of scattered notes.',
        'Anyone who benefits from short modules plus quick quiz checks.',
        $level !== '' ? 'Best suited for ' . $level . ' learners.' : 'Suitable for self-paced undergraduate learning.',
    ];
}
