<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace TbUpdaterModule;

use TbUpdaterModule\SemVer\Version;

/**
 * Class AjaxProcessor
 *
 * @package TbUpdaterModule
 *
 * @since   1.0.0
 */
class AjaxProcessor
{
    protected static $instance;
    /** @var bool $stepDone */
    public $stepDone = true;
    /** @var bool $status */
    public $status = true;
    public $backupName;
    public $backupFilesFilename;
    /** @var array $backupIgnoreFiles */
    public $backupIgnoreFiles = [];
    public $backupDbFilename;
    public $restoreName;
    public $installVersion;
    public $restoreFilesFilename;
    /** @var array $restoreDbFilenames */
    public $restoreDbFilenames = [];
    /** @var array $installedLanguagesIso */
    public $installedLanguagesIso = [];
    public $modulesAddons;
    public $warningExists;
    /** @var string $error */
    public $error = '0';
    /** @var string $next */
    public $next = '';
    /** @var string $nextDesc */
    public $nextDesc = '';
    /** @var array $nextParams */
    public $nextParams = [];
    /** @var array $nextQuickInfo */
    public $nextQuickInfo = [];
    /** @var array $nextErrors */
    public $nextErrors = [];
    /** @var array|null $currentParams */
    public $currentParams = [];
    public $latestRootDir;
    /** @var UpgraderTools $tools */
    public $tools;
    /** @var Upgrader $upgrader */
    public $upgrader;
    public $nextResponseType;
    /**
     * Theses values will be automatically added in "nextParams"
     * if their properties exists, but will also be set as properties
     * of this object in case they are found in the ajax request
     *
     * @var array
     */
    public $ajaxParams = [
        'installVersion',
        'backupName',
        'backupFilesFilename',
        'backupDbFilename',
        'restoreName',
        'restoreFilesFilename',
        'restoreDbFilenames',
        'installedLanguagesIso',
        'modulesAddons',
        'warningExists',
        'backupIgnoreFiles',
        'backupIgnoreAbsoluteFiles',
        'restoreIgnoreFiles',
        'restoreIgnoreAbsoluteFiles',
        'excludeFilesFromUpgrade',
        'excludeAbsoluteFilesFromUpgrade',
    ];
    /** @var array $restoreIgnoreAbsoluteFiles */
    protected $restoreIgnoreAbsoluteFiles = [];
    /** @var array $excludeFilesFromUpgrade */
    protected $excludeFilesFromUpgrade = [];
    /** @var array $backupIgnoreAbsoluteFiles */
    protected $backupIgnoreAbsoluteFiles = [];
    /** @var array $excludeAbsoluteFilesFromUpgrade */
    protected $excludeAbsoluteFilesFromUpgrade = [];
    protected $keepImages;
    protected $restoreIgnoreFiles;
    protected $deactivateCustomModule;
    protected $deactivateOverrides;

    /**
     * @return AjaxProcessor
     *
     * @since 1.0.0
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function optionDisplayErrors()
    {
        if (UpgraderTools::getConfig(UpgraderTools::DISPLAY_ERRORS)) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'on');
        } else {
            ini_set('display_errors', 'off');
        }
    }

    /**
     * AjaxProcessor constructor.
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        // Database instantiation (need to be cached because there will be at least 100k calls in the upgrade process
        $this->db = Db::getInstance();

        $request = json_decode(file_get_contents('php://input'), true);

        $this->action = (isset($request['action']) ? $request['action'] : null);
        $this->currentParams = (isset($request['params']) ? $request['params'] : null);

        if (isset($this->currentParams['channel'])) {
            UpgraderTools::setConfig('channel', $this->currentParams['channel']);
        }
        $this->tools = UpgraderTools::getInstance();
        $this->upgrader = Upgrader::getInstance();
        $this->installVersion = $this->upgrader->version;
        $this->latestRootDir = $this->tools->latestPath.DIRECTORY_SEPARATOR.'prestashop';

        foreach ($this->ajaxParams as $prop) {
            if (property_exists($this, $prop)) {
                $this->{$prop} = isset($this->currentParams[$prop]) ? $this->currentParams[$prop] : '';
            }
        }

        // Initialize things here at the first step
        if ($this->action === 'upgradeNow') {
            if (!$this->resetTables()) {
                $this->next = 'error';
                $this->nextErrors[] = $this->l('Could not prepare the DB tables for the update.');
                $this->error = 1;
                $this->status = 'error';

                $this->displayAjax();

                die();
            } else {
                $this->initializeFiles();
            }
        }
    }

    /**
     * @param mixed  $string
     * @param string $class
     * @param bool   $addslashes
     * @param bool   $htmlentities
     *
     * @return mixed|string
     *
     * @since 1.0.0
     */
    protected function l($string, $class = 'AdminThirtyBeesMigrateController', $addslashes = false, $htmlentities = true)
    {
        // need to be called in order to populate $classInModule
        $str = UpgraderTools::findTranslation('tbupdater', $string, $class);
        $str = $htmlentities ? str_replace('"', '&quot;', htmlentities($str, HTML_ENTITIES, 'UTF-8')) : $str;
        $str = $addslashes ? addslashes($str) : stripslashes($str);

        return $str;
    }

    /**
     * The very first step of the upgrade process.
     * The only thing done here is the selection of the next step.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeNow()
    {
        $this->nextQuickInfo[] = sprintf($this->l('Archives will come from %s and %s'), $this->upgrader->coreLink, $this->upgrader->extraLink);
        $this->nextQuickInfo[] = sprintf($this->l('md5 hashes for core and extra should be resp. %s and %s'), $this->upgrader->md5Core, $this->upgrader->md5Extra);

        if (isset($this->currentParams['newsletter']) && $this->currentParams['newsletter'] && $this->currentParams['employee']) {
            $employee = new Employee((int) $this->currentParams['employee']);
            if (Validate::isLoadedObject($employee)) {
                $guzzle = new \TbUpdaterModule\GuzzleHttp\Client([
                    'base_uri'    => 'https://api.thirtybees.com',
                    'timeout'     => 5,
                    'http_errors' => false,
                ]);
                $language = new Language($employee->id_lang);
                try {
                    $guzzle->post(
                        '/migration/newsletter/',
                        [
                            'json' =>
                                [
                                    'email'    => $employee->email,
                                    'fname'    => $employee->firstname,
                                    'lname'    => $employee->lastname,
                                    'activity' => Configuration::get('PS_SHOP_ACTIVITY'),
                                    'country'  => Configuration::get('PS_LOCALE_COUNTRY'),
                                    'language' => $language->iso_code,
                                ],
                        ]
                    );
                } catch (\Exception $e) {
                    // Don't care
                }
            }
        }

        $this->next = 'testDirs';
        $this->nextDesc = $this->l('Testing directories...');
    }

    /**
     * Test directories
     *
     * @return bool
     */
    public function ajaxProcessTestDirs()
    {
        $testDirs = [
            '/classes/',
            '/controllers/',
            '/vendor/',
            '/config/',
            '/js/',
            '/localization/',
            '/css/',
            '/mails/',
            '/webservice/',
        ];

        foreach ($testDirs as $dir) {
            $report = '';
            if (!ConfigurationTest::testDir($dir, true, $report)) {
                $this->next = 'error';
                $this->nextDesc = $this->l('Directory tests failed.');
                $this->nextErrors[] = $report;

                return false;
            }
        }

        $this->next = 'download';
        $this->nextDesc = $this->l('Downloading... (this can take a while)');

        return true;
    }

