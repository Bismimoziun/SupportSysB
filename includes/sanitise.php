<?php
// ============================================================
// G&G Support Portal — sanitise.php
// Input sanitisation helpers
// ============================================================

function clean_string(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function clean_int(mixed $value): int {
    return (int) $value;
}

function clean_post(string $key, string $type = 'string'): mixed {
    $value = $_POST[$key] ?? '';
    return match($type) {
        'int'    => clean_int($value),
        'string' => clean_string((string)$value),
        'raw'    => $value,  // for TinyMCE HTML — sanitise separately
        default  => clean_string((string)$value),
    };
}

function clean_get(string $key, string $type = 'string'): mixed {
    $value = $_GET[$key] ?? '';
    return match($type) {
        'int'    => clean_int($value),
        'string' => clean_string((string)$value),
        default  => clean_string((string)$value),
    };
}
