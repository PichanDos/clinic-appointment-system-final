USE clinic_booking;

INSERT INTO doctors (id, name, specialty) VALUES
  (1, 'พญ. กานดา สุขสวัสดิ์', 'อายุรกรรม'),
  (2, 'นพ. ธนกร วิริยะ', 'เวชศาสตร์ครอบครัว'),
  (3, 'พญ. พิมพ์ชนก วัฒนกุล', 'ผิวหนัง'),
  (4, 'นพ. ภาณุเดช เกียรติไพศาล', 'ศัลยกรรมทั่วไป')
ON DUPLICATE KEY UPDATE name = VALUES(name), specialty = VALUES(specialty);

