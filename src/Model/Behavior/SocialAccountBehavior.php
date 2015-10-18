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

namespace Users\Model\Behavior;

use ArrayObject;
use Users\Exception\AccountAlreadyActiveException;
use Users\Exception\AccountNotActiveException;
use Users\Exception\MissingEmailException;
use Users\Model\Behavior\Behavior;
use Users\Model\Entity\User;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\Event;
use Cake\Mailer\Email;
use Cake\ORM\Entity;
use InvalidArgumentException;

/**
 * Covers social account features
 *
 */
class SocialAccountBehavior extends Behavior
{
    /**
     * Initialize, attaching belongsTo Users association
     *
     * @param array $config config
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->_table->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
            'className' => Configure::read('Users.table')
        ]);
    }

    /**
     * After save callback
     *
     * @param Event $event event
     * @param Entity $entity entity
     * @param ArrayObject $options options
     * @return mixed
     */
    public function afterSave(Event $event, Entity $entity, $options)
    {
        if ($entity->active) {
            return true;
        }
        $user = $this->_table->Users->find()->where(['Users.id' => $entity->user_id, 'Users.active' => true])->first();
        if (empty($user)) {
            return true;
        }
        return $this->sendSocialValidationEmail($entity, $user);
    }

    /**
     * Send social validation email to the user
     *
     * @param EntityInterface $socialAccount social account
     * @param EntityInterface $user user
     * @param Email $email Email instance or null to use 'default' configuration
     * @return mixed
     */
    public function sendSocialValidationEmail(EntityInterface $socialAccount, EntityInterface $user, Email $email = null)
    {
        $emailInstance = $this->_getEmailInstance($email);
        if (empty($email)) {
            $emailInstance->template('Users.social_account_validation');
        }
        $firstName = isset($user['first_name'])? $user['first_name'] . ', ' : '';
        //note: we control the space after the username in the previous line
        $subject = __d('Users', '{0}Your social account validation link', $firstName);
        return $emailInstance
            ->to($user['email'])
            ->subject($subject)
            ->viewVars(compact('user', 'socialAccount'))
            ->send();
    }

    /**
     * Validates the social account
     *
     * @param string $provider provider
     * @param string $reference reference
     * @param string $token token
     * @throws RecordNotFoundException
     * @throws AccountAlreadyActiveException
     * @return User
     */
    public function validateAccount($provider, $reference, $token)
    {
        $socialAccount = $this->_table->find()
            ->select(['id', 'provider', 'reference', 'active', 'token'])
            ->where(['provider' => $provider, 'reference' => $reference])
            ->first();

        if (!empty($socialAccount) && $socialAccount->token === $token) {
            if ($socialAccount->active) {
                throw new AccountAlreadyActiveException(__d('Users', "Account already validated"));
            }
        } else {
            throw new RecordNotFoundException(__d('Users', "Account not found for the given token and email."));
        }

        return $this->_activateAccount($socialAccount);
    }

    /**
     * Validates the social account
     *
     * @param string $provider provider
     * @param string $reference reference
     * @throws RecordNotFoundException
     * @throws AccountAlreadyActiveException
     * @return User
     */
    public function resendValidation($provider, $reference)
    {
        $socialAccount = $this->_table->find()
            ->where(['provider' => $provider, 'reference' => $reference])
            ->contain('Users')
            ->first();

        if (!empty($socialAccount)) {
            if ($socialAccount->active) {
                throw new AccountAlreadyActiveException(__d('Users', "Account already validated"));
            }
        } else {
            throw new RecordNotFoundException(__d('Users', "Account not found for the given token and email."));
        }

        return $this->sendSocialValidationEmail($socialAccount, $socialAccount->user);
    }

    /**
     * Activates an account
     *
     * @param Account $socialAccount social account
     * @return EntityInterface
     */
    protected function _activateAccount($socialAccount)
    {
        $socialAccount->active = true;
        $result = $this->_table->save($socialAccount);
        return $result;
    }
}
