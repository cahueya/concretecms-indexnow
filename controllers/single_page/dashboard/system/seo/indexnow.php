<?php
namespace Concrete\Package\Indexnow\Controller\SinglePage\Dashboard\System\Seo;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Support\Facade\Session;
use Concrete\Package\Indexnow\Queue\QueueRepository;
use Concrete\Package\Indexnow\Service\QueueProcessor;
use Concrete\Package\Indexnow\Service\Reconciler;

class Indexnow extends DashboardPageController
{
    public function view()
    {
        $this->set('apiKey', Config::get('indexnow.api_key'));
        $this->set('endpoint', Config::get('indexnow.endpoint', 'https://api.indexnow.org/indexnow'));
        $this->set('debounceMinutes', (int) Config::get('indexnow.debounce_minutes', 5));
        $this->set('batchSize', (int) Config::get('indexnow.batch_size', 500));
        $this->set('maxAttempts', (int) Config::get('indexnow.max_attempts', 5));
        $this->set('debugLogging', (bool) Config::get('indexnow.debug_logging', false));
        $queue = new QueueRepository();
        $this->set('queueStats', $queue->getStats());
        $this->set('recentQueue', $queue->getRecent(25));
        $this->set('success', Session::get('indexnow.success'));
        Session::remove('indexnow.success');
    }

    public function save_settings()
    {
        if (!$this->token->validate('save_indexnow_settings')) {
            $this->error->add(t('Invalid security token. Please reload and try again.'));
            return $this->view();
        }
        $endpoint = trim((string) $this->post('endpoint'));
        $parts = parse_url($endpoint);
        if (!$endpoint || empty($parts['host']) || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $this->error->add(t('Please enter a valid HTTP(S) endpoint URL.'));
            return $this->view();
        }
        $apiKey = trim((string) $this->post('api_key'));
        if ($apiKey !== '' && !preg_match('/^[A-Za-z0-9-]{8,128}$/', $apiKey)) {
            $this->error->add(t('The IndexNow API key must be 8–128 characters and contain only letters, numbers, and hyphens.'));
            return $this->view();
        }
        $debounce = max(0, min(1440, (int) $this->post('debounce_minutes')));
        $batch = max(1, min(10000, (int) $this->post('batch_size')));
        $attempts = max(1, min(20, (int) $this->post('max_attempts')));
        Config::save('indexnow.api_key', $apiKey);
        Config::save('indexnow.endpoint', $endpoint);
        Config::save('indexnow.debounce_minutes', $debounce);
        Config::save('indexnow.batch_size', $batch);
        Config::save('indexnow.max_attempts', $attempts);
        Config::save('indexnow.debug_logging', (bool) $this->post('debug_logging'));
        Session::set('indexnow.success', t('IndexNow settings saved.'));
        return $this->redirect('/dashboard/system/seo/indexnow');
    }

    public function process_queue()
    {
        if (!$this->token->validate('indexnow_process_queue')) {
            $this->error->add(t('Invalid security token.'));
            return $this->view();
        }
        $result = (new QueueProcessor())->process();
        Session::set('indexnow.success', $result['message']);
        return $this->redirect('/dashboard/system/seo/indexnow');
    }

    public function reconcile()
    {
        if (!$this->token->validate('indexnow_reconcile')) {
            $this->error->add(t('Invalid security token.'));
            return $this->view();
        }
        $result = (new Reconciler())->reconcile();
        Session::set('indexnow.success', $result['message']);
        return $this->redirect('/dashboard/system/seo/indexnow');
    }

    public function requeue_failed()
    {
        if (!$this->token->validate('indexnow_requeue_failed')) {
            $this->error->add(t('Invalid security token.'));
            return $this->view();
        }
        $count = (new QueueRepository())->requeueFailed();
        Session::set('indexnow.success', t('Requeued %s failed URL(s).', $count));
        return $this->redirect('/dashboard/system/seo/indexnow');
    }
}
