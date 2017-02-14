<?php
/**
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 *  @author    thirty bees <modules@thirtybees.com>
 *  @copyright 2017 thirty bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class TbUpdater
 */
class TbUpdater extends Module
{
    const AUTO_UPDATE = 'TBUPDATER_AUTO_UPDATE';
    const LAST_CHECK = 'TBUPDATER_LAST_CHECK';
    const LAST_UPDATE = 'TBUPDATER_LAST_UPDATE';

    const DOWNLOAD_URL = 'https://api.thirtybees.com/updates/1.0/updates.json';

    const CHECK_INTERVAL = 86400;

    public $baseUrl = '';

    /**
     * ModSelfUpdate constructor.
     */
    public function __construct()
    {
        $this->name = 'tbupdater';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'thirty bees';
        $this->bootstrap = true;
        $this->need_instance = 1;

        parent::__construct();
        $this->displayName = $this->l('thirty bees updater');
        $this->description = $this->l('Use this module to keep the core files and modules updated!');

        // Only check from Back Office
        if ($this->context->cookie->id_employee) {
            $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query([
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ]);

            $this->checkForUpdates();
        }
    }

    /**
     * Install this module
     *
     * @return bool Whether this module was successfully installed
     * @throws PrestaShopException
     */
    public function install()
    {
        return parent::install();
    }

    /**
     * Uninstall this module
     *
     * @return bool Whether this module was successfully uninstalled
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        Configuration::deleteByName(self::AUTO_UPDATE);
        Configuration::deleteByName(self::LAST_CHECK);

        return parent::uninstall();
    }

    /**
     * Update configuration value in ALL contexts
     *
     * @param string $key    Configuration key
     * @param mixed  $values Configuration values, can be string or array with id_lang as key
     * @param bool   $html   Contains HTML
     */
    public function updateAllValue($key, $values, $html = false)
    {
        foreach (Shop::getShops() as $shop) {
            Configuration::updateValue($key, $values, $html, $shop['id_shop_group'], $shop['id_shop']);
        }
        Configuration::updateGlobalValue($key, $values, $html);
    }

    /**
     * Get the Shop ID of the current context
     * Retrieves the Shop ID from the cookie
     *
     * @return int Shop ID
     */
    public function getShopId()
    {
        $cookie = Context::getContext()->cookie->getFamily('shopContext');

        return (int) Tools::substr($cookie['shopContext'], 2, count($cookie['shopContext']));
    }

