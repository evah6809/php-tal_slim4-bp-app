<?php
// データベース接続設定 (さくらのレンタルサーバーのMySQL環境に合わせて変更してください)
$host = 'db'; // またはさくらの指定するデータベースサーバー名
$db   = 'health-bp';
$user = 'root';
$pass = 'evah6809';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo "データベース接続失敗: " . $e->getMessage() . "\n";
    exit(1);
}

// バックアップファイルの読み込み
$backupFile = __DIR__ . '/blood_pressure_db_backup.sql';
if (!file_exists($backupFile)) {
    echo "バックアップファイルが見つかりません: $backupFile\n";
    exit(1);
}

$lines = file($backupFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$isCopyData = false;
$rawRecords = [];

// COPY文のセクションからデータを抽出
foreach ($lines as $line) {
    if (strpos($line, 'COPY public.blood_pressure_records') === 0) {
        $isCopyData = true;
        continue;
    }
    if ($isCopyData) {
        if ($line === '\.') {
            break; // データ終了
        }
        $cols = explode("\t", $line);
        // カラム構造: id, user_id, date, timing, weight, systolic1, diastolic1, pulse1, systolic2, diastolic2, pulse2, created_at, updated_at
        if (count($cols) >= 13) {
            $userId   = $cols[1];
            $date     = $cols[2];
            $timing   = $cols[3]; // 'morning' または 'evening'
            $weight   = ($cols[4] !== '\N') ? $cols[4] : null;
            $sys1     = ($cols[5] !== '\N') ? $cols[5] : null;
            $dia1     = ($cols[6] !== '\N') ? $cols[6] : null;
            $pulse1   = ($cols[7] !== '\N') ? $cols[7] : null;
            $sys2     = ($cols[8] !== '\N') ? $cols[8] : null;
            $dia2     = ($cols[9] !== '\N') ? $cols[9] : null;
            $pulse2   = ($cols[10] !== '\N') ? $cols[10] : null;
            $createdAt = ($cols[11] !== '\N') ? $cols[11] : null;
            $updatedAt = ($cols[12] !== '\N') ? $cols[12] : null;

            // ユーザーIDと日付ごとにデータを集約
            if (!isset($rawRecords[$userId])) {
                $rawRecords[$userId] = [];
            }
            if (!isset($rawRecords[$userId][$date])) {
                $rawRecords[$userId][$date] = [
                    'weight' => null,
                    'morning' => [],
                    'evening' => [],
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ];
            }

            if ($weight !== null) {
                $rawRecords[$userId][$date]['weight'] = $weight;
            }

            // タイムスタンプの保持（より新しいものを維持する、または最初に入ったものを採用）
            if ($createdAt && (!$rawRecords[$userId][$date]['created_at'] || $createdAt < $rawRecords[$userId][$date]['created_at'])) {
                $rawRecords[$userId][$date]['created_at'] = $createdAt;
            }
            if ($updatedAt && (!$rawRecords[$userId][$date]['updated_at'] || $updatedAt > $rawRecords[$userId][$date]['updated_at'])) {
                $rawRecords[$userId][$date]['updated_at'] = $updatedAt;
            }

            if ($timing === 'morning') {
                $rawRecords[$userId][$date]['morning'] = [
                    'sys1' => $sys1, 'dia1' => $dia1, 'pulse1' => $pulse1,
                    'sys2' => $sys2, 'dia2' => $dia2, 'pulse2' => $pulse2
                ];
            } elseif ($timing === 'evening') {
                $rawRecords[$userId][$date]['evening'] = [
                    'sys1' => $sys1, 'dia1' => $dia1, 'pulse1' => $pulse1,
                    'sys2' => $sys2, 'dia2' => $dia2, 'pulse2' => $pulse2
                ];
            }
        }
    }
}

// MySQLへUPSERT (user_id と record_date の複合キーで判定)
$sql = "INSERT INTO blood_pressure_records (
            user_id, record_date, weight,
            morning_systolic1, morning_diastolic1, morning_pulse1,
            morning_systolic2, morning_diastolic2, morning_pulse2,
            evening_systolic1, evening_diastolic1, evening_pulse1,
            evening_systolic2, evening_diastolic2, evening_pulse2,
            created_at, updated_at
        ) VALUES (
            :user_id, :date, :weight,
            :m_sys1, :m_dia1, :m_p1,
            :m_sys2, :m_dia2, :m_p2,
            :e_sys1, :e_dia1, :e_p1,
            :e_sys2, :e_dia2, :e_p2,
            :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            weight = COALESCE(VALUES(weight), weight),
            morning_systolic1 = COALESCE(VALUES(morning_systolic1), morning_systolic1),
            morning_diastolic1 = COALESCE(VALUES(morning_diastolic1), morning_diastolic1),
            morning_pulse1 = COALESCE(VALUES(morning_pulse1), morning_pulse1),
            morning_systolic2 = COALESCE(VALUES(morning_systolic2), morning_systolic2),
            morning_diastolic2 = COALESCE(VALUES(morning_diastolic2), morning_diastolic2),
            morning_pulse2 = COALESCE(VALUES(morning_pulse2), morning_pulse2),
            evening_systolic1 = COALESCE(VALUES(evening_systolic1), evening_systolic1),
            evening_diastolic1 = COALESCE(VALUES(evening_diastolic1), evening_diastolic1),
            evening_pulse1 = COALESCE(VALUES(evening_pulse1), evening_pulse1),
            evening_systolic2 = COALESCE(VALUES(evening_systolic2), evening_systolic2),
            evening_diastolic2 = COALESCE(VALUES(evening_diastolic2), evening_diastolic2),
            evening_pulse2 = COALESCE(VALUES(evening_pulse2), evening_pulse2),
            updated_at = VALUES(updated_at)";

$stmt = $pdo->prepare($sql);
$count = 0;

foreach ($rawRecords as $userId => $dates) {
    foreach ($dates as $date => $data) {
        $m = $data['morning'] ?? [];
        $e = $data['evening'] ?? [];

        $stmt->execute([
            ':user_id'    => $userId,
            ':date'       => $date,
            ':weight'     => $data['weight'],
            ':m_sys1'     => $m['sys1'] ?? null,
            ':m_dia1'     => $m['dia1'] ?? null,
            ':m_p1'       => $m['pulse1'] ?? null,
            ':m_sys2'     => $m['sys2'] ?? null,
            ':m_dia2'     => $m['dia2'] ?? null,
            ':m_p2'       => $m['pulse2'] ?? null,
            ':e_sys1'     => $e['sys1'] ?? null,
            ':e_dia1'     => $e['dia1'] ?? null,
            ':e_p1'       => $e['pulse1'] ?? null,
            ':e_sys2'     => $e['sys2'] ?? null,
            ':e_dia2'     => $e['dia2'] ?? null,
            ':e_p2'       => $e['pulse2'] ?? null,
            ':created_at' => $data['created_at'],
            ':updated_at' => $data['updated_at'],
        ]);
        $count++;
    }
}

echo "データ移行が完了しました！ 合計 {$count} 件のユーザー日別レコードをインポートしました。\n";
