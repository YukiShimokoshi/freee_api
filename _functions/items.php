<?php
require_once 'call_token.php';

/**
 * 品目一覧を取得
 */
function getItems($companyId) {
  // 認証チェック
  if (!isAuthenticated()) {
    return false;
  }
  
  $url = "https://api.freee.co.jp/api/1/items?company_id=" . urlencode($companyId);
  $headers = getApiHeaders();
  
  if (!$headers) {
    return false;
  }
  
  // cURLセッションを初期化
  $ch = curl_init();
  
  // cURLオプションを設定
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 開発環境用
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  
  // APIリクエストを実行
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  
  // エラーチェック
  if (curl_error($ch)) {
    error_log('cURL Error: ' . curl_error($ch));
    curl_close($ch);
    return false;
  }
  
  curl_close($ch);
  
  // HTTPステータスコードチェック
  if ($httpCode !== 200) {
    error_log('HTTP Error: ' . $httpCode . ' - ' . $response);
    return false;
  }
  
  // JSONデコード
  $data = json_decode($response, true);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON Error: ' . json_last_error_msg());
    return false;
  }
  
  return $data;
}

/**
 * 品目一覧を整形して取得
 */
function getFormattedItems($companyId) {
  $items = getItems($companyId);
  
  if ($items === false) {
    return [];
  }
  
  if (!isset($items['items']) || !is_array($items['items'])) {
    return [];
  }
  
  $formattedItems = [];
  foreach ($items['items'] as $item) {
    // available が true の品目のみを含める
    if (isset($item['available']) && $item['available'] === true) {
      $formattedItems[] = [
        'id' => $item['id'],
        'name' => $item['name'],
        'shortcut1' => $item['shortcut1'] ?? '',
        'shortcut2' => $item['shortcut2'] ?? '',
        'code' => $item['code'] ?? ''
      ];
    }
  }
  
  // 名前順でソート
  usort($formattedItems, function($a, $b) {
    return strcmp($a['name'], $b['name']);
  });
  
  return $formattedItems;
}
?>
