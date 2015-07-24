<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\components;

use Yii;
use humhub\models\ModuleEnabled;
use yii\base\Exception;

/**
 * Base Class for Modules / Extensions
 *
 * @author luke
 */
class Module extends \yii\base\Module
{

    /**
     * Loaded Module JSON File
     *
     * @var Array
     */
    private $_moduleInfo = null;

    /**
     * Config Route
     */
    public $configRoute = null;

    /**
     * The path for module resources (images, javascripts)
     * Also module related assets like README.md and module_image.png should be placed here.
     *
     * @var type
     */
    public $resourcesPath = 'assets';

    /**
     * Returns modules name provided by module.json file
     *
     * @return string Description
     */
    public function getName()
    {
        $info = $this->getModuleInfo();

        if ($info['name']) {
            return $info['name'];
        }

        return $this->getId();
    }

    /**
     * Returns modules description provided by module.json file
     *
     * @return string Description
     */
    public function getDescription()
    {
        $info = $this->getModuleInfo();

        if ($info['description']) {
            return $info['description'];
        }

        return "";
    }

    /**
     * Returns modules version number provided by module.json file
     *
     * @return string Version Number
     */
    public function getVersion()
    {
        $info = $this->getModuleInfo();

        if ($info['version']) {
            return $info['version'];
        }

        return "1.0";
    }

    /**
     * Returns image url for this module
     * Place your modules image in assets/module_image.png
     *
     * @return String Image Url
     */
    public function getImage()
    {
        $moduleImageFile = $this->getBasePath() . '/' . $this->resourcesPath . '/module_image.png';

        if (is_file($moduleImageFile)) {
            $published = $assetManager = Yii::$app->assetManager->publish($moduleImageFile);
            return $published[1];
        }

        return Yii::getAlias("@web/img/default_module.jpg");
    }

    /**
     * Enables this module
     * It will be available on the next request.
     *
     * @return boolean
     */
    public function enable()
    {
        if (!Yii::$app->hasModule($this->id)) {

            $moduleEnabled = ModuleEnabled::findOne(['module_id' => $this->id]);
            if ($moduleEnabled == null) {
                $moduleEnabled = new ModuleEnabled();
                $moduleEnabled->module_id = $this->id;
                $moduleEnabled->save();
            }

            $this->migrate();
            return true;
        }

        return false;
    }

    /**
     * Disables a module
     *
     * Which means delete all (user-) data created by the module.
     *
     */
    public function disable()
    {

        // Seems not enabled
        if (!Yii::$app->hasModule($this->id)) {
            return false;
        }

        /*
          // Check this module is a SpaceModule
          if ($this->isSpaceModule()) {
          foreach ($this->getSpaceModuleSpaces() as $space) {
          $space->disableModule($this->getId());
          }
          }

          // Check this module is a UserModule
          if ($this->isUserModule()) {
          foreach ($this->getUserModuleUsers() as $user) {
          $user->disableModule($this->getId());
          }
          }
         */

        // Disable module in database
        $moduleEnabled = ModuleEnabled::findOne(['module_id' => $this->id]);
        if ($moduleEnabled != null) {
            $moduleEnabled->delete();
        }
        /*
          HSetting::model()->deleteAllByAttributes(array('module_id' => $this->getId()));
          SpaceSetting::model()->deleteAllByAttributes(array('module_id' => $this->getId()));
          UserSetting::model()->deleteAllByAttributes(array('module_id' => $this->getId()));

          // Delete also records with disabled state from SpaceApplicationModule Table
          foreach (SpaceApplicationModule::model()->findAllByAttributes(array('module_id' => $this->getId())) as $sam) {
          $sam->delete();
          }

          // Delete also records with disabled state from UserApplicationModule Table
          foreach (UserApplicationModule::model()->findAllByAttributes(array('module_id' => $this->getId())) as $uam) {
          $uam->delete();
          }

          ModuleManager::flushCache();
         */
        return true;
    }

    /**
     * Execute all not applied module migrations
     */
    protected function migrate()
    {
        $migrationPath = $this->basePath . '/migrations';
        if (is_dir($migrationPath)) {
            \humhub\commands\MigrateController::webMigrateUp($migrationPath);
        }
    }

    /**
     * Reads module.json which contains basic module informations and
     * returns it as array
     *
     * @return Array module.json content
     */
    protected function getModuleInfo()
    {
        if ($this->_moduleInfo != null) {
            return $this->_moduleInfo;
        }

        $moduleJson = file_get_contents($this->getBasePath() . DIRECTORY_SEPARATOR . 'module.json');
        return \yii\helpers\Json::decode($moduleJson);
    }

    /**
     * Uninstalls a module
     *
     * You may overwrite this method to add more cleanup stuff.
     *
     * This method shall:
     *      - Delete all module files
     *      - Delete all modules tables, database changes
     */
    public function uninstall()
    {
        // Module enabled?
        if (Yii::$app->hasModule($this->id)) {
            $this->disable();
        }

        // Use uninstall migration, when found
        $migrationPath = $this->getBasePath() . '/migrations';
        $uninstallMigration = $migrationPath . '/uninstall.php';
        if (file_exists($uninstallMigration)) {

            /**
             * Execute Uninstall Migration
             */
            ob_start();
            require_once($uninstallMigration);
            $migration = new \uninstall;
            try {
                $migration->up();
            } catch (\yii\db\Exception $ex) {
                ;
            }
            ob_get_clean();

            /**
             * Delete all Migration Table Entries
             */
            $migrations = opendir($migrationPath);
            while (false !== ($migration = readdir($migrations))) {
                if ($migration == '.' || $migration == '..' || $migration == 'uninstall.php') {
                    continue;
                }
                Yii::$app->db->createCommand()->delete('migration', ['version' => str_replace('.php', '', $migration)])->execute();
            }

            $this->removeModuleFolder();
        }

        Yii::$app->moduleManager->flushCache();
    }

    /**
     * Installs a module
     */
    public function install()
    {
        $this->migrate();
    }

    /**
     * This method is called after an update is performed.
     * You may extend it with your own update process.
     *
     */
    public function update()
    {
        $this->migrate();
    }

    /**
     * Removes the module folder
     * This is required for uninstall or while update.
     */
    public function removeModuleFolder()
    {

        $moduleBackupFolder = Yii::getAlias("@runtime/module_backups");
        if (!is_dir($moduleBackupFolder)) {
            if (!@mkdir($moduleBackupFolder)) {
                throw new Exception("Could not create module backup folder!");
            }
        }

        $backupFolderName = $moduleBackupFolder . DIRECTORY_SEPARATOR . $this->id . "_" . time();
        if (!@rename($this->getBasePath(), $backupFolderName)) {
            throw new Exception("Could not remove module folder!");
        }
    }

    /**
     * Indicates that module acts as Space Module.
     *
     * @return boolean
     */
    public function isSpaceModule()
    {
        foreach ($this->getBehaviors() as $name => $behavior) {
            if ($behavior instanceof \humhub\modules\space\behaviors\SpaceModule) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indicates that module acts as User Module.
     *
     * @return boolean
     */
    public function isUserModule()
    {
        foreach ($this->getBehaviors() as $name => $behavior) {
            if ($behavior instanceof \humhub\modules\user\behaviors\UserModule) {
                return true;
            }
        }

        return false;
    }

    public function canDisable()
    {
        return true;
    }

    public function canUninstall()
    {
        return true;
    }

    public function getConfigUrl()
    {
        return "";
    }

}
