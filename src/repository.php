<?php
declare(strict_types=1);

function patient_list(PDO $db, string $search = ''): array
{
    $search = trim($search);
    if ($search === '') return $db->query('SELECT * FROM patients ORDER BY registered_at DESC, id DESC LIMIT 100')->fetchAll();
    $stmt = $db->prepare('SELECT * FROM patients WHERE hn LIKE :hn OR CONCAT(first_name, " ", last_name) LIKE :name OR phone LIKE :phone ORDER BY registered_at DESC, id DESC LIMIT 100');
    $needle = '%' . $search . '%';
    $phone = normalize_phone($search);
    $stmt->execute(['hn' => $needle, 'name' => $needle, 'phone' => '%' . ($phone ?? preg_replace('/\D/', '', $search)) . '%']);
    return $stmt->fetchAll();
}

function create_patient(PDO $db, array $data): string
{
    $first = trim((string) ($data['first_name'] ?? ''));
    $last = trim((string) ($data['last_name'] ?? ''));
    $phone = normalize_phone((string) ($data['phone'] ?? ''));
    $birth = (string) ($data['birth_date'] ?? '');
    if ($first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($last) > 100) throw new DomainException('กรุณากรอกชื่อและนามสกุลไม่เกิน 100 ตัวอักษร');
    if ($phone === null) throw new DomainException('กรุณากรอกเบอร์มือถือไทยให้ถูกต้อง');
    if (!valid_date($birth) || $birth > date('Y-m-d')) throw new DomainException('กรุณาเลือกวันเกิดให้ถูกต้อง');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO patients (first_name, last_name, phone, birth_date) VALUES (?, ?, ?, ?)');
        $stmt->execute([$first, $last, $phone, $birth]);
        $id = (int) $db->lastInsertId();
        $hn = 'HN' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $db->prepare('UPDATE patients SET hn = ? WHERE id = ?')->execute([$hn, $id]);
        $db->commit();
        return $hn;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function update_patient(PDO $db, array $data): void
{
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $first = trim((string) ($data['first_name'] ?? ''));
    $last = trim((string) ($data['last_name'] ?? ''));
    $phone = normalize_phone((string) ($data['phone'] ?? ''));
    $birth = (string) ($data['birth_date'] ?? '');
    if (!$id || $first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($last) > 100) throw new DomainException('ข้อมูลผู้ป่วยไม่ครบหรือไม่ถูกต้อง');
    if ($phone === null) throw new DomainException('กรุณากรอกเบอร์มือถือไทยให้ถูกต้อง');
    if (!valid_date($birth) || $birth > date('Y-m-d')) throw new DomainException('กรุณาเลือกวันเกิดให้ถูกต้อง');
    $stmt = $db->prepare('UPDATE patients SET first_name = ?, last_name = ?, phone = ?, birth_date = ? WHERE id = ?');
    $stmt->execute([$first, $last, $phone, $birth, $id]);
    if ($stmt->rowCount() === 0 && !patient_by_id($db, $id)) throw new DomainException('ไม่พบผู้ป่วย');
}

function patient_by_id(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function doctors(PDO $db): array
{
    return $db->query('SELECT * FROM doctors ORDER BY id')->fetchAll();
}

function create_appointment(PDO $db, array $data): void
{
    $patientId = filter_var($data['patient_id'] ?? null, FILTER_VALIDATE_INT);
    $doctorId = filter_var($data['doctor_id'] ?? null, FILTER_VALIDATE_INT);
    $date = (string) ($data['appointment_date'] ?? '');
    $start = (string) ($data['start_time'] ?? '');
    $end = (string) ($data['end_time'] ?? '');
    if (!$patientId || !$doctorId || !valid_date($date) || $date < date('Y-m-d') || !valid_time($start) || !valid_time($end) || $start >= $end) {
        throw new DomainException('กรุณาเลือกผู้ป่วย แพทย์ วัน และช่วงเวลาให้ถูกต้อง');
    }
    $db->beginTransaction();
    try {
        // Lock the doctor's row so two concurrent requests cannot both reserve an empty slot.
        $lock = $db->prepare('SELECT id FROM doctors WHERE id = ? FOR UPDATE');
        $lock->execute([$doctorId]);
        if (!$lock->fetch()) throw new DomainException('ไม่พบแพทย์');
        if (!patient_by_id($db, (int) $patientId)) throw new DomainException('ไม่พบผู้ป่วย');
        $overlap = $db->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status <> 'cancelled' AND start_time < ? AND end_time > ? LIMIT 1");
        $overlap->execute([$doctorId, $date, $end, $start]);
        if ($overlap->fetch()) throw new DomainException('แพทย์มีนัดซ้อนในช่วงเวลานี้ กรุณาเลือกเวลาอื่น');
        $insert = $db->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([$patientId, $doctorId, $date, $start, $end]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function appointment_list(PDO $db, string $date, ?int $doctorId = null): array
{
    $sql = 'SELECT a.*, p.hn, p.first_name, p.last_name, p.phone, d.name AS doctor_name, d.specialty FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN doctors d ON d.id = a.doctor_id WHERE a.appointment_date = ?';
    $params = [$date];
    if ($doctorId) { $sql .= ' AND a.doctor_id = ?'; $params[] = $doctorId; }
    $sql .= ' ORDER BY a.start_time, a.id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function update_appointment_status(PDO $db, int $id, string $status): void
{
    if (!in_array($status, ['pending', 'arrived', 'cancelled'], true)) throw new DomainException('สถานะไม่ถูกต้อง');
    $db->beginTransaction();
    try {
        // Use the same doctor lock as booking to keep cancellations and reactivation consistent.
        $stmt = $db->prepare('SELECT doctor_id FROM appointments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new DomainException('ไม่พบนัดหมาย');
        $db->prepare('SELECT id FROM doctors WHERE id = ? FOR UPDATE')->execute([$row['doctor_id']]);
        $stmt = $db->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $appointment = $stmt->fetch();
        if ($appointment['status'] === 'cancelled' && $status !== 'cancelled') {
            $overlap = $db->prepare("SELECT id FROM appointments WHERE id <> ? AND doctor_id = ? AND appointment_date = ? AND status <> 'cancelled' AND start_time < ? AND end_time > ? LIMIT 1");
            $overlap->execute([$id, $appointment['doctor_id'], $appointment['appointment_date'], $appointment['end_time'], $appointment['start_time']]);
            if ($overlap->fetch()) throw new DomainException('คืนสถานะไม่ได้ เพราะช่วงเวลานี้มีนัดอื่นแล้ว');
        }
        $db->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$status, $id]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function report(PDO $db, string $from, string $to): array
{
    $patients = $db->prepare('SELECT COUNT(*) FROM patients WHERE registered_at >= ? AND registered_at < DATE_ADD(?, INTERVAL 1 DAY)');
    $patients->execute([$from, $to]);
    $appointments = $db->prepare('SELECT status, COUNT(*) AS total FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY status');
    $appointments->execute([$from, $to]);
    $counts = ['pending' => 0, 'arrived' => 0, 'cancelled' => 0];
    foreach ($appointments->fetchAll() as $row) $counts[$row['status']] = (int) $row['total'];
    return ['new_patients' => (int) $patients->fetchColumn(), 'total' => array_sum($counts), 'statuses' => $counts];
}
