<?php defined('C5_EXECUTE') or die("Access Denied."); ?>

<?php
use Concrete\Core\Page\Page;

if (!isset($c)) {
    $c = Page::getCurrentPage();
}

?>

<div class="ccm-ui">
    <?php if (isset($error) && $error->has()) { ?>
    <div class="alert alert-danger"><?=$error->output()?></div>
<?php } ?>
    <h2><?=t('IndexNow Settings')?></h2>
    <?php if (isset($success) && $success) { ?>
        <div class="alert alert-success"><?=$success?></div>
    <?php } ?>
    <?php if (isset($error) && $error->has()) { ?>
        <div class="alert alert-danger"><?=$error->output()?></div>
    <?php } ?>
    <form method="post" action="<?=$view->action('save_settings')?>">
        <?php
            $token = \Core::make('token');
            echo $token->output('save_indexnow_settings');
        ?>
        <div class="form-group">
            <label for="api_key"><?=t('API Key')?></label>
            <input type="text" name="api_key" id="api_key" class="form-control" value="<?=h($apiKey)?>" required />
        </div>
        <div class="form-group">
            <label for="endpoint"><?=t('Endpoint URL')?></label>
            <input type="text" name="endpoint" id="endpoint" class="form-control" value="<?=h($endpoint)?>" />
            <small class="form-text text-muted"><?=t('Default: %s', 'https://api.indexnow.org/indexnow')?></small>
        </div>
        <button type="submit" class="btn btn-primary"><?=t('Save Settings')?></button>
    </form>
</div>
