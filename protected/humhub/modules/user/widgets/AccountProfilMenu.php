<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\user\widgets;

use Yii;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\ui\menu\widgets\TabMenu;

/**
 * Account Settings Tab Menu
 */
class AccountProfilMenu extends TabMenu
{

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->addEntry(new MenuLink([
            'label' => Yii::t('UserModule.base', 'General'),
            'url' => ['/user/account/edit'],
            'sortOrder' => 100,
            'isActive' => MenuLink::isActiveState('user', 'account', 'edit')
        ]));

        if (Yii::$app->user->canChangeUsername()) {
            $this->addItem([
                'label' => Yii::t('UserModule.base', 'Change Username'),
                'url' => Url::toRoute(['/user/account/change-username']),
                'sortOrder' => 200,
                'isActive' => (Yii::$app->controller->module && Yii::$app->controller->module->id == 'user' && Yii::$app->controller->id == 'account' && Yii::$app->controller->action->id == 'change-username'),
            ]);
        }

        if (Yii::$app->user->canChangeEmail()) {
            $this->addItem([
                'label' => Yii::t('UserModule.base', 'Change Email'),
                'url' => Url::toRoute(['/user/account/change-email']),
                'sortOrder' => 300,
                'isActive' => (Yii::$app->controller->module && Yii::$app->controller->module->id == 'user' && Yii::$app->controller->id == 'account' && (Yii::$app->controller->action->id == 'change-email' || Yii::$app->controller->action->id == 'change-email-validate')),
            ]);
        }
        $this->addEntry(new MenuLink([
            'label' => Yii::t('UserModule.base', 'Change Email'),
            'url' => ['/user/account/change-email'],
            'sortOrder' => 200,
            'isActive' => MenuLink::isActiveState('user', 'account', ['change-email', 'change-email-validate']),
            'isVisible' => Yii::$app->user->canChangeEmail()
        ]));

        $this->addEntry(new MenuLink([
            'label' => Yii::t('UserModule.base', 'Change Password'),
            'url' => ['/user/account/change-password'],
            'sortOrder' => 400,
            'isActive' => MenuLink::isActiveState('user', 'account', 'change-password'),
            'isVisible' => Yii::$app->user->canChangePassword()
        ]));

        $this->addEntry(new MenuLink([
            'label' => Yii::t('UserModule.base', 'Delete Account'),
            'url' => ['/user/account/delete'],
            'sortOrder' => 500,
            'isActive' => MenuLink::isActiveState('user', 'account', 'delete'),
            'isVisible' => Yii::$app->user->canDeleteAccount()
        ]));

        parent::init();
    }

    /**
     * Returns optional authclients
     *
     * @return \yii\authclient\ClientInterface[]
     * @throws \yii\base\InvalidConfigException
     */
    protected function getSecondoaryAuthProviders()
    {
        $clients = [];
        foreach (Yii::$app->get('authClientCollection')->getClients() as $client) {
            if (!$client instanceof \humhub\modules\user\authclient\BaseFormAuth && !$client instanceof \humhub\modules\user\authclient\interfaces\PrimaryClient) {
                $clients[] = $client;
            }
        }

        return $clients;
    }

}
