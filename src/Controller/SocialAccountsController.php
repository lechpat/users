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

namespace Users\Controller;

use Users\Controller\AppController;
use Users\Exception\AccountAlreadyActiveException;
use Users\Model\Table\SocialAccountsTable;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Response;

/**
 * SocialAccounts Controller
 *
 * @property SocialAccountsTable $SocialAccounts
 */
class SocialAccountsController extends AppController
{

    /**
     * Init
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['validateAccount', 'resendValidation']);
    }

    /**
     * Validates social account
     *
     * @param string $provider provider
     * @param string $reference reference
     * @param string $token token
     * @return Response
     */
    public function validateAccount($provider, $reference, $token)
    {
        try {
            $result = $this->SocialAccounts->validateAccount($provider, $reference, $token);
            if ($result) {
                $this->Flash->success(__d('Users', 'Account validated successfully'));
            } else {
                $this->Flash->error(__d('Users', 'Account could not be validated'));
            }
        } catch (RecordNotFoundException $exception) {
            $this->Flash->error(__d('Users', 'Invalid token and/or social account'));
        } catch (AccountAlreadyActiveException $exception) {
            $this->Flash->error(__d('Users', 'SocialAccount already active'));
        } catch (Exception $exception) {
            $this->Flash->error(__d('Users', 'Social Account could not be validated'));
        }
        return $this->redirect(['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'login']);
    }

    /**
     * Resends validation email if required
     *
     * @param string $provider provider
     * @param string $reference reference
     * @return Response|void
     * @throws \Users\Model\Table\AccountAlreadyActiveException
     */
    public function resendValidation($provider, $reference)
    {
        try {
            $result = $this->SocialAccounts->resendValidation($provider, $reference);
            if ($result) {
                $this->Flash->success(__d('Users', 'Email sent successfully'));
            } else {
                $this->Flash->error(__d('Users', 'Email could not be sent'));
            }
        } catch (RecordNotFoundException $exception) {
            $this->Flash->error(__d('Users', 'Invalid account'));
        } catch (AccountAlreadyActiveException $exception) {
            $this->Flash->error(__d('Users', 'Social Account already active'));
        } catch (Exception $exception) {
            $this->Flash->error(__d('Users', 'Email could not be resent'));
        }
        return $this->redirect(['plugin' => 'Users', 'controller' => 'Gateway', 'action' => 'login']);
    }
}
