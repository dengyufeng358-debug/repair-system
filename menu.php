<?php

$current_username =
    $_SESSION["username"] ?? "未登入";

$current_role =
    $_SESSION["role"] ?? "";

$current_shop_name =
    $_SESSION["shop_name"] ?? "未設定車行";

?>

<style>

.menu-top-btn {
    position: fixed;
    top: 18px;
    left: 18px;
    z-index: 2001;

    background: #111827;
    color: white;

    border: none;

    width: 55px;
    height: 55px;

    font-size: 28px;
    border-radius: 12px;
    cursor: pointer;

    box-shadow:
        0 5px 15px
        rgba(0,0,0,.25);
}

.menu-top-btn:hover {
    background: #273449;
}

.side-menu {
    position: fixed;

    top: 0;
    left: -330px;

    width: 330px;
    height: 100vh;

    background: #111827;
    color: white;

    z-index: 2000;

    transition: left .3s ease;

    overflow-y: auto;
}

.side-menu.active {
    left: 0;
}

.side-header {
    background: #1e293b;

    padding: 28px 20px;

    text-align: center;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}

.side-header h3 {
    margin: 0 0 15px 0;
    font-weight: bold;
}

.shop-name-box {
    background:
        rgba(255,255,255,.08);

    border-radius: 10px;

    padding: 12px;

    margin-bottom: 12px;
}

.shop-name-label {
    color: #94a3b8;
    font-size: 13px;
}

.shop-name {
    color: white;

    font-size: 19px;
    font-weight: bold;

    margin-top: 3px;
}

.login-user {
    color: #cbd5e1;

    font-size: 14px;

    margin-bottom: 10px;
}

.side-section-title {
    padding: 11px 24px;

    background: #0f172a;

    color: #94a3b8;

    font-size: 13px;
    font-weight: bold;

    letter-spacing: .8px;

    border-top:
        1px solid
        rgba(255,255,255,.08);

    border-bottom:
        1px solid
        rgba(255,255,255,.05);
}

.side-section-title.ai-title {
    color: #c4b5fd;
    background: #20143a;
}

.side-menu a {
    display: block;

    padding: 16px 26px;

    color: #e2e8f0;

    text-decoration: none;

    font-size: 17px;

    border-bottom:
        1px solid
        rgba(255,255,255,.05);

    transition: .2s;
}

.side-menu a:hover {
    background: #243447;

    color: white;

    padding-left: 33px;
}

.side-menu a.ai-link {
    background:
        linear-gradient(
            90deg,
            rgba(111,66,193,.18),
            rgba(37,99,235,.08)
        );

    border-left: 4px solid #8b5cf6;
}

.side-menu a.ai-link:hover {
    background:
        linear-gradient(
            90deg,
            rgba(111,66,193,.35),
            rgba(37,99,235,.18)
        );
}

.side-logout {
    background: #dc2626 !important;

    color: white !important;

    font-weight: bold;

    text-align: center;
}

.side-logout:hover {
    background: #b91c1c !important;
}

.menu-overlay {
    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(0,0,0,.55);

    z-index: 1999;
}

.menu-overlay.active {
    display: block;
}

@media (max-width: 768px) {

    .side-menu {
        width: 285px;
    }

    .menu-top-btn {
        width: 52px;
        height: 52px;

        font-size: 25px;
    }

    .side-menu a {
        font-size: 16px;

        padding:
            15px 20px;
    }

    .side-section-title {
        padding:
            10px 20px;
    }
}

</style>

<!-- 三條槓 -->
<button
    type="button"
    class="menu-top-btn"
    onclick="openSideMenu()"
>
    ☰
</button>

<!-- 黑色遮罩 -->
<div
    id="menuOverlay"
    class="menu-overlay"
    onclick="closeSideMenu()"
>
</div>

<!-- 左側選單 -->
<div
    id="sideMenu"
    class="side-menu"
