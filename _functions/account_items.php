<?php
/**
 * Freee API 勘定科目関連の関数
 */

require_once 'call_token.php';

/**
 * 勘定科目一覧を取得する
 * @param int $companyId 事業所ID
 * @return array|false 勘定科目一覧データまたはfalse（エラー時、未認証時）
 */
function getAccountItems($companyId) {
  // 認証チェック
  if (!isAuthenticated()) {
    return false;
  }
  
  $url = 'https://api.freee.co.jp/api/1/account_items?company_id=' . $companyId;
  $headers = getApiHeaders();
  
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
 * 勘定科目一覧を整形して表示用配列として返す
 * @param int $companyId 事業所ID
 * @return array 表示用勘定科目データ
 */
function getFormattedAccountItems($companyId) {
  $accountItemsData = getAccountItems($companyId);
  
  if ($accountItemsData === false || !isset($accountItemsData['account_items'])) {
    return [];
  }
  
  $formattedAccountItems = [];
  
  foreach ($accountItemsData['account_items'] as $item) {
    $formattedAccountItems[] = [
      'id' => $item['id'],
      'name' => $item['name'],
      'tax_code' => $item['tax_code'],
      'shortcut' => $item['shortcut'] ?? '',
      'shortcut_num' => $item['shortcut_num'] ?? '',
      'account_category_id' => $item['account_category_id'],
      'corresponding_income_name' => $item['corresponding_income_name'] ?? '',
      'corresponding_expense_name' => $item['corresponding_expense_name'] ?? '',
      'group_name' => $item['group_name'] ?? '',
      'default_tax_code' => $item['default_tax_code'],
      'account_category' => $item['account_category'],
      'categories' => $item['categories'] ?? [],
      'available' => $item['available'],
      'update_date' => $item['update_date'] ?? ''
    ];
  }
  
  return $formattedAccountItems;
}

/**
 * 利用可能な勘定科目のみを取得する
 * @param int $companyId 事業所ID
 * @return array 利用可能な勘定科目データ
 */
function getAvailableAccountItems($companyId) {
  $accountItems = getFormattedAccountItems($companyId);
  
  return array_filter($accountItems, function($item) {
    return $item['available'] === true;
  });
}

/**
 * カテゴリ別に勘定科目を分類する
 * @param int $companyId 事業所ID
 * @return array カテゴリ別の勘定科目データ
 */
function getAccountItemsByCategory($companyId) {
  $accountItems = getFormattedAccountItems($companyId);
  $categorized = [];
  
  foreach ($accountItems as $item) {
    $category = $item['account_category'];
    if (!isset($categorized[$category])) {
      $categorized[$category] = [];
    }
    $categorized[$category][] = $item;
  }
  
  return $categorized;
}

/**
 * 勘定科目IDで特定の勘定科目を検索する
 * @param int $companyId 事業所ID
 * @param int $accountItemId 勘定科目ID
 * @return array|null 勘定科目データまたはnull（見つからない場合）
 */
function findAccountItemById($companyId, $accountItemId) {
  $accountItems = getFormattedAccountItems($companyId);
  
  foreach ($accountItems as $item) {
    if ($item['id'] == $accountItemId) {
      return $item;
    }
  }
  
  return null;
}

/**
 * 勘定科目名で検索する
 * @param int $companyId 事業所ID
 * @param string $searchName 検索する勘定科目名（部分一致）
 * @return array マッチした勘定科目データ
 */
function searchAccountItemsByName($companyId, $searchName) {
  $accountItems = getFormattedAccountItems($companyId);
  
  return array_filter($accountItems, function($item) use ($searchName) {
    return strpos($item['name'], $searchName) !== false;
  });
}
?>
