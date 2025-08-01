<?php
// 必要な関数を読み込み
require_once '../_functions/companies.php';
require_once '../_functions/account_items.php';

// セッション開始
session_start();

// 認証チェック
if (!isAuthenticated()) {
  header('Location: ../auth.php');
  exit;
}

// 事業所一覧を取得
$companies = getFormattedCompanies();

// 検索処理
$accountItems = [];
$selectedCompanyId = null;
$selectedCompanyName = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $companyId = $_POST['company_id'] ?? null;
  
  if (empty($companyId)) {
    $error = '事業所を選択してください。';
  } else {
    $selectedCompanyId = $companyId;
    
    // 選択された事業所名を取得
    foreach ($companies as $company) {
      if ($company['id'] == $companyId) {
        $selectedCompanyName = $company['display_name'];
        break;
      }
    }
    
    // 勘定科目一覧を取得
    $accountItems = getFormattedAccountItems($companyId);
    
    if (empty($accountItems)) {
      $error = '勘定科目データを取得できませんでした。';
    }
  }
}

// ヘッダーを読み込み
include '../_includes/header.php';
?>

<div class="container">
  <div class="header-section">
    <h1>勘定科目一覧</h1>
    <a href="../index.php" class="btn btn-secondary">← 事業所一覧に戻る</a>
  </div>
  
  <?php if ($error): ?>
    <div class="alert alert-danger">
      <p><?php echo htmlspecialchars($error); ?></p>
    </div>
  <?php endif; ?>
  
  <!-- 事業所選択フォーム -->
  <div class="search-form-container">
    <form method="POST" class="search-form">
      <div class="form-group">
        <label for="company_id">事業所を選択 <span class="required">*</span></label>
        <div class="form-row">
          <div class="form-input">
            <select name="company_id" id="company_id" class="form-control" required>
              <option value="">事業所を選択してください</option>
              <?php foreach ($companies as $company): ?>
                <option value="<?php echo htmlspecialchars($company['id']); ?>" 
                        <?php echo ($selectedCompanyId == $company['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($company['display_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-button">
            <button type="submit" class="btn btn-primary">勘定科目を取得</button>
          </div>
        </div>
      </div>
    </form>
  </div>
  
  <!-- 勘定科目一覧表示 -->
  <?php if (!empty($accountItems)): ?>
    <div class="account-items-section">
      <h2><?php echo htmlspecialchars($selectedCompanyName); ?>の勘定科目一覧（<?php echo count($accountItems); ?>件）</h2>
      
      <!-- 利用可能な勘定科目のみ表示するフィルター -->
      <div class="filter-section">
        <label>
          <input type="checkbox" id="show-available-only"> 利用可能な勘定科目のみ表示
        </label>
      </div>
      
      <div class="table-responsive">
        <table class="table table-striped" id="account-items-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>勘定科目名</th>
              <th>税区分</th>
              <th>ショートカット</th>
              <th>ショートカット番号</th>
              <th>カテゴリ</th>
              <th>グループ名</th>
              <th>利用可能</th>
              <th>更新日</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accountItems as $item): ?>
              <tr class="account-item-row" data-available="<?php echo $item['available'] ? 'true' : 'false'; ?>">
                <td class="account-id"><?php echo htmlspecialchars($item['id']); ?></td>
                <td class="account-name"><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['tax_code']); ?></td>
                <td><?php echo htmlspecialchars($item['shortcut']); ?></td>
                <td><?php echo htmlspecialchars($item['shortcut_num']); ?></td>
                <td><?php echo htmlspecialchars($item['account_category']); ?></td>
                <td><?php echo htmlspecialchars($item['group_name']); ?></td>
                <td>
                  <span class="badge <?php echo $item['available'] ? 'badge-success' : 'badge-secondary'; ?>">
                    <?php echo $item['available'] ? '利用可能' : '利用不可'; ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($item['update_date']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <!-- カテゴリ別統計 -->
      <div class="stats-section">
        <h3>カテゴリ別統計</h3>
        <div class="stats-grid">
          <?php
          $categoryStats = [];
          foreach ($accountItems as $item) {
            $category = $item['account_category'];
            if (!isset($categoryStats[$category])) {
              $categoryStats[$category] = ['total' => 0, 'available' => 0];
            }
            $categoryStats[$category]['total']++;
            if ($item['available']) {
              $categoryStats[$category]['available']++;
            }
          }
          ksort($categoryStats);
          ?>
          <?php foreach ($categoryStats as $category => $stats): ?>
            <div class="stat-card">
              <h4><?php echo htmlspecialchars($category); ?></h4>
              <p>総数: <?php echo $stats['total']; ?>件</p>
              <p>利用可能: <?php echo $stats['available']; ?>件</p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
// 利用可能な勘定科目のみ表示するフィルター
document.addEventListener('DOMContentLoaded', function() {
  const checkbox = document.getElementById('show-available-only');
  const rows = document.querySelectorAll('.account-item-row');
  
  if (checkbox) {
    checkbox.addEventListener('change', function() {
      rows.forEach(row => {
        if (this.checked) {
          if (row.getAttribute('data-available') === 'false') {
            row.style.display = 'none';
          } else {
            row.style.display = '';
          }
        } else {
          row.style.display = '';
        }
      });
    });
  }
});
</script>

<?php
// フッターを読み込み
include '../_includes/footer.php';
?>
