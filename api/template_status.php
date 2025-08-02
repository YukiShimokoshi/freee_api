<?php
/**
 * テンプレートディレクトリの状況確認ツール
 */

require_once '../_functions/deal_templates.php';
require_once '../_functions/call_token.php';

// セッション開始
session_start();

// 認証チェック
if (!isAuthenticated()) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

$templateDir = dirname(__DIR__) . '/templates';

$response = [
    'success' => true,
    'template_directory' => $templateDir,
    'directory_exists' => is_dir($templateDir),
    'directory_writable' => is_writable($templateDir),
    'directory_permissions' => is_dir($templateDir) ? substr(sprintf('%o', fileperms($templateDir)), -4) : 'N/A',
    'templates' => [],
    'raw_files' => [],
    'errors' => []
];

try {
    // テンプレート関数を使用してテンプレート一覧を取得
    $templates = getDealTemplates();
    $response['templates'] = $templates;
    $response['template_count'] = count($templates);
    
    // ディレクトリ内の生のファイル一覧も取得
    if (is_dir($templateDir)) {
        $files = scandir($templateDir);
        $jsonFiles = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filePath = $templateDir . '/' . $file;
                $jsonFiles[] = [
                    'filename' => $file,
                    'size' => filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
                    'readable' => is_readable($filePath),
                    'writable' => is_writable($filePath)
                ];
            }
        }
        $response['raw_files'] = $jsonFiles;
        $response['raw_file_count'] = count($jsonFiles);
    } else {
        $response['errors'][] = 'Template directory does not exist';
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['errors'][] = 'Exception: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