    /**
     * download PrestaShop archive according to the chosen channel
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessDownload()
    {
        if (!is_object($this->upgrader)) {
            $this->upgrader = Upgrader::getInstance();
        }

        $this->next = 'error';
        $this->nextDesc = $this->l('Download error.');

        $downloadPath = realpath($this->tools->downloadPath);
        $report = '';
        if (!ConfigurationTest::testDir($downloadPath, false, $report, true)) {
            $this->nextDesc = $this->nextQuickInfo[] = $this->l('Download directory is not writable.');
            $this->nextErrors[] = $report;

            return;
        }

        $result = $this->upgrader->downloadLast($this->tools->downloadPath);
        if ($result) {
            $md5Core = md5_file($this->tools->downloadPath.DIRECTORY_SEPARATOR
                                .'thirtybees-v'.$this->upgrader->version.'.zip');
            $md5Extra = md5_file($this->tools->downloadPath.DIRECTORY_SEPARATOR
                                 .'thirtybees-extra-v'.$this->upgrader->version.'.zip');
            if ($md5Core === $this->upgrader->md5Core
                && $md5Extra === $this->upgrader->md5Extra) {
                $this->next = 'unzip';
                $this->nextDesc = $this->l('Download complete. Now extracting...');
            } else {
                if ($md5Core !== $this->upgrader->md5Core) {
                    $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('MD5 sum of the core package (%s) does not match.'), $md5Core);
                }
                if ($md5Extra !== $this->upgrader->md5Extra) {
                    $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('MD5 sum of the extra package (%s) does not match.'), $md5Extra);
                }
            }
        } else {
            $this->nextQuickInfo[] = $this->nextDesc;
            $this->nextErrors[] = $this->l('Unknown error during download.');
        }
    }

    /**
     * Extract chosen version into $this->latestPath directory
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUnzip()
    {
        $coreFilePath = $this->getCoreFilePath();
        $coreFileDest = $this->tools->latestPath;
        $extraFilePath = $this->getExtraFilePath();
        $extraFileDest = $this->tools->latestPath.'/upgrade';

        if (file_exists($coreFileDest)) {
            Tools::deleteDirectory($coreFileDest, false);
            $this->nextQuickInfo[] = $this->l('"/latest" directory has been emptied');
        }
        $relativeExtractPath = str_replace(_PS_ROOT_DIR_, '', $coreFileDest);
        $report = '';
        if (ConfigurationTest::testDir($relativeExtractPath, false, $report)) {
            if ($this->extractZip($coreFilePath, $coreFileDest) && $this->extractZip($extraFilePath, $extraFileDest)) {
                // Unsetting to force listing
                unset($this->nextParams['removeList']);
                if (UpgraderTools::getConfig('skip_backup')) {
                    $this->next = 'upgradeFiles';
                    $this->nextDesc = $this->l('File extraction complete. Backup process skipped. Now upgrading files.');
                } else {
                    $this->next = 'backupFiles';
                    $this->nextDesc = $this->l('File extraction complete. Now backing up files.');
                }

                return true;
            } else {
                $this->next = 'error';
                $this->nextDesc = sprintf($this->l('Unable to extract %1$s and/or %2$s into %3$s folder...'), $coreFilePath, $extraFilePath, $coreFileDest);

                return true;
            }
        } else {
            $this->next = 'error';
            $this->nextDesc = $this->nextQuickInfo[] = $this->l('Extraction directory is not writable.');
            $this->nextErrors[] = $report;
        }

        return false;
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessBackupFiles()
    {
        if (!UpgraderTools::getConfig(UpgraderTools::BACKUP)) {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->nextDesc = 'File backup skipped.';

            return true;
        }

        $this->nextParams = $this->currentParams;
        $this->stepDone = false;
        if (empty($this->backupFilesFilename)) {
            $this->next = 'error';
            $this->error = 1;
            $this->nextDesc = $this->l('Error during backupFiles');
            $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('backupFiles filename has not been set');

            return false;
        }
        $filesToBackup = Backup::getBackupFiles(UpgraderTools::$loopBackupFiles);

        if (empty($filesToBackup)) {
            $filesToBackup = $this->listFilesInDir(_PS_ROOT_DIR_, 'backup', false);
            if (count($filesToBackup)) {
                $this->nextQuickInfo[] = sprintf($this->l('%s Files to backup.'), count($filesToBackup));
                Backup::addFiles($filesToBackup);
            }

            // delete old backup, create new
            if (!empty($this->backupFilesFilename) && file_exists($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                unlink($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
            }

            $this->nextQuickInfo[] = sprintf($this->l('Backup files initialized in %s.'), $this->backupFilesFilename);

            $filesToBackup = Backup::getBackupFiles(UpgraderTools::$loopBackupFiles);
        }

        $this->next = 'backupFiles';
        if (count($filesToBackup)) {
            $this->nextQuickInfo[] = sprintf($this->l('Backup files in progress. %d files left.'), (int) Backup::count());
        }
        if (is_array($filesToBackup)) {
            $zipArchive = true;
            $zip = new \ZipArchive();
            $res = $zip->open($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename, \ZipArchive::CREATE);
            if ($res) {
                $res = (isset($zip->filename) && $zip->filename) ? true : false;
            }

            if ($zip && $res) {
                $this->next = 'backupFiles';
                $this->stepDone = false;
                $filesToAdd = [];
                $closeFlag = true;
                foreach (array_column($filesToBackup, 'file') as $file) {
                    // filesForBackup already contains all the correct files
                    $archiveFilename = ltrim(str_replace(_PS_ROOT_DIR_, '', $file), DIRECTORY_SEPARATOR);
                    $size = filesize($file);
                    if ($size < UpgraderTools::$maxBackupFileSize) {
                        if (isset($zipArchive) && $zipArchive) {
                            $addedToZip = $zip->addFile($file, $archiveFilename);
                            if (!$addedToZip) {
                                // if an error occur, it's more safe to delete the corrupted backup
                                $zip->close();
                                if (file_exists($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                                    unlink($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                                }
                                $this->next = 'error';
                                $this->error = 1;
                                $this->nextDesc = sprintf($this->l('Error when trying to add %1$s to archive %2$s.', 'AdminThirtyBeesMigrate', true), $file, $archiveFilename);
                                $closeFlag = false;
                                break;
                            }
                        } else {
                            $filesToAdd[] = $file;
                            $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %2$s) added to archive.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                        }
                    } else {
                        $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %2$s) has been skipped during backup.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                    }
                }

                if (is_array($filesToBackup) && !empty($filesToBackup)) {
                    Backup::removeFiles(array_column($filesToBackup, Backup::PRIMARY));
                }

                if (Backup::count() <= 0) {
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'backupDb';
                    $this->nextDesc = $this->l('All files backed up. Now backing up database');
                    $this->nextQuickInfo[] = $this->l('All files have been added to the archive.', 'AdminThirtyBeesMigrate', true);
                }

                if ($zipArchive && $closeFlag && is_object($zip)) {
                    $zip->close();
                }

                if (is_array($filesToBackup) && !empty($filesToBackup)) {
                    Backup::removeFiles(array_column($filesToBackup, Backup::PRIMARY));
                }

                return true;
            } else {
                $this->next = 'error';
                $this->nextDesc = $this->l('unable to open archive');

                return false;
            }
        } else {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->nextDesc = $this->l('All files saved. Now backing up database.');

            if (is_array($filesToBackup) && !empty($filesToBackup)) {
                Backup::removeFiles(array_column($filesToBackup, Backup::PRIMARY));
            }

            return true;
        }
    }

    /**
     * Ajax process backup DB
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessBackupDb()
    {
        if (!UpgraderTools::getConfig(UpgraderTools::BACKUP)) {
            $this->stepDone = true;
            $this->nextParams['dbStep'] = 0;
            $this->nextDesc = sprintf($this->l('Database backup skipped. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }

        $relativeBackupPath = str_replace(_PS_ROOT_DIR_, '', $this->tools->backupPath);
        $report = '';
        if (!ConfigurationTest::testDir($relativeBackupPath, false, $report)) {
            $this->next = 'error';
            $this->nextDesc = $this->nextQuickInfo[] = 'Backup directory is not writable.';
            $this->nextErrors[] = $report;
            $this->error = 1;

            return false;
        }

        $this->stepDone = false;
        $this->next = 'backupDb';
        $this->nextParams = $this->currentParams;
        $startTime = time();

        $psBackupAll = true;
        $psBackupDropTable = true;
        if (!$psBackupAll) {
            $ignoreStatsTable = [
                _DB_PREFIX_.'connections',
                _DB_PREFIX_.'connections_page',
                _DB_PREFIX_.'connections_source',
                _DB_PREFIX_.'guest',
                _DB_PREFIX_.'statssearch',
            ];
        } else {
            $ignoreStatsTable = [];
        }

        // INIT LOOP
        if (!isset($this->nextParams['tablesToBackup']) || empty($this->nextParams['tablesToBackup'])) {
            $this->nextParams['dbStep'] = 0;
            $tablesToBackup = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
            $this->nextParams['tablesToBackup'] = $tablesToBackup;
        }

        if (!isset($tablesToBackup)) {
            $tablesToBackup = $this->nextParams['tablesToBackup'];
        }
        $found = 0;
        $views = '';

        // MAIN BACKUP LOOP //
        $written = 0;
        do {
            if (!empty($this->nextParams['backupTable'])) {
                // only insert (schema already done)
                $table = $this->nextParams['backupTable'];
                $lines = $this->nextParams['backupLines'];
            } else {
                if (count($tablesToBackup) == 0) {
                    break;
                }
                $table = current(array_shift($tablesToBackup));
                $this->nextParams['backupLoopLimit'] = 0;
            }

            if ($written == 0 || $written > UpgraderTools::$maxWrittenAllowed) {
                // increment dbStep will increment filename each time here
                $this->nextParams['dbStep']++;
                // new file, new step
                $written = 0;
                if (isset($fp)) {
                    fclose($fp);
                }
                $backupFile = $this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupName.DIRECTORY_SEPARATOR.$this->backupDbFilename;
                $backupFile = preg_replace("#_XXXXXX_#", '_'.str_pad($this->nextParams['dbStep'], 6, '0', STR_PAD_LEFT).'_', $backupFile);
                if (!file_exists($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupName)) {
                    mkdir($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupName, 0777, true);
                }

                // start init file
                // Figure out what compression is available and open the file
                if (file_exists($backupFile)) {
                    $this->next = 'error';
                    $this->error = 1;
                    $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Backup file %s already exists. Operation aborted.'), $backupFile);
                }

                if (function_exists('bzopen')) {
                    $backupFile .= '.bz2';
                    $fp = bzopen($backupFile, 'w');
                } elseif (function_exists('gzopen')) {
                    $backupFile .= '.gz';
                    $fp = gzopen($backupFile, 'w');
                } else {
                    $fp = fopen($backupFile, 'w');
                }

                if ($fp === false) {
                    $this->next = 'error';
                    $this->nextDesc = $this->l('Error during database backup.');
                    $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Unable to create backup database file %s.'), addslashes($backupFile));
                    $this->error = 1;

                    return false;
                }

                $written += fwrite($fp, '/* Backup '.$this->nextParams['dbStep'].' for '.Tools::getHttpHost(false, false).realpath(dirname($_SERVER['SCRIPT_NAME']).'/../../')."\n *  at ".date('r')."\n */\n");
                $written += fwrite($fp, "\n".'SET NAMES \'utf8mb4\';'."\n\n");
                // end init file
            }

            // Skip tables which do not start with _DB_PREFIX_
            if (strlen($table) <= strlen(_DB_PREFIX_) || strncmp($table, _DB_PREFIX_, strlen(_DB_PREFIX_)) != 0) {
                continue;
            }

            // Start schema: drop & create table only
            if (empty($this->currentParams['backupTable']) && isset($fp)) {
                // Export the table schema
                $schema = $this->db->executeS('SHOW CREATE TABLE `'.$table.'`', true, false);

                if (count($schema) != 1 ||
                    !((isset($schema[0]['Table']) && isset($schema[0]['Create Table']))
                        || (isset($schema[0]['View']) && isset($schema[0]['Create View'])))
                ) {
                    fclose($fp);
                    if (isset($backupFile) && file_exists($backupFile)) {
                        unlink($backupFile);
                    }
                    $this->next = 'error';
                    $this->nextDesc = $this->l('Error during database backup.');
                    $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('An error occurred while backing up. Unable to obtain the schema of %s'), $table);
                    $this->error = 1;

                    return false;
                }

                if (isset($schema[0]['View'])) {
                    $views .= '/* Scheme for view'.$schema[0]['View']." */\n";
                    if ($psBackupDropTable) {
                        // If some *upgrade* transform a table in a view, drop both just in case
                        $views .= 'DROP VIEW IF EXISTS `'.$schema[0]['View'].'`;'."\n";
                        $views .= 'DROP TABLE IF EXISTS `'.$schema[0]['View'].'`;'."\n";
                    }
                    $views .= preg_replace('#DEFINER=[^\s]+\s#', 'DEFINER=CURRENT_USER ', $schema[0]['Create View']).";\n\n";
                    $written += fwrite($fp, "\n".$views);
                    $ignoreStatsTable[] = $schema[0]['View'];
                } elseif (isset($schema[0]['Table'])) {
                    // Case common table
                    $written += fwrite($fp, '/* Scheme for table '.$schema[0]['Table']." */\n");
                    if ($psBackupDropTable && !in_array($schema[0]['Table'], $ignoreStatsTable)) {
                        // If some *upgrade* transforms a table into a view, drop both just in case
                        $written += fwrite($fp, "DROP VIEW IF EXISTS `{$schema[0]['Table']}`;\n");
                        $written += fwrite($fp, "DROP TABLE IF EXISTS `{$schema[0]['Table']}`;\n");
                        // CREATE TABLE
                        $written += fwrite($fp, $schema[0]['Create Table'].";\n\n");
                    }
                    // schema created, now we need to create the missing vars
                    $this->nextParams['backupTable'] = $table;
                    $lines = $this->nextParams['backupLines'] = explode("\n", $schema[0]['Create Table']);
                }
            }
            // End of schema

            // POPULATE TABLE
            if (!in_array($table, $ignoreStatsTable) && isset($fp)) {
                do {
                    $backupLoopLimit = $this->nextParams['backupLoopLimit'];
                    $data = $this->db->executeS('SELECT * FROM '.$table.' LIMIT '.(int)$backupLoopLimit.',200');
                    $this->nextParams['backupLoopLimit'] += 200;
                    $sizeof = $this->db->numRows();
                    if ($data && ($sizeof > 0)) {
                        // Export the table data
                        $written += fwrite($fp, "INSERT INTO `$table` VALUES\n");
                        $i = 1;
                        foreach ($data as $row) {
                            // this starts a row
                            $s = '(';
                            foreach ($row as $field => $value) {
                                $tmp = "'".$this->db->escape($value, true)."',";
                                if ($tmp != "'',") {
                                    $s .= $tmp;
                                } elseif (isset($lines)) {
                                    foreach ($lines as $line) {
                                        if (strpos($line, '`'.$field.'`') !== false) {
                                            if (preg_match('/(.*NOT NULL.*)/Ui', $line)) {
                                                $s .= "'',";
                                            } else {
                                                $s .= 'NULL,';
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            $s = rtrim($s, ',');

                            if ($i < $sizeof) {
                                $s .= "),\n";
                            } else {
                                $s .= ");\n";
                            }

                            $written += fwrite($fp, $s);
                            ++$i;
                        }
                        $timeElapsed = time() - $startTime;
                    } else {
                        unset($this->nextParams['backupTable']);
                        unset($this->currentParams['backupTable']);
                        break;
                    }
                } while (($timeElapsed < UpgraderTools::$loopBackupDbTime) && ($written < UpgraderTools::$maxWrittenAllowed));
            }
            $found++;
            $timeElapsed = time() - $startTime;
            $this->nextQuickInfo[] = sprintf($this->l('%1$s table has been saved.'), $table);
        } while (($timeElapsed < UpgraderTools::$loopBackupDbTime) && ($written < UpgraderTools::$maxWrittenAllowed));

        // end of loop
        if (isset($fp)) {
            fclose($fp);
            unset($fp);
        }

        $this->nextParams['tablesToBackup'] = $tablesToBackup;

        if (count($tablesToBackup) > 0) {
            $this->nextQuickInfo[] = sprintf($this->l('%1$s tables have been saved.'), $found);
            $this->next = 'backupDb';
            $this->stepDone = false;
            if (count($tablesToBackup)) {
                $this->nextDesc = sprintf($this->l('Database backup: %s table(s) left...'), count($tablesToBackup));
                $this->nextQuickInfo[] = sprintf($this->l('Database backup: %s table(s) left...'), count($tablesToBackup));
            }

            return true;
        }

        if (!$found && !empty($backupFile)) {
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
            $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('No valid tables were found to back up. Backup of file %s canceled.'), $backupFile);
            $this->error = 1;
            $this->nextDesc = sprintf($this->l('Error during database backup for file %s.'), $backupFile);

            return false;
        } else {
            unset($this->nextParams['backupLoopLimit']);
            unset($this->nextParams['backupLines']);
            unset($this->nextParams['backupTable']);
            unset($this->nextParams['tablesToBackup']);
            if ($found) {
                $this->nextQuickInfo[] = sprintf($this->l('%1$s tables have been saved.'), $found);
            }
            $this->stepDone = true;
            // reset dbStep at the end of this step
            $this->nextParams['dbStep'] = 0;

            $this->nextDesc = sprintf($this->l('Database backup done in filename %s. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeFiles()
    {
        $this->nextParams = $this->currentParams;
        $upgradeTheme = UpgraderTools::getConfig(UpgraderTools::UPGRADE_DEFAULT_THEME);

        $fileActions = FileActions::getFileActions(UpgraderTools::$loopUpgradeFiles);

        if (empty($fileActions)) {
            // list saved in $this->toUpgradeFileList
            // get files differences (previously generated)

            $filepathListDiff = [$this->upgrader->version => _PS_ADMIN_DIR_."/autoupgrade/download/thirtybees-file-actions-v{$this->upgrader->version}.json"];
            foreach (Upgrader::getInstance()->requires as $require) {
                // Filter requires that are lower than the current one
                if (Version::gt($require, _TB_VERSION_)) {
                    $filepathListDiff[$require] = _PS_ADMIN_DIR_."/autoupgrade/download/thirtybees-file-actions-v{$require}.json";
                }
            }

            // Sort ascending
            uksort($filepathListDiff, ['TbUpdaterModule\\SemVer\\Version', 'gt']);

            $deleteFilesForUpgrade = [];
            $addFilesForUpgrade = [];
            foreach ($filepathListDiff as $version => $diffFile) {
                if (!file_exists($diffFile)) {
                    $this->next = 'error';
                    $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('One or more lists of files to upgrade are missing.');

                    return false;
                }

                $unsortedListFilesToUpgrade = json_decode(file_get_contents($diffFile), true);

                foreach ($unsortedListFilesToUpgrade as $path => $fileAction) {
                    // Make sure the delete actions happen before the adds
                    // Leave the md5 sum for now
                    if ($fileAction['action'] === 'delete') {
                        $deleteFilesForUpgrade[] = [
                            'path'   => $path,
                            'action' => $fileAction['action'],
                        ];
                        foreach ($addFilesForUpgrade as $index => $file) {
                            if ($file['path'] === $path) {
                                unset($addFilesForUpgrade[$index]);

                                break;
                            }
                        }
                    } else {
                        if (!($fileAction['action'] === 'add' && !$upgradeTheme && substr($path, 0, 31) === '/themes/community-theme-default')) {
                            $addFilesForUpgrade[] = [
                                'path'   => $path,
                                'action' => $fileAction['action'],
                            ];
                        }
                        foreach ($deleteFilesForUpgrade as $index => $file) {
                            if ($file['path'] === $path) {
                                unset($addFilesForUpgrade[$index]);

                                break;
                            }
                        }
                    }
                }
            }

            // Save in an array in `fileActions`
            // Delete actions at back of array, we will process those first
            FileActions::addFileActions($addFilesForUpgrade + $deleteFilesForUpgrade);
            $fileActions = FileActions::getFileActions(UpgraderTools::$loopUpgradeFiles);

            if (empty($fileActions)) {
                $this->next = 'error';
                $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('Unable to find files to upgrade.');

                return false;
            }
            $count = FileActions::count();
            $this->next = 'upgradeFiles';
            $this->nextDesc = $this->nextQuickInfo[] = sprintf($this->l('%s files will be upgraded.'), $count);
            $this->stepDone = false;

            return true;
        }

        // Later we can choose between _PS_ROOT_DIR_ or _PS_TEST_DIR_
        $this->next = 'upgradeFiles';
        if (!is_array($fileActions)) {
            $this->next = 'error';
            $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('`fileActions` is not an array');

            return false;
        }
        foreach ($fileActions as $fileAction) {
            if (!$this->upgradeThisFile($fileAction)) {
                // put the file back to the begin of the list
                $this->next = 'error';
                $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Error when trying to upgrade file %s.'), $fileAction['path']);
                break;
            }
        }
        FileActions::removeFileActions(array_column($fileActions, FileActions::PRIMARY));
        $count = (int) FileActions::count();
        if ($count) {
            $this->nextDesc = sprintf($this->l('%1$s files left to upgrade.'), $count);
            $this->nextQuickInfo[] = sprintf($this->l('%2$s files left to upgrade.'), $count);
            $this->stepDone = false;
        } else {
            $this->next = 'upgradeDb';
            $this->nextDesc = $this->l('All files upgraded. Now upgrading database...');
            $this->nextResponseType = 'json';
            $this->stepDone = true;
        }

        return true;
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeDb()
    {
        $this->nextParams = $this->currentParams;
        if (!$this->doUpgrade()) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Error during database upgrade. You may need to restore your database.');

            return false;
        } else {
            $this->next = 'upgradeModules';
        }

        return true;
    }

    /**
     * Install thirty bees modules
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function ajaxProcessUpgradeModules()
    {
        $this->next = 'upgradeComplete';

        return true;
    }

    /**
     * Clean unwanted entries from the database
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessCleanDatabase()
    {
        /* Clean tabs order */
        foreach ($this->db->executeS((new DbQuery())->select('DISTINCT `id_parent`')->from('tab')) as $parent) {
            $i = 1;
            foreach ($this->db->executeS('SELECT `id_tab` FROM `'._DB_PREFIX_.'tab` WHERE `id_parent` = '.(int) $parent['id_parent'].' ORDER BY IF(class_name IN ("AdminHome", "AdminDashboard"), 1, 2), position ASC') as $child) {
                $this->db->execute('UPDATE '._DB_PREFIX_.'tab SET position = '.(int) ($i++).' WHERE id_tab = '.(int) $child['id_tab'].' AND id_parent = '.(int) $parent['id_parent']);
            }
        }

        /* Clean configuration integrity */
        $this->db->delete('configuration_lang', '(`value` IS NULL AND `date_upd` IS NULL) OR `value` LIKE ""', 0, false);

        $this->status = 'ok';
        $this->next = 'upgradeComplete';
        $this->nextDesc = $this->nextQuickInfo[] = $this->l('The database has been cleaned.');
    }

    /**
     * ends the upgrade process
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeComplete()
    {
        if (!$this->warningExists) {
            $this->nextDesc = $this->l('Upgrade process done. Congratulations! You can now reactivate your shop.');
        } else {
            $this->nextDesc = $this->l('Upgrade process done, but some warnings have been found.');
        }
        $this->next = '';

        if (UpgraderTools::getConfig('channel') != 'archive' && file_exists($this->getCoreFilePath()) && unlink($this->getCoreFilePath())) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->getCoreFilePath());
        } elseif (is_file($this->getCoreFilePath())) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->getCoreFilePath()).'</strong>';
        }

        if (UpgraderTools::getConfig('channel') != 'directory' && file_exists($this->latestRootDir) && Tools::deleteDirectory($this->latestRootDir, true)) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->latestRootDir);
        } elseif (is_dir($this->latestRootDir)) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->latestRootDir).'</strong>';
        }

        $this->runSql('clean');
    }

    /**
     * Start the rollback process
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRollback()
    {
        // First need to analyze what was wrong
        $this->nextParams = $this->currentParams;
        $this->restoreFilesFilename = $this->restoreName;
        if (!empty($this->restoreName)) {
            $files = scandir($this->tools->backupPath);
            // Find backup filenames, and be sure they exist
            foreach ($files as $file) {
                if (preg_match('#'.preg_quote('auto-backupfiles_'.$this->restoreName).'#', $file)) {
                    $this->restoreFilesFilename = $file;
                    break;
                }
            }
            if (!is_file($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename)) {
                $this->next = 'error';
                $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('File %s is missing: unable to restore files. Operation aborted.'), $this->restoreFilesFilename);

                return false;
            }
            $files = scandir($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreName);
            foreach ($files as $file) {
                if (preg_match('#auto-backupdb_'.preg_quote($this->restoreName).'#', $file)) {
                    if (!is_array($this->restoreDbFilenames)) {
                        $this->restoreDbFilenames = [];
                    }
                    $this->restoreDbFilenames[] = $file;
                }
            }

            // file order is important !
            if (is_array($this->restoreDbFilenames)) {
                sort($this->restoreDbFilenames);
            }
            if (count($this->restoreDbFilenames) == 0) {
                $this->next = 'error';
                $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('No backup database files found: it would be impossible to restore the database. Operation aborted.');

                return false;
            }

            $this->next = 'restoreFiles';
            $this->nextDesc = $this->l('Restoring files ...');
            // remove tmp files related to restoreFiles
            if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FROM_ARCHIVE_FILE_LIST)) {
                unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FROM_ARCHIVE_FILE_LIST);
            }
            if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST)) {
                unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST);
            }
        } else {
            $this->next = 'noRollbackFound';
        }

        return false;
    }

    /**
     * ajaxProcessRestoreFiles restore the previously saved files,
     * and delete files that weren't archived
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRestoreFiles()
    {
        $this->nextParams = $this->currentParams;
        $filepath = $this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename;
        $destExtract = _PS_ROOT_DIR_;
        $zip = new \ZipArchive();
        $zip->open($filepath);

        // If we do not have a list of files to restore, we make one first
        if (!isset($this->nextParams['filesToRestore'])) {
            $this->nextParams['filesToRestore'] = [];

            $this->nextParams['filesToRestore'] = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->nextParams['filesToRestore'][] = $zip->getNameIndex($i);
            }
        }

        // Take a chunk of 400 files and extract them
        $filesToRestoreNow = array_splice($this->nextParams['filesToRestore'], 0, 400);

        if ($zip->extractTo($destExtract, $filesToRestoreNow)) {
            if (empty($this->nextParams['filesToRestore'])) {
                $this->nextDesc = $this->l('Files restored. Now restoring database...');
                $this->nextQuickInfo[] = $this->l('Files restored. Now restoring database...');
                $this->next = 'restoreDb';

                return true;
            }

            $this->next = 'restoreFiles';
            $this->nextQuickInfo[] = sprintf($this->l('%d files left to restore.'), count($this->nextParams['filesToRestore']));

            return true;
        } else {
            $this->next = 'error';
            $this->nextDesc = $this->l('Unable to extract backup.');

            return false;
        }
    }

    /**
     * try to restore db backup file
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRestoreDb()
    {
        // Already set the next step
        $this->nextParams['dbStep'] = $this->currentParams['dbStep'];
        $startTime = time();
        $listQuery = [];

        // Continue current restore, we know that we have to execute some queries from the last session if the list file exists
        if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST)) {
            $listQuery = json_decode(file_get_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST));
        }

        // Deal with the next files stored in `$this->restoreDbFileNames` in case we do not have to execute previous queries
        if (empty($listQuery) && isset($this->restoreDbFilenames) && !empty($this->restoreDbFilenames)) {
            $currentDbFilename = array_shift($this->restoreDbFilenames);
            if (!preg_match('#auto-backupdb_#', $currentDbFilename, $match)) {
                $this->next = 'error';
                $this->nextDesc = $this->nextQuickInfo[] = $this->l(sprintf('%s: File format does not match.', $currentDbFilename));
                $this->error = 1;

                return false;
            }
            $this->nextParams['dbStep'] = $match[1];
            $backupdbPath = $this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreName;

            $dotPos = strrpos($currentDbFilename, '.');
            $fileext = substr($currentDbFilename, $dotPos + 1);
            $content = '';

            $this->nextQuickInfo[] = $this->l(sprintf('Opening backup database file %1s in %2s mode', $currentDbFilename, $fileext));

            switch ($fileext) {
                case 'bz':
                case 'bz2':
                    if ($fp = bzopen($backupdbPath.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= bzread($fp, 4096);
                        }
                        bzclose($fp);
                        break;
                    }
                // Fall through if failure
                case 'gz':
                    if ($fp = gzopen($backupdbPath.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= gzread($fp, 4096);
                        }
                    }
                    gzclose($fp);
                    break;
                default:
                    if ($fp = fopen($backupdbPath.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= fread($fp, 4096);
                        }
                    }
                    fclose($fp);
            }

            if (empty($content)) {
                $this->next = 'rollback';
                $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('Database backup is empty.');

                return false;
            }

            // preg_match_all is better than preg_split (what is used in do Upgrade.php)
            // This way we avoid extra blank lines
            // option s (PCRE_DOTALL) added
            $listQuery = preg_split('/;[\n\r]+/Usm', $content);
            unset($content);

            // @TODO : drop all old tables (created in upgrade)
            // This part has to be executed only onces (if dbStep=0)
            if ($this->nextParams['dbStep'] == '1') {
                $allTables = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
                $drops = [];
                foreach ($allTables as $k => $v) {
                    $table = array_shift($v);
                    $drops['drop table '.$k] = 'DROP TABLE IF EXISTS `'.bqSql($table).'`';
                    $drops['drop view '.$k] = 'DROP VIEW IF EXISTS `'.bqSql($table).'`';
                }
                unset($allTables);
                $listQuery = array_merge($drops, $listQuery);
            }
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST, base64_encode(serialize($listQuery)));
        }
        // If we have to execute previous queries, we do so over here
        if (is_array($listQuery) && (count($listQuery) > 0)) {
            do {
                if (count($listQuery) == 0) {
                    if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST)) {
                        unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST);
                    }

                    if (count($this->restoreDbFilenames)) {
                        $this->nextDesc = sprintf($this->l('Database restoration file %1$s done. %2$s file(s) left...'), $this->nextParams['dbStep'], count($this->restoreDbFilenames));
                    } else {
                        $this->nextDesc = sprintf($this->l('Database restoration file %1$s done.'), $this->nextParams['dbStep']);
                    }

                    $this->nextQuickInfo[] = $this->nextDesc;
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'restoreDb';
                    if (count($this->restoreDbFilenames) == 0) {
                        $this->next = 'rollbackComplete';
                        $this->nextQuickInfo[] = $this->nextDesc = $this->l('Database has been restored.');
                    }

                    return true;
                }
                // filesForBackup already contains all the correct files
                if (count($listQuery) == 0) {
                    continue;
                }

                $query = array_shift($listQuery);
                if (!empty($query)) {
                    if (!$this->db->execute($query, false)) {
                        if (is_array($listQuery)) {
                            array_unshift($listQuery, $query);
                        }

                        $this->next = 'error';
                        $this->nextDesc = $this->l('Error during database restoration');
                        $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('[SQL ERROR] ').$query.' - '.$this->db->getMsgError();
                        $this->error = 1;
                        unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST);

                        return false;
                    }
                }

                $timeElapsed = time() - $startTime;
            } while ($timeElapsed < UpgraderTools::$loopRestoreQueryTime);

            $queriesLeft = count($listQuery);

            // If we weren't able to execute all queries within the given time, we save them
            if ($queriesLeft > 0) {
                file_put_contents(UpgraderTools::TO_RESTORE_QUERY_LIST, json_encode($listQuery));
            } elseif (file_exists(UpgraderTools::TO_RESTORE_QUERY_LIST)) {
                unlink(UpgraderTools::TO_RESTORE_QUERY_LIST);
            }

            $this->stepDone = false;
            $this->next = 'restoreDb';
            $this->nextQuickInfo[] = $this->nextDesc = sprintf($this->l('%1$s queries left for file %2$s...'), $queriesLeft, $this->nextParams['dbStep']);
            unset($query);
            unset($listQuery);
        } else {
            $this->stepDone = true;
            $this->status = 'ok';
            $this->next = 'rollbackComplete';
            $this->nextQuickInfo[] = $this->nextDesc = $this->l('Database restore complete.');
        }

        return true;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessNoRollbackFound()
    {
        $this->nextDesc = $this->l('Nothing to restore');
        $this->next = 'rollbackComplete';
    }

    /**
     * ends the rollback process
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessRollbackComplete()
    {
        $this->nextDesc = $this->l('Restoration process done. Congratulations! You can now reactivate your shop.');
        $this->next = '';
    }

    /**
     * getCoreFilePath return the path to the zip file containing thirty bees core.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getCoreFilePath()
    {
        return $this->tools->downloadPath.DIRECTORY_SEPARATOR.'thirtybees-v'.$this->upgrader->version.'.zip';
    }

    /**
     * getExtraFilePath return the path to the zip file containing thirty bees core.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getExtraFilePath()
    {
        return $this->tools->downloadPath.DIRECTORY_SEPARATOR.'thirtybees-extra-v'.$this->upgrader->version.'.zip';
    }

    /**
     * display informations related to the selected channel : link/changelog for remote channel,
     * or configuration values for special channels
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessGetChannelInfo()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';

        $channel = $this->currentParams['channel'];
        UpgraderTools::setConfig('channel', $channel);
        $upgrader = Upgrader::getInstance();
        $upgrader->selectedChannel = $channel;
        $upgrader->checkTbVersion(true);

        $this->nextParams['result'] = [
            'version'   => $upgrader->version,
            'channel'   => $upgrader->channel,
            'coreLink'  => $upgrader->coreLink,
            'extraLink' => $upgrader->extraLink,
            'md5Link'   => $upgrader->fileActionsLink,
            'changelog' => $upgrader->changelogLink,
            'available' => (bool) $upgrader->version,
        ];

        UpgraderTools::setConfig('channel', $upgrader->channel);
    }

    /**
     * @desc  extract a zip file to the given directory
     * @return bool success
     * we need a copy of it to be able to restore without keeping Tools and Autoload stuff
     *
     * @since 1.0.0
     */
    private function extractZip($fromFile, $toDir)
    {
        if (!is_file($fromFile)) {
            $this->next = 'error';
            $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('%s is not a file'), $fromFile);

            return false;
        }

        if (!file_exists($toDir)) {
            if (!mkdir($toDir, 0777, true)) {
                $this->next = 'error';
                $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Unable to create directory %s.'), $toDir);

                return false;
            } else {
                chmod($toDir, 0777);
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($fromFile) === true && isset($zip->filename) && $zip->filename) {
            $extractResult = true;
            // We extract file by file, it is very fast
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $extractResult &= $zip->extractTo($toDir, [$zip->getNameIndex($i)]);
            }

            if ($extractResult) {
                $this->nextQuickInfo[] = sprintf($this->l('Archive %s extracted'), $fromFile);

                return true;
            } else {
                $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('zip->extractTo(): unable to use %s as extract destination.'), $toDir);

                return false;
            }
        } elseif (isset($zip->filename) && $zip->filename) {
            $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Unable to open zipFile %s'), $fromFile);

            return false;
        }

        return false;
    }

    /**
     * list modules to upgrade and save them in a serialized array in $this->toUpgradeModuleList
     *
     * @return false|int Number of files found
     *
     * @since 1.0.0
     */
    public function listModulesToUpgrade()
    {
        static $list = [];

        $dir = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules';

        if (!is_dir($dir)) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Nothing has been extracted. It seems the unzip step has been skipped.');
            $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('%s does not exist or is not a directory.'), $dir);

            return false;
        }

        $allModules = scandir($dir);
        foreach ($allModules as $moduleName) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$moduleName)) {
                continue;
            } elseif (is_dir($dir.DIRECTORY_SEPARATOR.$moduleName.DIRECTORY_SEPARATOR)) {
                if (is_array($this->modulesAddons)) {
                    $idAddons = array_search($moduleName, $this->modulesAddons);
                }
                if (isset($idAddons) && $idAddons) {
                    if ($moduleName != $this->tools->autoupgradeDir) {
                        $list[] = ['id' => $idAddons, 'name' => $moduleName];
                    }
                }
            }
        }
        file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_UPGRADE_MODULE_LIST, base64_encode(serialize($list)));
        $this->nextParams['modulesToUpgrade'] = UpgraderTools::TO_UPGRADE_MODULE_LIST;

        return count($list);
    }

    /**
     * upgrade module $name (identified by $id_module on addons server)
     *
     * @param mixed $idModule
     * @param mixed $name
     *
     * @access public
     * @return void
     */
    public function upgradeThisModule($idModule, $name)
    {

    }

    /**
     * This function now replaces doUpgrade.php or upgrade.php
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function doUpgrade()
    {
        if (!$this->doUpgradeSetupConst()) {
            return false;
        }

        $filePrefix = 'PREFIX_';
        $engineType = 'ENGINE_TYPE';

        $mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');

        //old version detection
        global $oldversion;
        $oldversion = false;
        if (file_exists(SETTINGS_FILE)) {
            include_once(SETTINGS_FILE);

            // include_once(DEFINES_FILE);
            $oldversion = _TB_VERSION_;
        } else {
            $this->next = 'error';
            $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('The config/settings.inc.php file was not found.');

            return false;
        }

        if (!defined('__PS_BASE_URI__')) {
            define('__PS_BASE_URI__', realpath(dirname($_SERVER['SCRIPT_NAME'])).'/../../');
        }

        if (!defined('_THEMES_DIR_')) {
            define('_THEMES_DIR_', __PS_BASE_URI__.'themes/');
        }

        //check DB access
        $this->db;
        error_reporting(E_ALL);
        $resultDB = Db::checkConnection(_DB_SERVER_, _DB_USER_, _DB_PASSWD_, _DB_NAME_);
        if ($resultDB !== 0) {
            // $logger->logError('Invalid database configuration.');
            $this->next = 'error';
            $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('Invalid database configuration');

            return false;
        }

        //custom sql file creation
        $upgradeFiles = [];
        $upgradeDirSql = $this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'latest'.DIRECTORY_SEPARATOR.'upgrade'.DIRECTORY_SEPARATOR.'sql';


        if (!file_exists($upgradeDirSql)) {
            $this->next = 'error';
            $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('Unable to find SQL upgrade directory in the installation path.');

            return false;
        }

        if ($handle = opendir($upgradeDirSql)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' and $file != '..') {
                    $upgradeFiles[] = str_replace(".sql", "", $file);
                }
            }
            closedir($handle);
        }
        if (empty($upgradeFiles)) {
            $this->next = 'error';
            $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.'), $upgradeDirSql);

            // fail 31
            return false;
        }
        natcasesort($upgradeFiles);
        $neededUpgradeFiles = [];

        $arrayVersion = explode('.', $oldversion);
        $versionNumbers = count($arrayVersion);
        if ($versionNumbers != 4) {
            $arrayVersion = array_pad($arrayVersion, 4, '0');
        }

        $oldversion = implode('.', $arrayVersion);

        foreach ($upgradeFiles as $version) {
            if (version_compare($version, $oldversion) == 1 && version_compare('2.999.999.999', $version) != -1) {
                $neededUpgradeFiles[] = $version;
            }
        }

        $sqlContentVersion = [];
        if ($this->deactivateCustomModule) {
            require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'deactivate_custom_modules.php');
            if (function_exists('deactivate_custom_modules')) {
                deactivate_custom_modules();
            }
        }

        foreach ($neededUpgradeFiles as $version) {
            $file = $upgradeDirSql.DIRECTORY_SEPARATOR.$version.'.sql';
            if (!file_exists($file)) {
                $this->next = 'error';
                $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('Error while loading SQL upgrade file "%s.sql".'), $version);

                return false;
            }
            if (!$sqlContent = file_get_contents($file)."\n") {
                $this->next = 'error';
                $this->nextErrors[] = $this->nextQuickInfo[] = $this->l(sprintf('Error while loading sql SQL file %s.', $version));

                return false;
            }
            $sqlContent = str_replace([$filePrefix, $engineType], [_DB_PREFIX_, $mysqlEngine], $sqlContent);
            $sqlContent = preg_split("/;\s*[\r\n]+/", $sqlContent);
            $sqlContentVersion[$version] = $sqlContent;
        }

        //sql file execution
        global $requests, $warningExist;
        $requests = '';
        $warningExist = false;

        // Configuration::loadConfiguration();
        foreach ($sqlContentVersion as $upgradeFile => $sqlContent) {
            foreach ($sqlContent as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    /* If php code have to be executed */
                    if (strpos($query, '/* PHP:') !== false) {
                        /* Parsing php code */
                        $pos = strpos($query, '/* PHP:') + strlen('/* PHP:');
                        $phpString = substr($query, $pos, strlen($query) - $pos - strlen(' */;'));
                        $php = explode('::', $phpString);
                        preg_match('/\((.*)\)/', $phpString, $pattern);
                        $paramsString = trim($pattern[0], '()');
                        preg_match_all('/([^,]+),? ?/', $paramsString, $parameters);
                        if (isset($parameters[1])) {
                            $parameters = $parameters[1];
                        } else {
                            $parameters = [];
                        }
                        if (is_array($parameters)) {
                            foreach ($parameters as &$parameter) {
                                $parameter = str_replace('\'', '', $parameter);
                            }
                        }

                        // reset phpRes to a null value
                        $phpRes = null;
                        /* Call a simple function */
                        if (strpos($phpString, '::') === false) {
                            $funcName = str_replace($pattern[0], '', $php[0]);

                            if (!file_exists(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($funcName).'.php')) {
                                $this->nextErrors[] = $this->nextQuickInfo[] = $upgradeFile.' PHP - missing file '._PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($funcName).'.php';
                                $warningExist = true;
                            } else {
                                require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($funcName).'.php');
                                $phpRes = call_user_func_array($funcName, $parameters);
                            }
                        } /* Or an object method */
                        else {
                            $funcName = [$php[0], str_replace($pattern[0], '', $php[1])];
                            $this->nextErrors[] = $this->nextQuickInfo[] = $upgradeFile.' PHP - Object Method call is forbidden ('.$funcName.')';
                            $warningExist = true;
                        }

                        if (isset($phpRes) && (is_array($phpRes) && !empty($phpRes['error'])) || $phpRes === false) {
                            // $this->next = 'error';
                            $this->nextErrors[] = $this->nextQuickInfo[] = '
                                PHP '.$upgradeFile.' '.$query."\n".'
								'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
								'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n");
                            $warningExist = true;
                        } else {
                            $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] PHP '.$upgradeFile.' : '.$query.'</div>';
                        }
                        if (isset($phpRes)) {
                            unset($phpRes);
                        }
                    } else {
                        if (strstr($query, 'CREATE TABLE') !== false) {
                            $pattern = '/CREATE TABLE.*[`]*'._DB_PREFIX_.'([^`]*)[`]*\s\(/';
                            preg_match($pattern, $query, $matches);
                            if (isset($matches[1]) && $matches[1]) {
                                $drop = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.$matches[1].'`;';
                                $result = $this->db->execute($drop, false);
                                if ($result) {
                                    $this->nextQuickInfo[] = '<div class="upgradeDbOk">'.sprintf($this->l('[DROP] SQL %s table has been dropped.'), '`'._DB_PREFIX_.$matches[1].'`').'</div>';
                                }
                            }
                        }
                        $result = $this->db->execute($query, false);
                        if (!$result) {
                            $error = $this->db->getMsgError();
                            $errorNumber = $this->db->getNumberError();
                            $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
								[WARNING] SQL '.$upgradeFile.'
								'.$errorNumber.' in '.$query.': '.$error.'</div>';

                            $duplicates = ['1050', '1054', '1060', '1061', '1062', '1091'];
                            if (!in_array($errorNumber, $duplicates)) {
                                $this->nextErrors[] = 'SQL '.$upgradeFile.' '.$errorNumber.' in '.$query.': '.$error;
                                $warningExist = true;
                            }
                        } else {
                            $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] SQL '.$upgradeFile.' '.$query.'</div>';
                        }
                    }
                    if (isset($query)) {
                        unset($query);
                    }
                }
            }
        }
        if ($this->next == 'error') {
            $this->nextDesc = $this->l('An error happened during the database upgrade.');

            return false;
        }

        $this->nextQuickInfo[] = $this->l('Database upgrade OK'); // no error !

        // At this point, database upgrade is over.
        // Now we need to add all previous missing settings items, and reset cache and compile directories
        $this->writeNewSettings();

        // Settings updated, compile and cache directories must be emptied
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty/cache/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty/compile/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty_v2/cache/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty_v2/compile/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/cache/smarty/cache/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/cache/smarty/compile/';

        foreach ($arrayToClean as $dir) {
            if (!file_exists($dir)) {
                $this->nextQuickInfo[] = sprintf($this->l('[SKIP] directory "%s" does not exist and cannot be emptied.'), str_replace(_PS_ROOT_DIR_, '', $dir));
                continue;
            } else {
                foreach (scandir($dir) as $file) {
                    if ($file[0] != '.' && $file != 'index.php' && $file != '.htaccess') {
                        if (is_file($dir.$file)) {
                            unlink($dir.$file);
                        } elseif (is_dir($dir.$file.DIRECTORY_SEPARATOR)) {
                            Tools::deleteDirectory($dir.$file.DIRECTORY_SEPARATOR, true);
                        }
                        $this->nextQuickInfo[] = sprintf($this->l('[CLEANING CACHE] File %s removed'), $file);
                    }
                }
            }
        }


        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `name` = \'PS_LEGACY_IMAGES\' WHERE name LIKE \'0\' AND `value` = 1');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `value` = 0 WHERE `name` LIKE \'PS_LEGACY_IMAGES\'');
        if (Db::getInstance()->getValue('SELECT COUNT(id_product_download) FROM `'._DB_PREFIX_.'product_download` WHERE `active` = 1') > 0) {
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `value` = 1 WHERE `name` LIKE \'PS_VIRTUAL_PROD_FEATURE_ACTIVE\'');
        }

        if (defined('_THEME_NAME_') && $this->updateDefaultTheme && preg_match('#(default|prestashop|default-bootstrap)$#', _THEME_NAME_)) {
            $separator = addslashes(DIRECTORY_SEPARATOR);
            $file = _PS_ROOT_DIR_.$separator.'themes'.$separator._THEME_NAME_.$separator.'cache'.$separator;
            if (file_exists($file)) {
                foreach (scandir($file) as $cache) {
                    if ($cache[0] != '.' && $cache != 'index.php' && $cache != '.htaccess' && file_exists($file.$cache) && !is_dir($file.$cache)) {
                        if (file_exists($dir.$cache)) {
                            unlink($file.$cache);
                        }
                    }
                }
            }
        }

        if (version_compare($this->installVersion, '1.5.4.0', '>=')) {
            // Upgrade languages
            if (!defined('_PS_TOOL_DIR_')) {
                define('_PS_TOOL_DIR_', _PS_ROOT_DIR_.'/tools/');
            }
            if (!defined('_PS_TRANSLATIONS_DIR_')) {
                define('_PS_TRANSLATIONS_DIR_', _PS_ROOT_DIR_.'/translations/');
            }
            if (!defined('_PS_MODULES_DIR_')) {
                define('_PS_MODULES_DIR_', _PS_ROOT_DIR_.'/modules/');
            }
            if (!defined('_PS_MAILS_DIR_')) {
                define('_PS_MAILS_DIR_', _PS_ROOT_DIR_.'/mails/');
            }
        }

        $path = _PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'header.tpl';
        if (file_exists($path)) {
            unlink($path);
        }

        if (file_exists(_PS_ROOT_DIR_.'/cache/class_index.php')) {
            unlink(_PS_ROOT_DIR_.'/cache/class_index.php');
        }

        // Remove old PrestaShop XML files
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/blog-fr.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/blog-fr.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/default_country_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/default_country_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/modules_native_addons.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/modules_native_addons.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/must_have_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/must_have_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/tab_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/tab_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/trusted_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/trusted_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/untrusted_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/untrusted_modules_list.xml');
        }

        if ($this->deactivateCustomModule) {
            $exist = Db::getInstance()->getValue('SELECT `id_configuration` FROM `'._DB_PREFIX_.'configuration` WHERE `name` LIKE \'PS_DISABLE_OVERRIDES\'');
            if ($exist) {
                Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value = 1 WHERE `name` LIKE \'PS_DISABLE_OVERRIDES\'');
            } else {
                Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'configuration` (name, value, date_add, date_upd) VALUES ("PS_DISABLE_OVERRIDES", 1, NOW(), NOW())');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/PrestaShopAutoload.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/PrestaShopAutoload.php');
            }
        }

        // delete cache filesystem if activated
        if (defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_) {
            $depth = (int) $this->db->getValue(
                'SELECT value
				FROM '._DB_PREFIX_.'configuration
				WHERE name = "PS_CACHEFS_DIRECTORY_DEPTH"'
            );
            if ($depth) {
                if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                    define('_PS_CACHEFS_DIRECTORY_', _PS_ROOT_DIR_.'/cache/cachefs/');
                }
                Tools::deleteDirectory(_PS_CACHEFS_DIRECTORY_, false);
                if (class_exists('CacheFs', false)) {
                    static::createCacheFsDirectories((int) $depth);
                }
            }
        }

        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="0" WHERE name = "PS_HIDE_OPTIMIZATION_TIS"', false);
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="1" WHERE name = "PS_NEED_REBUILD_INDEX"', false);
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="1.6.1.999" WHERE name = "PS_VERSION_DB"', false);

        if ($this->next === 'error') {
            return false;
        } elseif (!empty($warningExist) || $this->warningExists) {
            $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] = $this->l('Warning detected during upgrade.');
        } else {
            $this->nextDesc = $this->l('Database upgrade completed');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function doUpgradeSetupConst()
    {
        // Initialize
        // setting the memory limit to 256M only if current is lower
        $memoryLimit = ini_get('memory_limit');
        if ((substr($memoryLimit, -1) != 'G')
            && ((substr($memoryLimit, -1) == 'M' and substr($memoryLimit, 0, -1) < 256)
                || is_numeric($memoryLimit) and (intval($memoryLimit) < 131072))
        ) {
            @ini_set('memory_limit', '256M');
        }

        /* Redefine REQUEST_URI if empty (on some webservers...) */
        if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
            if (!isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['SCRIPT_FILENAME'])) {
                $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'];
            }
            if (isset($_SERVER['SCRIPT_NAME'])) {
                if (basename($_SERVER['SCRIPT_NAME']) == 'index.php' && empty($_SERVER['QUERY_STRING'])) {
                    $_SERVER['REQUEST_URI'] = dirname($_SERVER['SCRIPT_NAME']).'/';
                } else {
                    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
                    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                        $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
                    }
                }
            }
        }
        $_SERVER['REQUEST_URI'] = str_replace('//', '/', $_SERVER['REQUEST_URI']);

        define('INSTALL_VERSION', $this->installVersion);
        // 1.4
        define('INSTALL_PATH', $this->tools->autoupgradePath);
        // 1.5 ...
        define('_PS_INSTALL_PATH_', INSTALL_PATH.DIRECTORY_SEPARATOR);
        // 1.6
        if (!defined('_PS_CORE_DIR_')) {
            define('_PS_CORE_DIR_', _PS_ROOT_DIR_);
        }

        define('PS_INSTALLATION_IN_PROGRESS', true);
        define('SETTINGS_FILE', _PS_ROOT_DIR_.'/config/settings.inc.php');
        define('DEFINES_FILE', _PS_ROOT_DIR_.'/config/defines.inc.php');
        define('INSTALLER__PS_BASE_URI', substr($_SERVER['REQUEST_URI'], 0, -1 * (strlen($_SERVER['REQUEST_URI']) - strrpos($_SERVER['REQUEST_URI'], '/')) - strlen(substr(dirname($_SERVER['REQUEST_URI']), strrpos(dirname($_SERVER['REQUEST_URI']), '/') + 1))));

        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set('Europe/Amsterdam');
        }

        // if _PS_ROOT_DIR_ is defined, use it instead of "guessing" the module dir.
        if (defined('_PS_ROOT_DIR_') and !defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', _PS_ROOT_DIR_.'/modules/');
        } elseif (!defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', INSTALL_PATH.'/../modules/');
        }

        $upgradeDirPhp = 'latest/upgrade/php';
        if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp)) {
            $this->next = 'error';
            $this->nextDesc = sprintf($this->l('%s directory is missing in archive or directory.'), INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp);
            $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('%s directory is missing in archive or directory.'), INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp);

            return false;
        }
        define('_PS_INSTALLER_PHP_UPGRADE_DIR_', $this->tools->autoupgradePath.'/latest/upgrade/php/');

        return true;
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function writeNewSettings()
    {
        // note : duplicated line
        $mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');

        $oldLevel = error_reporting(E_ALL);
        //refresh conf file
        copy(SETTINGS_FILE, str_replace('.php', '.old.php', SETTINGS_FILE));
        $confFile = new AddConfToFile(SETTINGS_FILE, 'w');
        if ($confFile->error) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Error when opening settings.inc.php file in write mode');
            $this->nextQuickInfo[] = $confFile->error;
            $this->nextErrors[] = $this->l('Error when opening settings.inc.php file in write mode').': '.$confFile->error;

            return false;
        }

        $caches = ['CacheMemcache', 'CacheApc', 'CacheFs', 'CacheMemcached', 'CacheXcache'];

        $datas = [
            ['_DB_SERVER_', _DB_SERVER_],
            ['_DB_NAME_', _DB_NAME_],
            ['_DB_USER_', _DB_USER_],
            ['_DB_PASSWD_', _DB_PASSWD_],
            ['_DB_PREFIX_', _DB_PREFIX_],
            ['_MYSQL_ENGINE_', $mysqlEngine],
            ['_PS_CACHING_SYSTEM_', (defined('_PS_CACHING_SYSTEM_') && in_array(_PS_CACHING_SYSTEM_, $caches)) ? _PS_CACHING_SYSTEM_ : 'CacheMemcache'],
            ['_PS_CACHE_ENABLED_', defined('_PS_CACHE_ENABLED_') ? _PS_CACHE_ENABLED_ : '0'],
            ['_COOKIE_KEY_', _COOKIE_KEY_],
            ['_COOKIE_IV_', _COOKIE_IV_],
            ['_PS_CREATION_DATE_', defined("_PS_CREATION_DATE_") ? _PS_CREATION_DATE_ : date('Y-m-d')],
            ['_PS_VERSION_', '1.6.1.999'],
            ['_TB_VERSION_', $this->upgrader->version],
        ];

        if (defined('_RIJNDAEL_KEY_')) {
            $datas[] = ['_RIJNDAEL_KEY_', _RIJNDAEL_KEY_];
        }
        if (defined('_RIJNDAEL_IV_')) {
            $datas[] = ['_RIJNDAEL_IV_', _RIJNDAEL_IV_];
        }
        if (!defined('_PS_CACHE_ENABLED_')) {
            define('_PS_CACHE_ENABLED_', '0');
        }
        if (!defined('_MYSQL_ENGINE_')) {
            define('_MYSQL_ENGINE_', 'MyISAM');
        }

        $datas[] = ['_PS_DIRECTORY_', __PS_BASE_URI__];

        foreach ($datas as $data) {
            $confFile->writeInFile($data[0], $data[1]);
        }

        if ($confFile->error != false) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Error when generating new settings.inc.php file.');
            $this->nextQuickInfo[] = $confFile->error;
            $this->nextErrors[] = $this->l('Error when generating new settings.inc.php file.').' '.$confFile->error;

            return false;
        } else {
            $this->nextQuickInfo[] = $this->l('Settings file updated');
        }
        error_reporting($oldLevel);

        return true;
    }

    /**
     * @param      $levelDepth
     * @param bool $directory
     *
     * @since 1.0.0
     */
    protected function createCacheFsDirectories($levelDepth, $directory = false)
    {
        if (!$directory) {
            if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                define('_PS_CACHEFS_DIRECTORY_', _PS_ROOT_DIR_.'/cache/cachefs/');
            }
            $directory = _PS_CACHEFS_DIRECTORY_;
        }
        $chars = '0123456789abcdef';
        for ($i = 0; $i < strlen($chars); $i++) {
            $newDir = $directory.$chars[$i].'/';
            if (mkdir($newDir, 0777) && chmod($newDir, 0777) && $levelDepth - 1 > 0) {
                static::createCacheFsDirectories($levelDepth - 1, $newDir);
            }
        }
    }

    /**
     * this function list all files that will be remove to retrieve the filesystem states before the upgrade
     *
     * @access public
     * @return array|false
     *
     * @since  1.0.0
     */
    public function listFilesToRemove()
    {
        $toRemove = false;
        // note : getDiffFilesList does not include files moved by upgrade scripts,
        // so this method can't be trusted to fully restore directory
        // $toRemove = $this->upgrader->getDiffFilesList(_PS_VERSION_, $prev_version, false);
        // if we can't find the diff file list corresponding to _PS_VERSION_ and prev_version,
        // let's assume to remove every files
        if (!$toRemove) {
            $toRemove = $this->listFilesInDir(_PS_ROOT_DIR_, 'restore', true);
        }

        $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
        // if a file in "ToRemove" has been skipped during backup,
        // just keep it
        foreach ($toRemove as $key => $file) {
            $filename = substr($file, strrpos($file, '/') + 1);
            $toRemove[$key] = preg_replace('#^/admin/#', $adminDir.'/', $file);
            // this is a really sensitive part, so we add extra checks: preserve everything that contains "autoupgrade"
            if ($this->skipFile($filename, $file, 'backup') || strpos($file, $this->tools->autoupgradeDir)) {
                unset($toRemove[$key]);
            }
        }

        return $toRemove;
    }

    /**
     * @param string $dir
     * @param string $way
     * @param bool   $listDirectories
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function listFilesInDir($dir, $way = 'backup', $listDirectories = false)
    {
        $list = [];
        $dir = rtrim($dir, '/').DIRECTORY_SEPARATOR;
        $allFiles = false;
        if (is_dir($dir) && is_readable($dir)) {
            $allFiles = scandir($dir);
        }
        if (is_array($allFiles)) {
            foreach ($allFiles as $file) {
                if ($file[0] != '.') {
                    $fullPath = $dir.$file;
                    if (!$this->skipFile($file, $fullPath, $way)) {
                        if (is_dir($fullPath)) {
                            $list = array_merge($list, $this->listFilesInDir($fullPath, $way, $listDirectories));
                            if ($listDirectories) {
                                $list[] = $fullPath;
                            }
                        } else {
                            $list[] = $fullPath;
                        }
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Check whether a file is in backup or restore skip list
     *
     * @param mixed $file     : current file or directory name eg:'.svn' , 'settings.inc.php'
     * @param mixed $fullpath : current file or directory fullpath eg:'/home/web/www/prestashop/config/settings.inc.php'
     * @param mixed $way      : 'backup' , 'upgrade'
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function skipFile($file, $fullpath, $way = 'backup')
    {
        $fullpath = str_replace('\\', '/', $fullpath); // wamp compliant
        $rootpath = str_replace('\\', '/', _PS_ROOT_DIR_);
        $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
        switch ($way) {
            case 'backup':
                if (in_array($file, $this->backupIgnoreFiles)) {
                    return true;
                }

                foreach ($this->backupIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$adminDir, $path);
                    if ($fullpath == $rootpath.$path) {
                        return true;
                    }
                }
                break;
            // restore or upgrade way : ignore the same files
            // note the restore process use skipFiles only if xml md5 files
            // are unavailable
            case 'restore':
                if (in_array($file, $this->restoreIgnoreFiles)) {
                    return true;
                }

                foreach ($this->restoreIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$adminDir, $path);
                    if ($fullpath == $rootpath.$path) {
                        return true;
                    }
                }
                break;
            case 'upgrade':
                // keep mail : will skip only if already exists
                /* If set to false, we will not upgrade/replace the "mails" directory */
                if (!UpgraderTools::getConfig('PS_AUTOUP_KEEP_MAILS')) {
                    if (strpos(str_replace('/', DIRECTORY_SEPARATOR, $fullpath), DIRECTORY_SEPARATOR.'mails'.DIRECTORY_SEPARATOR)) {
                        return true;
                    }
                }
                if (in_array($file, $this->excludeFilesFromUpgrade)) {
                    if ($file[0] != '.') {
                        $this->nextQuickInfo[] = sprintf($this->l('File %s is preserved'), $file);
                    }

                    return true;
                }

                foreach ($this->excludeAbsoluteFilesFromUpgrade as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$adminDir, $path);
                    if (strpos($fullpath, $rootpath.$path) !== false) {
                        $this->nextQuickInfo[] = sprintf($this->l('File %s is preserved'), $fullpath);

                        return true;
                    }
                }

                break;
            // default : if it's not a backup or an upgrade, do not skip the file
            default:
                return false;
        }

        // by default, don't skip
        return false;
    }

    /**
     * @param string $dir
     * @param array  $ignore
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function isDirEmpty($dir, $ignore = ['.svn', '.git'])
    {
        $arrayIgnore = array_merge(['.', '..'], $ignore);
        $content = scandir($dir);
        foreach ($content as $filename) {
            if (!in_array($filename, $arrayIgnore)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessMergeTranslations()
    {
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxPreProcess()
    {
        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            return;
        }

        /* PrestaShop demo mode*/
        $request = json_decode(file_get_contents('php://input'), true);
        if (!empty($request['responseType']) && $request['responseType'] === 'json') {
            header('Content-Type: application/json');
        }

        if (isset($this->action) && $this->action) {
            $action = $this->action;
            if (!method_exists(get_class($this), 'ajaxProcess'.$action)) {
                $this->nextDesc = sprintf($this->l('action "%1$s" not found'), $action);
                $this->next = 'error';
                $this->error = '1';
            }
        }

        if (!method_exists('Tools', 'apacheModExists') || Tools::apacheModExists('evasive')) {
            sleep(1);
        }
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function displayAjax()
    {
        die($this->buildAjaxResult());
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function buildAjaxResult()
    {
        $return = [];

        $return['error'] = (bool) $this->error;
        $return['stepDone'] = $this->stepDone;
        $return['next'] = $this->next;
        $return['status'] = $this->next == 'error' ? 'error' : 'ok';
        $return['nextDesc'] = $this->nextDesc;

        $this->nextParams['config'] = UpgraderTools::getConfig();

        foreach ($this->ajaxParams as $v) {
            if (property_exists($this, $v)) {
                $this->nextParams[$v] = $this->$v;
            } else {
                $this->nextQuickInfo[] = sprintf($this->l('[WARNING] Property %s is missing'), $v);
            }
        }

        $return['nextParams'] = $this->nextParams;
        if (!isset($return['nextParams']['dbStep'])) {
            $return['nextParams']['dbStep'] = 0;
        }

        $return['nextParams']['typeResult'] = $this->nextResponseType;

        $return['nextQuickInfo'] = $this->nextQuickInfo;
        $return['nextErrors'] = $this->nextErrors;

        return json_encode($return, JSON_PRETTY_PRINT);
    }

    /**
     * upgradeThisFile
     *
     * @param mixed $fileAction
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function upgradeThisFile($fileAction)
    {
        // note : keepMails is handled in skipFiles
        // translations_custom and mails_custom list are currently not used
        // later, we could handle customization with some kind of diff functions
        // for now, just copy $file in str_replace($this->latestRootDir,_PS_ROOT_DIR_)
        $path = $fileAction['path'];
        $orig = $this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'latest'.$path;
        if (Tools::substr($path, 0, 1) !== '/') {
            $path = '/'.$path;
        }

        $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
        if (Tools::substr($path, 0, 6) === '/admin') {
            $newPath = $adminDir.Tools::substr($path, 6);
        } else {
            $newPath = $path;
        }

        $dest = _PS_ROOT_DIR_.$newPath;

        switch ($fileAction['action']) {
            case 'delete':
                if (is_dir($dest)) {
                    UpgraderTools::rrmdir($dest);
                    $this->nextQuickInfo[] = sprintf($this->l('Recursively removed directory %1$s.'), $dest);
                } else {
                    @unlink($dest);
                    $this->nextQuickInfo[] = sprintf($this->l('Removed file %1$s.'), $dest);
                }
                break;

            case 'add':
            default:
                if (!file_exists($orig)) {
                    $this->nextQuickInfo[] = sprintf($this->l('Source file %1$s not found. Skipping'), $orig);

                    break;
                }

                if ($this->isTranslationFile($dest) && file_exists($dest)) {
                    $typeTrad = $this->getTranslationFileType($dest);
                    $res = $this->mergeTranslationFile($orig, $dest, $typeTrad);
                    if ($res) {
                        $this->nextQuickInfo[] = sprintf($this->l('[TRANSLATION] The translation files have been merged into file %1$s.'), $dest);

                        return true;
                    } else {
                        $this->nextErrors[] = $this->nextQuickInfo[] = sprintf($this->l('[TRANSLATION] The translation files have not been merged into file %1$s. Switch to copy %2$s.'), $dest, $dest);
                    }
                }

                // upgrade exception were above. This part now process all files that have to be upgraded (means to modify or to remove)
                // delete before updating (and this will also remove deprecated files)
                if (!file_exists(dirname($dest))) {
                    mkdir(dirname($dest), 0777, true);
                }

                if (copy($orig, $dest)) {
                    $this->nextQuickInfo[] = sprintf($this->l('Copied %1$s to %2$s.'), $orig, $dest);

                    return true;
                } else {
                    $this->next = 'error';
                    $this->nextDesc = $this->nextErrors[] = $this->nextQuickInfo[] =
                        sprintf($this->l('Error while copying from %1$s to %2$s'), $orig, $dest);

                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * return true if $file is a translation file
     *
     * @param string $file filepath (from prestashop root)
     *
     * @access public
     * @return bool
     *
     * @since  1.0.0
     */
    public function isTranslationFile($file)
    {
        if ($this->getTranslationFileType($file) !== false) {
            return true;
        }

        return false;
    }

    /**
     * getTranslationFileType
     *
     * @param string $file filepath to check
     *
     * @access public
     * @return string type of translation item
     *
     * @since  1.0.0
     */
    public function getTranslationFileType($file)
    {
        $type = false;
        // line shorter
        $separator = addslashes(DIRECTORY_SEPARATOR);
        $translationDir = $separator.'translations'.$separator;
        $regexModule = '#'.$separator.'modules'.$separator.'.*'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')\.php#';


        if (preg_match($regexModule, $file)) {
            $type = 'module';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'admin\.php#', $file)) {
            $type = 'back office';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'errors\.php#', $file)) {
            $type = 'error message';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'fields\.php#', $file)) {
            $type = 'field';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'pdf\.php#', $file)) {
            $type = 'pdf';
        } elseif (preg_match('#'.$separator.'themes'.$separator.'(default|prestashop)'.$separator.'lang'.$separator.'('.implode('|', $this->installedLanguagesIso).')\.php#', $file)) {
            $type = 'front office';
        }

        return $type;
    }

    /**
     * merge the translations of $orig into $dest, according to the $type of translation file
     *
     * @param string $orig file from upgrade package
     * @param string $dest filepath of destination
     * @param string $type type of translation file (module, bo, fo, field, pdf, error)
     *
     * @access public
     * @return bool
     *
     * @since  1.0.0
     */
    public function mergeTranslationFile($orig, $dest, $type)
    {
        switch ($type) {
            case 'front office':
                $varName = '_LANG';
                break;
            case 'back office':
                $varName = '_LANGADM';
                break;
            case 'error message':
                $varName = '_ERRORS';
                break;
            case 'field':
                $varName = '_FIELDS';
                break;
            case 'module':
                $varName = '_MODULE';
                break;
            case 'pdf':
                $varName = '_LANGPDF';
                break;
            case 'mail':
                $varName = '_LANGMAIL';
                break;
            default:
                return false;
        }

        if (!file_exists($orig)) {
            $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] File %s does not exist, merge skipped.'), $orig);

            return true;
        }
        include($orig);
        if (!isset($$varName)) {
            $this->nextQuickInfo[] = sprintf($this->l('[WARNING] %1$s variable missing in file %2$s. Merge skipped.'), $varName, $orig);

            return true;
        }
        $varOrig = $$varName;

        if (!file_exists($dest)) {
            $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] File %s does not exist, merge skipped.'), $dest);

            return false;
        }
        include($dest);
        if (!isset($$varName)) {
            // in that particular case : file exists, but variable missing, we need to delete that file
            // (if not, this invalid file will be copied in /translations during upgradeDb process)
            if ('module' == $type && file_exists($dest)) {
                unlink($dest);
            }
            $this->nextQuickInfo[] = sprintf($this->l('[WARNING] %1$s variable missing in file %2$s. File %2$s deleted and merge skipped.'), $varName, $dest);

            return false;
        }
        $varDest = $$varName;

        $merge = array_merge($varOrig, $varDest);

        if ($fd = fopen($dest, 'w')) {
            fwrite($fd, "<?php\n\nglobal \$".$varName.";\n\$".$varName." = array();\n");
            foreach ($merge as $k => $v) {
                if (get_magic_quotes_gpc()) {
                    $v = stripslashes($v);
                }
                if ('mail' == $type) {
                    fwrite($fd, '$'.$varName.'[\''.$this->db->escape($k).'\'] = \''.$this->db->escape($v).'\';'."\n");
                } else {
                    fwrite($fd, '$'.$varName.'[\''.$this->db->escape($k, true).'\'] = \''.$this->db->escape($v, true).'\';'."\n");
                }
            }
            fwrite($fd, "\n?>");
            fclose($fd);
        } else {
            return false;
        }

        return true;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function getServerFullBaseUrl()
    {
        $s = $_SERVER;
        $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
        $sp = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')).(($ssl) ? 's' : '');
        $port = $s['SERVER_PORT'];
        $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':'.$port;
        $host = isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null;
        $host = isset($host) ? $host : $s['SERVER_NAME'].$port;

        return $protocol.'://'.$host;
    }

    /**
     * Initialize files
     */
    protected function initializeFiles()
    {
        // installedLanguagesIso is used to merge translations files
        $isoIds = Language::getIsoIds(false);
        if (!is_array($this->installedLanguagesIso)) {
            $this->installedLanguagesIso = [];
        }
        foreach ($isoIds as $v) {
            $this->installedLanguagesIso[] = $v['iso_code'];
        }
        $this->installedLanguagesIso = array_unique($this->installedLanguagesIso);

        $rand = dechex(mt_rand(0, min(0xffffffff, mt_getrandmax())));
        $date = date('Ymd-His');
        $version = _TB_VERSION_;
        $this->backupName = "v{$version}_{$date}-{$rand}";
        $this->backupFilesFilename = 'auto-backupfiles_'.$this->backupName.'.zip';
        $this->backupDbFilename = 'auto-backupdb_'.$this->backupName.'.sql';

        $this->keepImages = UpgraderTools::getConfig(UpgraderTools::BACKUP_IMAGES);
        $this->keepMails = UpgraderTools::getConfig(UpgraderTools::KEEP_MAILS);
        $this->manualMode = (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) ? (bool) UpgraderTools::getConfig(UpgraderTools::MANUAL_MODE) : false;
        $this->deactivateCustomModule = false;

        $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);

        // during restoration, do not remove :
        if (!is_array($this->restoreIgnoreAbsoluteFiles)) {
            $this->restoreIgnoreAbsoluteFiles = [];
        }
        $this->restoreIgnoreAbsoluteFiles[] = '/config/settings.inc.php';
        $this->restoreIgnoreAbsoluteFiles[] = '/modules/autoupgrade';
        $this->restoreIgnoreAbsoluteFiles[] = "/$adminDir/autoupgrade";
        $this->restoreIgnoreAbsoluteFiles[] = '.';
        $this->restoreIgnoreAbsoluteFiles[] = '..';

        // during backup, do not save
        if (!is_array($this->backupIgnoreAbsoluteFiles)) {
            $this->backupIgnoreAbsoluteFiles = [];
        }
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty_v2/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty_v2/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/smarty/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/smarty/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/tcpdf';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/cachefs';

        // do not care about the two autoupgrade dir we use;
        if (!is_array($this->backupIgnoreAbsoluteFiles)) {
            $this->backupIgnoreAbsoluteFiles = [];
        }
        $this->backupIgnoreAbsoluteFiles[] = '/modules/autoupgrade';
        $this->backupIgnoreAbsoluteFiles[] = '/modules/psonefivemigrator';
        $this->backupIgnoreAbsoluteFiles[] = '/modules/tbupdater';
        $this->backupIgnoreAbsoluteFiles[] = '/modules/psonesevenmigrator';
        $this->backupIgnoreAbsoluteFiles[] = "/$adminDir/autoupgrade";

        if (!is_array($this->backupIgnoreFiles)) {
            $this->backupIgnoreFiles = [];
        }
        $this->backupIgnoreFiles[] = '.';
        $this->backupIgnoreFiles[] = '..';
        $this->backupIgnoreFiles[] = '.svn';
        $this->backupIgnoreFiles[] = '.git';
        $this->backupIgnoreFiles[] = $this->tools->autoupgradeDir;

        if (!is_array($this->excludeFilesFromUpgrade)) {
            $this->excludeFilesFromUpgrade = [];
        }
        $this->excludeFilesFromUpgrade[] = '.';
        $this->excludeFilesFromUpgrade[] = '..';
        $this->excludeFilesFromUpgrade[] = '.svn';
        $this->excludeFilesFromUpgrade[] = '.git';

        // do not copy install, neither settings.inc.php in case it would be present
        if (!is_array($this->excludeAbsoluteFilesFromUpgrade)) {
            $this->excludeAbsoluteFilesFromUpgrade = [];
        }
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/settings.inc.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/'.$this->tools->autoupgradeDir.'/index_cli.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install-dev';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/modules_list.xml';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/xml/modules_list.xml';

        // this will exclude autoupgrade dir from admin, and autoupgrade from modules
        if (!is_array($this->excludeFilesFromUpgrade)) {
            $this->excludeFilesFromUpgrade = [];
        }
        $this->excludeFilesFromUpgrade[] = $this->tools->autoupgradeDir;

        if ($this->keepImages === '0') {
            $this->backupIgnoreAbsoluteFiles[] = '/img';
            $this->restoreIgnoreAbsoluteFiles[] = '/img';
        } else {
            $this->backupIgnoreAbsoluteFiles[] = '/img/tmp';
            $this->restoreIgnoreAbsoluteFiles[] = '/img/tmp';
        }

        $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/default-bootstrap';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/community-theme-default';
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function cleanTmpFiles()
    {
        $tools = UpgraderTools::getInstance();
        $tmpFiles = [
            UpgraderTools::TO_UPGRADE_QUERIES_LIST,
            UpgraderTools::TO_UPGRADE_FILE_LIST,
            UpgraderTools::TO_UPGRADE_MODULE_LIST,
            UpgraderTools::FILES_DIFF,
            UpgraderTools::TO_BACKUP_FILE_LIST,
            UpgraderTools::TO_BACKUP_DB_LIST,
            UpgraderTools::TO_RESTORE_QUERY_LIST,
            UpgraderTools::TO_REMOVE_FILE_LIST,
            UpgraderTools::FROM_ARCHIVE_FILE_LIST,
            UpgraderTools::MAIL_CUSTOM_LIST,
            UpgraderTools::TRANSLATIONS_CUSTOM_LIST,
        ];
        foreach ($tmpFiles as $tmpFile) {
            if (file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.$tmpFile)) {
                unlink($tools->autoupgradePath.DIRECTORY_SEPARATOR.$tmpFile);
            }
        }
    }

    /**
     * Verify thirty bees token
     *
     * @return bool
     */
    public function verifyToken()
    {
        $request = json_decode(file_get_contents('php://input'), true);
        if (isset($_SERVER['HTTP_X_THIRTYBEES_AUTH'])) {
            $ajaxToken = $_SERVER['HTTP_X_THIRTYBEES_AUTH'];
        } elseif (isset($request['ajaxToken'])) {
            $ajaxToken = $request['ajaxToken'];
        } else {
            return false;
        }

        $blowfish = new Blowfish(_COOKIE_KEY_, _COOKIE_IV_);

        $tokenShouldBe = $blowfish->encrypt('thirtybees1337H4ck0rzz');

        return $tokenShouldBe && $ajaxToken === $tokenShouldBe;
    }

    /**
     * Reset module tables
     *
     * @return bool
     */
    protected function resetTables()
    {
        return $this->runSql('reset');
    }

    /**
     * Run an sql file
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function runSql($filename)
    {
        if (!file_exists(_PS_MODULE_DIR_.'tbupdater'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.$filename.'.sql')) {
            return false;
        } elseif (!$sql = file_get_contents(_PS_MODULE_DIR_.'tbupdater'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.$filename.'.sql')) {
            return false;
        }
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE', 'DB_NAME'], [_DB_PREFIX_, _MYSQL_ENGINE_, _DB_NAME_], $sql);
        $sql = preg_split("/;\s*[\r\n]+/", trim($sql));

        foreach ($sql as $query) {
            try {
                Db::getInstance()->execute(trim($query), false);
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }
}
