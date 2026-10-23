<?php

if (!defined('ITOPS')) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var array $form */
/** @var array $errors */
/** @var array $statuses */
/** @var array $types */
/** @var array $technicians */
/** @var string $submitLabel */
?>
<form method="post" class="split" action="">
  <?= csrf_field() ?>
  <?php if (!empty($formId)): ?>
    <input type="hidden" name="id" value="<?= (int) $formId ?>">
  <?php endif; ?>
  <section class="panel">
    <div class="field">
      <label for="description">描述 <span class="req">*</span></label>
      <textarea id="description" name="description" rows="6" maxlength="4000" required<?= described_by($errors, 'description') ?>><?= e($form['description']) ?></textarea>
      <?= field_error($errors, 'description') ?>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="report_by">報告人 <span class="req">*</span></label>
        <input id="report_by" name="report_by" maxlength="80" required value="<?= e($form['report_by']) ?>" placeholder="例如 KEN、Flora"<?= described_by($errors, 'report_by') ?>>
        <?= field_error($errors, 'report_by') ?>
      </div>
      <div class="field">
        <label for="location">地點</label>
        <input id="location" name="location" maxlength="80" value="<?= e($form['location']) ?>" placeholder="例如 RM109、Hall"<?= described_by($errors, 'location') ?>>
        <?= field_error($errors, 'location') ?>
      </div>
    </div>
    <div class="field">
      <label for="remarks">備註</label>
      <textarea id="remarks" name="remarks" rows="4" maxlength="4000"<?= described_by($errors, 'remarks') ?>><?= e($form['remarks']) ?></textarea>
      <?= field_error($errors, 'remarks') ?>
    </div>
  </section>
  <section class="panel side-card">
    <div class="field">
      <label for="status_id">狀態 <span class="req">*</span></label>
      <select id="status_id" name="status_id" required<?= described_by($errors, 'status_id') ?>>
        <?php foreach ($statuses as $status): ?>
          <option value="<?= (int) $status['id'] ?>"<?= (int) $form['status_id'] === (int) $status['id'] ? ' selected' : '' ?>><?= e($status['label_zh']) ?> (<?= (int) $status['id'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'status_id') ?>
    </div>
    <div class="field">
      <label for="type_id">類型 <span class="req">*</span></label>
      <select id="type_id" name="type_id" required<?= described_by($errors, 'type_id') ?>>
        <option value=""<?= (int) $form['type_id'] === 0 ? ' selected' : '' ?>>請選擇</option>
        <?php foreach ($types as $type): ?>
          <option value="<?= (int) $type['id'] ?>"<?= (int) $form['type_id'] === (int) $type['id'] ? ' selected' : '' ?>><?= e($type['name']) ?> · <?= e($type['label_zh']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'type_id') ?>
    </div>
    <div class="field">
      <label for="case_date">日期 <span class="req">*</span></label>
      <input id="case_date" type="datetime-local" name="case_date" required value="<?= e($form['case_date']) ?>"<?= described_by($errors, 'case_date') ?>>
      <?= field_error($errors, 'case_date') ?>
    </div>
    <div class="field">
      <label for="handler_id">負責人</label>
      <select id="handler_id" name="handler_id"<?= described_by($errors, 'handler_id') ?>>
        <option value=""<?= $form['handler_id'] === null ? ' selected' : '' ?>>未指派</option>
        <?php foreach ($technicians as $tech): ?>
          <option value="<?= (int) $tech['id'] ?>"<?= (int) $form['handler_id'] === (int) $tech['id'] ? ' selected' : '' ?>><?= e($tech['display_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'handler_id') ?>
    </div>
    <button class="btn btn-primary btn-block" type="submit"><?= e($submitLabel) ?></button>
  </section>
</form>
