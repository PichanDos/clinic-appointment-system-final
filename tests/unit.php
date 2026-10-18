<?php
declare(strict_types=1);
require __DIR__ . '/../src/functions.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach (['081-234-5678', '+66812345678', '66 81 234 5678', '0812345678'] as $input) {
    check(normalize_phone($input) === '0812345678', 'Phone normalization failed: ' . $input);
}
foreach (['1234567890', '081234567', '+668123456789', '081abc5678'] as $input) {
    check(normalize_phone($input) === null, 'Invalid phone accepted: ' . $input);
}
check(valid_date('2026-10-15'), 'Valid date rejected');
check(!valid_date('2026-02-30'), 'Invalid date accepted');
check(valid_time('09:30') && !valid_time('25:00'), 'Time validation failed');
check(e('<script>') === '&lt;script&gt;', 'HTML escaping failed');
echo "Unit checks passed\n";
