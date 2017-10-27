<?php

namespace TbUpdaterModule;

/**
 * Class Backup
 *
 * @package TbUpdaterModule
 */
class Backup
{
    const PRIMARY = 'id_files_for_backup';
    const TABLE = 'tbupdater_files_for_backup';
    const CHUNK_SIZE = 100;

    /**
     * Get files for backup
     *
     * @param int $limit
     *
     * @return array
     */
    public static function getBackupFiles($limit)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('*')
                ->from(static::TABLE)
                ->limit($limit),
            true,
            false
        );
    }

    /**
     * Add files
     *
     * @param array $files
     *
     * @return bool
     */
    public static function addFiles(array $files)
    {
        foreach ($files as &$file) {
            $file = ['file' => Db::getInstance()->escape($file)];
        }
        unset($file);

        $success = true;
        foreach (array_chunk($files, static::CHUNK_SIZE) as $chunk) {
            $success &= Db::getInstance()->insert(
                static::TABLE,
                $chunk,
                false,
                false
            );
        }

        return $success;
    }

    /**
     * @param int[] $range
     *
     * @return bool
     */
    public static function removeFiles(array $range)
    {
        return Db::getInstance()->delete(
            static::TABLE,
            static::PRIMARY.' IN ('.implode(',', array_map('intval', $range)).')',
            0,
            false
        );
    }

    /**
     * @return int
     */
    public static function count()
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new DbQuery())
                ->select('COUNT(*)')
                ->from(static::TABLE),
            false
        );
    }
}
