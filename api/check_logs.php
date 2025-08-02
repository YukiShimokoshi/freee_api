<?php
/**
 * PHP エラーログを確認するための簡易ツール
 */

header('Content-Type: application/json; charset=utf-8');

// エラーログのパスを取得
$errorLogPath = ini_get('error_log');
if (!$errorLogPath) {
    $errorLogPath = '/var/log/php_errors.log'; // デフォルトパス
}

$response = [
    'success' => false,
    'error_log_path' => $errorLogPath,
    'recent_errors' => []
];

try {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        // 最新の50行を取得
        $lines = file($errorLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recentLines = array_slice($lines, -50);
        
        // deleteDealTemplate関連のログのみ抽出
        $templateRelatedLogs = [];
        foreach ($recentLines as $line) {
            if (strpos($line, 'deleteDealTemplate') !== false) {
                $templateRelatedLogs[] = $line;
            }
        }
        
        $response['success'] = true;
        $response['recent_errors'] = $templateRelatedLogs;
        $response['total_lines'] = count($lines);
        $response['template_related_lines'] = count($templateRelatedLogs);
    } else {
        $response['error'] = 'Error log file not found or not readable: ' . $errorLogPath;
        
        // 代替として、現在のディレクトリに error.log があるかチェック
        $altLogPath = __DIR__ . '/../error.log';
        if (file_exists($altLogPath)) {
            $response['alternative_log'] = $altLogPath;
            $lines = file($altLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLines = array_slice($lines, -20);
            $response['recent_errors'] = $recentLines;
            $response['success'] = true;
        }
    }
} catch (Exception $e) {
    $response['error'] = 'Exception: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
