<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_phone(string $input): ?string
{
    if (!preg_match('/^[0-9+\s()-]+$/', trim($input))) return null;
    $digits = preg_replace('/[^0-9+]/', '', trim($input));
    if ($digits === null || !preg_match('/^\+?[0-9]+$/', $digits)) return null;
    if (str_starts_with($digits, '+66')) $digits = '0' . substr($digits, 3);
    elseif (str_starts_with($digits, '66')) $digits = '0' . substr($digits, 2);
    return preg_match('/^0[689][0-9]{8}$/', $digits) ? $digits : null;
}

function valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('คำขอหมดอายุ กรุณากลับไปโหลดหน้าใหม่');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function status_label(string $status): string
{
    return ['pending' => 'รอเข้ารับบริการ', 'arrived' => 'มาแล้ว', 'cancelled' => 'ยกเลิก'][$status] ?? $status;
}

function thai_date(string $date): string
{
    $stamp = strtotime($date);
    if ($stamp === false) return $date;
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return date('j', $stamp) . ' ' . $months[(int) date('n', $stamp) - 1] . ' ' . ((int) date('Y', $stamp) + 543);
}
