<?php
namespace Users\Controller\Admin;

use App\Controller\Admin\AppController as BaseController;

/**
 * AppController for Users Plugin
 */
class AppController extends BaseController
{
    /**
     * Initialize
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('Security');
        $this->loadComponent('Csrf');
        $this->loadComponent('Users.UsersAuth');
    }
}
