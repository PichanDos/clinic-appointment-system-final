-- Optional demo data. Import once after schema.sql and seed.sql.
USE clinic_booking;

INSERT INTO patients (first_name, last_name, phone, birth_date) VALUES ('ณัฐชา', 'วัฒนากร', '0812345678', '1993-04-12');
SET @patient_1 = LAST_INSERT_ID();
UPDATE patients SET hn = CONCAT('HN', LPAD(@patient_1, 6, '0')) WHERE id = @patient_1;

INSERT INTO patients (first_name, last_name, phone, birth_date) VALUES ('กิตติพงษ์', 'ใจดี', '0891234567', '1988-09-25');
SET @patient_2 = LAST_INSERT_ID();
UPDATE patients SET hn = CONCAT('HN', LPAD(@patient_2, 6, '0')) WHERE id = @patient_2;

INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, status) VALUES
  (@patient_1, 1, CURDATE(), '09:00', '09:30', 'pending'),
  (@patient_2, 2, CURDATE(), '10:00', '10:30', 'arrived'),
  (@patient_1, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '13:00', '13:30', 'pending');
