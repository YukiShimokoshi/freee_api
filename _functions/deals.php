<?php
/**
 * Freee API 取引関連の関数
 */

require_once 'call_token.php';

/**
 * 取引を作成する
 * @param array $dealData 取引データ
 * @return array|false 作成された取引データまたはfalse（エラー時）
 */
function createDeal($dealData) {
  $url = 'https://api.freee.co.jp/api/1/deals';
  $headers = [
    'accept: application/json',
    'Authorization: Bearer ' . getAccessToken(),
    'Content-Type: application/json',
    'X-Api-Version: 2020-06-15'
  ];
  
  // 必須項目のチェック
  $requiredFields = ['issue_date', 'type', 'company_id'];
  foreach ($requiredFields as $field) {
    if (!isset($dealData[$field]) || empty($dealData[$field])) {
      error_log("Required field missing: {$field}");
      return false;
    }
  }
  
  // POSTデータをjson形式で構築
  $postData = json_encode($dealData, JSON_UNESCAPED_UNICODE);
  
  // cURLセッションを初期化
  $ch = curl_init();
  
  // cURLオプションを設定
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 開発環境用
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  
  // APIリクエストを実行
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  // エラーチェック
  if (curl_error($ch)) {
    $errorMsg = 'cURL Error: ' . curl_error($ch);
    error_log($errorMsg);
    curl_close($ch);
    return [
      'success' => false,
      'status_code' => 0,
      'error_message' => $errorMsg
    ];
  }

  curl_close($ch);

  // JSONデコード
  $data = json_decode($response, true);

  // HTTPステータスコードチェック
  if ($httpCode !== 201 && $httpCode !== 200) {
    $errorInfo = [
      'success' => false,
      'status_code' => $httpCode,
      'response' => $response
    ];
    // エラー詳細がjsonの場合はパースして追加
    if (is_array($data) && isset($data['errors'])) {
      $errorInfo['errors'] = $data['errors'];
    } else if (json_last_error() !== JSON_ERROR_NONE) {
      $errorInfo['json_error'] = json_last_error_msg();
    }
    error_log('HTTP Error: ' . $httpCode . ' - ' . $response);
    return $errorInfo;
  }

  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON Error: ' . json_last_error_msg());
    return [
      'success' => false,
      'status_code' => $httpCode,
      'json_error' => json_last_error_msg(),
      'response' => $response
    ];
  }

  return $data;
}

/**
 * 収入取引を作成する
 * @param int $companyId 事業所ID
 * @param string $issueDate 発生日 (YYYY-MM-DD形式)
 * @param array $details 取引明細
 * @param array $options 追加オプション（due_date, partner_id, ref_number, payments等）
 * @return array|false 作成された取引データまたはfalse（エラー時）
 */
function createIncomeDeal($companyId, $issueDate, $details, $options = []) {
  $dealData = [
    'company_id' => $companyId,
    'issue_date' => $issueDate,
    'type' => 'income'
  ];
  
  // オプションをマージ
  $dealData = array_merge($dealData, $options);
  
  // detailsが配列でなければ配列化
  if (!is_array($details)) {
    $details = [$details];
  }
  // 明細が空や不正な場合はエラー
  if (empty($details) || !isset($details[0]) || !is_array($details[0])) {
    return [
      'success' => false,
      'status_code' => 400,
      'errors' => [['type' => 'validation', 'messages' => ['detailsが正しい配列でありません。']]]
    ];
  }
  $dealData['details'] = array_values($details); // インデックス配列化
  return createDeal($dealData);
}

/**
 * 支出取引を作成する
 * @param int $companyId 事業所ID
 * @param string $issueDate 発生日 (YYYY-MM-DD形式)
 * @param array $details 取引明細
 * @param array $options 追加オプション（due_date, partner_id, ref_number, payments等）
 * @return array|false 作成された取引データまたはfalse（エラー時）
 */
function createExpenseDeal($companyId, $issueDate, $details, $options = []) {
  $dealData = [
    'company_id' => $companyId,
    'issue_date' => $issueDate,
    'type' => 'expense'
  ];
  
  // オプションをマージ
  $dealData = array_merge($dealData, $options);
  
  // detailsが配列でなければ配列化
  if (!is_array($details)) {
    $details = [$details];
  }
  // 明細が空や不正な場合はエラー
  if (empty($details) || !isset($details[0]) || !is_array($details[0])) {
    return [
      'success' => false,
      'status_code' => 400,
      'errors' => [['type' => 'validation', 'messages' => ['detailsが正しい配列でありません。']]]
    ];
  }
  $dealData['details'] = array_values($details); // インデックス配列化
  return createDeal($dealData);
}

/**
 * 簡単な収入取引を作成する（基本項目のみ）
 * @param int $companyId 事業所ID
 * @param string $issueDate 発生日 (YYYY-MM-DD形式)
 * @param int $amount 金額
 * @param int $accountItemId 勘定科目ID
 * @param int $taxCode 税区分
 * @param array $options 追加オプション
 * @return array|false 作成された取引データまたはfalse（エラー時）
 */
function createSimpleIncomeDeal($companyId, $issueDate, $amount, $accountItemId, $taxCode = 1, $options = []) {
  $details = [
    [
      'amount' => $amount,
      'account_item_id' => $accountItemId,
      'tax_code' => $taxCode
    ]
  ];
  
  // 品目IDが指定されている場合は追加
  if (!empty($options['item_id'])) {
    $details[0]['item_id'] = $options['item_id'];
  }
  
  return createIncomeDeal($companyId, $issueDate, $details, $options);
}

/**
 * 簡単な支出取引を作成する（基本項目のみ）
 * @param int $companyId 事業所ID
 * @param string $issueDate 発生日 (YYYY-MM-DD形式)
 * @param int $amount 金額
 * @param int $accountItemId 勘定科目ID
 * @param int $taxCode 税区分
 * @param array $options 追加オプション
 * @return array|false 作成された取引データまたはfalse（エラー時）
 */
function createSimpleExpenseDeal($companyId, $issueDate, $amount, $accountItemId, $taxCode = 1, $options = []) {
  $details = [
    [
      'amount' => $amount,
      'account_item_id' => $accountItemId,
      'tax_code' => $taxCode
    ]
  ];
  
  // 品目IDが指定されている場合は追加
  if (!empty($options['item_id'])) {
    $details[0]['item_id'] = $options['item_id'];
  }
  
  return createExpenseDeal($companyId, $issueDate, $details, $options);
}

/**
 * 支払情報を作成する
 * @param int $amount 支払額
 * @param int $walletableId ウォレットID（口座ID等）
 * @param string $walletableType ウォレットタイプ（bank_account, credit_card, wallet, private_account_item）
 * @param string $date 支払日 (YYYY-MM-DD形式)
 * @return array 支払情報配列
 */
function createPayment($amount, $walletableId, $walletableType, $date) {
  return [
    'amount' => $amount,
    'from_walletable_id' => $walletableId,
    'from_walletable_type' => $walletableType,
    'date' => $date
  ];
}

/**
 * 取引明細を作成する
 * @param int $amount 金額
 * @param int $accountItemId 勘定科目ID
 * @param int $taxCode 税区分
 * @param array $options 追加オプション（item_id, section_id, tag_ids, description等）
 * @return array 取引明細配列
 */
function createDealDetail($amount, $accountItemId, $taxCode, $options = []) {
  $detail = [
    'amount' => $amount,
    'account_item_id' => $accountItemId,
    'tax_code' => $taxCode
  ];
  
  return array_merge($detail, $options);
}
?>
