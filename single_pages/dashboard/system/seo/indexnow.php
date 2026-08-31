<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<div class="ccm-ui">
    <h2><?=t('IndexNow Settings')?></h2>
    <?php if (!empty($success)) { ?><div class="alert alert-success"><?=h($success)?></div><?php } ?>
    <?php if (isset($error) && $error->has()) { ?><div class="alert alert-danger"><?=$error->output()?></div><?php } ?>

    <form method="post" action="<?=$view->action('save_settings')?>" class="mb-4">
        <?=Core::make('token')->output('save_indexnow_settings')?>
        <div class="form-group mb-3">
            <label for="api_key" class="form-label"><?=t('API Key')?></label>
            <input type="text" name="api_key" id="api_key" class="form-control" value="<?=h($apiKey)?>" autocomplete="off">
        </div>
        <div class="form-group mb-3">
            <label for="endpoint" class="form-label"><?=t('Endpoint URL')?></label>
            <input type="url" name="endpoint" id="endpoint" class="form-control" value="<?=h($endpoint)?>" required>
            <small class="form-text text-muted"><?=t('Default: %s', 'https://api.indexnow.org/indexnow')?></small>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="debounce_minutes" class="form-label"><?=t('Debounce (minutes)')?></label>
                <input type="number" min="0" max="1440" name="debounce_minutes" id="debounce_minutes" class="form-control" value="<?=h($debounceMinutes)?>">
                <small class="form-text text-muted"><?=t('Repeated changes reset this timer, deduplicating rapid edits.')?></small>
            </div>
            <div class="col-md-4 mb-3">
                <label for="batch_size" class="form-label"><?=t('Batch size')?></label>
                <input type="number" min="1" max="10000" name="batch_size" id="batch_size" class="form-control" value="<?=h($batchSize)?>">
                <small class="form-text text-muted"><?=t('URLs per IndexNow request; maximum 10,000.')?></small>
            </div>
            <div class="col-md-4 mb-3">
                <label for="max_attempts" class="form-label"><?=t('Maximum attempts')?></label>
                <input type="number" min="1" max="20" name="max_attempts" id="max_attempts" class="form-control" value="<?=h($maxAttempts)?>">
            </div>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="debug_logging" value="1" id="debug_logging" class="form-check-input" <?=$debugLogging ? 'checked' : ''?>>
            <label for="debug_logging" class="form-check-label"><strong><?=t('Log IndexNow queue and API queries to the Concrete logs')?></strong></label>
            <div class="form-text"><?=t('Logs queued URLs, submitted batches, endpoint/host, response status and retry decisions under Dashboard → Reports → Logs. The API key is never written to the log.')?></div>
        </div>
        <button type="submit" class="btn btn-primary"><?=t('Save Settings')?></button>
    </form>

    <hr>
    <h3><?=t('Queue')?></h3>
    <p><?=t('Pending: %s · Processing: %s · Failed: %s · Total: %s', (int)$queueStats['pending'], (int)$queueStats['processing'], (int)$queueStats['failed'], (int)$queueStats['total'])?></p>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <form method="post" action="<?=$view->action('process_queue')?>">
            <?=Core::make('token')->output('indexnow_process_queue')?>
            <button class="btn btn-secondary" type="submit"><?=t('Process Ready Queue Now')?></button>
        </form>
        <form method="post" action="<?=$view->action('reconcile')?>">
            <?=Core::make('token')->output('indexnow_reconcile')?>
            <button class="btn btn-secondary" type="submit"><?=t('Reconcile Searchable Pages')?></button>
        </form>
        <?php if ((int)$queueStats['failed'] > 0) { ?>
        <form method="post" action="<?=$view->action('requeue_failed')?>">
            <?=Core::make('token')->output('indexnow_requeue_failed')?>
            <button class="btn btn-warning" type="submit"><?=t('Requeue Failed URLs')?></button>
        </form>
        <?php } ?>
    </div>

    <?php if (!empty($recentQueue)) { ?>
    <h4><?=t('Recent queued URLs')?></h4>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead><tr><th><?=t('Status')?></th><th><?=t('Reason')?></th><th><?=t('URL')?></th><th><?=t('Ready')?></th><th><?=t('Attempts')?></th></tr></thead>
            <tbody>
            <?php foreach ($recentQueue as $row) { ?>
                <tr>
                    <td><?=h($row['status'])?></td>
                    <td><?=h($row['reason'])?></td>
                    <td style="word-break:break-all"><?=h($row['url'])?></td>
                    <td><?=h($row['availableAt'])?></td>
                    <td><?=h($row['attempts'])?><?php if (!empty($row['lastError'])) { ?><br><small class="text-danger"><?=h($row['lastError'])?></small><?php } ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <div class="alert alert-info mt-4">
        <?=t('Normal page publishing only updates the local queue. Run the “IndexNow: Process Queue” task regularly (for example every 5 minutes) so ready URLs are submitted in batches.')?>
    </div>
</div>
