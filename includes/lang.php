<?php
declare(strict_types=1);

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'fil'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

$GLOBALS['__lang'] = $_SESSION['lang'] ?? 'en';
$GLOBALS['__strings'] = require __DIR__ . '/../lang/' . $GLOBALS['__lang'] . '.php';

function t(string $key): string {
    return $GLOBALS['__strings'][$key] ?? $key;
}

function current_lang(): string {
    return $GLOBALS['__lang'];
}
