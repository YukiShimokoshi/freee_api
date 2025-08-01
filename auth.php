<?php
require_once '_functions/oauth.php';

session_start();

// OAuth認証の処理
$step = $_GET['step'] ?? 'start';
$error = null;
$success = null;

switch ($step) {
  case 'start':
    // 認証開始
    $state = generateRandomState();
    $_SESSION['oauth_state'] = $state;
    $authUrl = generateAuthUrl($state);
    break;
    
  case 'callback':
    // 認可コードの処理
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    
    if (empty($code)) {
      $error = '認可コードが取得できませんでした。';
      break;
    }
    
    // CSRF対策：stateの確認
    if (!isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
      $error = 'セキュリティエラー：不正なリクエストです。';
      break;
    }
    
    // アクセストークンを取得
    $tokenData = getAccessTokenFromCode($code);
    if ($tokenData === false) {
      $error = 'アクセストークンの取得に失敗しました。';
      break;
    }
    
    // トークンをファイルに保存
    if (saveTokenToFile($tokenData)) {
      $success = 'アクセストークンの取得に成功しました。';
      unset($_SESSION['oauth_state']);
    } else {
      $error = 'トークンの保存に失敗しました。';
    }
    break;
    
  case 'check':
    // 現在のトークン状態をチェック
    $token = getValidAccessToken();
    if ($token) {
      $success = 'アクセストークンは有効です。';
    } else {
      $error = 'アクセストークンが無効または存在しません。';
    }
    break;
}

include '_includes/header.php';
?>

<div class="container">
  <div class="header-section">
    <h1>OAuth認証</h1>
    <a href="index.php" class="btn btn-secondary">← メインページに戻る</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <p><?php echo htmlspecialchars($error); ?></p>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <p><?php echo htmlspecialchars($success); ?></p>
    </div>
  <?php endif; ?>

  <?php if ($step === 'start'): ?>
    <div class="deal-form-container">
      <h2>Freee API認証</h2>
      <p>Freee APIを利用するために認証が必要です。以下のボタンをクリックして認証を開始してください。</p>
      
      <div class="form-group">
        <h3>認証手順：</h3>
        <ol>
          <li>「認証を開始」ボタンをクリック</li>
          <li>Freeeのログイン画面でログイン</li>
          <li>事業所を選択（複数ある場合）</li>
          <li>アプリ連携を許可</li>
          <li>表示された認可コードをコピー</li>
          <li>このページに戻って認可コードを入力</li>
        </ol>
      </div>
      
      <div class="form-actions">
        <a href="<?php echo htmlspecialchars($authUrl); ?>" target="_blank" class="btn btn-primary">
          認証を開始
        </a>
      </div>
      
      <div style="margin-top: 30px;">
        <h3>認可コード入力</h3>
        <form method="GET" action="auth.php">
          <input type="hidden" name="step" value="callback">
          <input type="hidden" name="state" value="<?php echo htmlspecialchars($_SESSION['oauth_state']); ?>">
          <div class="form-group">
            <label for="code">認可コード <span class="required">*</span></label>
            <input type="text" name="code" id="code" class="form-control" required 
                   placeholder="認証後に表示された認可コードを入力してください">
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-success">アクセストークンを取得</button>
          </div>
        </form>
      </div>
    </div>

  <?php elseif ($step === 'callback' && $success): ?>
    <div class="deal-form-container">
      <h2>認証完了</h2>
      <p>OAuth認証が正常に完了しました。これでFreee APIを利用できます。</p>
      <div class="form-actions">
        <a href="index.php" class="btn btn-primary">メインページに戻る</a>
        <a href="auth.php?step=check" class="btn btn-info">トークン状態を確認</a>
      </div>
    </div>

  <?php elseif ($step === 'check'): ?>
    <div class="deal-form-container">
      <h2>トークン状態確認</h2>
      <?php 
      $tokenData = loadTokenFromFile();
      if ($tokenData): 
      ?>
        <div class="table-responsive">
          <table class="table">
            <tr>
              <th>項目</th>
              <th>値</th>
            </tr>
            <tr>
              <td>アクセストークン</td>
              <td><?php echo htmlspecialchars(substr($tokenData['access_token'], 0, 20)) . '...'; ?></td>
            </tr>
            <?php if (isset($tokenData['expires_at'])): ?>
            <tr>
              <td>有効期限</td>
              <td><?php echo date('Y-m-d H:i:s', $tokenData['expires_at']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (isset($tokenData['company_id'])): ?>
            <tr>
              <td>事業所ID</td>
              <td><?php echo htmlspecialchars($tokenData['company_id']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (isset($tokenData['external_cid'])): ?>
            <tr>
              <td>External CID</td>
              <td><?php echo htmlspecialchars($tokenData['external_cid']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (isset($tokenData['scope'])): ?>
            <tr>
              <td>スコープ</td>
              <td><?php echo htmlspecialchars($tokenData['scope']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
              <td>リフレッシュトークン</td>
              <td><?php echo isset($tokenData['refresh_token']) ? '有り' : '無し'; ?></td>
            </tr>
            <?php if (isset($tokenData['created_at'])): ?>
            <tr>
              <td>作成日時</td>
              <td><?php echo date('Y-m-d H:i:s', $tokenData['created_at']); ?></td>
            </tr>
            <?php endif; ?>
          </table>
        </div>
      <?php else: ?>
        <p>トークンファイルが見つかりません。</p>
      <?php endif; ?>
      
      <div class="form-actions">
        <a href="auth.php?step=start" class="btn btn-primary">再認証</a>
        <a href="index.php" class="btn btn-secondary">メインページに戻る</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include '_includes/footer.php'; ?>
