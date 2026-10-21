<?php

declare(strict_types=1);

/**
 * Small global view/helpers. Loaded once during bootstrap.
 */

if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output.
     */
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Build a URL relative to the front controller (respects sub-directory installs).
     */
    function base_url(string $path = ''): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        if ($path === '') {
            return $base === '' ? '/' : $base;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('relative_time')) {
    /**
     * Human-friendly relative time for a datetime string, e.g.
     * "Today, 9:15", "Yesterday, 17:40", "3 days ago", or a date.
     */
    function relative_time(?string $datetime): string
    {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return 'Never';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return 'Never';
        }

        $today = strtotime('today');
        $day = strtotime(date('Y-m-d', $ts));
        $diffDays = (int) round(($today - $day) / 86400);

        if ($diffDays === 0) {
            return 'Today, ' . date('G:i', $ts);
        }
        if ($diffDays === 1) {
            return 'Yesterday, ' . date('G:i', $ts);
        }
        if ($diffDays > 1 && $diffDays < 7) {
            return $diffDays . ' days ago';
        }

        return date('M j, Y', $ts);
    }
}
