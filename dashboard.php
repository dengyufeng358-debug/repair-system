<?php
session_start();
include "db.php";

if (
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["shop_id"])
) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION["username"];
$role = $_SESSION["role"] ?? "manager";
$shop_id = (int)$_SESSION["shop_id"];
$shop_name = $_SESSION["shop_name"] ?? "未設定車行";

/*
    =========================
    維修紀錄統計
    只統計目前登入車行
    =========================
*/

$repair_summary_sql = "
    SELECT
        COUNT(*) AS total_repairs,

        COALESCE(
            SUM(
                CASE
                    WHEN repair_date = CURDATE() THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS today_repairs,

        COALESCE(
            SUM(quantity * unit_cost),
            0
        ) AS total_cost,

        COALESCE(
            SUM(quantity * unit_price),
            0
        ) AS total_income,

        COALESCE(
            SUM(
                quantity * (unit_price - unit_cost)
            ),
            0
        ) AS total_profit

    FROM repair_records
    WHERE shop_id = ?
";

$repair_stmt = $conn->prepare($repair_summary_sql);

$total_repairs = 0;
$today_repairs = 0;
$total_cost = 0;
$total_income = 0;
$total_profit = 0;

if ($repair_stmt) {

    $repair_stmt->bind_param("i", $shop_id);
    $repair_stmt->execute();

    $repair_summary_result =
        $repair_stmt->get_result();

    if (
        $repair_summary_result &&
        $repair_summary_result->num_rows > 0
    ) {
        $repair_summary =
            $repair_summary_result->fetch_assoc();

        $total_repairs =
            (int)$repair_summary["total_repairs"];

        $today_repairs =
            (int)$repair_summary["today_repairs"];

        $total_cost =
            (float)$repair_summary["total_cost"];

        $total_income =
            (float)$repair_summary["total_income"];

        $total_profit =
            (float)$repair_summary["total_profit"];
    }

    $repair_stmt->close();
}

/*
    =========================
    零件庫存統計
    只統計目前登入車行
    =========================
*/

$stock_summary_sql = "
    SELECT
        COALESCE(
            SUM(stock * cost),
            0
        ) AS stock_total_cost,

        COALESCE(
            SUM(stock * price),
            0
        ) AS stock_total_income,

        COALESCE(
            SUM(
                stock * (price - cost)
            ),
            0
        ) AS stock_total_profit

    FROM parts
    WHERE shop_id = ?
";

$stock_stmt = $conn->prepare($stock_summary_sql);

$stock_total_cost = 0;
$stock_total_income = 0;
$stock_total_profit = 0;

if ($stock_stmt) {

    $stock_stmt->bind_param("i", $shop_id);
    $stock_stmt->execute();

    $stock_summary_result =
        $stock_stmt->get_result();

    if (
        $stock_summary_result &&
        $stock_summary_result->num_rows > 0
    ) {
        $stock_summary =
            $stock_summary_result->fetch_assoc();

        $stock_total_cost =
            (float)$stock_summary["stock_total_cost"];

        $stock_total_income =
            (float)$stock_summary["stock_total_income"];

        $stock_total_profit =
            (float)$stock_summary["stock_total_profit"];
    }

    $stock_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>機車維修智慧管理系統</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
            font-family: "Microsoft JhengHei", sans-serif;
        }

        /* =========================
           上方標題
        ========================= */

        .topbar {
            background: #1f3f6d;
            color: white;
            padding: 22px 30px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
        }

        .topbar h2 {
            margin: 0;
            font-weight: bold;
        }

        .topbar-text {
            margin-top: 7px;
            margin-bottom: 0;
            color: #dbeafe;
            font-size: 15px;
        }

        /* =========================
           漢堡按鈕
        ========================= */

        .menu-btn {
            position: fixed;
            top: 18px;
            left: 18px;
            width: 55px;
            height: 55px;
            border: none;
            border-radius: 12px;
            background: #111827;
            color: white;
            font-size: 28px;
            z-index: 2001;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.25);
            cursor: pointer;
        }

        .menu-btn:hover {
            background: #273449;
        }

        /* =========================
           遮罩
        ========================= */

        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 1999;
        }

        .overlay.active {
            display: block;
        }

        /* =========================
           側邊欄
        ========================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: -320px;
            width: 320px;
            height: 100vh;
            background: #111827;
            color: white;
            z-index: 2000;
            transition: 0.35s;
            overflow-y: auto;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            background: #1e293b;
            padding: 35px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-header h3 {
            font-weight: bold;
            margin-bottom: 15px;
        }

        .sidebar-header p {
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .sidebar a {
            display: block;
            padding: 20px 28px;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 19px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #243447;
            color: white;
            padding-left: 35px;
        }

        .logout-btn {
            background: #dc2626 !important;
            color: white !important;
            text-align: center;
            font-weight: bold;
        }

        .logout-btn:hover {
            background: #b91c1c !important;
        }

        /* =========================
           區塊標題
        ========================= */

        .section-title {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 20px;
        }

        /* =========================
           經營統計卡片
        ========================= */

        .stat-card {
            border: none;
            border-radius: 18px;
            color: white;
            min-height: 160px;
            height: 100%;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.13);
            transition: 0.25s;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .card-body {
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .stat-title {
            font-size: 17px;
            opacity: 0.9;
        }

        .stat-number {
            font-size: 31px;
            font-weight: bold;
            margin-top: 7px;
            line-height: 1.25;
            word-break: break-word;
        }

        .stat-description {
            margin-top: 7px;
            margin-bottom: 0;
            font-size: 14px;
            line-height: 1.5;
            opacity: 0.85;
        }

        .stat-today {
            background: linear-gradient(
                135deg,
                #0d6efd,
                #084298
            );
        }

        .stat-cost {
            background: linear-gradient(
                135deg,
                #dc3545,
                #842029
            );
        }

        .stat-income {
            background: linear-gradient(
                135deg,
                #0dcaf0,
                #087990
            );
        }

        .stat-profit {
            background: linear-gradient(
                135deg,
                #198754,
                #0f5132
            );
        }

        .stat-stock-cost {
            background: linear-gradient(
                135deg,
                #fd7e14,
                #984c0c
            );
        }

        .stat-stock-profit {
            background: linear-gradient(
                135deg,
                #6f42c1,
                #432874
            );
        }

        /* =========================
           功能入口卡片
        ========================= */

        .card-box {
            border: none;
            border-radius: 18px;
            color: white;
            min-height: 190px;
            height: 100%;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.13);
            transition: 0.25s;
            overflow: hidden;
        }

        .card-box:hover {
            transform: translateY(-5px);
        }

        .card-box .card-body {
            height: 100%;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
        }

        .feature-icon {
            font-size: 34px;
            margin-bottom: 8px;
        }

        .card-box h3 {
            color: white;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .card-box p {
            color: rgba(255, 255, 255, 0.88);
            min-height: 48px;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .feature-btn {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.17);
            color: white;
            font-weight: bold;
            border-radius: 9px;
            padding: 9px 12px;
            text-decoration: none;
            transition: 0.2s;
        }

        .feature-btn:hover {
            background: white;
            color: #1f2937;
        }

        .feature-maintenance { background: linear-gradient(135deg, #0d6efd, #084298); }
        .feature-stock { background: linear-gradient(135deg, #198754, #0f5132); }
        .feature-repair { background: linear-gradient(135deg, #334155, #111827); }
        .feature-notify { background: linear-gradient(135deg, #fd7e14, #984c0c); }
        .feature-customer { background: linear-gradient(135deg, #dc3545, #842029); }
        .feature-voice { background: linear-gradient(135deg, #6f42c1, #432874); }
        .feature-plate { background: linear-gradient(135deg, #64748b, #334155); }
        .feature-add-part { background: linear-gradient(135deg, #0dcaf0, #087990); }
        .feature-add-repair { background: linear-gradient(135deg, #2563eb, #1e3a8a); }

        /* =========================
           AI 智慧功能卡片
        ========================= */

        .feature-ai-voice {
            background:
                linear-gradient(
                    135deg,
                    #7c3aed,
                    #4c1d95
                );
        }

        .feature-ai-vehicle {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1e40af
                );
        }

        .feature-ai-inventory {
            background:
                linear-gradient(
                    135deg,
                    #059669,
                    #065f46
                );
        }

        .feature-qr {
            background:
                linear-gradient(
                    135deg,
                    #475569,
                    #1e293b
                );
        }

        .ai-intro {
            background:
                linear-gradient(
                    135deg,
                    #f5f3ff,
                    #eff6ff
                );

            border:
                1px solid #ddd6fe;

            border-radius: 16px;

            padding: 18px 20px;

            margin-bottom: 20px;

            color: #334155;

            box-shadow:
                0 4px 14px
                rgba(76,29,149,.06);
        }

        .ai-intro strong {
            color: #5b21b6;
        }

        /* =========================
           登入資訊
        ========================= */

        .login-info {
            background: white;
            border-radius: 15px;
            padding: 16px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .login-info-name {
            font-weight: bold;
            color: #1f2937;
        }

        /* =========================
           平板版
        ========================= */

        @media (max-width: 991px) {

            .stat-card {
                min-height: 170px;
            }

            .stat-number {
                font-size: 27px;
            }
        }

        /* =========================
           手機版
        ========================= */

        @media (max-width: 768px) {

            .topbar {
                padding: 25px 55px 25px 85px;
                text-align: left;
            }

            .topbar h2 {
                font-size: 21px;
                line-height: 1.35;
            }

            .topbar-text {
                font-size: 13px;
                line-height: 1.4;
            }

            .menu-btn {
                top: 18px;
                left: 15px;
                width: 54px;
                height: 54px;
                font-size: 25px;
            }

            .sidebar {
                width: 280px;
            }

            .container {
                padding-left: 14px;
                padding-right: 14px;
            }

            .container.py-5 {
                padding-top: 30px !important;
                padding-bottom: 35px !important;
            }

            .login-info {
                display: block;
                text-align: center;
                padding: 18px 12px;
                margin-bottom: 24px;
            }

            .login-info-name {
                font-size: 16px;
            }

            .login-info .badge {
                display: inline-block;
                margin-top: 10px;
                font-size: 14px !important;
            }

            .section-title {
                font-size: 24px;
                margin-bottom: 15px;
            }

            /*
                手機一排兩張統計卡片
            */

            .stat-card {
                min-height: 205px;
                border-radius: 16px;
            }

            .stat-card .card-body {
                padding: 17px 13px;
                justify-content: flex-start;
            }

            .stat-icon {
                font-size: 28px;
                margin-bottom: 10px;
            }

            .stat-title {
                font-size: 14px;
                line-height: 1.4;
                min-height: 40px;
            }

            .stat-number {
                font-size: 21px;
                margin-top: 6px;
                line-height: 1.3;
                white-space: normal;
                word-break: break-word;
            }

            .stat-description {
                font-size: 12px;
                line-height: 1.45;
                margin-top: auto;
                padding-top: 10px;
            }

            /*
                縮小統計卡片左右間距
            */

            .statistics-row {
                --bs-gutter-x: 12px;
                --bs-gutter-y: 12px;
            }

            /* 功能入口手機一排兩張 */
            .functions-row {
                --bs-gutter-x: 12px;
                --bs-gutter-y: 12px;
            }

            .card-box {
                min-height: 205px;
                border-radius: 16px;
            }

            .card-box .card-body {
                padding: 16px 11px;
            }

            .feature-icon {
                font-size: 27px;
                margin-bottom: 6px;
            }

            .card-box h3 {
                font-size: 17px;
                line-height: 1.35;
                margin-bottom: 8px;
            }

            .card-box p {
                min-height: 64px;
                font-size: 12px;
                line-height: 1.45;
                margin-bottom: 12px;
            }

            .feature-btn {
                font-size: 13px;
                padding: 8px 6px;
            }
        }

        /* =========================
           特別窄的手機
        ========================= */

        @media (max-width: 390px) {

            .topbar {
                padding-left: 78px;
                padding-right: 12px;
            }

            .topbar h2 {
                font-size: 18px;
            }

            .topbar-text {
                font-size: 12px;
            }

            .stat-card {
                min-height: 195px;
            }

            .stat-card .card-body {
                padding: 15px 10px;
            }

            .stat-icon {
                font-size: 25px;
            }

            .stat-title {
                font-size: 13px;
                min-height: 37px;
            }

            .stat-number {
                font-size: 18px;
            }

            .stat-description {
                font-size: 11px;
            }

            .card-box { min-height: 195px; }
            .card-box .card-body { padding: 14px 8px; }
            .feature-icon { font-size: 24px; }
            .card-box h3 { font-size: 15px; }
            .card-box p { min-height: 62px; font-size: 11px; }
            .feature-btn { font-size: 12px; padding: 7px 4px; }
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<!-- 上方標題 -->
<div class="topbar">

    <h2>
        🏍️ 機車維修智慧管理系統
    </h2>

    <p class="topbar-text">
        AI 智慧維修、庫存與經營資料統一管理
    </p>

</div>

<!-- 主內容 -->
<div class="container py-5">

    <!-- 登入資訊 -->
    <div class="login-info">

        <div class="login-info-name">

            🏪 <?php
            echo htmlspecialchars(
                $shop_name,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

            <span class="text-muted ms-2">
                ｜登入帳號：
                <?php
                echo htmlspecialchars(
                    $username,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </span>

        </div>

        <span class="badge bg-primary fs-6">

            登入身分：
            <?php echo htmlspecialchars($role); ?>

        </span>

    </div>

    <!-- 經營統計 -->
    <h3 class="section-title">
        📊 經營統計
    </h3>

    <div class="row g-4 mb-5 statistics-row">

        <!-- 今日維修筆數 -->
        <div class="col-xl-4 col-md-6 col-6">

            <div class="card stat-card stat-today">

                <div class="card-body">

                    <div class="stat-icon">
                        🔧
                    </div>

                    <div class="stat-title">
                        今日維修筆數
                    </div>

                    <div class="stat-number">

                        <?php
                        echo number_format(
                            $today_repairs
                        );
                        ?>
                        筆

                    </div>

                    <p class="stat-description">

                        全部維修紀錄共
                        <?php
                        echo number_format(
                            $total_repairs
                        );
                        ?>
                        筆

                    </p>

                </div>

            </div>

        </div>

        <!-- 維修總成本 -->
        <div class="col-xl-4 col-md-6 col-6">

            <div class="card stat-card stat-cost">

                <div class="card-body">

                    <div class="stat-icon">
                        💸
                    </div>

                    <div class="stat-title">
                        維修總成本
                    </div>

                    <div class="stat-number">

                        NT$
                        <?php
                        echo number_format(
                            $total_cost,
                            0
                        );
                        ?>

                    </div>

                    <p class="stat-description">
                        維修紀錄使用零件的成本合計
                    </p>

                </div>

            </div>

        </div>

        <!-- 維修總收入 -->
        <div class="col-xl-4 col-md-6 col-6">

            <div class="card stat-card stat-income">

                <div class="card-body">

                    <div class="stat-icon">
                        💰
                    </div>

                    <div class="stat-title">
                        維修總收入
                    </div>

                    <div class="stat-number">

                        NT$
                        <?php
                        echo number_format(
                            $total_income,
                            0
                        );
                        ?>

                    </div>

                    <p class="stat-description">
                        維修零件售價的收入合計
                    </p>

                </div>

            </div>

        </div>

        <!-- 維修總毛利 -->
        <div class="col-xl-4 col-md-6 col-6">

            <div class="card stat-card stat-profit">

                <div class="card-body">

                    <div class="stat-icon">
                        📈
                    </div>

                    <div class="stat-title">
                        維修總毛利
                    </div>

                    <div class="stat-number">

                        NT$
                        <?php
                        echo number_format(
                            $total_profit,
                            0
                        );
                        ?>

                    </div>

                    <p class="stat-description">
                        維修總收入減去維修總成本
                    </p>

                </div>

            </div>

        </div>

        <!-- 庫存總成本 -->
        <div class="col-xl-4 col-md-6 col-6">

            <div class="card stat-card stat-stock-cost">

                <div class="card-body">

                    <div class="stat-icon">
                        📦
                    </div>

                    <div class="stat-title">
                        目前庫存總成本
                    </div>

                    <div class="stat-number">

                        NT$
                        <?php
                        echo number_format(
                            $stock_total_cost,
                            0
                        );
                        ?>

                    </div>

                    <p class="stat-description">

                        預估庫存售價：
                        NT$
                        <?php
                        echo number_format(
                            $stock_total_income,
                            0
                        );
                        ?>

                    </p>

                </div>

            </div>

        </div>

        <!-- 庫存預估毛利 -->
        <div class="col-xl-4 col-md-6 col-6">

            <div class="card stat-card stat-stock-profit">

                <div class="card-body">

                    <div class="stat-icon">
                        🧮
                    </div>

                    <div class="stat-title">
                        庫存預估毛利
                    </div>

                    <div class="stat-number">

                        NT$
                        <?php
                        echo number_format(
                            $stock_total_profit,
                            0
                        );
                        ?>

                    </div>

                    <p class="stat-description">
                        目前庫存全部售出後的預估毛利
                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- AI 智慧功能 -->
    <h3 class="section-title">
        ✨ AI 智慧應用
    </h3>

    <div class="ai-intro">
        <strong>AI 智慧應用：</strong>
        結合自然語言理解、車輛維修紀錄分析與庫存需求分析，
        協助技師快速操作系統並提供維修、補貨決策參考。
    </div>

    <div class="row g-4 mb-5 functions-row">

        <div class="col-xl-3 col-md-6 col-6">
            <div class="card card-box feature-ai-voice">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">🎤</div>
                        <h3>AI 語音控制</h3>
                        <p>用自然語言新增、查詢、回報與開啟系統頁面</p>
                    </div>
                    <a href="voice_command.php" class="feature-btn">
                        開始 AI 操作
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-6">
            <div class="card card-box feature-ai-vehicle">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">🤖</div>
                        <h3>AI 維修建議</h3>
                        <p>分析車輛歷史保養紀錄並提供技師檢查建議</p>
                    </div>
                    <a href="ai_vehicle_advice.php" class="feature-btn">
                        AI 車輛分析
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-6">
            <div class="card card-box feature-ai-inventory">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">📦</div>
                        <h3>AI 庫存建議</h3>
                        <p>分析近期零件用量、庫存與毛利並提出補貨建議</p>
                    </div>
                    <a href="ai_inventory_advice.php" class="feature-btn">
                        AI 庫存分析
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-6">
            <div class="card card-box feature-qr">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">📱</div>
                        <h3>專屬 QR Code</h3>
                        <p>產生目前車行的客戶查詢網址與專屬 QR Code</p>
                    </div>
                    <a href="shop_qr.php" class="feature-btn">
                        開啟 QR Code
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- 功能入口 -->
    <h3 class="section-title">
        🧭 系統功能
    </h3>

    <div class="row g-4 functions-row">

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-maintenance">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">🛠️</div>
                        <h3>保養案件</h3>
                        <p>新增、修改、刪除及查詢保養案件</p>
                    </div>
                    <a href="maintenance_list.php" class="feature-btn">進入管理</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-stock">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">📦</div>
                        <h3>零件庫存</h3>
                        <p>管理零件庫存、成本、售價與毛利</p>
                    </div>
                    <a href="stock.php" class="feature-btn">進入庫存</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-repair">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">📋</div>
                        <h3>維修紀錄</h3>
                        <p>新增維修紀錄並計算收入與毛利</p>
                    </div>
                    <a href="repair_list.php" class="feature-btn">查看紀錄</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-notify">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">📢</div>
                        <h3>完成通知</h3>
                        <p>更新維修狀態並通知客戶領車</p>
                    </div>
                    <a href="notify_customer.php" class="feature-btn">通知客戶</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-customer">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">🔍</div>
                        <h3>客戶查詢</h3>
                        <p>依照車牌查詢目前維修進度</p>
                    </div>
                    <a href="view_progress.php" class="feature-btn">查看進度</a>
                </div>
            </div>
        </div>


        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-plate">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">🏍️</div>
                        <h3>車牌登記</h3>
                        <p>使用語音快速登記車牌資料</p>
                    </div>
                    <a href="plate_login.php" class="feature-btn">車牌登記</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-add-part">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">➕</div>
                        <h3>新增零件</h3>
                        <p>建立零件成本、售價與庫存資料</p>
                    </div>
                    <a href="add_part.php" class="feature-btn">新增零件</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-6">
            <div class="card card-box feature-add-repair">
                <div class="card-body">
                    <div>
                        <div class="feature-icon">🔧</div>
                        <h3>新增維修</h3>
                        <p>選擇零件並自動扣除目前庫存</p>
                    </div>
                    <a href="add_repair.php" class="feature-btn">新增紀錄</a>
                </div>
            </div>
        </div>

    </div>

</div>



</body>

</html>