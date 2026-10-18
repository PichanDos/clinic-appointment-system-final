CREATE DATABASE IF NOT EXISTS clinic_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinic_booking;

CREATE TABLE IF NOT EXISTS patients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hn VARCHAR(16) DEFAULT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(10) NOT NULL,
  birth_date DATE NOT NULL,
  registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_patients_hn (hn),
  KEY idx_patients_name (first_name, last_name),
  KEY idx_patients_phone (phone),
  KEY idx_patients_registered (registered_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  specialty VARCHAR(150) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id BIGINT UNSIGNED NOT NULL,
  doctor_id BIGINT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending', 'arrived', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
  CONSTRAINT fk_appointments_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id),
  KEY idx_appointments_doctor_slot (doctor_id, appointment_date, status, start_time, end_time),
  KEY idx_appointments_date_status (appointment_date, status),
  KEY idx_appointments_patient (patient_id)
) ENGINE=InnoDB;

