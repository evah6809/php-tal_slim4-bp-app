<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Infrastructure\PdoBloodPressureRepository;

// セッション開始
session_start();

// Composerのオートローダー読み込み
require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// データベース接続（環境に合わせて変更してください）
$pdo = new PDO('mysql:host=db;dbname=health-bp;charset=utf8mb4', 'root', 'evah6809', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// トップページ（血圧入力フォーム & 日付ごとのデータ表示）
$app->get('/', function (Request $request, Response $response) use ($pdo) {
    // 1. ログイン認証チェック（未ログインなら /login へリダイレクト）
    if (!isset($_SESSION['user_id'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    $userId = $_SESSION['user_id'];

    // 2. クエリパラメータから日付を取得（指定がなければ本日の日付）
    $queryParams = $request->getQueryParams();
    $targetDate = $queryParams['date'] ?? date('Y-m-d');
    $today_str = date('Y-m-d');

    // 3. リポジトリのインスタンス化
    $repository = new PdoBloodPressureRepository($pdo);

    // 4. JavaScriptからの非同期リクエスト（?action=get_record）の処理
    if (isset($queryParams['action']) && $queryParams['action'] === 'get_record') {
        $record = $repository->findByDate($targetDate, $userId);
        
        $data = [
            'status' => 'success',
            'data' => $record ? $record->toArray() : [
                'weight' => null,
                'morning' => null,
                'evening' => null
            ]
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // 5. 通常の画面表示用データ取得
    $record = $repository->findByDate($targetDate, $userId);
    $recordData = $record ? $record->toArray() : null;

    try {
        // 6. PHPTALの初期化とテンプレートへの値の割り当て
        $template = new PHPTAL(__DIR__ . '/../templates/index.html');
        $template->userId = $userId;
        $template->targetDate = $targetDate;
        $template->today_str = $today_str;
        $template->record = $recordData;
        $template->name = $_SESSION['user_name'] ?? 'ゲストユーザー';
        $template->is_logged_in = $_SESSION['user_name'] ?? false;
        $template->is_home_active = $_SESSION['user_name'] ?? false;
        $template->is_history_active = $_SESSION['user_name'] ?? false;
        $html = $template->execute();
        $response->getBody()->write($html);
    } catch (Exception $e) {
        $response->getBody()->write("PHPTAL Error: " . $e->getMessage());
        return $response->withStatus(500);
    }

    return $response;
});

// ログイン画面
$app->get('/login', function (Request $request, Response $response) {
    try {
        // Google Client の初期化とURLの生成を追加
        $client = new Google_Client();
        $client->setClientId('494370894984-9v8s8bkl376njn1tc2o9jsno650jhskb.apps.googleusercontent.com');
        $client->setClientSecret('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');
        $client->setRedirectUri('http://localhost:8080/auth/callback.php');
        $client->addScope('email');
        $client->addScope('profile');

        $googleLoginUrl = $client->createAuthUrl();

        $template = new PHPTAL(__DIR__ . '/../templates/login.html');
        // PHPTALへ確実に変数を渡す
        $template->set('google_login_url', $googleLoginUrl);
        
        $html = $template->execute();
        $response->getBody()->write($html);
    } catch (Exception $e) {
        $response->getBody()->write("Login Template Error: " . $e->getMessage());
        return $response->withStatus(500);
    }
    return $response;
});

// アプリの実行
$app->run();