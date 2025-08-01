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

if (empty($templateName)) {
  echo json_encode(['success' => false, 'error' => 'Template name is required']);
  exit;
}

// テンプレートを削除
$result = deleteDealTemplate($templateName);

// レスポンス
header('Content-Type: application/json');
echo json_encode([
  'success' => $result,
  'error' => $result ? null : 'Failed to delete template'
]);
?>
