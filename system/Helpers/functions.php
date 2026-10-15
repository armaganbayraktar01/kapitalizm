<?php

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $baseUrl = 'http://localhost/manager';
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void {
        header("Location: " . base_url($path));
        exit;
    }
}