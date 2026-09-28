<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\PdoBloodPressureRepository;
use App\Domain\BloodPressureRecord;

// セッションの開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 修正後（存在チェックを行う）
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
// または PHP 7.0以降なら Null合体演算子も使えます
$userId = $_SESSION['user_id'] ?? null;

// データベース接続設定 (Docker環境のMySQL)
$host = 'db';
$db   = 'health-bp';
$user = 'root';
$pass = 'evah6809';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    echo "データベース接続エラー: " . $e->getMessage();
    exit;
}

$repository = new PdoBloodPressureRepository($pdo);
$todayStr = date('Y-m-d');

// ==========================================
// 🔐 1. ログイン状態のチェック
// ==========================================
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    // 【未ログインの場合】 Googleログイン画面を表示する
    
    // Google Client の初期化
    $client = new Google_Client();
    $client->setClientId('494370894984-9v8s8bkl376njn1tc2o9jsno650jhskb.apps.googleusercontent.com');
    $client->setClientSecret('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');
    // リダイレクト先（あとで作成するコールバック用ファイル）
    $client->setRedirectUri('http://localhost:8080/auth/callback.php'); 
    $client->addScope('email');
    $client->addScope('profile');

    $googleLoginUrl = $client->createAuthUrl();

    try {
        $template = new PHPTAL(__DIR__ . '/../templates/login.html');
        $template->setOutputMode(PHPTAL::HTML5);
        $template->setEncoding('UTF-8');
        
        // テンプレートへGoogleログインURLを渡す
        $template->google_login_url = $googleLoginUrl;

        echo $template->execute();
    } catch (Exception $e) {
        echo "テンプレート描画エラー: " . $e->getMessage();
    }
    exit; // 未ログイン時はここで処理を終了
}

// ======================================================
// 📡 2. AJAX用：指定日のデータを取得するエンドポイント
// ======================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_record') {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? $todayStr;
    $userId = $_SESSION['user_id'] ?? null; // 先にユーザーIDを取得

    // user_id と date を両方渡す
    $record = $repository->findByDate($date, $userId);

    if ($record) {
        echo json_encode([
            'status' => 'success',
            'data' => $record->toArray()
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'date' => $date,
                'weight' => null,
                'morning' => null,
                'evening' => null
            ]
        ]);
    }
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'get_record') {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? $todayStr;
    $record = $repository->findByDate($date);
    $userId = $_SESSION['user_id'] ?? null; // ログイン中のユーザーID

    if ($record) {
        echo json_encode([
            'status' => 'success',
            'data' => $record->toArray()
        ]);
    } else {
        // 👇 データがない場合も status を 'success' にして、データの中身を null にする
        echo json_encode([
            'status' => 'success',
            'data' => [
                'date' => $date,
                'weight' => null,
                'morning' => null,
                'evening' => null
            ]
        ]);
    }
    exit;
}

// ==========================================
// 書き込み・更新処理 (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ログイン中のユーザーIDをセッションから取得
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        header('Location: /');
        exit;
    }
    $date = $_POST['date'] ?? $todayStr;
    $weight = ($_POST['weight'] !== '') ? (float)$_POST['weight'] : null;

    $morning = [
        'systolic1'  => $_POST['m_systolic1'] !== '' ? (int)$_POST['m_systolic1'] : null,
        'diastolic1' => $_POST['m_diastolic1'] !== '' ? (int)$_POST['m_diastolic1'] : null,
        'pulse1'     => $_POST['m_pulse1'] !== '' ? (int)$_POST['m_pulse1'] : null,
        'systolic2'  => $_POST['m_systolic2'] !== '' ? (int)$_POST['m_systolic2'] : null,
        'diastolic2' => $_POST['m_diastolic2'] !== '' ? (int)$_POST['m_diastolic2'] : null,
        'pulse2'     => $_POST['m_pulse2'] !== '' ? (int)$_POST['m_pulse2'] : null,
    ];

    $evening = [
        'systolic1'  => $_POST['e_systolic1'] !== '' ? (int)$_POST['e_systolic1'] : null,
        'diastolic1' => $_POST['e_diastolic1'] !== '' ? (int)$_POST['e_diastolic1'] : null,
        'pulse1'     => $_POST['e_pulse1'] !== '' ? (int)$_POST['e_pulse1'] : null,
        'systolic2'  => $_POST['e_systolic2'] !== '' ? (int)$_POST['e_systolic2'] : null,
        'diastolic2' => $_POST['e_diastolic2'] !== '' ? (int)$_POST['e_diastolic2'] : null,
        'pulse2'     => $_POST['e_pulse2'] !== '' ? (int)$_POST['e_pulse2'] : null,
    ];

    // ドメインモデルの生成と保存
    $record = new BloodPressureRecord($date, $weight, $morning, $evening);
    $repository->save($record, $userId);

    // 保存後はトップへリダイレクト
    header('Location: /?date=' . $targetDate);
    exit;
}

// ==============================================
// 🏠 3. ログイン済み：通常の血圧入力画面を描画
// ==============================================
try {
    $template = new PHPTAL(__DIR__ . '/../templates/index.html');
    $template->setOutputMode(PHPTAL::HTML5);
    $template->setEncoding('UTF-8');
    
    // テンプレートへ渡す値の安全な設定
    $template->target_date = isset($targetDate) ? $targetDate : date('Y-m-d');
    $template->record = isset($record) ? $record : null;
    $template->today_str = date('Y-m-d');

    $template->is_home_active = 'font-bold text-white border-b-2 border-white pb-1';
    $template->is_history_active = '';
   
    // セッションにユーザーIDがあればログイン中とみなす
    $template->is_logged_in = true;
    
    // もしGoogleから取得した名前があればそれを表示、なければデフォルト
    $template->name = $_SESSION['user_name'] ?? 'ゲスト利用者'; 

    echo $template->execute();
} catch (Exception $e) {
    echo "テンプレート描画エラー: " . $e->getMessage();
}
