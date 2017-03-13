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

use function GuzzleHttp\Promise\settle;
use TbUpdaterModule\SemVer\Expression;
use TbUpdaterModule\SemVer\Version;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/classes/autoload.php';

/**
 * Class TbUpdater
 *
 * @since 1.0.0
 */
class TbUpdater extends Module
{
    const AUTO_UPDATE = 'TBUPDATER_AUTO_UPDATE';
    const LAST_CHECK = 'TBUPDATER_LAST_CHECK';
    const LAST_UPDATE = 'TBUPDATER_LAST_UPDATE';
    const CHANNEL = 'TBUPDATER_CHANNEL';

    const BASE_URL = 'https://api.thirtybees.com/updates/';

    const CHECK_INTERVAL = 86400;

    const LATEST_CORE_PATCH = 'TB_LATEST_CORE_PATCH';
    const LATEST_CORE_MINOR = 'TB_LATEST_CORE_MINOR';
    const LATEST_CORE_MAJOR = 'TB_LATEST_CORE_MAJOR';

    public $baseUrl = '';

    /**
     * ModSelfUpdate constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'tbupdater';
        $this->tab = 'administration';
        $this->version = '1.0.1';
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

            if (Configuration::get(self::AUTO_UPDATE)) {
                $this->checkForUpdates();
            }
        }
    }

    /**
     * Install this module
     *
     * @return bool Whether this module was successfully installed
     * @throws PrestaShopException
     *
     * @since 1.0.0
     */
    public function install()
    {
        Configuration::updateGlobalValue(self::AUTO_UPDATE, true);
        Configuration::updateGlobalValue(self::CHANNEL, 'stable');

        return parent::install();
    }

    /**
     * Uninstall this module
     *
     * @return bool Whether this module was successfully uninstalled
     * @throws PrestaShopException
     *
     * @since 1.0.0
     */
    public function uninstall()
    {
        Configuration::deleteByName(self::AUTO_UPDATE);
        Configuration::deleteByName(self::LAST_CHECK);
        Configuration::deleteByName(self::CHANNEL);

        return parent::uninstall();
    }

    /**
     * Update configuration value in ALL contexts
     *
     * @param string $key    Configuration key
     * @param mixed  $values Configuration values, can be string or array with id_lang as key
     * @param bool   $html   Contains HTML
     *
     * @return void
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('checkForUpdate')) {
            $this->checkForUpdates(true);
        } elseif (Tools::isSubmit('submitOptionsconfiguration')) {
            $this->postProcessGeneralOptions();
        }
    }

    /**
     * Process General Options
     *
     * @since 1.0.0
     */
    protected function postProcessGeneralOptions()
    {
        return false;
    }

