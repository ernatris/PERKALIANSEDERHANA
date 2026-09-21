-- Import file ini lewat phpMyAdmin (tab Import) pada database yang sudah Anda buat.
CREATE TABLE IF NOT EXISTS `skor` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`       VARCHAR(40)  NOT NULL,
  `sekolah`    VARCHAR(60)  NOT NULL,
  `permainan`  ENUM('kali','bagi','campur') NOT NULL,
  `tingkat`    ENUM('mudah','sedang','sulit') NOT NULL,
  `skor`       INT UNSIGNED NOT NULL,
  `benar`      SMALLINT UNSIGNED NOT NULL,
  `salah`      SMALLINT UNSIGNED NOT NULL,
  `streak_max` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `token_id`   CHAR(16) NOT NULL,
  `dibuat`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token_id`),
  KEY `idx_permainan_skor` (`permainan`, `skor`),
  KEY `idx_pemain` (`nama`, `sekolah`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
