<?php
namespace Concrete\Package\Indexnow\Controller\SinglePage\Dashboard\System\Seo;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Support\Facade\Session;
use Concrete\Core\Support\Facade\Url;


class Indexnow extends DashboardPageController
{
    public function view()
    {
        $this->set('token', $this->app->make('token'));
        $this->set('apiKey', \Concrete\Core\Support\Facade\Config::get('indexnow.api_key'));
        $endpoint = \Concrete\Core\Support\Facade\Config::get('indexnow.endpoint');
        if (!$endpoint) {
            $endpoint = 'https://api.indexnow.org/indexnow';
        }
        $this->set('endpoint', $endpoint);
        $this->set('success', Session::get('indexnow.success'));
        Session::remove('indexnow.success');
        

    }

    public function save_settings()
    {
        $this->set('token', $this->app->make('token'));
        if (!$this->token->validate('save_indexnow_settings')) {
            $this->error->add(t('Invalid security token. Please reload and try again.'));
            $this->view();
        }
        Config::save('indexnow.api_key', $this->post('api_key'));
        Config::save('indexnow.endpoint', $this->post('endpoint'));
        Session::set('indexnow.success', t('Settings saved!'));
        

        //return $this->redirect(Url::to('/dashboard/system/seo/indexnow'));
        $this->view();
    }
}
