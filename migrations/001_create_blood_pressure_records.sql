DROP TABLE IF EXISTS blood_pressure_records;

CREATE TABLE blood_pressure_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_date DATE NOT NULL UNIQUE,           -- 記録日 (例: 2026-06-12)
    weight DECIMAL(5,2) DEFAULT NULL,           -- 体重 (kg)
    
    -- 朝の計測値 (1回目)
    morning_systolic1 INT DEFAULT NULL,
    morning_diastolic1 INT DEFAULT NULL,
    morning_pulse1 INT DEFAULT NULL,
    
    -- 朝の計測値 (2回目)
    morning_systolic2 INT DEFAULT NULL,
    morning_diastolic2 INT DEFAULT NULL,
    morning_pulse2 INT DEFAULT NULL,
    
    -- 夜の計測値 (1回目)
    evening_systolic1 INT DEFAULT NULL,
    evening_diastolic1 INT DEFAULT NULL,
    evening_pulse1 INT DEFAULT NULL,
    
    -- 夜の計測値 (2回目)
    evening_systolic2 INT DEFAULT NULL,
    evening_diastolic2 INT DEFAULT NULL,
    evening_pulse2 INT DEFAULT NULL,
    
    memo TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
