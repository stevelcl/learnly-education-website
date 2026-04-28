<?php

function book_cover_src(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return 'assets/images/book-placeholder.svg';
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return 'image-proxy.php?src=' . rawurlencode($url);
    }

    return $url;
}
