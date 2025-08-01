<?php
/**
 * 取引テンプレート関連の関数をまとめるファイル
 */

/**
 * テンプレートディレクトリのパス
 */
function getTemplateDir() {
  return dirname(__DIR__) . '/templates';
}

/**
 * テンプレートファイルのパス
 * @param string $templateId テンプレートID（ファイル名用）
 */
function getTemplateFilePath($templateId) {
  return getTemplateDir() . '/' . $templateId . '.json';
}

/**
 * テンプレート名からファイル安全な ID を生成
 * @param string $templateName テンプレート名
 * @return string ファイル安全なID
 */
function generateTemplateId($templateName) {
  // 日本語を含む名前から安全なファイル名を生成
  $timestamp = time();
  $hash = substr(md5($templateName . $timestamp), 0, 8);
  return 'template_' . $timestamp . '_' . $hash;
}

/**
 * テンプレートディレクトリが存在しない場合は作成
 */
function ensureTemplateDir() {
  $dir = getTemplateDir();
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
}

/**
 * 取引テンプレートを保存する
 * @param string $templateName テンプレート名
 * @param array $dealData 取引データ
 * @return bool 成功時true、失敗時false
 */
function saveDealTemplate($templateName, $dealData) {
  // テンプレート名のバリデーション
  if (empty($templateName)) {
    return false;
  }
  
  ensureTemplateDir();
  
  // ファイル安全なIDを生成
  $templateId = generateTemplateId($templateName);
  
  // 保存用データの準備（日付と金額は除外）
  $templateData = [
    'id' => $templateId,
    'name' => $templateName,
    'created_at' => date('Y-m-d H:i:s'),
    'data' => [
      'type' => $dealData['type'] ?? '',
      'account_item_id' => $dealData['account_item_id'] ?? '',
      'tax_code' => $dealData['tax_code'] ?? '',
      'from_walletable_id' => $dealData['from_walletable_id'] ?? '',
      'item_id' => $dealData['item_id'] ?? '',
      'ref_number' => $dealData['ref_number'] ?? '',
      'description' => $dealData['description'] ?? ''
    ]
  ];
  
  $filePath = getTemplateFilePath($templateId);
  $json = json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  
  return file_put_contents($filePath, $json) !== false;
}

/**
 * 取引テンプレート一覧を取得する
 * @return array テンプレート一覧
 */
function getDealTemplates() {
  $templates = [];
  $dir = getTemplateDir();
  
  if (!is_dir($dir)) {
    return $templates;
  }
  
  $files = glob($dir . '/*.json');
  foreach ($files as $file) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if ($data && isset($data['name'])) {
      $templates[] = [
        'id' => $data['id'] ?? basename($file, '.json'),
        'name' => $data['name'],
        'created_at' => $data['created_at'] ?? '',
        'data' => $data['data'] ?? []
      ];
    }
  }
  
  // 作成日時の降順でソート
  usort($templates, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
  });
  
  return $templates;
}

/**
 * 取引テンプレートを読み込む
 * @param string $templateName テンプレート名（表示名）
 * @return array|false テンプレートデータまたは失敗時false
 */
function loadDealTemplate($templateName) {
  $templates = getDealTemplates();
  
  // 名前でテンプレートを検索
  foreach ($templates as $template) {
    if ($template['name'] === $templateName) {
      return $template['data'];
    }
  }
  
  return false;
}

/**
 * 取引テンプレートを削除する
 * @param string $templateName テンプレート名（表示名）
 * @return bool 成功時true、失敗時false
 */
function deleteDealTemplate($templateName) {
  $templates = getDealTemplates();
  
  // 名前でテンプレートを検索
  foreach ($templates as $template) {
    if ($template['name'] === $templateName) {
      $filePath = getTemplateFilePath($template['id']);
      if (file_exists($filePath)) {
        return unlink($filePath);
      }
    }
  }
  
  return false;
}

/**
 * テンプレート名が既に存在するかチェック
 * @param string $templateName テンプレート名
 * @return bool 存在する場合true
 */
function templateExists($templateName) {
  $templates = getDealTemplates();
  
  foreach ($templates as $template) {
    if ($template['name'] === $templateName) {
      return true;
    }
  }
  
  return false;
}
?>
