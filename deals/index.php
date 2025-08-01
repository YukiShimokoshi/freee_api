<?php
// 必要な関数を読み込み
require_once '../_functions/companies.php';
require_once '../_functions/deals.php';
require_once '../_functions/account_items.php';
require_once '../_functions/tax_codes.php';
require_once '../_functions/walletables.php';
require_once '../_functions/deal_templates.php';
require_once '../_functions/items.php';

// セッション開始
session_start();

// 認証チェック
if (!isAuthenticated()) {
  header('Location: ../auth.                                  onclick="deleteTemplate('<?php echo htmlspecialchars($template['name']); ?>')">                         onclick="applyTemplate('<?php echo htmlspecialchars($template['name']); ?>')">hp');
  exit;
}

// 事業所一覧を取得
$companies = getFormattedCompanies();

// テンプレート一覧を取得
$templates = getDealTemplates();

// POSTリクエストの処理
$result = null;
$error = null;
$templateSaved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // テンプレート保存の処理
  if (isset($_POST['save_template'])) {
    $templateName = trim($_POST['template_name'] ?? '');
    if (empty($templateName)) {
      $error = 'テンプレート名を入力してください。';
    } elseif (templateExists($templateName)) {
      $error = 'そのテンプレート名は既に存在します。';
    } else {
      $templateData = [
        'type' => $_POST['type'] ?? '',
        'account_item_id' => $_POST['account_item_id'] ?? '',
        'tax_code' => $_POST['tax_code'] ?? '',
        'from_walletable_id' => $_POST['from_walletable_id'] ?? '',
        'item_id' => $_POST['item_id'] ?? '',
        'ref_number' => $_POST['ref_number'] ?? '',
        'description' => $_POST['description'] ?? ''
      ];
      
      if (saveDealTemplate($templateName, $templateData)) {
        $templateSaved = true;
        $_SESSION['success_message'] = 'テンプレート「' . htmlspecialchars($templateName) . '」を保存しました。';
        // テンプレート一覧を再取得
        $templates = getDealTemplates();
      } else {
        $error = 'テンプレートの保存に失敗しました。';
      }
    }
    } else {
      // 通常の取引作成処理
  // バリデーション
  $companyId = $_POST['company_id'] ?? null;
  $type = $_POST['type'] ?? null;
  $issueDate = $_POST['issue_date'] ?? null;
  $amount = $_POST['amount'] ?? null;
  $accountItemId = $_POST['account_item_id'] ?? null;

  // 事業所が選択されていれば勘定科目一覧を取得
  $accountItems = [];
  $taxCodes = [];
  $walletables = [];
  $items = [];
  if (!empty($companyId)) {
    $accountItems = getFormattedAccountItems($companyId);
    $taxCodes = getFormattedTaxCodes($companyId);
    $walletables = getGroupedWalletables($companyId);
    $items = getFormattedItems($companyId);
  }

  $taxCode = $_POST['tax_code'] ?? (!empty($taxCodes) ? $taxCodes[0]['code'] : 1);
  $dueDate = $_POST['due_date'] ?? null;
  $refNumber = $_POST['ref_number'] ?? null;
  $description = $_POST['description'] ?? null;
  $fromWalletableId = $_POST['from_walletable_id'] ?? null;
  $itemId = $_POST['item_id'] ?? null;
  
  // 必須項目チェック
  if (empty($companyId) || empty($type) || empty($issueDate) || empty($amount) || empty($accountItemId) || empty($taxCode)) {
    $error = '必須項目が入力されていません。';
  } else {
    // 事業所IDを設定
    setCompanyId($companyId);
    
    // 取引作成のオプション
    $options = [];
    if (!empty($dueDate)) {
      $options['due_date'] = $dueDate;
    }
    if (!empty($refNumber)) {
      $options['ref_number'] = $refNumber;
    }
    
    // 決済方法が指定されている場合、paymentsを追加
    if (!empty($fromWalletableId)) {
      if ($fromWalletableId === 'private_account_item') {
        // プライベート資金の場合、取引タイプに応じた勘定科目IDを取得
        $privateAccountItemId = getPrivateAccountItemId($companyId, $type);
        if ($privateAccountItemId) {
          $options['payments'] = [
            [
              'date' => $issueDate,
              'from_walletable_type' => 'private_account_item',
              'from_walletable_id' => $privateAccountItemId,
              'amount' => $amount
            ]
          ];
        } else {
          if ($type === 'income') {
            $error = 'プライベート資金用の勘定科目（事業主貸）が見つかりません。事業主貸の勘定科目を作成してください。';
          } else {
            $error = 'プライベート資金用の勘定科目（事業主借）が見つかりません。事業主借の勘定科目を作成してください。';
          }
        }
      } else {
        // 通常の口座の場合、口座情報を取得して種別を確認
        $walletInfo = getWalletableInfo($fromWalletableId, $token, $companyId);
        if ($walletInfo) {
          $options['payments'] = [
            [
              'date' => $issueDate,
              'from_walletable_type' => $walletInfo['type'],
              'from_walletable_id' => $fromWalletableId,
              'amount' => $amount
            ]
          ];
        } else {
          $error = '指定された決済口座が見つかりません。';
        }
      }
    }
    
    // 明細オプション
    $detailOptions = [];
    if (!empty($description)) {
      $detailOptions['description'] = $description;
    }
    if (!empty($itemId)) {
      $detailOptions['item_id'] = $itemId;
    }
    
    // 明細作成
    $details = [createDealDetail($amount, $accountItemId, $taxCode, $detailOptions)];
    
    // 取引作成
    if ($type === 'income') {
      $result = createIncomeDeal($companyId, $issueDate, $details, $options);
    } else {
      $result = createExpenseDeal($companyId, $issueDate, $details, $options);
    }
    
    if ($result === false) {
      $error = '取引の作成に失敗しました。';
    } else if (is_array($result) && isset($result['success']) && $result['success'] === false) {
      // 詳細なAPIエラー内容を表示
      $error = '取引の作成に失敗しました。';
      if (isset($result['status_code'])) {
        $error .= ' [status_code: ' . htmlspecialchars($result['status_code']) . ']';
      }
      if (isset($result['errors']) && is_array($result['errors'])) {
        foreach ($result['errors'] as $err) {
          if (isset($err['messages']) && is_array($err['messages'])) {
            foreach ($err['messages'] as $msg) {
              $error .= '<br>' . htmlspecialchars($msg);
            }
          }
        }
      } elseif (isset($result['error_message'])) {
        $error .= '<br>' . htmlspecialchars($result['error_message']);
      } elseif (isset($result['response'])) {
        $error .= '<br>API Response: ' . htmlspecialchars($result['response']);
      }
    } else {
      $_SESSION['success_message'] = '取引が正常に作成されました。';
      header('Location: index.php');
      exit;
    }
    }
  }
}

