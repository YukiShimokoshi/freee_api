<?php
/**
 * 税区分関連の関数をまとめるファイル
 */

require_once 'call_token.php';

/**
 * 税区分一覧を取得する
 * @param int $companyId 事業所ID
 * @param string $displayCategory 税区分の表示カテゴリ（オプション）
 * @param bool $available 使用設定（オプション）
 * @return array|false 税区分一覧または失敗時false
 */
function getTaxCodes($companyId, $displayCategory = null, $available = null) {
  // 認証チェック
  if (!isAuthenticated()) {
    return false;
  }
  
  $url = "https://api.freee.co.jp/api/1/taxes/companies/{$companyId}";
  
  $queryParams = [];
  if ($displayCategory !== null) {
    $queryParams['display_category'] = $displayCategory;
  }
  if ($available !== null) {
    $queryParams['available'] = $available ? 'true' : 'false';
  }
  
  if (!empty($queryParams)) {
    $url .= '?' . http_build_query($queryParams);
  }
  
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => getApiHeaders()
  ]);
  
  $response = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  
  if ($httpCode !== 200) {
    return false;
  }
  
  $data = json_decode($response, true);
  return $data['taxes'] ?? false;
}

/**
 * 使用可能な税区分のみを取得する
 * @param int $companyId 事業所ID
 * @return array|false 使用可能な税区分一覧または失敗時false
 */
function getAvailableTaxCodes($companyId) {
  return getTaxCodes($companyId, null, true);
}

/**
 * フォーマット済み税区分一覧を取得（選択ボックス用）
 * @param int $companyId 事業所ID
 * @return array フォーマット済み税区分配列
 */
function getFormattedTaxCodes($companyId) {
  $taxCodes = getAvailableTaxCodes($companyId);
  
  if ($taxCodes === false) {
    return [];
  }
  
  $formatted = [];
  foreach ($taxCodes as $tax) {
    $formatted[] = [
      'code' => $tax['code'],
      'name' => $tax['name'],
      'name_ja' => $tax['name_ja'],
      'rate' => $tax['rate'],
      'description' => $tax['name_ja'] . ' (' . $tax['rate'] . '%)'
    ];
  }
  
  return $formatted;
}

/**
 * 税区分コードから税区分情報を取得
 * @param int $companyId 事業所ID
 * @param int $taxCode 税区分コード
 * @return array|null 税区分情報または見つからない場合null
 */
function getTaxCodeInfo($companyId, $taxCode) {
  $taxCodes = getTaxCodes($companyId);
  
  if ($taxCodes === false) {
    return null;
  }
  
  foreach ($taxCodes as $tax) {
    if ($tax['code'] == $taxCode) {
      return $tax;
    }
  }
  
  return null;
}
?>
