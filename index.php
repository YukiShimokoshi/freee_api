<?php
// 事業所関連の関数を読み込み
require_once '_functions/companies.php';
require_once '_functions/oauth.php';
require_once '_functions/call_token.php';

// トークンの状態をチェック
$hasValidToken = getValidAccessToken() !== null;
$oauthCompanyId = getOAuthCompanyIdDirect();
$oauthExternalCid = getOAuthExternalCidDirect();

// 事業所一覧を取得（認証済みの場合のみ）
$companies = [];
if ($hasValidToken) {
  $companies = getFormattedCompanies();
  if ($companies === false) {
    $companies = [];
  }
}

// ヘッダーを読み込み
include '_includes/header.php';
?>

<div class="container">
  <div class="header-section">
    <h1>Freee API - 事業所一覧</h1>
    <div class="header-buttons">
      <a href="auth.php" class="btn btn-secondary">OAuth認証</a>
      <a href="deals/" class="btn btn-primary">取引登録</a>
      <a href="items/" class="btn btn-info">勘定科目一覧</a>
    </div>
  </div>
  
  <?php if (!$hasValidToken): ?>
    <div class="alert alert-warning">
      <h4>OAuth認証が必要です</h4>
      <p>Freee APIを利用するために認証が必要です。以下のボタンをクリックして認証を行ってください。</p>
      <a href="auth.php" class="btn btn-primary">OAuth認証を開始</a>
    </div>
  <?php else: ?>
    <div class="alert alert-success">
      <p><strong>OAuth認証済み：</strong> Freee APIを利用できます。
      <?php if ($oauthCompanyId): ?>
        <br><small>認証済み事業所ID: <?php echo htmlspecialchars($oauthCompanyId); ?></small>
      <?php endif; ?>
      <a href="auth.php?step=check" class="btn btn-sm btn-info" style="margin-left: 10px;">トークン状態確認</a></p>
    </div>
  <?php endif; ?>
  
  <?php if (!$hasValidToken): ?>
    <div class="deal-form-container">
      <h2>認証後に利用可能な機能</h2>
      <ul>
        <li>事業所一覧の表示</li>
        <li>取引の登録・管理</li>
        <li>勘定科目一覧の表示</li>
        <li>税区分の管理</li>
      </ul>
      <p>まずはOAuth認証を完了してください。</p>
    </div>
  <?php elseif (empty($companies)): ?>
    <div class="alert alert-warning">
      <p>事業所データを取得できませんでした。トークンの有効期限が切れている可能性があります。</p>
      <a href="auth.php?step=check" class="btn btn-info">トークン状態を確認</a>
    </div>
  <?php else: ?>
    <div class="companies-list">
      <h2>登録されている事業所（<?php echo count($companies); ?>件）</h2>
      
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>表示名</th>
              <th>事業所名</th>
              <th>事業所名（カナ）</th>
              <th>法人番号</th>
              <th>権限</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($companies as $company): ?>
              <tr>
                <td><?php echo htmlspecialchars($company['id']); ?></td>
                <td><?php echo htmlspecialchars($company['display_name']); ?></td>
                <td><?php echo htmlspecialchars($company['name']); ?></td>
                <td><?php echo htmlspecialchars($company['name_kana']); ?></td>
                <td><?php echo htmlspecialchars($company['company_number']); ?></td>
                <td>
                  <span class="badge <?php echo $company['role'] === 'admin' ? 'badge-primary' : 'badge-secondary'; ?>">
                    <?php echo htmlspecialchars($company['role']); ?>
                  </span>
                </td>
                <td>
                  <a href="deals/register.php?company_id=<?php echo urlencode($company['id']); ?>" class="btn btn-primary btn-sm">取引登録</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
// フッターを読み込み
include '_includes/footer.php';
?>