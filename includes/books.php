<?php

function book_url(int $bookId): string
{
    return 'book/' . $bookId;
}

function render_stars(float $rating): string
{
    $rounded = (int) round($rating);
    $rounded = max(0, min(5, $rounded));
    return str_repeat('★', $rounded) . str_repeat('☆', 5 - $rounded);
}

function rating_label(float $rating, int $count): string
{
    if ($count <= 0) {
        return 'No reviews yet';
    }

    return number_format($rating, 1) . ' (' . $count . ' review' . ($count === 1 ? '' : 's') . ')';
}
