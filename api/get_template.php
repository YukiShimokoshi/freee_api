<?php
/**
 * テンプレート取得API
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

// テンプレート名を取得
$templateName = $_GET['name'] ?? '';

if (empty($templateName)) {
  echo json_encode(['success' => false, 'error' => 'Template name is required']);
  exit;
}

// テンプレートを読み込み
$template = loadDealTemplate($templateName);

if ($template === false) {
  echo json_encode(['success' => false, 'error' => 'Template not found']);
  exit;
}

// 成功レスポンス
header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'template' => $template
]);
?>
