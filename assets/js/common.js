/**
 * Freee API アプリケーション共通JavaScript
 */

$(document).ready(function() {
  // 税区分フィルタリング機能
  initTaxCodeFilter();
  
  // 事業所選択時の動的読み込み機能（deals/index.php用）
  initCompanySelection();
  
  // テンプレート機能の初期化
  initTemplateFeatures();
  
  // 勘定科目検索機能の初期化
  initAccountItemSearch();
  
  // テンプレート保存時の必須項目チェック制御
  initTemplateFormValidation();
});

/**
 * 税区分フィルタリング機能を初期化
 */
function initTaxCodeFilter() {
  const typeSelect = document.getElementById('type');
  const taxCodeSelect = document.getElementById('tax_code');
  
  if (!typeSelect || !taxCodeSelect) {
    return; // 要素が存在しない場合は何もしない
  }
  
  // すべての税区分オプションを保存
  const allTaxOptions = Array.from(taxCodeSelect.options);
  
  // 支出用の税区分（非課仕入まで）を定義
  const expenseAllowedTexts = [
    '課対仕入10%',
    '課対仕入8%（軽）',
    '課対仕入8%',
    '課対仕入',
    '対象外',
    '非課売上',
    '非課仕入'
  ];
  
  /**
   * 税区分選択肢をフィルタリング
   */
  function filterTaxCodes() {
    const selectedType = typeSelect.value;
    
    // すべてのオプションを一旦削除（最初の空オプション以外）
    taxCodeSelect.innerHTML = '<option value="">税区分を選択してください</option>';
    
    if (selectedType === 'expense') {
      // 支出の場合：非課仕入までの項目のみ表示
      allTaxOptions.forEach(option => {
        if (option.value === '') return; // 空オプションはスキップ
        
        const optionText = option.textContent.trim();
        const isAllowed = expenseAllowedTexts.some(allowedText => 
          optionText.includes(allowedText)
        );
        
        if (isAllowed) {
          taxCodeSelect.appendChild(option.cloneNode(true));
        }
      });
    } else if (selectedType === 'income') {
      // 収入の場合：すべての税区分を表示
      allTaxOptions.forEach(option => {
        if (option.value !== '') { // 空オプション以外
          taxCodeSelect.appendChild(option.cloneNode(true));
        }
      });
    } else {
      // 取引タイプが選択されていない場合：すべての税区分を表示
      allTaxOptions.forEach(option => {
        if (option.value !== '') {
          taxCodeSelect.appendChild(option.cloneNode(true));
        }
      });
    }
  }
  
  // 取引タイプ変更時にフィルタリング実行
  typeSelect.addEventListener('change', filterTaxCodes);
  
  // 初期表示時にもフィルタリング実行
  filterTaxCodes();
}

/**
 * 事業所選択時の動的読み込み機能（deals/index.php用）
 */
function initCompanySelection() {
  const companySelect = document.getElementById('company_id');
  const accountItemSelect = document.getElementById('account_item_id');
  const taxCodeSelect = document.getElementById('tax_code');
  const itemSelect = document.getElementById('item_id');
  
  if (!companySelect || !accountItemSelect || !taxCodeSelect) {
    return; // deals/index.phpでない場合は何もしない
  }
  
  // 事業所変更時にフォームを再送信して勘定科目と税区分を読み込み
  companySelect.addEventListener('change', function() {
    if (this.value) {
      // 事業所が選択された場合、フォームを再送信
      // （PHPで勘定科目と税区分を再取得するため）
      showReloadMessage();
    } else {
      // 事業所が未選択の場合、勘定科目と税区分を無効化
      disableFormSelects();
    }
  });
  
  /**
   * 再読み込みメッセージを表示
   */
  function showReloadMessage() {
    const message = document.createElement('div');
    message.className = 'alert alert-info';
    message.innerHTML = '<p>勘定科目、税区分、品目を読み込んでいます。ページを再送信してください。</p>';
    
    const form = companySelect.closest('form');
    if (form) {
      form.insertBefore(message, form.firstChild);
      
      // 3秒後にメッセージを削除
      setTimeout(() => {
        if (message.parentNode) {
          message.parentNode.removeChild(message);
        }
      }, 3000);
    }
  }
  
  /**
   * フォーム内のセレクトボックスを無効化
   */
  function disableFormSelects() {
    accountItemSelect.innerHTML = '<option value="">事業所を先に選択してください</option>';
    accountItemSelect.disabled = true;
    
    taxCodeSelect.innerHTML = '<option value="">事業所を先に選択してください</option>';
    taxCodeSelect.disabled = true;
    
    if (itemSelect) {
      itemSelect.innerHTML = '<option value="">事業所を先に選択してください</option>';
      itemSelect.disabled = true;
    }
  }
}

