<?php

namespace TbUpdaterModule;

/**
 * Class FileActions
 *
 * @package TbUpdaterModule
 */
class FileActions
{
    const PRIMARY = 'id_file_actions';
    const TABLE = 'tbupdater_file_actions';
    const CHUNK_SIZE = 100;

    /**
     * Get files for backup
     *
     * @param int $limit
     *
     * @return array
     */
    public static function getFileActions($limit)
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
     * Add file actions
     *
     * @param array $fileActions
     *
     * @return bool
     */
    public static function addFileActions(array $fileActions)
    {
        if (empty($fileActions)) {
            return true;
        }

        $success = true;
        foreach (array_chunk($fileActions, static::CHUNK_SIZE) as $chunk) {
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
    public static function removeFileActions(array $range)
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