// ヘッダーを読み込み
include '../_includes/header.php';
?>

<div class="container">
  <div class="header-section">
    <h1>取引登録</h1>
    <a href="../index.php" class="btn btn-secondary">← 事業所一覧に戻る</a>
  </div>
  
  <?php if ($error): ?>
    <div class="alert alert-danger">
      <p><?php echo htmlspecialchars($error); ?></p>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
      <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>
  
  <div class="deal-form-container">
    <form method="POST" class="deal-form">
      <div class="form-group col-md-4 d-flex align-items-end">
        <button type="button" class="btn btn-info" onclick="loadTemplate()">テンプレート読み込み</button>
      </div>
      
      <div class="form-group">
        <label for="company_id">事業所 <span class="required">*</span></label>
        <select name="company_id" id="company_id" class="form-control" required>
          <option value="">事業所を選択してください</option>
          <?php foreach ($companies as $company): ?>
            <option value="<?php echo htmlspecialchars($company['id']); ?>" 
                    <?php echo (isset($_POST['company_id']) && $_POST['company_id'] == $company['id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($company['display_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label for="type">取引タイプ <span class="required">*</span></label>
        <select name="type" id="type" class="form-control" required>
          <option value="">取引タイプを選択してください</option>
          <option value="income" <?php echo (isset($_POST['type']) && $_POST['type'] === 'income') ? 'selected' : ''; ?>>収入</option>
          <option value="expense" <?php echo (isset($_POST['type']) && $_POST['type'] === 'expense') ? 'selected' : ''; ?>>支出</option>
        </select>
      </div>
      
      <div class="form-row">
        <div class="form-group col-md-6">
          <label for="issue_date">発生日 <span class="required">*</span></label>
          <input type="date" name="issue_date" id="issue_date" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')); ?>" required>
        </div>
        
        <div class="form-group col-md-6">
          <label for="due_date">支払期日</label>
          <input type="date" name="due_date" id="due_date" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group col-md-6">
          <label for="amount">金額 <span class="required">*</span></label>
          <input type="number" name="amount" id="amount" class="form-control" 
                 value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                 min="1" step="1" required>
        </div>
        
        <div class="form-group col-md-6">
          <label for="account_item_id">勘定科目 <span class="required">*</span></label>
          <?php if (empty($companyId)): ?>
            <select name="account_item_id" id="account_item_id" class="form-control" disabled required>
              <option value="">事業所を先に選択してください</option>
            </select>
          <?php else: ?>
            <select name="account_item_id" id="account_item_id" class="form-control" required>
              <option value="">勘定科目を選択してください</option>
              <?php foreach ($accountItems as $item): ?>
                <option value="<?php echo htmlspecialchars($item['id']); ?>" <?php echo (isset($_POST['account_item_id']) && $_POST['account_item_id'] == $item['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($item['name']) . ' (' . $item['id'] . ')'; ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <small class="form-text text-muted">勘定科目名で選択してください</small>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group col-md-6">
          <label for="tax_code">税区分 <span class="required">*</span></label>
          <?php if (empty($companyId)): ?>
            <select name="tax_code" id="tax_code" class="form-control" disabled required>
              <option value="">事業所を先に選択してください</option>
            </select>
          <?php else: ?>
            <select name="tax_code" id="tax_code" class="form-control" required>
              <?php if (empty($taxCodes)): ?>
                <option value="1" selected>標準税率 (10%)</option>
              <?php else: ?>
                <option value="">税区分を選択してください</option>
                <?php foreach ($taxCodes as $tax): ?>
                  <option value="<?php echo htmlspecialchars($tax['code']); ?>" <?php echo (isset($_POST['tax_code']) && $_POST['tax_code'] == $tax['code']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($tax['description']); ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          <?php endif; ?>
          <small class="form-text text-muted">適用する税区分を選択してください</small>
        </div>
        <div class="form-group col-md-6">
          <label for="item_id">品目</label>
          <?php if (empty($companyId)): ?>
            <select name="item_id" id="item_id" class="form-control" disabled>
              <option value="">事業所を先に選択してください</option>
            </select>
          <?php else: ?>
            <select name="item_id" id="item_id" class="form-control">
              <option value="">品目を選択してください（オプション）</option>
              <?php foreach ($items as $item): ?>
                <option value="<?php echo htmlspecialchars($item['id']); ?>" <?php echo (isset($_POST['item_id']) && $_POST['item_id'] == $item['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($item['name']); ?>
                  <?php if (!empty($item['code'])): ?>
                    (<?php echo htmlspecialchars($item['code']); ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <small class="form-text text-muted">該当する品目を選択してください（オプション）</small>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label for="from_walletable_id">決済口座</label>
          <?php if (empty($companyId)): ?>
            <select name="from_walletable_id" id="from_walletable_id" class="form-control" disabled>
              <option value="">事業所を先に選択してください</option>
            </select>
          <?php else: ?>
            <select name="from_walletable_id" id="from_walletable_id" class="form-control">
              <option value="">決済口座を選択してください（オプション）</option>
              <?php if (!empty($walletables['bank_account'])): ?>
                <optgroup label="銀行口座">
                  <?php foreach ($walletables['bank_account'] as $wallet): ?>
                    <option value="<?php echo htmlspecialchars($wallet['id']); ?>" <?php echo (isset($_POST['from_walletable_id']) && $_POST['from_walletable_id'] == $wallet['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($wallet['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <?php if (!empty($walletables['credit_card'])): ?>
                <optgroup label="クレジットカード">
                  <?php foreach ($walletables['credit_card'] as $wallet): ?>
                    <option value="<?php echo htmlspecialchars($wallet['id']); ?>" <?php echo (isset($_POST['from_walletable_id']) && $_POST['from_walletable_id'] == $wallet['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($wallet['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <?php if (!empty($walletables['wallet'])): ?>
                <optgroup label="その他の決済口座">
                  <?php foreach ($walletables['wallet'] as $wallet): ?>
                    <option value="<?php echo htmlspecialchars($wallet['id']); ?>" <?php echo (isset($_POST['from_walletable_id']) && $_POST['from_walletable_id'] == $wallet['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($wallet['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <optgroup label="プライベート資金">
                <option value="private_account_item" <?php echo (isset($_POST['from_walletable_id']) && $_POST['from_walletable_id'] == 'private_account_item') ? 'selected' : ''; ?>>
                  プライベート資金（役員借入金・事業主貸借）
                </option>
              </optgroup>
            </select>
          <?php endif; ?>
          <small class="form-text text-muted">決済に使用する口座を選択してください（オプション）</small>
        </div>
      </div>
      
      <div class="form-group">
        <label for="ref_number">管理番号</label>
        <input type="text" name="ref_number" id="ref_number" class="form-control" 
               value="<?php echo htmlspecialchars($_POST['ref_number'] ?? ''); ?>" 
               maxlength="255">
        <small class="form-text text-muted">管理用の番号を入力してください（オプション）</small>
      </div>
      
      <div class="form-group">
        <label for="description">備考</label>
        <textarea name="description" id="description" class="form-control" rows="3" 
                  maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
      </div>
      
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">取引を作成</button>
        <a href="../index.php" class="btn btn-secondary">キャンセル</a>
      </div>
      
      <!-- テンプレート保存セクション -->
      <div class="template-save-section">
        <h5>取引テンプレートとして保存</h5>
        
        <div class="form-row">
          <div class="form-group col-md-8">
            <label for="template_name">テンプレート名 <span class="required">*</span></label>
            <input type="text" name="template_name" id="template_name" class="form-control" placeholder="例：毎月の家賃支払い" maxlength="50">
          </div>
        </div>
        <div class="form-group col-md-4 d-flex align-items-end">
          <button type="submit" name="save_template" class="btn btn-success btn-block">テンプレート保存</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- テンプレート読み込みモーダル -->
<div class="modal fade" id="templateModal" tabindex="-1" role="dialog" aria-labelledby="templateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <?php if (empty($templates)): ?>
          <p class="text-center text-muted">保存されたテンプレートがありません。</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>テンプレート名</th>
                  <th>取引タイプ</th>
                  <th>作成日時</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($templates as $template): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($template['name']); ?></td>
                    <td>
                      <span class="badge <?php echo $template['data']['type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $template['data']['type'] === 'income' ? '収入' : '支出'; ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($template['created_at']); ?></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-primary" 
                              onclick="applyTemplate('<?php echo htmlspecialchars($template['name']); ?>')">
                        適用
                      </button>
                      <button type="button" class="btn btn-sm btn-danger" 
                              onclick="deleteTemplate('<?php echo htmlspecialchars($template['name']); ?>')">
                        削除
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>

<?php
// フッターを読み込み
include '../_includes/footer.php';
?>
