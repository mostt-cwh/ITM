# I.T. OPS 工作記錄系統

IT 同事用來記錄每項工作的 PHP + MySQL 系統。未登入也可以查看全部記錄；技術員登入後可以新增和更新；管理員可以刪除個案和重設密碼。

版面可以依完成狀態、負責人、類型篩選，也可以搜尋、排序和選擇顯示欄位。

## 環境

- PHP 7.4 或以上（需啟用 PDO MySQL 與 mbstring）
- MySQL 5.7+ 或 MariaDB 10.3+
- 網站根目錄指向這個專案資料夾

## 安裝

1. 把整個資料夾放到網站目錄，例如 XAMPP 的 `htdocs/it-ops`。
2. 啟動 MySQL。
3. 匯入資料庫結構與帳戶：

```bash
mysql -u root -p --default-character-set=utf8mb4 < sql/schema.sql
mysql -u root -p --default-character-set=utf8mb4 < sql/sample_data.sql
```

也可以在 phpMyAdmin 先匯入 `sql/schema.sql`，再匯入 `sql/sample_data.sql`。

4. 如果資料庫帳戶不是 `root`、密碼不是空白，修改 `config.php` 的 `DB_USER` 與 `DB_PASS`。
5. 開啟 `http://localhost/it-ops/`。

`sql/schema.sql` 會建立資料庫 `it_ops`、資料表、狀態、工作類型和四個帳戶。再次匯入會重建資料表，原有資料會被清除。

`sql/sample_data.sql` 會清除現有個案並寫入示範記錄，不會重設已更改的密碼，也不會刪除訂房記錄。

已經安裝、不想重建工作記錄的系統，只需再匯入訂房表：

```bash
mysql -u root -p --default-character-set=utf8mb4 < sql/bookings.sql
```

`sql/bookings.sql` 可重複匯入，不會刪除工作記錄、帳戶或已同步的訂房。全新安裝用 `sql/schema.sql` 已經包含訂房表，不必再匯入這份。

## 預設帳戶

首次登入後請到右上角名字更改密碼。管理員也可以在「帳戶」頁重設其他人的密碼。

| 帳戶 | 密碼 | 身份 |
| --- | --- | --- |
| admin | admin123 | 管理員 |
| marco | marco123 | 技術員 Marco |
| dragon | dragon123 | 技術員 Dragon |
| stanley | stanley123 | 技術員 Stanley |

## 誰可以做什麼

- 任何人，包括未登入：查看全部個案、篩選、搜尋、打開單筆內容。
- Marco、Dragon、Stanley 和 admin：新增工作、更新狀態、負責人、描述和備註。
- admin：另外可以刪除個案，以及重設任何帳戶的密碼。

狀態沿用編號：等待處理 (1)、處理中 (2)、待跟進 (3)、已完成 (4)。

## eClass 訂房

場地預訂仍在 eClass。eClass 沒有可讀取訂房的 API，這個系統不會代你登入，也不會儲存 eClass 密碼。

「訂房」頁給所有人查看已同步的記錄。技術員或管理員登入後，在「同步 eClass」用下面其中一種方式寫入：

1. 把同步頁的書籤拖到瀏覽器書籤列。登入 eClass 並打開訂房列表後按書籤，頁面上的表格會送到這個系統。
2. 在 eClass 複製表格（連標題列），貼到同步頁。或把列表另存 CSV 再上傳。不支援 xlsx。

標題列要能認出日期、場地、時間。申請人、用途、狀態、備註、編號有就一併保留。有預訂編號時，之後同步會更新同一筆；沒有編號時，以場地、時間、申請人和用途辨認。

勾選「以今次匯入取代相同日期的記錄」後，那些日期裡沒有出現在今次資料的舊預訂會移除，其他日期保持不變。取消的預訂因此不會一直留在清單。

同步碼放在書籤裡。管理員可以在同步頁更新同步碼，舊書籤會失效，已同步的訂房不會被刪掉。

## 檔案

- `index.php`：公開記錄清單
- `bookings.php`：公開訂房清單
- `booking_sync.php`：登入後貼上、上傳或產生 eClass 書籤
- `booking_push.php`：書籤用來送出表格的入口，要有同步碼
- `case.php`：單筆內容；登入後可更新
- `report.php`：新增工作
- `login.php` / `logout.php`：登入與登出
- `password.php`：更改自己的密碼
- `accounts.php`：管理員查看帳戶與重設密碼
- `config.php`：資料庫連線
- `includes/`：共用程式
- `assets/`：版面與互動
- `sql/schema.sql`：建立資料庫、資料表與帳戶
- `sql/sample_data.sql`：示範個案
- `sql/bookings.sql`：已安裝系統補上訂房表與示範訂房

Apache 會用 `.htaccess` 擋住 `config.php`、`includes/` 和 `sql/`。如果使用 Nginx，請不要把這三個路徑公開。
