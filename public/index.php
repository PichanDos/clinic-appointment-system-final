<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$page = (string) ($_GET['page'] ?? 'dashboard');
$allowed = ['dashboard', 'patients', 'appointments', 'report'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';

try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'patient_create') {
            $hn = create_patient($pdo, $_POST);
            flash('success', "เพิ่มผู้ป่วยเรียบร้อย เลข HN: $hn");
            redirect('?page=patients');
        } elseif ($action === 'patient_update') {
            update_patient($pdo, $_POST);
            flash('success', 'แก้ไขข้อมูลผู้ป่วยเรียบร้อย');
            redirect('?page=patients');
        } elseif ($action === 'appointment_create') {
            create_appointment($pdo, $_POST);
            flash('success', 'บันทึกนัดหมายเรียบร้อย');
            redirect('?page=appointments&date=' . urlencode((string) $_POST['appointment_date']));
        } elseif ($action === 'appointment_status') {
            update_appointment_status($pdo, (int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
            flash('success', 'อัปเดตสถานะนัดหมายเรียบร้อย');
            $returnDate = (string) ($_POST['return_date'] ?? date('Y-m-d'));
            redirect('?page=appointments&date=' . urlencode($returnDate));
        }
        throw new DomainException('คำสั่งไม่ถูกต้อง');
    }
} catch (DomainException $error) {
    flash('error', $error->getMessage());
    redirect('?page=' . urlencode($page));
} catch (PDOException $error) {
    http_response_code(503);
    $databaseError = 'เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบการตั้งค่าและนำเข้าไฟล์ SQL ตาม README';
}

$today = date('Y-m-d');
$date = (string) ($_GET['date'] ?? $today);
if (!valid_date($date)) $date = $today;
$doctorFilter = filter_var($_GET['doctor'] ?? null, FILTER_VALIDATE_INT) ?: null;
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? $today);
if (!valid_date($from)) $from = date('Y-m-01');
if (!valid_date($to)) $to = $today;
if ($from > $to) [$from, $to] = [$to, $from];