    /**
     * Check for module updates
     *
     * @param bool $force Force check
     *
     * @return bool Indicates whether the update was successful
     *
     * @since 1.0.0
     */
    protected function checkForUpdates($force = false)
    {
        $lastCheck = (int) Configuration::get(self::LAST_CHECK);

        if ($force || $lastCheck < (time() - self::CHECK_INTERVAL) || Tools::getValue($this->name.'CheckUpdate')) {
            $guzzle = new GuzzleHttp\Client([
                'base_uri' => 'https://api.thirtybees.com',
                'verify' => _PS_TOOL_DIR_.'cacert.pem',
            ]);

            $channel = Configuration::get(self::CHANNEL);
            if (!in_array($channel, ['stable', 'beta', 'dev'])) {
                $channel = 'stable';
            }

            $currentVersion = new Version(_TB_VERSION_);
            $countryModules = Tools::strtolower(Configuration::get('PS_LOCALE_COUNTRY'));

            $promises = [
                'updates' => $guzzle->getAsync("/updates/channel/{$channel}.json"),
                'modules' => $guzzle->getAsync("/updates/modules/all.json"),
                'country' => $guzzle->getAsync("/updates/modules/{$countryModules}.json"),
            ];

            $results = settle($promises)->wait();

            $cache = [];

            if (isset($results['updates']['value']) && $results['updates']['value'] instanceof GuzzleHttp\Psr7\Response) {
                $updates = (string) $results['updates']['value']->getBody();
                $updates = json_decode($updates, true);
                if ($updates) {
                    $versions = array_keys($updates);

                    $patchRange = new Expression('~'._TB_VERSION_);
                    $minorRange = new Expression('~'.$currentVersion->getMajor().'.'.$currentVersion->getMinor());
                    $majorRange = new Expression('*');

                    if ($maxPatchSatisfying = $patchRange->maxSatisfying($versions)->getVersion()) {
                        Configuration::updateGlobalValue(self::LATEST_CORE_PATCH, $maxPatchSatisfying);
                    }
                    if ($maxMinorSatisfying = $minorRange->maxSatisfying($versions)->getVersion()) {
                        Configuration::updateGlobalValue(self::LATEST_CORE_MINOR, $maxMinorSatisfying);
                    }
                    if ($maxMajorSatisfying = $majorRange->maxSatisfying($versions)->getVersion()) {
                        Configuration::updateGlobalValue(self::LATEST_CORE_MAJOR, $maxMajorSatisfying);
                    }
                }
            }

            if (isset($results['modules']['value']) && $results['modules']['value'] instanceof GuzzleHttp\Psr7\Response) {
                $modules = (string) $results['modules']['value']->getBody();
                $modules = json_decode($modules, true);
                if ($modules && is_array($modules)) {
                    foreach ($modules as $moduleName => &$module) {
                        if (isset($module['versions']) && $highestVersion = self::findHighestVersion(_TB_VERSION_, $module['versions'])) {
                            $module['versions'] = [
                                'latest' => $highestVersion,
                                'binary' => $module['versions'][$highestVersion]['binary'],
                            ];

                            $cache[$moduleName] = $module;
                        }
                    }
                }
            }

            if (isset($results['country']['value']) && $results['country']['value'] instanceof GuzzleHttp\Psr7\Response) {
                $countryModules = (string) $results['country']['value']->getBody();
                $countryModules = json_decode($countryModules, true);
                if ($countryModules && is_array($countryModules)) {
                    foreach ($countryModules as $moduleName => &$module) {
                        if (isset($module['versions']) && $highestVersion = self::findHighestVersion(_TB_VERSION_, $module['versions'])) {
                            $module['versions'] = [
                                'latest' => $highestVersion,
                                'binary' => $module['versions'][$highestVersion]['binary'],
                            ];

                            $cache[$moduleName] = $module;
                        }
                    }
                }
            }

            if (is_array($cache) && !empty($cache)) {
                @file_put_contents(__DIR__.'/cache/modules.json', json_encode($cache, JSON_PRETTY_PRINT));
            }

            Configuration::updateGlobalValue(self::LAST_CHECK, time());
        }

        return true;
    }

    /**
     * Add information message
     *
     * @param string $message Message
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
     *
     * @return bool Whether the repository is valid
     *
     * @since 1.0.0
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
     *
     * @return bool
     *
     * @since 1.0.0
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
                $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name.'backup');
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
        }

        if (!$success) {
            $this->addError($this->l('There was an error while extracting the module update(file may be corrupted).'));
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
        @unlink(_PS_MODULE_DIR_.$this->name.'backup');
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
     *
     * @since 1.0.0
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

    /**
     * Find the highest version of a module
     *
     * @param string $tbVersion      Current TB version
     * @param array  $moduleVersions Module version info
     *
     * @return bool|string Version number
     *                     `false` if not found
     *
     * @since 1.0.0
     */
    protected function findHighestVersion($tbVersion, $moduleVersions)
    {
        if (!$tbVersion || !is_array($moduleVersions)) {
            return false;
        }

        $tbVersion = new Version($tbVersion);

        $versions = [];
        foreach ($moduleVersions as $versionNumber => $versionInfo) {
            $range = new Expression($versionInfo['range']);
            if ($tbVersion->satisfies($range)) {
                $versions[] = $versionNumber;
            }
        }

        usort($versions, ['self', 'compareVersionReverse']);
        if (!empty($versions)) {
            return $versions[0];
        }

        return false;
    }

    /**
     * Reverse compare version
     *
     * @param string $a
     * @param string $b
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function compareVersionReverse($a, $b)
    {
        return Version::lt($a, $b);
    }
}
