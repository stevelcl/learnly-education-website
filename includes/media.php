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

function video_embed_src(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('~^https?://(?:www\.)?youtube\.com/embed/([A-Za-z0-9_-]{6,})~i', $url, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . $matches[1];
    }

    if (preg_match('~^https?://(?:www\.)?youtu\.be/([A-Za-z0-9_-]{6,})~i', $url, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . $matches[1];
    }

    if (preg_match('~^https?://(?:www\.)?youtube\.com/watch\?([^#]+)~i', $url, $matches)) {
        parse_str($matches[1], $query);
        if (!empty($query['v'])) {
            return 'https://www.youtube-nocookie.com/embed/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $query['v']);
        }
    }

    if (preg_match('~^https?://(?:www\.)?youtube\.com/shorts/([A-Za-z0-9_-]{6,})~i', $url, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . $matches[1];
    }

    return $url;
}