    /**
     * Get module configuration page
     *
     * @return string Configuration page HTML
     */
    public function getContent()
    {
        $this->postProcess();

        $this->context->smarty->assign([
            'baseUrl' => $this->baseUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl').$this->renderGeneralOptions();
    }

    /**
     * Render the General options form
     *
     * @return string HTML
     */
    protected function renderGeneralOptions()
    {
        $helper = new HelperOptions();
        $helper->id = 1;
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;

        return $helper->generateOptions(array_merge($this->getModuleOptions()));
    }

    /**
     * Get available general options
     *
     * @return array General options
     */
    protected function getModuleOptions()
    {
        return [
            'module' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                'fields' => [
                    self::AUTO_UPDATE => [
                        'title' => $this->l('Auto update'),
                        'desc' => $this->l('Automatically update this module'),
                        'type' => 'bool',
                        'name' => self::AUTO_UPDATE,
                        'value' => Configuration::get(self::AUTO_UPDATE),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Process module settings
     *
     * @return void
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitOptionsconfiguration')) {
            $this->postProcessGeneralOptions();
        }
    }

    /**
     * Process General Options
     */
    protected function postProcessGeneralOptions()
    {
        return false;
    }

    /**
     * Check for module updates
     */
    protected function checkForUpdates()
    {
        $lastCheck = (int) Configuration::get(self::LAST_CHECK);

        if ($lastCheck < (time() - self::CHECK_INTERVAL) || Tools::getValue($this->name.'CheckUpdate')) {
            $guzzle = new GuzzleHttp\Client([
                'base_uri' => 'https://api.thirtybees.com',
                'http_errors' => false,
                'verify' => _PS_TOOL_DIR_.'cacert.pem',
            ]);

            try {
                $updates = (string) $guzzle->get('/updates/1.0/updates.json')->getBody();
                file_put_contents(__DIR__.'/data/updates.json', $updates);
            } catch (Exception $e) {
                return false;
            }

            Configuration::updateGlobalValue(self::LAST_CHECK, time());
        }

        return true;
    }

    /**
     * Add information message
     *
     * @param string $message Message
     */
    protected function addInformation($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->informations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->informations[] = $message;
        }
    }

    /**
     * Add confirmation message
     *
     * @param string $message Message
     */
    protected function addConfirmation($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->confirmations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->confirmations[] = $message;
        }
    }

    /**
     * Add warning message
     *
     * @param string $message Message
     */
    protected function addWarning($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->warnings[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Add error message
     *
     * @param string $message Message
     */
    protected function addError($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->errors[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            // Do not add error in this case
            // It will break execution of AdminController
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Validate GitHub repository
     *
     * @param string $repo Repository: username/repository
     * @return bool Whether the repository is valid
     */
    protected function validateRepo($repo)
    {
        return count(explode('/', $repo)) === 2;
    }

    /**
     * Extract module archive
     *
     * @param string $file     File location
     * @param bool   $redirect Whether there should be a redirection after extracting
     * @return bool
     */
    protected function extractArchive($file, $redirect = true)
    {
        $zipFolders = [];
        $tmpFolder = _PS_MODULE_DIR_.'selfupdate'.md5(time());

        if (@!file_exists($file)) {
            $this->addError($this->l('Module archive could not be downloaded'));

            return false;
        }

        $success = false;
        if (substr($file, -4) == '.zip') {
            if (Tools::ZipExtract($file, $tmpFolder) && file_exists($tmpFolder.DIRECTORY_SEPARATOR.$this->name)) {
                if (@rename(_PS_MODULE_DIR_.$this->name, _PS_MODULE_DIR_.$this->name.'backup') && @rename($tmpFolder.DIRECTORY_SEPARATOR.$this->name, _PS_MODULE_DIR_.$this->name)) {
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name.'backup');
                    $success = true;
                } else {
                    if (file_exists(_PS_MODULE_DIR_.$this->name.'backup')) {
                        $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name);
                        @rename(_PS_MODULE_DIR_.$this->name.'backup', _PS_MODULE_DIR_.$this->name);
                    }
                }
            }
        } else {
            require_once(_PS_TOOL_DIR_.'tar/Archive_Tar.php');
            $archive = new Archive_Tar($file);
            if ($archive->extract($tmpFolder)) {
                $zipFolders = scandir($tmpFolder);
                if ($archive->extract(_PS_MODULE_DIR_)) {
                    $success = true;
                }
            }
        }

        if (!$success) {
            $this->addError($this->l('There was an error while extracting the update (file may be corrupted).'));
            // Force a new check
            Configuration::updateGlobalValue(self::LAST_CHECK, 0);
        } else {
            //check if it's a real module
            foreach ($zipFolders as $folder) {
                if (!in_array($folder, ['.', '..', '.svn', '.git', '__MACOSX']) && !Module::getInstanceByName($folder)) {
                    $this->addError(sprintf($this->l('The module %1$s that you uploaded is not a valid module.'), $folder));
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$folder);
                }
            }
        }

        @unlink($file);
        $this->recursiveDeleteOnDisk($tmpFolder);

        if ($success) {
            Configuration::updateGlobalValue(self::LAST_UPDATE, (int) time());
            if ($redirect) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&doNotAutoUpdate=1');
            }
        }

        return $success;
    }

    /**
     * Delete folder recursively
     *
     * @param string $dir Directory
     */
    protected function recursiveDeleteOnDisk($dir)
    {
        if (strpos(realpath($dir), realpath(_PS_MODULE_DIR_)) === false) {
            return;
        }

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        $this->recursiveDeleteOnDisk($dir.'/'.$object);
                    } else {
                        @unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