// ========================================
// テンプレート機能
// ========================================

/**
 * テンプレート機能を初期化
 */
function initTemplateFeatures() {
  // モーダルが存在する場合のみイベントを設定
  const templateModal = document.getElementById('templateModal');
  if (templateModal) {
    console.log('Template features initialized');
  }
}

/**
 * テンプレート読み込みモーダルを表示
 */
function loadTemplate() {
  $('#templateModal').modal('show');
}

/**
 * テンプレートを適用
 * @param {string} templateName テンプレート名
 */
function applyTemplate(templateName) {
  // AJAXでテンプレートデータを取得
  fetch('../api/get_template.php?name=' + encodeURIComponent(templateName))
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // フォームにデータを適用
        const fields = [
          'type',
          'account_item_id', 
          'tax_code',
          'from_walletable_id',
          'item_id',
          'ref_number',
          'description'
        ];
        
        fields.forEach(fieldName => {
          const element = document.getElementById(fieldName);
          if (element) {
            element.value = data.template[fieldName] || '';
            
            // 勘定科目の場合は検索フィールドも更新
            if (fieldName === 'account_item_id' && data.template[fieldName]) {
              updateAccountItemDisplay(data.template[fieldName]);
            }
          }
        });
        
        // モーダルを閉じる
        closeTemplateModal();
        
        // 成功メッセージ
        showMessage('テンプレートを適用しました。', 'success');
      } else {
        console.error('Template load error:', data);
        showMessage('テンプレートの読み込みに失敗しました。', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showMessage('テンプレートの読み込み中にエラーが発生しました。', 'error');
    });
}

/**
 * テンプレートを削除
 * @param {string} templateName テンプレート名
 */
function deleteTemplate(templateName) {
  if (confirm('テンプレート「' + templateName + '」を削除しますか？')) {
    console.log('Starting deletion for template:', templateName);
    
    // 削除前にテンプレート一覧を取得して数を確認
    var templatesBeforeDelete = $('.template-item').length;
    console.log('Templates count before delete:', templatesBeforeDelete);
    
    // AJAXでテンプレートを削除
    fetch('../api/delete_template.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({name: templateName})
    })
    .then(response => {
      console.log('Delete response status:', response.status);
      console.log('Delete response headers:', response.headers);
      return response.json();
    })
    .then(data => {
      console.log('Delete response data:', data);
      console.log('Response success:', data.success);
      console.log('Response error:', data.error);
      
      if (data.debug_info) {
        console.log('Debug info:', data.debug_info);
      }
      
      if (data.success) {
        showMessage('テンプレートを削除しました。', 'success');
        
        // 少し待ってからページを再読み込みして確認
        setTimeout(() => {
          console.log('Reloading page to verify deletion...');
          location.reload();
        }, 2000);
      } else {
        console.error('Delete failed:', data.error);
        showMessage('テンプレートの削除に失敗しました。エラー: ' + (data.error || '不明なエラー'), 'error');
      }
    })
    .catch(error => {
      console.error('Delete request error:', error);
      showMessage('テンプレートの削除中にエラーが発生しました。', 'error');
    });
  }
}

/**
 * テンプレートモーダルを閉じる
 */
function closeTemplateModal() {
  $('#templateModal').modal('hide');
}

/**
 * メッセージを表示
 * @param {string} message メッセージ内容
 * @param {string} type メッセージタイプ (success, error, info, warning)
 */
