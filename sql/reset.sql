DROP TABLE IF EXISTS `PREFIX_tbupdater_files_for_backup`;
DROP TABLE IF EXISTS `PREFIX_tbupdater_file_actions`;

CREATE TABLE IF NOT EXISTS `PREFIX_tbupdater_files_for_backup` (
  `id_files_for_backup` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `file`                TEXT,
  PRIMARY KEY (`id_files_for_backup`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_tbupdater_file_actions` (
  `id_file_actions` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`          VARCHAR(64) NOT NULL,
  `path`            TEXT        NOT NULL,
  `md5`             CHAR(32),
  PRIMARY KEY (`id_file_actions`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
