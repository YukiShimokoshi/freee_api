<?php
/**
 * Freee API OAuth認証関連の関数
 */

// OAuth設定
define('FREEE_CLIENT_ID', '618180911857504');
define('FREEE_CLIENT_SECRET', 'l1CtgDJorAVXW2n3f1WDi0eAnFoRd-9Cs-MWgjHwGdXUud43hTk3ouOYNxwOOJMDZwLk5PeghNm8KTyfQtKwhw');
define('FREEE_REDIRECT_URI', 'urn:ietf:wg:oauth:2.0:oob');
define('FREEE_AUTH_BASE_URL', 'https://accounts.secure.freee.co.jp/public_api/authorize');
define('FREEE_TOKEN_URL', 'https://accounts.secure.freee.co.jp/public_api/token');

/**
 * 認証URLを生成する
 * @param string $state CSRF対策用のランダム文字列
 * @return string 認証URL
 */
function generateAuthUrl($state = null) {
  if ($state === null) {
    $state = generateRandomState();
  }
  
  $params = [
    'response_type' => 'code',
    'client_id' => FREEE_CLIENT_ID,
    'redirect_uri' => FREEE_REDIRECT_URI,
    'state' => $state,
    'prompt' => 'select_company'
  ];
  
  return FREEE_AUTH_BASE_URL . '?' . http_build_query($params);
}

/**
 * CSRF対策用のランダムな文字列を生成
 * @param int $length 文字列の長さ
 * @return string ランダムな文字列
 */
function generateRandomState($length = 32) {
  return bin2hex(random_bytes($length / 2));
}

/**
 * 認可コードからアクセストークンを取得
 * @param string $authCode 認可コード
 * @return array|false トークン情報または失敗時false
 */
function getAccessTokenFromCode($authCode) {
  $postData = [
    'grant_type' => 'authorization_code',
    'client_id' => FREEE_CLIENT_ID,
    'client_secret' => FREEE_CLIENT_SECRET,
    'code' => $authCode,
    'redirect_uri' => FREEE_REDIRECT_URI
  ];
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, FREEE_TOKEN_URL);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
  ]);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($httpCode !== 200) {
    error_log("Token request failed. HTTP Code: $httpCode, Response: $response");
    return false;
  }
  
  $data = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    return false;
  }
  
  return $data;
}

/**
 * リフレッシュトークンからアクセストークンを更新
 * @param string $refreshToken リフレッシュトークン
 * @return array|false 新しいトークン情報または失敗時false
 */
function refreshAccessToken($refreshToken) {
  $postData = [
    'grant_type' => 'refresh_token',
    'client_id' => FREEE_CLIENT_ID,
    'client_secret' => FREEE_CLIENT_SECRET,
    'refresh_token' => $refreshToken
  ];
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, FREEE_TOKEN_URL);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
  ]);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($httpCode !== 200) {
    error_log("Token refresh failed. HTTP Code: $httpCode, Response: $response");
    return false;
  }
  
  $data = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    return false;
  }
  
  return $data;
}

/**
 * トークン情報をファイルに保存
 * @param array $tokenData トークン情報
 * @return bool 成功時true
 */
function saveTokenToFile($tokenData) {
  $tokenFile = dirname(__FILE__) . '/../_data/token.json';
  $tokenDir = dirname($tokenFile);
  
  // ディレクトリが存在しない場合は作成
  if (!is_dir($tokenDir)) {
    mkdir($tokenDir, 0755, true);
  }
  
  // 有効期限を計算して追加
  if (isset($tokenData['expires_in'])) {
    $tokenData['expires_at'] = time() + $tokenData['expires_in'];
  }
  
  // リフレッシュで取得した場合、古いcompany_idとexternal_cidを保持
  $existingData = loadTokenFromFile();
  if ($existingData !== null) {
    if (!isset($tokenData['company_id']) && isset($existingData['company_id'])) {
      $tokenData['company_id'] = $existingData['company_id'];
    }
    if (!isset($tokenData['external_cid']) && isset($existingData['external_cid'])) {
      $tokenData['external_cid'] = $existingData['external_cid'];
    }
  }
  
  $result = file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
  return $result !== false;
}

/**
 * ファイルからトークン情報を読み込み
 * @return array|null トークン情報またはnull
 */
function loadTokenFromFile() {
  $tokenFile = dirname(__FILE__) . '/../_data/token.json';
  
  if (!file_exists($tokenFile)) {
    return null;
  }
  
  $content = file_get_contents($tokenFile);
  if ($content === false) {
    return null;
  }
  
  $data = json_decode($content, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return null;
  }
  
  return $data;
}

/**
 * トークンが有効かどうかチェック
 * @param array $tokenData トークン情報
 * @return bool 有効時true
 */
function isTokenValid($tokenData) {
  if (!isset($tokenData['access_token']) || !isset($tokenData['expires_at'])) {
    return false;
  }
  
  // 5分のマージンを設けて有効期限をチェック
  return time() < ($tokenData['expires_at'] - 300);
}

/**
 * 有効なアクセストークンを取得（自動更新機能付き）
 * @return string|null 有効なアクセストークンまたはnull
 */
function getValidAccessToken() {
  $tokenData = loadTokenFromFile();
  
  if ($tokenData === null) {
    // トークンファイルが存在しない
    return null;
  }
  
  if (isTokenValid($tokenData)) {
    // トークンが有効
    return $tokenData['access_token'];
  }
  
  // トークンが無効な場合、リフレッシュトークンで更新を試行
  if (isset($tokenData['refresh_token'])) {
    $newTokenData = refreshAccessToken($tokenData['refresh_token']);
    if ($newTokenData !== false) {
      // 新しいトークンを保存
      saveTokenToFile($newTokenData);
      return $newTokenData['access_token'];
    }
  }
  
  // リフレッシュも失敗した場合
  return null;
}

/**
 * OAuth認証で取得したトークンデータから事業所IDを取得
 * @return string|null 事業所IDまたはnull
 */
function getOAuthCompanyId() {
  $tokenData = loadTokenFromFile();
  
  if ($tokenData === null) {
    return null;
  }
  
  return $tokenData['company_id'] ?? null;
}

/**
 * OAuth認証で取得したトークンデータからexternal_cidを取得
 * @return string|null external_cidまたはnull
 */
function getOAuthExternalCid() {
  $tokenData = loadTokenFromFile();
  
  if ($tokenData === null) {
    return null;
  }
  
  return $tokenData['external_cid'] ?? null;
}

/**
 * トークンファイルからすべてのトークン情報を取得
 * @return array|null トークン情報配列またはnull
 */
function getOAuthTokenInfo() {
  return loadTokenFromFile();
}
?>
