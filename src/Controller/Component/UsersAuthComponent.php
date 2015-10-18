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

namespace Users\Controller\Component;

use Users\Exception\BadConfigurationException;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Routing\Router;
use Cake\Utility\Hash;

class UsersAuthComponent extends Component
{
    const EVENT_IS_AUTHORIZED = 'Users.Component.UsersAuth.isAuthorized';
    const EVENT_BEFORE_LOGIN = 'Users.Component.UsersAuth.beforeLogin';
    const EVENT_AFTER_LOGIN = 'Users.Component.UsersAuth.afterLogin';
    const EVENT_AFTER_COOKIE_LOGIN = 'Users.Component.UsersAuth.afterCookieLogin';
    const EVENT_BEFORE_REGISTER = 'Users.Component.UsersAuth.beforeRegister';
    const EVENT_AFTER_REGISTER = 'Users.Component.UsersAuth.afterRegister';
    const EVENT_BEFORE_LOGOUT = 'Users.Component.UsersAuth.beforeLogout';
    const EVENT_AFTER_LOGOUT = 'Users.Component.UsersAuth.afterLogout';

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        //Table used to manage users
        'table' => 'Users.Users',
        //configure Auth component
        'auth' => true,
        //Password Hasher
        'passwordHasher' => '\Cake\Auth\DefaultPasswordHasher',
        //token expiration, 1 hour
        'Token' => ['expiration' => 3600],
        'Email' => [
            //determines if the user should include email
            'required' => true,
            //determines if registration workflow includes email validation
            'validate' => true,
        ],
        'Registration' => [
            //determines if the register is enabled
            'active' => true,
            //determines if the reCaptcha is enabled for registration
            'reCaptcha' => true,
        ],
        'Tos' => [
            //determines if the user should include tos accepted
            'required' => true,
        ],
        'Social' => [
            //enable social login
            'login' => true,
        ],
        'Profile' => [
            //Allow view other users profiles
            'viewOthers' => true,
            'route' => ['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'profile'],
        ],
        'Key' => [
            'Session' => [
                //session key to store the social auth data
                'social' => 'Users.social',
                //userId key used in reset password workflow
                'resetPasswordUserId' => 'Users.resetPasswordUserId',
            ],
            //form key to store the social auth data
            'Form' => [
                'social' => 'social'
            ],
            'Data' => [
                //data key to store the users email
                'email' => 'email',
                //data key to store email coming from social networks
                'socialEmail' => 'info.email',
                //data key to check if the remember me option is enabled
                'rememberMe' => 'remember_me',
            ],
        ],
        //Avatar placeholder
        'Avatar' => ['placeholder' => 'Users.avatar_placeholder.png'],
        'RememberMe' => [
            //configure Remember Me component
            'active' => true,
            'Cookie' => [
                'name' => 'remember_me',
                'Config' => [
                    'expires' => '1 month',
                    'httpOnly' => true,
                ]
            ]
        ],
        //default configuration used to auto-load the Auth Component, override to change the way Auth works
        'Auth' => [
            'loginAction' => [
                'plugin' => 'Users',
                'controller' => 'Gateway',
                'action' => 'login',
            ],
            'authenticate' => [
                'all' => [
                    'scope' => ['active' => 1]
                ],
                'Users.RememberMe',
                'Form',
            ],
            'authorize' => [
                'Users.Superuser',
                'Users.SimpleRbac',
            ],
        ],
        //default Opauth configuration, you'll need to provide the strategy keys
        'Opauth' => [
            'path' => ['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'opauthInit'],
            'callback_param' => 'callback',
            'complete_url' => ['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'login'],
            'Strategy' => [
                'Facebook' => [
                    'scope' => ['public_profile', 'user_friends', 'email'],
                    //app_id => 'YOUR_APP_ID',
                    //app_secret = 'YOUR_APP_SECRET',
                ],
                'Twitter' => [
                    'curl_cainfo' => false,
                    'curl_capath' => false,
                    //'key' => 'YOUR_APP_KEY',
                    //'secret' => 'YOUR_APP_SECRET',
                ]
            ]
        ]
    ];

    /**
     * Initialize method, setup Auth if not already done passing the $config provided and
     * setup the default table to Users.Users if not provided
     *
     * @param array $config config options
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $defaults = Hash::merge(
            $this->_defaultConfig,
            (array) Configure::read('Users'),
            $config
        );
        Configure::write('Users',$defaults);
        $this->config($defaults);

        $this->_validateConfig();
        $this->_initAuth();

        if (Configure::read('Users.Social.login') && Configure::read('Opauth')) {
            $this->_configOpauthRoutes();
        }
        if (Configure::read('Users.Social.login')) {
            $this->_loadSocialLogin();
        }
        if (Configure::read('Users.RememberMe.active')) {
            $this->_loadRememberMe();
        }

        $this->_attachPermissionChecker();
    }

    /**
     * Load Social Auth object
     *
     * @return void
     */
    protected function _loadSocialLogin()
    {
        $this->_registry->getController()->Auth->config('authenticate', [
            'Users.Social'
        ], true);
    }

    /**
     * Load RememberMe component and Auth objects
     *
     * @return void
     */
    protected function _loadRememberMe()
    {
        $this->_registry->getController()->loadComponent('Users.RememberMe');
    }

    /**
     * Attach the isUrlAuthorized event to allow using the Auth authorize from the UserHelper
     *
     * @return void
     */
    protected function _attachPermissionChecker()
    {
        $this->_registry->getController()->eventManager()->on(self::EVENT_IS_AUTHORIZED, [], [$this, 'isUrlAuthorized']);
    }

    /**
     * Initialize the AuthComponent and configure allowed actions
     *
     * @return void
     */
    protected function _initAuth()
    {
        if(!$this->_registry->has('Auth')) {
            if($this->_config['auth']) {
                //initialize Auth
                $this->_registry->getController()->loadComponent('Auth', $this->_config['Auth']);
            }
        }

        $this->_registry->getController()->Auth->allow([
            'register',
            'validateEmail',
            'resendTokenValidation',
            'login',
            'logout',
            'socialEmail',
            'opauthInit',
            'resetPassword',
            'requestResetPassword',
            'changePassword',
        ]);
    }

    /**
     * Check if a given url is authorized
     *
     * @param Event $event event
     *
     * @return bool
     */
    public function isUrlAuthorized(Event $event)
    {
        $user = $this->_registry->getController()->Auth->user();
        if (empty($user)) {
            return false;
        }
        $url = Hash::get((array)$event->data, 'url');
        if (empty($url)) {
            return false;
        }

        if (is_array($url)) {
            $requestUrl = Router::reverse($url);
            $requestParams = Router::parse($requestUrl);
        } else {
            $requestParams = Router::parse($url);
            $requestUrl = $url;
        }
        $request = new Request($requestUrl);
        $request->params = $requestParams;

        $isAuthorized = $this->_registry->getController()->Auth->isAuthorized(null, $request);
        return $isAuthorized;
    }

    /**
     * Validate if the passed configuration makes sense
     *
     * @throws BadConfigurationException
     * @return void
     */
    protected function _validateConfig()
    {
        if (!Configure::read('Users.Email.required') && Configure::read('Users.Email.validate')) {
            $message = __d('Users', 'You can\'t enable email validation workflow if use_email is false');
            throw new BadConfigurationException($message);
        }
    }

    /**
     * Config Opauth urls
     *
     * @return void
     */
    protected function _configOpauthRoutes()
    {
        $path = Configure::read('Opauth.path');
        Configure::write('Opauth.path', Router::url($path) . '/');
        //Generate callback url
        if (is_array($path)) {
            $path[] = Configure::read('Opauth.callback_param');
        } else {
            $path = $path . Configure::read('Opauth.callback_param');
        }
        Configure::write('Opauth.callback_url', Router::url($path));
    }
}