if (!isset($databaseError)) {
    $doctorRows = doctors($pdo);
    $todayRows = appointment_list($pdo, $today);
    $todayPending = count(array_filter($todayRows, fn($a) => $a['status'] === 'pending'));
    $todayArrived = count(array_filter($todayRows, fn($a) => $a['status'] === 'arrived'));
    if ($page === 'patients') {
        $search = trim((string) ($_GET['q'] ?? ''));
        $patients = patient_list($pdo, $search);
        $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $editing = $editId ? patient_by_id($pdo, $editId) : null;
    }
    if ($page === 'appointments') {
        $appointments = appointment_list($pdo, $date, $doctorFilter);
        $patientSearch = trim((string) ($_GET['patient_q'] ?? ''));
        $patientOptions = patient_list($pdo, $patientSearch);
    }
    if ($page === 'report') $summary = report($pdo, $from, $to);
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$title = ['dashboard' => 'ภาพรวมวันนี้', 'patients' => 'ข้อมูลผู้ป่วย', 'appointments' => 'นัดหมาย', 'report' => 'รายงานสรุป'][$page];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · คลินิกนัดหมาย</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <a class="brand" href="?page=dashboard"><span class="brand-mark">✳</span><span><strong>CLINIC<span class="brand-accent">.</span></strong><small>ระบบนัดหมายคลินิก</small></span></a>
    <div class="nav-label">เมนูหลัก</div>
    <nav aria-label="เมนูหลัก">
      <a class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard"><span>◫</span> ภาพรวม</a>
      <a class="nav-item <?= $page === 'patients' ? 'active' : '' ?>" href="?page=patients"><span>♙</span> ผู้ป่วย</a>
      <a class="nav-item <?= $page === 'appointments' ? 'active' : '' ?>" href="?page=appointments"><span>▦</span> นัดหมาย</a>
      <a class="nav-item <?= $page === 'report' ? 'active' : '' ?>" href="?page=report"><span>▤</span> รายงาน</a>
    </nav>
    <div class="sidebar-bottom"><span class="online-dot"></span> ระบบพร้อมใช้งาน <small>เวลาไทย · Asia/Bangkok</small></div>
  </aside>
  <div class="main-column">
    <header class="topbar"><div class="breadcrumb">คลินิก <span>/</span> <?= e($title) ?></div><div class="topbar-right"><span class="today-pill">● วันนี้ <?= e(thai_date($today)) ?></span><div class="avatar">C</div></div></header>
    <main class="content">
      <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div><?php endif; ?>
      <?php if (isset($databaseError)): ?><div class="alert error" role="alert"><?= e($databaseError) ?></div>
      <?php elseif ($page === 'dashboard'): ?>
        <div class="page-heading"><div><span class="eyebrow">DASHBOARD</span><h1>ภาพรวมวันนี้</h1><p>ติดตามคิวและสถานะการเข้ารับบริการในที่เดียว</p></div><a class="button primary" href="?page=appointments">+ เพิ่มนัดหมาย</a></div>
        <div class="metrics three"><div class="metric"><span class="metric-icon violet">▦</span><span class="metric-label">นัดหมายวันนี้</span><strong><?= count($todayRows) ?></strong><small>รวมทุกสถานะ</small></div><div class="metric"><span class="metric-icon amber">◷</span><span class="metric-label">รอเข้ารับบริการ</span><strong><?= $todayPending ?></strong><small>กำลังรอคิว</small></div><div class="metric"><span class="metric-icon green">✓</span><span class="metric-label">มาแล้ว</span><strong><?= $todayArrived ?></strong><small>เช็กอินเรียบร้อย</small></div></div>
        <div class="section-head"><div><h2>รายการนัดหมายวันนี้</h2><p><?= e(thai_date($today)) ?> · เรียงตามเวลา</p></div><a class="text-link" href="?page=appointments">ดูทั้งหมด →</a></div>
        <?php render_appointments($todayRows, $today, false); ?>
      <?php elseif ($page === 'patients'): ?>
        <div class="page-heading"><div><span class="eyebrow">PATIENTS</span><h1>ข้อมูลผู้ป่วย</h1><p>ลงทะเบียน ค้นหา และแก้ไขข้อมูลผู้ป่วย</p></div></div>
        <div class="split-layout">
          <section class="card form-card"><div class="card-heading"><span class="heading-icon">＋</span><div><h2><?= $editing ? 'แก้ไขข้อมูลผู้ป่วย' : 'ลงทะเบียนผู้ป่วย' ?></h2><p><?= $editing ? e($editing['hn']) : 'เลข HN จะถูกสร้างอัตโนมัติ' ?></p></div></div>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $editing ? 'patient_update' : 'patient_create' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
              <div class="form-grid"><label>ชื่อ <span>*</span><input name="first_name" required maxlength="100" value="<?= e($editing['first_name'] ?? '') ?>" placeholder="ชื่อจริง"></label><label>นามสกุล <span>*</span><input name="last_name" required maxlength="100" value="<?= e($editing['last_name'] ?? '') ?>" placeholder="นามสกุล"></label></div>
              <label>เบอร์โทรศัพท์ <span>*</span><input name="phone" type="tel" required value="<?= e($editing['phone'] ?? '') ?>" placeholder="081-234-5678"><small>รับรูปแบบ 081-234-5678, +66812345678 และ 66 81 234 5678</small></label>
              <label>วันเกิด <span>*</span><input name="birth_date" type="date" required max="<?= e($today) ?>" value="<?= e($editing['birth_date'] ?? '') ?>"></label>
              <div class="form-actions"><button class="button primary" type="submit"><?= $editing ? 'บันทึกการแก้ไข' : 'บันทึกผู้ป่วย' ?></button><?php if ($editing): ?><a class="button ghost" href="?page=patients">ยกเลิก</a><?php endif; ?></div>
            </form>
          </section>
          <section class="card list-card"><div class="card-heading space-between"><div><h2>รายชื่อผู้ป่วย</h2><p>แสดงสูงสุด 100 รายการล่าสุด</p></div><span class="count-pill"><?= count($patients) ?> รายการ</span></div>
            <form class="search-form" method="get"><input type="hidden" name="page" value="patients"><input name="q" value="<?= e($search) ?>" placeholder="ค้นหาชื่อ, HN หรือเบอร์โทร" aria-label="ค้นหาผู้ป่วย"><button type="submit">ค้นหา</button></form>
            <div class="table-wrap"><table><thead><tr><th>ผู้ป่วย</th><th>เบอร์โทร</th><th>วันลงทะเบียน</th><th></th></tr></thead><tbody>
              <?php foreach ($patients as $patient): ?><tr><td><strong><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></strong><span class="subline"><?= e($patient['hn']) ?></span></td><td><?= e($patient['phone']) ?></td><td><?= e(thai_date(substr($patient['registered_at'], 0, 10))) ?></td><td><a class="text-link" href="?page=patients&edit=<?= (int) $patient['id'] ?>">แก้ไข</a></td></tr><?php endforeach; ?>
              <?php if (!$patients): ?><tr><td colspan="4" class="empty-cell">ไม่พบผู้ป่วย</td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
        </div>
      <?php elseif ($page === 'appointments'): ?>
        <div class="page-heading"><div><span class="eyebrow">APPOINTMENTS</span><h1>นัดหมาย</h1><p>จัดการคิวแพทย์และติดตามสถานะการเข้ารับบริการ</p></div></div>
        <section class="card booking-card"><div class="card-heading"><span class="heading-icon">＋</span><div><h2>สร้างนัดหมายใหม่</h2><p>ระบบตรวจสอบเวลาซ้อนของแพทย์อัตโนมัติ</p></div></div>
          <form class="patient-picker" method="get"><input type="hidden" name="page" value="appointments"><input type="hidden" name="date" value="<?= e($date) ?>"><input name="patient_q" value="<?= e($patientSearch) ?>" placeholder="ค้นหาผู้ป่วยด้วยชื่อ, HN หรือเบอร์โทร ก่อนเลือกนัด" aria-label="ค้นหาผู้ป่วย"><button type="submit" class="button secondary">ค้นหา</button></form>
          <form method="post" class="booking-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="appointment_create">
            <label>ผู้ป่วย <span>*</span><select name="patient_id" required><option value="">เลือกผู้ป่วย</option><?php foreach ($patientOptions as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= e($patient['hn'] . ' · ' . $patient['first_name'] . ' ' . $patient['last_name']) ?></option><?php endforeach; ?></select></label>
            <label>แพทย์ <span>*</span><select name="doctor_id" required><option value="">เลือกแพทย์</option><?php foreach ($doctorRows as $doctor): ?><option value="<?= (int) $doctor['id'] ?>"><?= e($doctor['name'] . ' · ' . $doctor['specialty']) ?></option><?php endforeach; ?></select></label>
            <label>วันที่ <span>*</span><input type="date" name="appointment_date" required min="<?= e($today) ?>" value="<?= e($date >= $today ? $date : $today) ?>"></label>
            <label>เริ่ม <span>*</span><input type="time" name="start_time" required value="09:00"></label>
            <label>สิ้นสุด <span>*</span><input type="time" name="end_time" required value="09:30"></label>
            <button class="button primary" type="submit" <?= !$patientOptions ? 'disabled title="กรุณาเพิ่มผู้ป่วยก่อน"' : '' ?>>บันทึกนัดหมาย</button>
          </form><?php if (!$patientOptions): ?><p class="form-hint">ไม่พบผู้ป่วยในรายการนี้ ลองค้นหาใหม่หรือ<a href="?page=patients">ลงทะเบียนผู้ป่วย</a></p><?php endif; ?>
        </section>
        <div class="section-head"><div><h2>รายการนัดหมาย</h2><p>เรียงตามเวลาเริ่มต้น</p></div><form class="filters" method="get"><input type="hidden" name="page" value="appointments"><input type="date" name="date" value="<?= e($date) ?>" aria-label="วันที่"><select name="doctor" aria-label="แพทย์"><option value="">แพทย์ทุกคน</option><?php foreach ($doctorRows as $doctor): ?><option value="<?= (int) $doctor['id'] ?>"<?= selected($doctor['id'], $doctorFilter) ?>><?= e($doctor['name']) ?></option><?php endforeach; ?></select><button class="button secondary" type="submit">แสดงผล</button></form></div>
        <?php render_appointments($appointments, $date, true); ?>
      <?php elseif ($page === 'report'): ?>
        <div class="page-heading"><div><span class="eyebrow">REPORTS</span><h1>รายงานสรุป</h1><p>ติดตามจำนวนผู้ป่วยใหม่และนัดหมายตามช่วงวันที่</p></div></div>
        <section class="card report-filter"><form method="get"><input type="hidden" name="page" value="report"><label>ตั้งแต่วันที่<input type="date" name="from" value="<?= e($from) ?>" required></label><label>ถึงวันที่<input type="date" name="to" value="<?= e($to) ?>" required></label><button type="submit" class="button primary">ดูรายงาน</button></form><p>ผู้ป่วยใหม่นับตามวันที่ลงทะเบียน · นัดหมายนับตามวันที่นัด รวมรายการที่ยกเลิก</p></section>
        <div class="metrics report-metrics"><div class="metric"><span class="metric-icon violet">♙</span><span class="metric-label">ผู้ป่วยใหม่</span><strong><?= $summary['new_patients'] ?></strong><small>ราย</small></div><div class="metric"><span class="metric-icon blue">▦</span><span class="metric-label">นัดหมายทั้งหมด</span><strong><?= $summary['total'] ?></strong><small>รายการ</small></div><div class="metric"><span class="metric-icon amber">◷</span><span class="metric-label">รอเข้ารับบริการ</span><strong><?= $summary['statuses']['pending'] ?></strong><small>รายการ</small></div><div class="metric"><span class="metric-icon green">✓</span><span class="metric-label">มาแล้ว</span><strong><?= $summary['statuses']['arrived'] ?></strong><small>รายการ</small></div><div class="metric"><span class="metric-icon red">×</span><span class="metric-label">ยกเลิก</span><strong><?= $summary['statuses']['cancelled'] ?></strong><small>รายการ</small></div></div>
        <section class="card report-note"><h2>ช่วงรายงาน</h2><p><?= e(thai_date($from)) ?> – <?= e(thai_date($to)) ?></p><div class="report-bar"><span style="width: <?= $summary['total'] ? round($summary['statuses']['pending'] / $summary['total'] * 100, 2) : 0 ?>%" class="bar-pending"></span><span style="width: <?= $summary['total'] ? round($summary['statuses']['arrived'] / $summary['total'] * 100, 2) : 0 ?>%" class="bar-arrived"></span><span style="width: <?= $summary['total'] ? round($summary['statuses']['cancelled'] / $summary['total'] * 100, 2) : 0 ?>%" class="bar-cancelled"></span></div><div class="legend"><span><i class="dot amber-bg"></i>รอเข้ารับบริการ</span><span><i class="dot green-bg"></i>มาแล้ว</span><span><i class="dot red-bg"></i>ยกเลิก</span></div></section>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>
<?php
function render_appointments(array $rows, string $date, bool $allowEdit): void
{
    echo '<div class="card table-card"><div class="table-wrap"><table class="appointments-table"><thead><tr><th>เวลา</th><th>ผู้ป่วย</th><th>แพทย์</th><th>สถานะ</th>' . ($allowEdit ? '<th>จัดการ</th>' : '') . '</tr></thead><tbody>';
    foreach ($rows as $a) {
        $status = e($a['status']);
        echo '<tr><td><strong>' . e(substr($a['start_time'], 0, 5)) . '–' . e(substr($a['end_time'], 0, 5)) . '</strong></td><td><strong>' . e($a['first_name'] . ' ' . $a['last_name']) . '</strong><span class="subline">' . e($a['hn']) . ' · ' . e($a['phone']) . '</span></td><td><strong>' . e($a['doctor_name']) . '</strong><span class="subline">' . e($a['specialty']) . '</span></td><td><span class="status ' . $status . '">' . e(status_label($a['status'])) . '</span></td>';
        if ($allowEdit) {
            echo '<td><form method="post" class="status-form"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="action" value="appointment_status"><input type="hidden" name="id" value="' . (int) $a['id'] . '"><input type="hidden" name="return_date" value="' . e($date) . '"><select name="status" aria-label="สถานะนัดหมาย">';
            foreach (['pending', 'arrived', 'cancelled'] as $option) echo '<option value="' . $option . '"' . selected($option, $a['status']) . '>' . e(status_label($option)) . '</option>';
            echo '</select><button type="submit" class="button mini">บันทึก</button></form></td>';
        }
        echo '</tr>';
    }
    if (!$rows) echo '<tr><td colspan="' . ($allowEdit ? 5 : 4) . '" class="empty-cell"><span class="empty-icon">▦</span>ยังไม่มีนัดหมายในวันที่เลือก</td></tr>';
    echo '</tbody></table></div></div>';
}
