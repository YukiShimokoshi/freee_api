<?php
/**
 * Freee API 事業所関連の関数
 */

require_once 'call_token.php';

/**
 * 事業所一覧を取得する
 * @return array|false 事業所一覧データまたはfalse（エラー時、未認証時）
 */
function getCompanies() {
  // 認証チェック
  if (!isAuthenticated()) {
    return false;
  }
  
  $url = 'https://api.freee.co.jp/api/1/companies';
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
 * 事業所一覧を整形して表示用配列として返す
 * @return array 表示用事業所データ
 */
function getFormattedCompanies() {
  $companiesData = getCompanies();
  
  if ($companiesData === false || !isset($companiesData['companies'])) {
    return [];
  }
  
  $formattedCompanies = [];
  
  foreach ($companiesData['companies'] as $company) {
    $formattedCompanies[] = [
      'id' => $company['id'],
      'name' => $company['name'] ?? '未設定',
      'name_kana' => $company['name_kana'] ?? '未設定',
      'display_name' => $company['display_name'],
      'company_number' => $company['company_number'],
      'role' => $company['role']
    ];
  }
  
  return $formattedCompanies;
}
?>
