<?php
namespace App\Infrastructure;

use App\Domain\BloodPressureRecord;
use App\Domain\BloodPressureRepositoryInterface;
use PDO;

class PdoBloodPressureRepository implements BloodPressureRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function findByDate(string $date, string $userId): ?BloodPressureRecord
    {
        // 👇 これを仕込むと、docker logs に強制出力されます
        error_log("【DEBUG】受け取った日付: " . $date . ", ユーザーID: " . $userId);
        $stmt = $this->pdo->prepare("SELECT * FROM blood_pressure_records WHERE record_date = :date AND user_id = :user_id");
        $stmt->execute([
            ':date' => $date,
            ':user_id' => $userId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 👇 取得できた行データをログに出してみる
        error_log("【DB検索結果】取得row: " . print_r($row, true));
        
        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByMonth(int $year, int $month, string $userId): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        
        $sql =  "SELECT * FROM blood_pressure_records 
             WHERE user_id = :user_id 
               AND record_date BETWEEN :start AND :end 
             ORDER BY record_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'    => $userId,
            ':start' => $startDate,
            ':end' => $endDate,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rows as $row) {
            $records[] = $this->mapRowToEntity($row);
        }

        return $records;
    }

    public function save(BloodPressureRecord $record, string $userId): void
    {
        $m = $record->getMorning() ?? [];
        $e = $record->getEvening() ?? [];

        $sql = "INSERT INTO blood_pressure_records (
                    user_id, record_date, weight,
                    morning_systolic1, morning_diastolic1, morning_pulse1,
                    morning_systolic2, morning_diastolic2, morning_pulse2,
                    evening_systolic1, evening_diastolic1, evening_pulse1,
                    evening_systolic2, evening_diastolic2, evening_pulse2
                ) VALUES (
                    :user_id, :date, :weight,
                    :m_sys1, :m_dia1, :m_p1,
                    :m_sys2, :m_dia2, :m_p2,
                    :e_sys1, :e_dia1, :e_p1,
                    :e_sys2, :e_dia2, :e_p2
                ) ON DUPLICATE KEY UPDATE
                    weight = VALUES(weight),
                    morning_systolic1 = VALUES(morning_systolic1),
                    morning_diastolic1 = VALUES(morning_diastolic1),
                    morning_pulse1 = VALUES(morning_pulse1),
                    morning_systolic2 = VALUES(morning_systolic2),
                    morning_diastolic2 = VALUES(morning_diastolic2),
                    morning_pulse2 = VALUES(morning_pulse2),
                    evening_systolic1 = VALUES(evening_systolic1),
                    evening_diastolic1 = VALUES(evening_diastolic1),
                    evening_pulse1 = VALUES(evening_pulse1),
                    evening_systolic2 = VALUES(evening_systolic2),
                    evening_diastolic2 = VALUES(evening_diastolic2),
                    evening_pulse2 = VALUES(evening_pulse2)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':date'   => $record->getDate(),
            ':weight' => $record->getWeight(),
            ':m_sys1' => $m['systolic1'] ?? null,
            ':m_dia1' => $m['diastolic1'] ?? null,
            ':m_p1'   => $m['pulse1'] ?? null,
            ':m_sys2' => $m['systolic2'] ?? null,
            ':m_dia2' => $m['diastolic2'] ?? null,
            ':m_p2'   => $m['pulse2'] ?? null,
            ':e_sys1' => $e['systolic1'] ?? null,
            ':e_dia1' => $e['diastolic1'] ?? null,
            ':e_p1'   => $e['pulse1'] ?? null,
            ':e_sys2' => $e['systolic2'] ?? null,
            ':e_dia2' => $e['diastolic2'] ?? null,
            ':e_p2'   => $e['pulse2'] ?? null,
        ]);
    }

    private function mapRowToEntity(array $row): BloodPressureRecord
    {
        // 朝の平均値計算（1回目と2回目がある場合は平均を出すなど、旧アプリの仕様を再現）
        $morning = null;
        if ($row['morning_systolic1'] !== null || $row['morning_systolic2'] !== null) {
            $mSys1 = $row['morning_systolic1'];
            $mSys2 = $row['morning_systolic2'];
            $mDia1 = $row['morning_diastolic1'];
            $mDia2 = $row['morning_diastolic2'];
            $mP1   = $row['morning_pulse1'];
            $mP2   = $row['morning_pulse2'];

            $morning = [
                'systolic1'  => $mSys1,
                'diastolic1' => $mDia1,
                'pulse1'     => $mP1,
                'systolic2'  => $mSys2,
                'diastolic2' => $mDia2,
                'pulse2'     => $mP2,
                'avg_sys'    => ($mSys1 !== null && $mSys2 !== null) ? round(($mSys1 + $mSys2) / 2) : ($mSys1 ?? $mSys2),
                'avg_dia'    => ($mDia1 !== null && $mDia2 !== null) ? round(($mDia1 + $mDia2) / 2) : ($mDia1 ?? $mDia2),
                'avg_pulse'  => ($mP1 !== null && $mP2 !== null) ? round(($mP1 + $mP2) / 2) : ($mP1 ?? $mP2),
            ];
        }

        // 夜の平均値計算
        $evening = null;
        if ($row['evening_systolic1'] !== null || $row['evening_systolic2'] !== null) {
            $eSys1 = $row['evening_systolic1'];
            $eSys2 = $row['evening_systolic2'];
            $eDia1 = $row['evening_diastolic1'];
            $eDia2 = $row['evening_diastolic2'];
            $eP1   = $row['evening_pulse1'];
            $eP2   = $row['evening_pulse2'];

            $evening = [
                'systolic1'  => $eSys1,
                'diastolic1' => $eDia1,
                'pulse1'     => $eP1,
                'systolic2'  => $eSys2,
                'diastolic2' => $eDia2,
                'pulse2'     => $eP2,
                'avg_sys'    => ($eSys1 !== null && $eSys2 !== null) ? round(($eSys1 + $eSys2) / 2) : ($eSys1 ?? $eSys2),
                'avg_dia'    => ($eDia1 !== null && $eDia2 !== null) ? round(($eDia1 + $eDia2) / 2) : ($eDia1 ?? $eDia2),
                'avg_pulse'  => ($eP1 !== null && $eP2 !== null) ? round(($eP1 + $eP2) / 2) : ($eP1 ?? $eP2),
            ];
        }

        return new BloodPressureRecord(
            date: $row['record_date'],
            weight: $row['weight'] !== null ? (float)$row['weight'] : null,
            morning: $morning,
            evening: $evening
        );
    }
}