<?php
/**
 * Copyright (C) 2017-2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

use TbUpdaterModule\SemVer\Expression;
use TbUpdaterModule\SemVer\Version;
use TbUpdaterModule\Upgrader;
use TbUpdaterModule\UpgraderTools;
use TbUpdaterModule\ConfigurationTest;

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
    /** @var array $upgradeOptions */
    public $upgradeOptions = [];
    /** @var array $backupOptions */
    public $backupOptions = [];
    /** @var UpgraderTools $tool */
    protected $tools;
    /** @var Upgrader $upgrader */
    protected $upgrader;
    /** @var string|null $backupName Chosen backup name */
    protected $backupName = null;
    /** @var mixed $lastAutoupgradeVersion */
    protected $lastAutoupgradeVersion;
    /**
     * @var bool $manualMode
     *
     * @deprecated 1.1.2
     */
    public $manualMode = false;

    /**
     * ModSelfUpdate constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'tbupdater';
        $this->tab = 'administration';
        $this->version = '1.5.0';
        $this->author = 'thirty bees';
        $this->bootstrap = true;
        $this->need_instance = 1;

        parent::__construct();
        $this->displayName = $this->l('thirty bees Updater');
        $this->description = $this->l('Updating thirty bees was moved to Core Updater. Nevertheless this module should still be installed.');
        $this->tb_versions_compliancy = '>= 1.0.8';
        $this->tb_min_version = '1.0.8';

        if (isset(Context::getContext()->employee->id) && Context::getContext()->employee->id && isset(Context::getContext()->link) && is_object(Context::getContext()->link)) {
            $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query([
                'configure'   => $this->name,
                'tab_module'  => $this->tab,
                'module_name' => $this->name,
            ]);
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
        Configuration::updateGlobalValue(static::CHANNEL, 'stable');

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
        Configuration::deleteByName(static::LAST_CHECK);
        Configuration::deleteByName(static::CHANNEL);

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
     * @version 1.0.0 Initial version.
     * @version 1.5.0 Renamed from getContent() to getContentOff() to disable
     *                the configuration page. Merchants should use Core Updater
     *                now.
     */
    public function getContentOff()
    {
        $this->postProcess();
        $this->tools = UpgraderTools::getInstance();
        $this->upgrader = Upgrader::getInstance();

        $this->context->smarty->assign([
            'baseUrl' => $this->baseUrl,
        ]);

        $this->context->controller->addJS($this->_path.'views/js/upgrader.js');
        $this->context->controller->addCSS($this->_path.'views/css/admin.css', 'all');

        $content = '';

        if (Module::isInstalled('psonefivemigrator')
            || Module::isInstalled('psonesixmigrator')
            || Module::isInstalled('psonesevenmigrator')) {
            $content .= '<div class="row">';
            $content .= $this->display(__FILE__, 'views/templates/admin/migratorwarning.tpl');
            $content .= '</div>';
        } else {
            $content .= $this->getUpdateContent();
        }

        return $content;
    }

    /**
     * @return string
     */
    public function getUpdateContent()
    {
        $configurationKeys = [
            UpgraderTools::KEEP_MAILS            => true,
            UpgraderTools::UPGRADE_DEFAULT_THEME => false,
            UpgraderTools::PERFORMANCE           => 1,
            UpgraderTools::MANUAL_MODE           => false,
            UpgraderTools::DISPLAY_ERRORS        => false,
            UpgraderTools::BACKUP                => true,
            UpgraderTools::BACKUP_IMAGES         => false,
        ];

        $config = UpgraderTools::getConfig();
        foreach ($configurationKeys as $k => $defaultValue) {
            if (!isset($config[$k])) {
                UpgraderTools::setConfig($k, $defaultValue);
            }
        }

        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            $html = '<div class="error">'.$this->l('This functionality has been disabled.').'</div>';
            $this->context->smarty->assign('updaterContent', $html);
            $this->context->smarty->assign('content', $html);

            return $html;
        }

        if ((!file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')
                || md5_file($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php') !== md5_file(__DIR__.'/ajax-upgradetab.php'))
            && !@copy(__DIR__.'/ajax-upgradetab.php', $this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')) {
            $html = '<div class="alert alert-danger">'.sprintf($this->l('[TECHNICAL ERROR] ajax-upgradetab.php could not be copied. Please make sure write permissions have been set on the folder `%s`.'), $this->tools->autoupgradePath.DIRECTORY_SEPARATOR).'</div>';

            return $html;
        }

        $html = '<div class="row">';
        $html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/welcome.phtml');

        $html .= $this->displayCurrentConfiguration();
        $html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/anotherchecklist.phtml');

        $html .= $this->displayBlockUpgradeButton();
        $html .= $this->displayRollbackForm();

        $html .= $this->getJsInit();
        $html .= $this->display(__FILE__, 'views/templates/admin/configure.tpl');
        $html .= '</div>';

        return $html;
    }

    /**
     * Check for module updates
     *
     * @param bool $force Force check
     *
     * @return false|array Indicates whether the update failed or not needed (returns `false`)
     *                     Otherwise returns the list with modules
     *
     * @since 1.0.0
     */
    public function checkForUpdates($force = false)
    {
        $lastCheck = (int) Configuration::get(static::LAST_CHECK);

        if ($force || $lastCheck < (time() - static::CHECK_INTERVAL) || Tools::getValue($this->name.'CheckUpdate') || !@file_exists(__DIR__.'/cache/modules.json')) {
            $guzzle = new GuzzleHttp\Client([
                'base_uri' => static::BASE_URL,
                'verify'   => _PS_TOOL_DIR_.'cacert.pem',
            ]);

            $channel = Configuration::get(static::CHANNEL);
            if (!in_array($channel, ['stable', 'rc', 'beta', 'alpha'])) {
                $channel = 'stable';
            }

            $currentVersion = new Version(_TB_VERSION_);
            $localModules = Tools::strtolower(Configuration::get('PS_LOCALE_COUNTRY'));

            $promises = [
                'updates' => $guzzle->getAsync("channel/{$channel}.json"),
                'modules' => $guzzle->getAsync("modules/all.json"),
                'country' => $guzzle->getAsync("modules/{$localModules}.json"),
            ];

            $results = GuzzleHttp\Promise\settle($promises)->wait();

            $cache = [];

            // Process core versions
            $updates = null;
            if (isset($results['updates']['value'])) {
                $updates = $results['updates']['value'];
            }
            if ($updates instanceof GuzzleHttp\Psr7\Response) {
                $updates = (string) $updates->getBody();
                $updates = json_decode($updates, true);
                if ($updates) {
                    // Find latest core versions
                    $versions = $this->calculateUpdateVersions($currentVersion, array_keys($updates));
                    if ($versions['patch']) {
                        Configuration::updateValue(self::LATEST_CORE_PATCH, $versions['patch']);
                    }
                    if ($versions['minor']) {
                        Configuration::updateValue(self::LATEST_CORE_MINOR, $versions['minor']);
                    }
                    if ($versions['major']) {
                        Configuration::updateValue(self::LATEST_CORE_MAJOR, $versions['major']);
                    }
                }
            }

            // Process global module versions
            $modules = null;
            if (isset($results['modules']['value'])) {
                $modules = $results['modules']['value'];
            }
            if ($modules instanceof GuzzleHttp\Psr7\Response) {
                $modules = (string) $modules->getBody();
                $modules = json_decode($modules, true);
                if ($modules && is_array($modules)) {
                    foreach ($modules as $moduleName => &$module) {
                        if (isset($module['versions'][$channel]) && $highestVersion = static::findHighestVersion(_TB_VERSION_, $module['versions'][$channel])) {
                            $module['version'] = $highestVersion;
                            $module['binary'] = $module['versions'][$channel][$highestVersion]['binary'];
                            unset($module['versions']);

                            $cache[$moduleName] = $module;
                        }
                    }
                }
            }

            // Process local module versions
            $country = null;
            if (isset($results['country']['value'])) {
                $country = $results['country']['value'];
            }
            if ($country instanceof GuzzleHttp\Psr7\Response) {
                $localModules = (string) $country->getBody();
                $localModules = json_decode($localModules, true);
                if ($localModules && is_array($localModules)) {
                    foreach ($localModules as $moduleName => &$module) {
                        if (isset($module['versions'][$channel]) && $highestVersion = static::findHighestVersion(_TB_VERSION_, $module['versions'][$channel])) {
                            $module['version'] = $highestVersion;
                            $module['binary'] = $module['versions'][$channel][$highestVersion]['binary'];
                            unset($module['versions']);

                            $cache[$moduleName] = $module;
                        }
                    }
                }
            }

            if (is_array($cache) && !empty($cache)) {
                @file_put_contents(__DIR__.'/cache/modules.json', json_encode($cache, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));

                return $cache;
            } else {
                $cache = json_encode($cache);

                Logger::addLog("Error: thirty bees updater did not understand this feed: $cache");
            }

            Configuration::updateGlobalValue(static::LAST_CHECK, time());
        }

        return false;
    }

    /**
     * Get the cached info about modules
     *
     * @param string|null $locale IETF Locale
     *                            If the locale does not exist it will
     *                            fall back onto en-us
     *
     * @return array|bool|false|mixed
     *
     * @since 1.0.0
     */
    public function getCachedModulesInfo($locale = null)
    {
        $modules = json_decode(@file_get_contents(__DIR__.'/cache/modules.json'), true);
        if (!$modules) {
            $modules = $this->checkForUpdates(true);
            if (!$modules) {
                return false;
            }
        }

        if ($locale) {
            foreach ($modules as &$module) {
                if (isset($module['displayName'][Tools::strtolower($locale)])) {
                    $module['displayName'] = $module['displayName'][Tools::strtolower($locale)];
                } elseif (isset($module['displayName']['en-us'])) {
                    $module['displayName'] = $module['displayName']['en-us'];
                } else {
                    // Broken feed
                    continue;
                }
                if (isset($module['description'][Tools::strtolower($locale)])) {
                    $module['description'] = $module['description'][Tools::strtolower($locale)];
                } elseif (isset($module['description']['en-us'])) {
                    $module['description'] = $module['description']['en-us'];
                } else {
                    // Broken feed
                    continue;
                }
            }
        }

        return $modules;
    }

    /**
     * Install a module by name
     *
     * @param string $moduleName
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function installModule($moduleName)
    {
        $moduleInfo = $this->getModuleInfo($moduleName);
        if (!$moduleInfo || !isset($moduleInfo['binary'])) {
            return false;
        }

        if (!$this->downloadModuleFromLocation($moduleName, $moduleInfo['binary'])) {
            return false;
        }

        if (!class_exists($moduleName)) {
            require _PS_MODULE_DIR_.$moduleName.DIRECTORY_SEPARATOR.$moduleName.'.php';
        }

        $module = Module::getInstanceByName($moduleName);

        if ($module->install()) {
            Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminModules', true).'&configure='.$moduleName);
        }

        return false;
    }

    /**
     * Update a module by name
     *
     * @param string $moduleName
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function updateModule($moduleName)
    {
        $moduleInfo = $this->getModuleInfo($moduleName);
        if (!$moduleInfo || !isset($moduleInfo['binary'])) {
            return false;
        }

        if (!$this->downloadModuleFromLocation($moduleName, $moduleInfo['binary'])) {
            return false;
        }

        return true;
    }

    /**
     * Get module info
     *
     * @param string $moduleName
     *
     * @return bool|mixed
     *
     * @since 1.0.0
     */
    public function getModuleInfo($moduleName)
    {
        $cache = $this->getCachedModulesInfo();
        if (!is_array($cache) || !in_array($moduleName, array_keys($cache))) {
            return false;
        }

        return $cache[$moduleName];
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function postProcess()
    {
        if (Tools::isSubmit('checkForUpdates')) {
            if ((bool) $this->checkForUpdates(true)) {
                $this->context->controller->confirmations[] = $this->l('Module information has been updated');
            } else {
                $this->context->controller->errors[] = $this->l('Unable to update module info');
            }
        }

        $this->setFields();

        // set default configuration to default channel & default configuration for backup and upgrade
        // (can be modified in expert mode)
        $config = UpgraderTools::getConfig('channel');
        if ($config === false) {
            $config = [];
            $config['channel'] = Upgrader::DEFAULT_CHANNEL;
            UpgraderTools::writeConfig($config);
            if (class_exists('Configuration', false)) {
                Configuration::updateValue('PS_UPGRADE_CHANNEL', $config['channel']);
            }

            UpgraderTools::writeConfig(
                [
                    UpgraderTools::PERFORMANCE           => 1,
                    UpgraderTools::UPGRADE_DEFAULT_THEME => false,
                    UpgraderTools::KEEP_MAILS            => true,
                    UpgraderTools::BACKUP                => true,
                    UpgraderTools::BACKUP_IMAGES         => false,
                ]
            );
        }

        if (Tools::isSubmit('putUnderMaintenance') && version_compare(_TB_VERSION_, '1.5.0.0', '>=')) {
            foreach (Shop::getCompleteListOfShopsID() as $idShop) {
                Configuration::updateValue('PS_SHOP_ENABLE', 0, false, null, (int) $idShop);
            }
            Configuration::updateGlobalValue('PS_SHOP_ENABLE', 0);
        } elseif (Tools::isSubmit('putUnderMaintenance')) {
            Configuration::updateValue('PS_SHOP_ENABLE', 0);
        }

        if (Tools::isSubmit('channel')) {
            $channel = Tools::getValue('channel');
            if (in_array($channel, ['stable', 'rc', 'beta', 'alpha'])) {
                UpgraderTools::writeConfig(['channel' => Tools::getValue('channel')]);
            }
        }

        if (Tools::isSubmit('customSubmitAutoUpgrade')) {
            $configKeys = array_keys(array_merge($this->upgradeOptions, $this->backupOptions));
            $config = [];
            foreach ($configKeys as $key) {
                if (isset($_POST[$key])) {
                    $config[$key] = $_POST[$key];
                }
            }
            $res = UpgraderTools::writeConfig($config);
            if ($res) {
                Tools::redirectAdmin($this->baseUrl);
            }
        }

        if (Tools::isSubmit('deletebackup')) {
            $res = false;
            $name = Tools::getValue('name');
            $tools = UpgraderTools::getInstance();
            $fileList = scandir($tools->backupPath);
            foreach ($fileList as $filename) {
                // the following will match file or dir related to the selected backup
                if (!empty($filename) && $filename[0] != '.' && $filename != 'index.php' && $filename != '.htaccess'
                    && preg_match('#^(auto-backupfiles_|)'.preg_quote($name).'(\.zip|)$#', $filename, $matches)
                ) {
                    if (is_file($tools->backupPath.DIRECTORY_SEPARATOR.$filename)) {
                        $res &= unlink($tools->backupPath.DIRECTORY_SEPARATOR.$filename);
                    } elseif (!empty($name) && is_dir($tools->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR)) {
                        $res = Tools::deleteDirectory($tools->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR, true);
                    }
                }
            }
            if ($res) {
                Tools::redirectAdmin($this->baseUrl);
            } else {
                $this->context->controller->errors[] = sprintf($this->l('Error when trying to delete backup %s'), $name);
            }
        }
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    public function getCheckCurrentPsConfig()
    {
        static $allowedArray;

        if (empty($allowedArray)) {
            $tools = UpgraderTools::getInstance();
            $adminDir = trim(str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_), DIRECTORY_SEPARATOR);
            $maxExecutionTime = ini_get('max_execution_time');

            $allowedArray = [];
            $allowedArray['root_writable'] = $this->getRootWritable();
            $allowedArray['admin_au_writable'] = ConfigurationTest::testDir($adminDir.DIRECTORY_SEPARATOR.$tools->autoupgradeDir, false, $report, false);
            $allowedArray['shop_deactivated'] = (!Configuration::get('PS_SHOP_ENABLE') || (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', 'localhost'])));
            $allowedArray['cache_deactivated'] = ! Configuration::get('TB_CACHE_ENABLED');
            $allowedArray['module_version_ok'] = true;
            $allowedArray['max_execution_time'] = !$maxExecutionTime || $maxExecutionTime >= 30;
        }

        return $allowedArray;
    }

    /**
     * @return bool|null
     *
     * @since 1.0.0
     */
    public function getRootWritable()
    {
        // Root directory permissions cannot be checked recursively anymore, it takes too much time
        $tools = UpgraderTools::getInstance();
        $tools->rootWritable = ConfigurationTest::testDir('/', false, $report);
        $tools->rootWritableReport = $report;

        return $tools->rootWritable;
    }

    /**
     * @return bool|mixed|string
     *
     * @since 1.0.0
     */
    public function checkAutoupgradeLastVersion()
    {
        $this->lastAutoupgradeVersion = true;

        return true;
    }

    /**
     * @return bool|null|string
     *
     * @since 1.0.0
     */
    public function getModuleVersion()
    {
        return false;
    }

    /**
     * @return float|int
     *
     * @since 1.0.0
     */
    public function configOk()
    {
        $allowedArray = $this->getCheckCurrentPsConfig();
        $allowed = array_product($allowedArray);

        return $allowed;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function displayDevTools()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/devtools.phtml');
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function displayBlockActivityLog()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/activitylog.phtml');
    }

    /**
     * Display a phtml template file
     *
     * @param string $file
     * @param array  $params
     *
     * @return string Content
     *
     * @since 1.0.0
     */
    public function displayAdminTemplate($file, $params = [])
    {
        foreach ($params as $name => $param) {
            $$name = $param;
        }

        ob_start();

        include($file);

        $content = ob_get_contents();
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }

        return $content;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessSetConfig()
    {
        if (!Tools::isSubmit('configKey') || !Tools::isSubmit('configValue') || !Tools::isSubmit('configType')) {
            die(json_encode([
                'success' => false,
            ]));
        }

        $configKey = Tools::getValue('configKey');
        $configType = Tools::getValue('configType');
        $configValue = Tools::getValue('configValue');
        if ($configType === 'bool') {
            if ($configValue === 'false' || !$configValue) {
                $configValue = false;
            } else {
                $configValue = true;
            }
        } elseif ($configType === 'select') {
            $configValue = (int) $configValue;
        }

        UpgraderTools::setConfig($configKey, $configValue);

        die(json_encode([
            'success' => true,
        ]));
    }

    /** this returns the fieldset containing the configuration points you need to use autoupgrade
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayCurrentConfiguration()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/checklist.phtml');
    }

    /**
     * @param $name
     * @param $fields
     * @param $tabname
     * @param $size
     * @param $icon
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayConfigForm($name, $fields, $tabname, $size, $icon)
    {
        $params = [
            'name'    => $name,
            'fields'  => $fields,
            'tabname' => $tabname,
            'size'    => $size,
            'icon'    => $icon,
        ];

        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/displayform.phtml', $params);
    }

    /**
     * Display rollback form
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayRollbackForm()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/rollbackform.phtml');
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    protected function getBackupDbAvailable()
    {
        $array = [];
        $files = scandir($this->tools->backupPath);
        foreach ($files as $file) {
            if ($file[0] === 'v' && is_dir($this->tools->backupPath.DIRECTORY_SEPARATOR.$file)) {
                $array[] = $file;
            }
        }

        return $array;

    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    protected function getBackupFilesAvailable()
    {
        $tools = UpgraderTools::getInstance();
        $array = [];
        $files = scandir($tools->backupPath);
        foreach ($files as $file) {
            if ($file[0] != '.') {
                if (substr($file, 0, 16) == 'auto-backupfiles') {
                    $array[] = preg_replace('#^auto-backupfiles_(.*-[0-9a-f]{1,8})\..*$#', '$1', $file);
                }
            }
        }

        return $array;
    }

    /**
     * function to set configuration fields display
     *
     * @return void
     */
    protected function setFields()
    {
        $this->backupOptions[UpgraderTools::BACKUP] = [
            'title'        => $this->l('Back up my files and database'),
            'cast'         => 'intval',
            'validation'   => 'isBool',
            'defaultValue' => '1',
            'type'         => 'bool',
            'desc'         => $this->l('Automatically back up your database and files in order to restore your shop if needed. This is experimental: you should still perform your own manual backup for safety.'),
        ];

        $this->backupOptions[UpgraderTools::BACKUP_IMAGES] = [
            'title'        => $this->l('Back up my images'),
            'cast'         => 'intval',
            'validation'   => 'isBool',
            'defaultValue' => '1',
            'type'         => 'bool',
            'desc'         => $this->l('To save time, you can decide not to back your images up. In any case, always make sure you did back them up manually.'),
        ];

        $this->upgradeOptions[UpgraderTools::PERFORMANCE] = [
            'title'        => $this->l('Server performance'),
            'cast'         => 'intval',
            'validation'   => 'isInt',
            'defaultValue' => '1',
            'type'         => 'select',
            'desc'         => $this->l('Unless you are using a dedicated server, select "Low".').'<br />'.$this->l('A high value can cause the upgrade to fail if your server is not powerful enough to process the upgrade tasks in a short amount of time.'),
            'choices'      => [
                1 => $this->l('Low (recommended)'),
                2 => $this->l('Medium'),
                3 => $this->l('High'),
            ],
        ];

        $this->upgradeOptions[UpgraderTools::UPGRADE_DEFAULT_THEME] = [
            'title'      => $this->l('Upgrade the default thirty bees theme'),
            'cast'       => 'intval',
            'validation' => 'isBool',
            'type'       => 'bool',
            'desc'       => $this->l('This will upgrade the default thirty bees theme'),
        ];

        if (UpgraderTools::getConfig(UpgraderTools::DISPLAY_ERRORS)) {
            UpgraderTools::writeConfig([UpgraderTools::DISPLAY_ERRORS => false]);
        }
    }

    /**
     * Get js init stuff
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function getJsInit()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/mainjs.phtml');
    }

    /**
     * Generate ajax token
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function generateAjaxToken()
    {
        $blowfish = new TbUpdaterModule\Blowfish(_COOKIE_KEY_, _COOKIE_IV_);

        return $blowfish->encrypt('thirtybees1337H4ck0rzz');
    }

    /**
     * _displayBlockUpgradeButton
     * display the summary current version / target version + "Upgrade Now" button with a "more options" button
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayBlockUpgradeButton()
    {
        $this->context->smarty->assign([
            'currentVersion'  => _TB_VERSION_,
            'configOk'        => $this->configOk(),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/blockupgradebutton.tpl');
    }

    /**
     * Install module from location
     *
     * @param string $moduleName
     * @param string $location
     *
     * @return bool
     * @since 1.0.0
     */
    protected function downloadModuleFromLocation($moduleName, $location)
    {
        $zipLocation = _PS_MODULE_DIR_.$moduleName.'.zip';
        if (@!file_exists($zipLocation)) {
            $guzzle = new \GuzzleHttp\Client([
                'timeout' => 30,
                'verify'  => _PS_TOOL_DIR_.'cacert.pem',
            ]);
            try {
                $guzzle->get($location, ['sink' => $zipLocation]);
            } catch (Exception $e) {
                return false;
            }
        }
        if (@file_exists($zipLocation)) {
            return $this->extractModuleArchive($moduleName, $zipLocation, false);
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
            if (!isset($versionInfo['compatibility']) || !$versionInfo['compatibility']) {
                continue;
            }

            try {
                $range = new Expression($versionInfo['compatibility']);
                if ($tbVersion->satisfies($range)) {
                    $versions[] = $versionNumber;
                }
            } catch (Exception $e) {
                Logger::addLog("thirty bees updater: {$e->getMessage()}");
            }
        }

        usort($versions, ['self', 'compareVersionReverse']);
        if (!empty($versions)) {
            return $versions[0];
        }

        return false;
    }

    /**
     * Calculate update versions
     *
     * @param Version $currentVersion
     * @param array   $availableVersions
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function calculateUpdateVersions(Version $currentVersion, $availableVersions)
    {
        $patchRange = new Expression('~'.$currentVersion->getVersion());
        $minorRange = new Expression('~'.$currentVersion->getMajor().'.'.$currentVersion->getMinor());
        $majorRange = new Expression('*');

        return [
            'patch' => $patchRange->maxSatisfying($availableVersions)->getVersion(),
            'minor' => $minorRange->maxSatisfying($availableVersions)->getVersion(),
            'major' => $majorRange->maxSatisfying($availableVersions)->getVersion(),
        ];
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
     * Extracts a module archive to the `modules` folder
     *
     * @param string $moduleName Module name
     * @param string $file     File source location
     * @param bool   $redirect Whether there should be a redirection to the BO module page after extracting
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function extractModuleArchive($moduleName, $file, $redirect = true)
    {
        $zipFolders = [];
        $tmpFolder = _PS_MODULE_DIR_.$moduleName.md5(time());

        if (@!file_exists($file)) {
            $this->addError($this->l('Module archive could not be downloaded'));

            return false;
        }

        $success = false;
        if (substr($file, -4) == '.zip') {
            if (Tools::ZipExtract($file, $tmpFolder) && file_exists($tmpFolder.DIRECTORY_SEPARATOR.$moduleName)) {
                if (file_exists(_PS_MODULE_DIR_.$moduleName)) {
                    if (!ConfigurationTest::testDir(_PS_MODULE_DIR_.$moduleName, true, $report, true)) {
                        $this->addError(sprintf($this->l('Could not update module `%s`: module directory not writable (`%s`).'), $moduleName, $report));
                        $this->recursiveDeleteOnDisk($tmpFolder);
                        @unlink(_PS_MODULE_DIR_.$moduleName.'.zip');

                        return false;
                    }
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$moduleName);
                }
                if (@rename($tmpFolder.DIRECTORY_SEPARATOR.$moduleName, _PS_MODULE_DIR_.$moduleName)) {
                    $success = true;
                }
            }
        }

        if (!$success) {
            $this->addError($this->l('There was an error while extracting the module file (file may be corrupted).'));
            // Force a new check
            Configuration::updateGlobalValue(static::LAST_CHECK, 0);
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
        @unlink(_PS_MODULE_DIR_.$moduleName.'backup');
        $this->recursiveDeleteOnDisk($tmpFolder);

        if ($success) {
            Configuration::updateGlobalValue(static::LAST_UPDATE, (int) time());
            if ($redirect) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&doNotAutoUpdate=1');
            }
        }

        return $success;
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
}