function showMessage(message, type = 'info') {
  // アラート用のCSSクラスマッピング
  const alertClasses = {
    success: 'alert-success',
    error: 'alert-danger',
    info: 'alert-info',
    warning: 'alert-warning'
  };
  
  const alertClass = alertClasses[type] || 'alert-info';
  
  // 既存のメッセージを削除
  const existingAlerts = document.querySelectorAll('.temp-alert');
  existingAlerts.forEach(alert => alert.remove());
  
  // 新しいメッセージを作成
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert ${alertClass} temp-alert`;
  alertDiv.innerHTML = `<p>${message}</p>`;
  
  // ページ上部に挿入
  const container = document.querySelector('.container');
  if (container) {
    const headerSection = container.querySelector('.header-section');
    if (headerSection) {
      container.insertBefore(alertDiv, headerSection.nextSibling);
    } else {
      container.insertBefore(alertDiv, container.firstChild);
    }
    
    // 5秒後に自動削除
    setTimeout(() => {
      if (alertDiv.parentNode) {
        alertDiv.parentNode.removeChild(alertDiv);
      }
    }, 5000);
  } else {
    // fallback: alert使用
    alert(message);
  }
}

// ========================================
// 勘定科目検索機能
// ========================================

/**
 * 勘定科目検索機能を初期化
 */
function initAccountItemSearch() {
  const searchInput = document.getElementById('account_item_search');
  const hiddenInput = document.getElementById('account_item_id');
  const dropdownList = document.getElementById('account_item_list');
  
  if (!searchInput || !hiddenInput || !dropdownList) {
    return; // 要素が存在しない場合は何もしない
  }
  
  const dropdownItems = dropdownList.querySelectorAll('.dropdown-item');
  let selectedIndex = -1;
  
  // 検索入力時のフィルタリング
  searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    let visibleItems = [];
    selectedIndex = -1;
    
    dropdownItems.forEach(item => {
      const text = item.textContent.toLowerCase();
      if (text.includes(searchTerm)) {
        item.classList.remove('hidden');
        visibleItems.push(item);
      } else {
        item.classList.add('hidden');
        item.classList.remove('selected');
      }
    });
    
    if (searchTerm.length > 0 && visibleItems.length > 0) {
      dropdownList.style.display = 'block';
    } else {
      dropdownList.style.display = 'none';
    }
    
    // hiddenInputをクリア
    hiddenInput.value = '';
    searchInput.classList.remove('has-selection');
  });
  
  // フォーカス時にドロップダウンを表示
  searchInput.addEventListener('focus', function() {
    if (this.value.length > 0) {
      dropdownList.style.display = 'block';
    }
  });
  
  // キーボードナビゲーション
  searchInput.addEventListener('keydown', function(e) {
    const visibleItems = Array.from(dropdownItems).filter(item => !item.classList.contains('hidden'));
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = Math.min(selectedIndex + 1, visibleItems.length - 1);
      updateSelection(visibleItems);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = Math.max(selectedIndex - 1, -1);
      updateSelection(visibleItems);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedIndex >= 0 && visibleItems[selectedIndex]) {
        selectItem(visibleItems[selectedIndex]);
      }
    } else if (e.key === 'Escape') {
      dropdownList.style.display = 'none';
      selectedIndex = -1;
    }
  });
  
  // ドロップダウン項目のクリック
  dropdownItems.forEach(item => {
    item.addEventListener('click', function() {
      selectItem(this);
    });
  });
  
  // 外部クリックでドロップダウンを閉じる
  document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !dropdownList.contains(e.target)) {
      dropdownList.style.display = 'none';
      selectedIndex = -1;
    }
  });
  
  /**
   * 選択状態を更新
   */
  function updateSelection(visibleItems) {
    // 全ての選択状態をクリア
    dropdownItems.forEach(item => item.classList.remove('selected'));
    
    // 新しい選択項目をハイライト
    if (selectedIndex >= 0 && visibleItems[selectedIndex]) {
      visibleItems[selectedIndex].classList.add('selected');
      // スクロール調整
      visibleItems[selectedIndex].scrollIntoView({ block: 'nearest' });
    }
  }
  
  /**
   * 項目を選択
   */
  function selectItem(item) {
    const value = item.getAttribute('data-value');
    const name = item.getAttribute('data-name');
    
    searchInput.value = name;
    hiddenInput.value = value;
    searchInput.classList.add('has-selection');
    dropdownList.style.display = 'none';
    selectedIndex = -1;
    
    // 選択状態をクリア
    dropdownItems.forEach(i => i.classList.remove('selected'));
  }
}

/**
 * 勘定科目の表示を更新（テンプレート適用時など）
 */
function updateAccountItemDisplay(accountItemId) {
  const searchInput = document.getElementById('account_item_search');
  const hiddenInput = document.getElementById('account_item_id');
  const dropdownList = document.getElementById('account_item_list');
  
  if (!searchInput || !hiddenInput || !dropdownList) {
    return;
  }
  
  // 該当する勘定科目を検索
  const dropdownItems = dropdownList.querySelectorAll('.dropdown-item');
  dropdownItems.forEach(item => {
    if (item.getAttribute('data-value') === accountItemId) {
      const name = item.getAttribute('data-name');
      searchInput.value = name;
      hiddenInput.value = accountItemId;
      searchInput.classList.add('has-selection');
    }
  });
}

/**
 * テンプレート保存時のフォームバリデーション制御
 */
function initTemplateFormValidation() {
  const saveTemplateBtn = document.querySelector('[name="save_template"]');
  if (saveTemplateBtn) {
    saveTemplateBtn.addEventListener('click', function(e) {
      // テンプレート名のチェックのみ
      const templateNameInput = document.getElementById('template_name');
      if (!templateNameInput || !templateNameInput.value.trim()) {
        alert('テンプレート名を入力してください。');
        e.preventDefault();
        return false;
      }
      
      // required属性を一時的に削除
      const form = document.querySelector('form');
      const requiredFields = form.querySelectorAll('[required]');
      requiredFields.forEach(field => {
        field.removeAttribute('required');
      });
    });
  }
}