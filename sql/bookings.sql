-- 已安裝的系統請匯入這份，為工作系統加上 eClass 訂房頁。
-- 不會刪除工作記錄或帳戶。可重複匯入。

USE it_ops;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(40) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_bookings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_key VARCHAR(191) NOT NULL,
  room VARCHAR(120) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  booker VARCHAR(80) NULL,
  purpose VARCHAR(255) NULL,
  status_label VARCHAR(40) NULL,
  remarks TEXT NULL,
  synced_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_room_bookings_source (source_key),
  KEY idx_room_bookings_start (starts_at),
  KEY idx_room_bookings_room (room)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_syncs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  synced_by INT UNSIGNED NULL,
  row_count INT UNSIGNED NOT NULL,
  removed_count INT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_booking_sync_user FOREIGN KEY (synced_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO room_bookings
  (source_key, room, starts_at, ends_at, booker, purpose, status_label, remarks, synced_at)
VALUES
  ('sample:hall-20261023-0900', 'Hall', '2026-10-23 09:00:00', '2026-10-23 10:30:00', 'LPK', '畢業典禮綵排', '已批核', '示範資料，同步 eClass 後會被同一天的正式記錄取代', '2026-10-23 08:00:00'),
  ('sample:rm109-20261023-1345', 'RM109', '2026-10-23 13:45:00', '2026-10-23 16:00:00', 'Dragon', '看管 Maker Lab', '已批核', NULL, '2026-10-23 08:00:00'),
  ('sample:rm203-20261023-1000', 'RM203', '2026-10-23 10:00:00', '2026-10-23 11:00:00', 'CKLI', 'Apple TV 檢查', '待批核', NULL, '2026-10-23 08:00:00'),
  ('sample:hall-20261024-1400', 'Hall', '2026-10-24 14:00:00', '2026-10-24 16:00:00', 'Flora', '家長日佈置', '已批核', NULL, '2026-10-23 08:00:00'),
  ('sample:lib-20261024-0830', 'LIB', '2026-10-24 08:30:00', '2026-10-24 12:00:00', 'KEN', '閱讀課', '已批核', NULL, '2026-10-23 08:00:00');
