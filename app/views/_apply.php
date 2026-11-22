<?php /** @var ?array $job */ $action = $job ? url('careers/' . $job['slug']) : url('careers'); ?>
<section class="form-card reveal" id="apply" aria-labelledby="apply-h">
  <h2 id="apply-h" class="h3"><?= e($job ? t('apply_title') : t('general_app')) ?></h2>
  <?php if (get('sent')): ?><div class="alert alert-ok" role="status" tabindex="-1" data-focus><?= icon('check') ?><?= e(t('app_ok')) ?></div><?php endif ?>
  <?= form_errors() ?>
  <form method="post" action="<?= e($action) ?>#apply" enctype="multipart/form-data" class="form" novalidate>
    <input type="hidden" name="_ft" value="<?= e(form_token('apply')) ?>">
    <div class="hp" aria-hidden="true"><label>website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="row2">
      <div class="field"><label for="a-name"><?= e(t('full_name')) ?> <span class="req">*</span></label><input id="a-name" name="name" required maxlength="120" autocomplete="name" value="<?= e(old('name')) ?>"></div>
      <div class="field"><label for="a-email"><?= e(t('email')) ?> <span class="req">*</span></label><input id="a-email" type="email" name="email" required maxlength="190" autocomplete="email" dir="ltr" value="<?= e(old('email')) ?>"></div>
    </div>
    <div class="row2">
      <div class="field"><label for="a-phone"><?= e(t('phone')) ?> <span class="req">*</span></label><input id="a-phone" type="tel" name="phone" required maxlength="40" autocomplete="tel" dir="ltr" value="<?= e(old('phone')) ?>"></div>
      <div class="field"><label for="a-city"><?= e(t('city')) ?> <small>(<?= e(t('optional')) ?>)</small></label><input id="a-city" name="city" maxlength="80" value="<?= e(old('city')) ?>"></div>
    </div>
    <div class="field"><label for="a-msg"><?= e(t('cover')) ?> <small>(<?= e(t('optional')) ?>)</small></label><textarea id="a-msg" name="message" rows="4" maxlength="3000"><?= e(old('message')) ?></textarea></div>
    <div class="field"><label for="a-cv"><?= e(t('cv')) ?> <span class="req">*</span></label><input id="a-cv" class="file-input" type="file" name="cv" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></div>
    <button class="btn btn-gold btn-lg" type="submit"><?= e(t('send_app')) ?></button>
  </form>
</section>
