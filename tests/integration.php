<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = db();
$ids = [];
try {
    $suffix = (string) random_int(100000, 999999);
    $hn1 = create_patient($db, ['first_name' => 'ทดสอบ', 'last_name' => 'หนึ่ง' . $suffix, 'phone' => '+66812345678', 'birth_date' => '1990-05-02']);
    $hn2 = create_patient($db, ['first_name' => 'ทดสอบ', 'last_name' => 'สอง' . $suffix, 'phone' => '66 89 123 4567', 'birth_date' => '1992-06-03']);
    $row1 = patient_list($db, $hn1)[0];
    $row2 = patient_list($db, $hn2)[0];
    $ids = [(int) $row1['id'], (int) $row2['id']];
    check($hn1 !== $hn2 && $row1['phone'] === '0812345678', 'HN or phone normalization failed');
    update_patient($db, ['id' => $ids[0], 'first_name' => 'แก้ไข', 'last_name' => 'หนึ่ง', 'phone' => '081-234-5678', 'birth_date' => '1990-05-02']);
    check(patient_by_id($db, $ids[0])['first_name'] === 'แก้ไข', 'Patient update failed');
    $date = date('Y-m-d', strtotime('+14 days'));
    create_appointment($db, ['patient_id' => $ids[0], 'doctor_id' => 1, 'appointment_date' => $date, 'start_time' => '10:00', 'end_time' => '11:00']);
    $blocked = false;
    try { create_appointment($db, ['patient_id' => $ids[1], 'doctor_id' => 1, 'appointment_date' => $date, 'start_time' => '10:30', 'end_time' => '11:30']); }
    catch (DomainException $error) { $blocked = true; }
    check($blocked, 'Overlapping appointment accepted');
    create_appointment($db, ['patient_id' => $ids[1], 'doctor_id' => 2, 'appointment_date' => $date, 'start_time' => '10:30', 'end_time' => '11:30']);
    $rows = appointment_list($db, $date);
    $firstId = (int) array_values(array_filter($rows, fn($a) => (int) $a['patient_id'] === $ids[0]))[0]['id'];
    update_appointment_status($db, $firstId, 'cancelled');
    create_appointment($db, ['patient_id' => $ids[1], 'doctor_id' => 1, 'appointment_date' => $date, 'start_time' => '10:30', 'end_time' => '11:30']);
    $blocked = false;
    try { update_appointment_status($db, $firstId, 'pending'); }
    catch (DomainException $error) { $blocked = true; }
    check($blocked, 'Conflicting cancelled appointment reactivated');
    $counts = report($db, $date, $date);
    check($counts['total'] >= 3 && $counts['statuses']['cancelled'] >= 1, 'Report totals failed');
    echo "Integration checks passed\n";
} finally {
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM appointments WHERE patient_id IN ($placeholders)")->execute($ids);
        $db->prepare("DELETE FROM patients WHERE id IN ($placeholders)")->execute($ids);
    }
}
