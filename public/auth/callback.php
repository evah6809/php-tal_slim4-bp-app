<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// セッションの開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Google Client の初期化（index.php と同じ設定）
$client = new Google_Client();
$client->setClientId('494370894984-9v8s8bkl376njn1tc2o9jsno650jhskb.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost:8080/auth/callback.php');

// 1. Googleから返ってきた認可コード（code）があるか確認
$code = $_GET['code'] ?? null;

if (!$code) {
    // コードがない場合はエラーまたはログイン画面へリダイレクト
    header('Location: /');
    exit;
}

try {
    // 2. 認可コードを使ってアクセストークンを取得
    $token = $client->fetchAccessTokenWithAuthCode($code);
    
    if (array_key_exists('error', $token)) {
        throw new Exception('認証エラー: ' . $token['error_description']);
    }

    $client->setAccessToken($token);

    // 3. Google Oauth2 API を使ってユーザー情報を取得
    $oauth2 = new Google_Service_Oauth2($client);
    $userInfo = $oauth2->userinfo->get();

    // 取得できる主な情報例：
    // $userInfo->id       -> Googleの固有ID (user_idとして利用可能)
    // $userInfo->email    -> メールアドレス
    // $userInfo->name     -> ユーザー名

    // 4. セッションにユーザー情報を保存
    // データベースの user_id に合わせるため、Googleの固有IDを保存します
    $_SESSION['user_id'] = $userInfo->id; 
    $_SESSION['user_email'] = $userInfo->email;
    $_SESSION['user_name'] = $userInfo->name;

    // 5. ログインが完了したので、トップページ（血圧記録画面）へリダイレクト
    header('Location: /');
    exit;

} catch (Exception $e) {
    // エラー時の処理（必要に応じてログ出力やエラーメッセージ表示）
    echo "ログイン処理中にエラーが発生しました: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<br><a href="/">トップへ戻る</a>';
    exit;
}