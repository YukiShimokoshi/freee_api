<?php
/**
 * テンプレート削除API
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

// POST データを取得
$input = json_decode(file_get_contents('php://input'), true);
$templateName = $input['name'] ?? '';

error_log("delete_template.php: Received template name: " . $templateName);
error_log("delete_template.php: Input data: " . json_encode($input));

if (empty($templateName)) {
  error_log("delete_template.php: Template name is empty");
  echo json_encode(['success' => false, 'error' => 'Template name is required']);
  exit;
}

// 削除前の状態を記録
$templatesBeforeDelete = getDealTemplates();
$templateDir = dirname(__DIR__) . '/templates';

// ディレクトリ内のファイル一覧（削除前）
$filesBeforeDelete = [];
if (is_dir($templateDir)) {
    $files = scandir($templateDir);
    $filesBeforeDelete = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'json';
    });
    $filesBeforeDelete = array_values($filesBeforeDelete);
}

error_log("delete_template.php: Templates before delete: " . count($templatesBeforeDelete));
error_log("delete_template.php: Files before delete: " . json_encode($filesBeforeDelete));

// テンプレートを削除
$result = deleteDealTemplate($templateName);

error_log("delete_template.php: Delete result: " . ($result ? 'true' : 'false'));

// 削除後の状態を記録
$templatesAfterDelete = getDealTemplates();

// ディレクトリ内のファイル一覧（削除後）
$filesAfterDelete = [];
if (is_dir($templateDir)) {
    $files = scandir($templateDir);
    $filesAfterDelete = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'json';
    });
    $filesAfterDelete = array_values($filesAfterDelete);
}

error_log("delete_template.php: Templates after delete: " . count($templatesAfterDelete));
error_log("delete_template.php: Files after delete: " . json_encode($filesAfterDelete));

// レスポンス
header('Content-Type: application/json');

// デバッグ情報も含めてレスポンス
$response = [
  'success' => $result,
  'error' => $result ? null : 'Failed to delete template',
  'template_name' => $templateName,
  'debug_info' => [
    'templates_directory' => $templateDir,
    'directory_exists' => is_dir($templateDir),
    'directory_writable' => is_writable($templateDir),
    'templates_before_count' => count($templatesBeforeDelete),
    'templates_after_count' => count($templatesAfterDelete),
    'files_before_count' => count($filesBeforeDelete),
    'files_after_count' => count($filesAfterDelete),
    'files_before' => $filesBeforeDelete,
    'files_after' => $filesAfterDelete,
    'templates_before_names' => array_column($templatesBeforeDelete, 'name'),
    'templates_after_names' => array_column($templatesAfterDelete, 'name')
  ]
];

error_log("delete_template.php: Final response: " . json_encode($response));

echo json_encode($response);
?>
