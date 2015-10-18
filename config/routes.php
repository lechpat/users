<?php
/**
 * Copyright 2010 - 2015, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2015, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

Router::plugin('Users', ['path' => '/users'], function ($routes) {
    $routes->fallbacks('DashedRoute');
});

$oauthPath = Configure::read('Opauth.path');
if (is_array($oauthPath)) {
    Router::scope('/auth', function ($routes) use ($oauthPath) {
        $routes->connect(
            '/*',
            $oauthPath
        );
    });
}

Router::connect('/accounts/validate/*', [
    'plugin' => 'Users',
    'controller' => 'SocialAccounts',
    'action' => 'validate'
]);
Router::connect('/profile/*', ['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'profile']);
Router::connect('/login', ['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'login'],['_name' => 'login']);
Router::connect('/logout', ['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'logout'],['_name' => 'logout']);
