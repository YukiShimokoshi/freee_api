<?php
/**
 * 口座（決済口座）関連の関数をまとめるファイル
 */

require_once 'call_token.php';
require_once 'account_items.php';

/**
 * プライベート資金用の勘定科目IDを取得する
 * @param int $companyId 事業所ID
 * @param string $dealType 取引タイプ（income: 収入, expense: 支出）
 * @return int|false 勘定科目IDまたは失敗時false
 */
function getPrivateAccountItemId($companyId, $dealType) {
  // 認証チェック
  if (!isAuthenticated()) {
    return false;
  }
  
  // 勘定科目一覧を取得
  $accountItems = getAccountItems($companyId);
  if (!$accountItems || !isset($accountItems['account_items'])) {
    return false;
  }
  
  // 取引タイプに応じて検索する勘定科目を決定
  if ($dealType === 'income') {
    // 収入取引の場合：事業主貸を検索
    $targetNames = ['事業主貸'];
  } else {
    // 支出取引の場合：事業主借を検索
    $targetNames = ['事業主借'];
  }
  
  // 該当する勘定科目を検索
  foreach ($targetNames as $targetName) {
    foreach ($accountItems['account_items'] as $item) {
      if (strpos($item['name'], $targetName) !== false) {
        return $item['id'];
      }
    }
  }
  
  // 見つからない場合はnullを返す
  return null;
}

/**
 * 口座一覧を取得する
 * @param int $companyId 事業所ID
 * @param string $type 口座種別（bank_account, credit_card, wallet）
 * @param bool $withBalance 残高情報を含める
 * @param bool $withLastSyncedAt 最終同期成功日時を含める
 * @param bool $withSyncStatus 同期ステータスを含める
 * @return array|false 口座一覧または失敗時false
 */
function getWalletables($companyId, $type = null, $withBalance = false, $withLastSyncedAt = false, $withSyncStatus = false) {
  // 認証チェック
  if (!isAuthenticated()) {
    return false;
  }
  
  $url = "https://api.freee.co.jp/api/1/walletables";
  
  $queryParams = [
    'company_id' => $companyId,
    'with_balance' => $withBalance ? 'true' : 'false',
    'with_last_synced_at' => $withLastSyncedAt ? 'true' : 'false',
    'with_sync_status' => $withSyncStatus ? 'true' : 'false'
  ];
  
  if ($type !== null) {
    $queryParams['type'] = $type;
  }
  
  $url .= '?' . http_build_query($queryParams);
  
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
  return $data['walletables'] ?? false;
}

/**
 * 銀行口座一覧を取得する
 * @param int $companyId 事業所ID
 * @return array|false 銀行口座一覧または失敗時false
 */
function getBankAccounts($companyId) {
  return getWalletables($companyId, 'bank_account');
}

/**
 * クレジットカード一覧を取得する
 * @param int $companyId 事業所ID
 * @return array|false クレジットカード一覧または失敗時false
 */
function getCreditCards($companyId) {
  return getWalletables($companyId, 'credit_card');
}

/**
 * その他の決済口座一覧を取得する
 * @param int $companyId 事業所ID
 * @return array|false その他の決済口座一覧または失敗時false
 */
function getWallets($companyId) {
  return getWalletables($companyId, 'wallet');
}

/**
 * フォーマット済み口座一覧を取得（選択ボックス用）
 * @param int $companyId 事業所ID
 * @return array フォーマット済み口座配列
 */
function getFormattedWalletables($companyId) {
  $walletables = getWalletables($companyId);
  
  if ($walletables === false) {
    return [];
  }
  
  $formatted = [];
  foreach ($walletables as $wallet) {
    $formatted[] = [
      'id' => $wallet['id'],
      'name' => $wallet['name'],
      'type' => $wallet['type'],
      'bank_id' => $wallet['bank_id'] ?? null,
      'group_name' => getWalletableGroupName($wallet['type']),
      'display_name' => $wallet['name'] . ' (' . getWalletableTypeName($wallet['type']) . ')'
    ];
  }
  
  return $formatted;
}

/**
 * 口座種別ごとにグループ化した口座一覧を取得
 * @param int $companyId 事業所ID
 * @return array 口座種別でグループ化された口座配列
 */
function getGroupedWalletables($companyId) {
  $walletables = getWalletables($companyId);
  
  if ($walletables === false) {
    return [];
  }
  
  $grouped = [
    'bank_account' => [],
    'credit_card' => [],
    'wallet' => []
  ];
  
  foreach ($walletables as $wallet) {
    $type = $wallet['type'];
    if (isset($grouped[$type])) {
      $grouped[$type][] = [
        'id' => $wallet['id'],
        'name' => $wallet['name'],
        'type' => $wallet['type'],
        'bank_id' => $wallet['bank_id'] ?? null
      ];
    }
  }
  
  return $grouped;
}

/**
 * 口座種別名を取得
 * @param string $type 口座種別
 * @return string 口座種別名
 */
function getWalletableTypeName($type) {
  switch ($type) {
    case 'bank_account':
      return '銀行口座';
    case 'credit_card':
      return 'クレジットカード';
    case 'wallet':
      return 'その他の決済口座';
    default:
      return $type;
  }
}

/**
 * 口座種別のグループ名を取得
 * @param string $type 口座種別
 * @return string グループ名
 */
function getWalletableGroupName($type) {
  return getWalletableTypeName($type);
}

/**
 * 特定の口座情報を取得
 * @param int $companyId 事業所ID
 * @param int $walletableId 口座ID
 * @return array|null 口座情報またはnull
 */
function getWalletableInfo($companyId, $walletableId) {
  $walletables = getWalletables($companyId);
  
  if ($walletables === false) {
    return null;
  }
  
  foreach ($walletables as $wallet) {
    if ($wallet['id'] == $walletableId) {
      return $wallet;
    }
  }
  
  return null;
}
?>
