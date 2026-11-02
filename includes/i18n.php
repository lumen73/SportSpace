<?php

function load_translations(string $locale = 'fr'): array
{
    $locale = in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
    return require __DIR__ . '/../lang/' . $locale . '.php';
}

function trans(string $key, array $translations): string
{
    return $translations[$key] ?? $key;
}
