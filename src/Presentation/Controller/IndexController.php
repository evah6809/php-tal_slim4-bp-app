<?php
namespace App\Presentation\Controller;

use PHPTAL;
use App\Application\UseCase\GetDashboardDataUseCase; // アプリケーション層の例

class IndexController 
{
    private GetDashboardDataUseCase $dashboardUseCase;

    public function __construct(GetDashboardDataUseCase $dashboardUseCase) {
        $this->dashboardUseCase = $dashboardUseCase;
    }

    public function __invoke()
    {
        // 1. ユースケースを呼び出してビジネスロジックの結果（ドメインデータやDTO）を取得
        $data = $this->dashboardUseCase->execute();

        // 2. PHPTALのインスタンス化と設定（プレゼンテーション層の責務）
        $template = new PHPTAL(__DIR__ . '/../../templates/index.html');
        $template->setOutputMode(PHPTAL::HTML5);
        $template->setEncoding('UTF-8');

        // 3. テンプレートへ変数をアサイン（渡す）
        $template->name = $data->getUserName();
        $template->today_str = $data->getTodayString();
        $template->trash_alerts = $data->getTrashAlerts();
        $template->weather = [
            'today' => $data->getWeatherToday(),
            'tomorrow' => $data->getWeatherTomorrow(),
        ];
        
        // アクティブなパスの判定など、ビュー特有のプレゼンテーションロジックもここに書く
        $template->is_logged_in = true; 
        $template->is_home_active = 'text-yellow-300 font-extrabold text-lg';

        // 4. 描画結果を出力
        try {
            echo $template->execute();
        } catch (\Exception $e) {
            // エラーハンドリング
            echo "テンプレート描画エラー: " . $e->getMessage();
        }
    }
}
?>