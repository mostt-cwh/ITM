-- I.T. OPS 工作記錄系統
-- 建立資料庫、資料表、狀態、類型，以及四個登入帳戶。
-- 匯入一次即可。再次匯入會刪除並重建資料表，原有個案與帳戶會一併清除。
--
-- 預設密碼（請於首次登入後更改）：
--   admin    / admin123    管理員
--   marco    / marco123    技術員
--   dragon   / dragon123   技術員
--   stanley  / stanley123  技術員

CREATE DATABASE IF NOT EXISTS it_ops
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE it_ops;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS booking_syncs;
DROP TABLE IF EXISTS room_bookings;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS work_cases;
DROP TABLE IF EXISTS work_types;
DROP TABLE IF EXISTS statuses;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(40) NOT NULL,
  display_name VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'technician') NOT NULL DEFAULT 'technician',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE statuses (
  id TINYINT UNSIGNED NOT NULL,
  label_en VARCHAR(40) NOT NULL,
  label_zh VARCHAR(40) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_types (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(40) NOT NULL,
  label_zh VARCHAR(40) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_work_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_cases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status_id TINYINT UNSIGNED NOT NULL,
  case_date DATETIME NOT NULL,
  type_id SMALLINT UNSIGNED NOT NULL,
  location VARCHAR(80) NULL,
  report_by VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  handler_id INT UNSIGNED NULL,
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cases_status_date (status_id, case_date),
  KEY idx_cases_handler_date (handler_id, case_date),
  KEY idx_cases_type (type_id),
  KEY idx_cases_date (case_date),
  CONSTRAINT fk_cases_status FOREIGN KEY (status_id) REFERENCES statuses (id),
  CONSTRAINT fk_cases_type FOREIGN KEY (type_id) REFERENCES work_types (id),
  CONSTRAINT fk_cases_handler FOREIGN KEY (handler_id) REFERENCES users (id),
  CONSTRAINT fk_cases_created_by FOREIGN KEY (created_by) REFERENCES users (id),
  CONSTRAINT fk_cases_updated_by FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO statuses (id, label_en, label_zh, sort_order) VALUES
  (1, 'Waiting', '等待處理', 1),
  (2, 'Processing', '處理中', 2),
  (3, 'Follow-up', '待跟進', 3),
  (4, 'Solved', '已完成', 4);

INSERT INTO work_types (id, name, label_zh, sort_order) VALUES
  (1, 'events', '活動支援', 1),
  (2, 'Hardware', '硬件', 2),
  (3, 'helpdesk', '服務台', 3),
  (4, 'Problem', '故障', 4),
  (5, 'Software', '軟件', 5),
  (6, 'Graphic', '圖像', 6),
  (7, 'homepage', '網頁', 7);

INSERT INTO users (id, username, display_name, password_hash, role, active, sort_order) VALUES
  (1, 'admin', 'Admin', '$2y$10$Qk4Z3iPSIAmwfPTiTxFAjeCepq88QGlMxCW9w.keYYL55.wlojqWO', 'admin', 1, 0),
  (2, 'marco', 'Marco', '$2y$10$hiMkPE.QJxibfo4OPSFpGelBgGG/IC9An0aBAxuWEV.0mP1Nyh.KG', 'technician', 1, 1),
  (3, 'dragon', 'Dragon', '$2y$10$u9seROASw4bz7u8lLDk5xOe3LoZYh5k4GW0Vyuf25mAUVtuRsRkDC', 'technician', 1, 2),
  (4, 'stanley', 'Stanley', '$2y$10$/XBTjfhzGN6PSB9LAvsCOuG8NflhTv9W9clnGgZxb.y7ODWR0E94i', 'technician', 1, 3);

CREATE TABLE app_settings (
  setting_key VARCHAR(40) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_bookings (
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

CREATE TABLE booking_syncs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  synced_by INT UNSIGNED NULL,
  row_count INT UNSIGNED NOT NULL,
  removed_count INT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(200) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_booking_sync_user FOREIGN KEY (synced_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO room_bookings
  (source_key, room, starts_at, ends_at, booker, purpose, status_label, remarks, synced_at)
VALUES
  ('sample:hall-20261023-0900', 'Hall', '2026-10-23 09:00:00', '2026-10-23 10:30:00', 'LPK', '畢業典禮綵排', '已批核', '示範資料，同步 eClass 後會被同一天的正式記錄取代', '2026-10-23 08:00:00'),
  ('sample:rm109-20261023-1345', 'RM109', '2026-10-23 13:45:00', '2026-10-23 16:00:00', 'Dragon', '看管 Maker Lab', '已批核', NULL, '2026-10-23 08:00:00'),
  ('sample:rm203-20261023-1000', 'RM203', '2026-10-23 10:00:00', '2026-10-23 11:00:00', 'CKLI', 'Apple TV 檢查', '待批核', NULL, '2026-10-23 08:00:00'),
  ('sample:hall-20261024-1400', 'Hall', '2026-10-24 14:00:00', '2026-10-24 16:00:00', 'Flora', '家長日佈置', '已批核', NULL, '2026-10-23 08:00:00'),
  ('sample:lib-20261024-0830', 'LIB', '2026-10-24 08:30:00', '2026-10-24 12:00:00', 'KEN', '閱讀課', '已批核', NULL, '2026-10-23 08:00:00');
