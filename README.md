\# 智機網－機車維修智慧管理系統



「智機網」是一套結合機車維修管理、零件庫存、客戶查詢與 AI 智慧功能的 Web 系統。



本系統主要提供機車行使用，可管理保養案件、維修紀錄、零件庫存與車牌資料，並加入多車行資料隔離、AI 自然語言控制、AI 維修建議、AI 庫存補貨分析以及 QR Code 客戶查詢功能。



\---



\## 專題功能



\### 車行管理

\- 保養案件管理

\- 維修紀錄管理

\- 零件庫存管理

\- 零件成本、售價與毛利計算

\- 庫存不足提醒

\- 維修完成通知

\- 車牌登記與紀錄



\### 多車行管理

\- 不同車行使用不同帳號登入

\- 維修、庫存、車牌等資料依車行隔離

\- 每間車行具有專屬車行代碼



\### 客戶查詢

\- 透過車牌查詢維修進度

\- 車行專屬 QR Code

\- 手機掃描 QR Code 快速進入查詢頁面



\### AI 智慧功能

\- AI 自然語言控制

\- AI 車輛維修建議

\- AI 庫存補貨分析

\- 語音辨識與語音回覆



\### Copilot 遠端控制

整合 Microsoft Copilot Studio、REST API 與 ngrok。



例如使用者輸入：



`幫我開啟 SHOP001 的零件庫存`



系統可以自動將該車行的網頁切換至零件庫存頁面。



\---



\## 使用技術



\### 前端

\- HTML

\- CSS

\- JavaScript

\- Bootstrap 5

\- Web Speech API



\### 後端

\- PHP

\- PHP Session

\- MySQLi

\- REST API



\### 資料庫

\- MySQL / MariaDB



\### AI

\- OpenAI API

\- Microsoft Copilot Studio



\### 開發環境

\- XAMPP

\- Apache

\- Visual Studio Code

\- ngrok

\- Git / GitHub



\---



\## 主要資料表



\- `shops`：車行資料

\- `users`：使用者帳號

\- `parts`：零件庫存

\- `repair\_records`：維修紀錄

\- `maintenance\_records`：保養案件

\- `maintenance\_progress`：維修進度

\- `plate\_records`：車牌紀錄

\- `navigation\_commands`：Copilot 遠端導頁指令



\---



\## 系統架構



使用者透過瀏覽器操作系統，由 PHP 負責後端資料處理及權限管理，並使用 MySQL / MariaDB 儲存各項資料。



AI 功能透過 OpenAI API 進行自然語言理解、維修建議與庫存分析。



Microsoft Copilot Studio 則透過 REST API 與 ngrok 連接本機系統，實現外部自然語言控制。



\---



\## 執行方式



\### 1. 啟動 XAMPP



啟動：



\- Apache

\- MySQL



\### 2. 開啟系統



瀏覽器輸入：



`http://localhost/repair-system/`



\### 3. 使用手機或 Copilot 遠端功能



啟動 ngrok：



`ngrok.exe http 80`



取得公開網址後，例如：



`https://xxxx.ngrok-free.dev`



公開系統網址為：



`https://xxxx.ngrok-free.dev/repair-system`



\---



\## API Key



本系統使用環境變數保存 API Key。



需要設定：



\- `OPENAI\_API\_KEY`

\- `REPAIR\_REMOTE\_API\_KEY`



請勿將 API Key、Token、密碼或其他敏感資訊直接上傳至 GitHub。



\---



\## 專題特色



\- 多車行共用系統與資料隔離

\- AI 自然語言系統操作

\- AI 車輛維修建議

\- AI 庫存補貨分析

\- 手機 QR Code 維修進度查詢

\- Microsoft Copilot 遠端控制系統頁面

\- 維修、保養、庫存與客戶資料整合



\---



\## 專題名稱



\*\*智機網－機車維修智慧管理系統\*\*



以數位化、AI 智慧分析與自然語言操作為核心，提升機車行維修管理效率與客戶查詢便利性。

