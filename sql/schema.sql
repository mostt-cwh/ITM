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
