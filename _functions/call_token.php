<?php
/**
 * Freee API アクセストークンを取得する関数
 */

require_once 'oauth.php';

// 事業所IDの保存用
$company_id = null;

/**
 * アクセストークンを返す
 * OAuth認証で取得したトークンのみを使用
 * @return string|null アクセストークンまたはnull（認証されていない場合）
 */
function getAccessToken() {
  // OAuth認証で取得したトークンのみを使用
  return getValidAccessToken();
}

/**
 * 事業所IDを設定する
 * @param int $id 事業所ID
 */
function setCompanyId($id) {
  global $company_id;
  $company_id = $id;
}

/**
 * 設定された事業所IDを取得する
 * 1. まず手動設定された事業所IDを確認
 * 2. 次にOAuth認証で取得した事業所IDを確認
 * @return int|null 事業所ID
 */
function getCompanyId() {
  global $company_id;
  
  // 手動設定された事業所IDがある場合はそれを優先
  if ($company_id !== null) {
    return $company_id;
  }
  
  // OAuth認証で取得した事業所IDを取得
  $oauthCompanyId = getOAuthCompanyId();
  if ($oauthCompanyId !== null) {
    return $oauthCompanyId;
  }
  
  return null;
}

/**
 * OAuth認証で取得した事業所IDを取得（直接アクセス用）
 * @return string|null OAuth事業所ID
 */
function getOAuthCompanyIdDirect() {
  return getOAuthCompanyId();
}

/**
 * OAuth認証で取得したexternal_cidを取得
 * @return string|null external_cid
 */
function getOAuthExternalCidDirect() {
  return getOAuthExternalCid();
}

/**
 * OAuth認証が完了しているかチェック
 * @return bool 認証済みの場合true
 */
function isAuthenticated() {
  return getAccessToken() !== null;
}

/**
 * 認証が必要な処理の前にチェックする
 * @return bool 認証済みの場合true、未認証の場合false
 */
function requireAuthentication() {
  if (!isAuthenticated()) {
    return false;
  }
  return true;
}

/**
 * API リクエスト用の共通ヘッダーを取得
 * @return array|null ヘッダー配列またはnull（未認証時）
 */
function getApiHeaders() {
  $token = getAccessToken();
  if ($token === null) {
    return null;
  }
  
  return [
    'accept: application/json',
    'Authorization: Bearer ' . $token,
    'X-Api-Version: 2020-06-15'
  ];
}

/**
 * POST リクエスト用の共通ヘッダーを取得
 * @return array|null ヘッダー配列またはnull（未認証時）
 */
function getPostApiHeaders() {
  $token = getAccessToken();
  if ($token === null) {
    return null;
  }
  
  return [
    'accept: application/json',
    'Authorization: Bearer ' . $token,
    'Content-Type: application/x-www-form-urlencoded',
    'X-Api-Version: 2020-06-15'
  ];
}
?>