>

    <div class="side-header">

        <h3>
            🏍️ 維修管理系統
        </h3>

        <div class="shop-name-box">

            <div class="shop-name-label">
                目前車行
            </div>

            <div class="shop-name">

                <?php
                echo htmlspecialchars(
                    $current_shop_name,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </div>

        </div>

        <div class="login-user">

            登入帳號：

            <?php
            echo htmlspecialchars(
                $current_username,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

        <?php if ($current_role !== "") { ?>

            <span
                class="badge bg-warning text-dark"
            >

                <?php
                echo htmlspecialchars(
                    $current_role,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </span>

        <?php } ?>

    </div>

    <div class="side-section-title">
        一般功能
    </div>

    <a href="dashboard.php">
        🏠 首頁總覽
    </a>

    <a href="maintenance_list.php">
        🛠️ 保養案件管理
    </a>

    <a href="add_maintenance.php">
        ➕ 新增保養案件
    </a>

    <a href="notify_customer.php">
        📢 維修完成通知
    </a>

    <a href="view_progress.php">
        🔍 客戶查詢進度
    </a>

    <a href="stock.php">
        📦 零件庫存管理
    </a>

    <a href="add_part.php">
        ➕ 新增零件
    </a>

    <a href="repair_list.php">
        🧾 維修紀錄
    </a>

    <a href="plate_login.php">
        🏍️ 車牌語音登記
    </a>

    <a href="plate_records.php">
        📋 車牌紀錄
    </a>

    <div class="side-section-title ai-title">
        ✨ AI 智慧功能
    </div>

    <a
        href="voice_command.php"
        class="ai-link"
    >
        🎤 AI 自然語言控制
    </a>

    <a
        href="ai_vehicle_advice.php"
        class="ai-link"
    >
        🤖 AI 車輛維修建議
    </a>

    <a
        href="ai_inventory_advice.php"
        class="ai-link"
    >
        📦 AI 庫存補貨建議
    </a>

    <div class="side-section-title">
        車行服務
    </div>

    <a href="shop_qr.php">
        📱 車行專屬 QR Code
    </a>

    <a
        href="logout.php"
        class="side-logout"
    >
        🚪 登出系統
    </a>

</div>

<script>

function openSideMenu() {

    document
        .getElementById("sideMenu")
        .classList
        .add("active");

    document
        .getElementById("menuOverlay")
        .classList
        .add("active");
}

function closeSideMenu() {

    document
        .getElementById("sideMenu")
        .classList
        .remove("active");

    document
        .getElementById("menuOverlay")
        .classList
        .remove("active");
}

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {
            closeSideMenu();
        }
    }
);

</script>

<script>

/*
    =========================================================
    Copilot / 遠端導頁監聽
    =========================================================
    每 1.5 秒只檢查「目前登入車行」的新導頁命令。
    不會傳送維修、客戶或庫存資料到外部。
*/
(function () {

    let navPollingBusy = false;

    async function initRemoteNavigation() {

        try {

            await fetch(
                "get_current_route.php?init=1",
                {
                    cache: "no-store",
                    credentials: "same-origin"
                }
            );

        } catch (error) {

            console.log(
                "遠端導頁初始化失敗",
                error
            );
        }
    }

    async function checkRemoteNavigation() {

        if (navPollingBusy) {
            return;
        }

        navPollingBusy = true;

        try {

            const response =
                await fetch(
                    "get_current_route.php",
                    {
                        cache: "no-store",
                        credentials: "same-origin"
                    }
                );

            if (!response.ok) {
                return;
            }

            const data =
                await response.json();

            if (
                data.ok &&
                data.action === "NAVIGATE" &&
                data.target
            ) {

                const currentFile =
                    window.location.pathname
                    .split("/")
                    .pop();

                const targetFile =
                    String(
                        data.target
                    )
                    .split("?")[0];

                if (
                    currentFile !== targetFile
                ) {

                    window.location.href =
                        data.target;
                }
            }

        } catch (error) {

            console.log(
                "遠端導頁檢查失敗",
                error
            );

        } finally {

            navPollingBusy = false;
        }
    }

    window.addEventListener(
        "load",
        async function () {

            await initRemoteNavigation();

            setInterval(
                checkRemoteNavigation,
                1500
            );
        }
    );

})();

</script>

