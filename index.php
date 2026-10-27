<?php
include "db.php";

/*
    =========================================================
    首頁：多車行搜尋 + 專屬網址 / QR Code
    =========================================================

    一般進入：
        搜尋車行名稱或車行代碼
        → 選擇車行
        → 輸入車牌查詢

    專屬網址：
        index.php?shop=SHOP002
        → 自動選好第二車行
*/

$selected_shop = null;

$shop_code = isset($_GET["shop"])
    ? strtoupper(trim($_GET["shop"]))
    : "";

if ($shop_code !== "") {

    $sql = "
        SELECT
            id,
            shop_name,
            shop_code
        FROM shops
        WHERE shop_code = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("s", $shop_code);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $selected_shop = $result->fetch_assoc();
        }

        $stmt->close();
    }
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
            min-height:100vh;
            background:linear-gradient(135deg,#eef2f7,#f8fafc);
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .main-wrap {
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:40px 15px;
        }

        .title {
            font-size:42px;
            font-weight:800;
            margin-bottom:15px;
            color:#1f2937;
        }

        .subtitle {
            text-align:center;
            margin-bottom:45px;
        }

        .multi-shop-badge {
            display:inline-block;
            background:#e8f1ff;
            color:#0d6efd;
            border-radius:999px;
            padding:7px 14px;
            font-weight:bold;
            font-size:14px;
        }

        .login-card {
            min-height:430px;
            border:0;
            border-radius:22px;
            box-shadow:0 12px 30px rgba(0,0,0,.12);
            padding:40px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            background:white;
        }

        .card-icon {
            font-size:52px;
            margin-bottom:15px;
        }

        .card-title {
            font-size:32px;
            font-weight:700;
            color:#111827;
        }

        .card-text {
            color:#6b7280;
            font-size:16px;
            margin-top:15px;
        }

        .btn {
            min-height:50px;
            font-size:17px;
            border-radius:10px;
            font-weight:bold;
        }

        .form-control {
            min-height:50px;
            border-radius:10px;
            font-size:16px;
        }

        .shop-search-wrap {
            position:relative;
        }

        .shop-results {
            position:absolute;
            left:0;
            right:0;
            top:54px;
            z-index:1000;
            display:none;
            max-height:240px;
            overflow-y:auto;
            background:white;
            border:1px solid #dee2e6;
            border-radius:10px;
            box-shadow:0 10px 25px rgba(0,0,0,.15);
        }

        .shop-result-item {
            padding:12px 14px;
            cursor:pointer;
            text-align:left;
            border-bottom:1px solid #eee;
        }

        .shop-result-item:hover {
            background:#eef6ff;
        }

        .shop-result-name {
            font-weight:bold;
            color:#111827;
        }

        .shop-result-code {
            color:#6b7280;
            font-size:13px;
        }

        .selected-shop-box {
            background:#eef8f0;
            border:1px solid #b8dfc1;
            border-radius:12px;
            padding:14px;
            margin-bottom:14px;
            text-align:left;
        }

        .selected-shop-name {
            font-weight:800;
            color:#146c43;
            font-size:18px;
        }

        .selected-shop-code {
            color:#6c757d;
            font-size:14px;
        }

        .search-help {
            text-align:left;
            margin-top:6px;
            font-size:13px;
            color:#6b7280;
        }

        @media(max-width:768px) {
            .title {
                font-size:30px;
            }

            .login-card {
                min-height:auto;
                padding:28px;
            }

            .card-title {
                font-size:27px;
            }
        }
    </style>
</head>

<body>

<div class="main-wrap">

    <div class="container">

        <h1 class="text-center title">
            🏍️ 機車維修智慧管理系統
        </h1>

        <div class="subtitle">
            <span class="multi-shop-badge">
                多車行資料隔離管理平台
            </span>
        </div>

        <div class="row justify-content-center g-4">

            <!-- 車行登入 -->
            <div class="col-lg-5 col-md-6">

                <div class="login-card text-center">

                    <div>
                        <div class="card-icon">👨‍🔧</div>

                        <div class="card-title">
                            車行登入
                        </div>

                        <p class="card-text">
                            各合作車行使用自己的帳號登入，
                            管理各自的維修、庫存、保養與車牌資料。
                        </p>
                    </div>

                    <a
                        href="login.php"
                        class="btn btn-primary w-100 mt-4"
                    >
                        車行帳號登入
                    </a>

                </div>

            </div>

            <!-- 客戶查詢 -->
            <div class="col-lg-5 col-md-6">

                <div class="login-card text-center">

                    <div>
                        <div class="card-icon">🔍</div>

                        <div class="card-title">
                            客戶查詢
                        </div>

                        <p class="card-text">
                            搜尋維修車行後，
                            再輸入車牌查詢維修進度。
                        </p>
                    </div>

                    <form
                        action="view_progress.php"
                        method="get"
                        id="customerQueryForm"
                        class="w-100 mt-3"
                    >

                        <input
                            type="hidden"
                            name="shop_id"
                            id="shop_id"
                            value="<?php
                                echo $selected_shop
                                    ? (int)$selected_shop["id"]
                                    : "";
                            ?>"
                        >

                        <?php if ($selected_shop) { ?>

                            <div
                                class="selected-shop-box"
                                id="selectedShopBox"
                            >

                                <div class="small text-muted mb-1">
                                    目前維修車行
                                </div>

                                <div
                                    class="selected-shop-name"
                                    id="selectedShopName"
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $selected_shop["shop_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </div>

                                <div
                                    class="selected-shop-code"
                                    id="selectedShopCode"
                                >
                                    車行代碼：
                                    <?php
                                    echo htmlspecialchars(
                                        $selected_shop["shop_code"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary mt-2"
                                    onclick="changeShop()"
                                >
                                    更換車行
                                </button>

                            </div>

                        <?php } else { ?>

                            <div
                                class="shop-search-wrap mb-3"
                                id="shopSearchWrap"
                            >

                                <input
                                    type="text"
                                    id="shop_search"
                                    class="form-control"
                                    placeholder="搜尋車行名稱或代碼，例如 SHOP002"
                                    autocomplete="off"
                                >

                                <div
                                    id="shopResults"
                                    class="shop-results"
                                ></div>

                                <div class="search-help">
                                    輸入車行名稱或 SHOP001、SHOP002 等代碼即可搜尋。
                                </div>

                            </div>

                            <div
                                class="selected-shop-box d-none"
                                id="selectedShopBox"
                            >

                                <div class="small text-muted mb-1">
                                    已選擇車行
                                </div>

                                <div
                                    class="selected-shop-name"
                                    id="selectedShopName"
                                ></div>

                                <div
                                    class="selected-shop-code"
                                    id="selectedShopCode"
                                ></div>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary mt-2"
                                    onclick="changeShop()"
                                >
                                    更換車行
                                </button>

                            </div>

                        <?php } ?>

                        <div class="mb-3">
                            <input
                                type="text"
                                name="plate"
                                id="plate"
                                class="form-control"
                                placeholder="請輸入車牌號碼"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >
                            查詢維修進度
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
const shopSearch =
    document.getElementById("shop_search");

const shopResults =
    document.getElementById("shopResults");

const shopId =
    document.getElementById("shop_id");

const selectedShopBox =
    document.getElementById("selectedShopBox");

const selectedShopName =
    document.getElementById("selectedShopName");

const selectedShopCode =
    document.getElementById("selectedShopCode");

const customerQueryForm =
    document.getElementById("customerQueryForm");

let searchTimer = null;

if (shopSearch) {

    shopSearch.addEventListener(
        "input",
        function() {

            const keyword =
                this.value.trim();

            shopId.value = "";

            clearTimeout(searchTimer);

            if (keyword.length < 1) {
                hideResults();
                return;
            }

            searchTimer = setTimeout(
                function() {
                    searchShops(keyword);
                },
                250
            );
        }
    );
}

function searchShops(keyword) {

    fetch(
        "search_shops.php?q=" +
        encodeURIComponent(keyword)
    )
    .then(function(response) {

        if (!response.ok) {
            throw new Error("搜尋失敗");
        }

        return response.json();
    })
    .then(function(data) {
        showResults(data);
    })
    .catch(function(error) {

        console.error(error);

        if (shopResults) {
            shopResults.innerHTML =
                '<div class="shop-result-item text-danger">搜尋車行失敗</div>';

            shopResults.style.display =
                "block";
        }
    });
}

function showResults(items) {

    if (!shopResults) {
        return;
    }

    shopResults.innerHTML = "";

    if (
        !Array.isArray(items) ||
        items.length === 0
    ) {
        shopResults.innerHTML =
            '<div class="shop-result-item text-muted">找不到符合的車行</div>';

        shopResults.style.display =
            "block";

        return;
    }

    items.forEach(
        function(shop) {

            const item =
                document.createElement("div");

            item.className =
                "shop-result-item";

            const name =
                document.createElement("div");

            name.className =
                "shop-result-name";

            name.textContent =
                shop.shop_name;

            const code =
                document.createElement("div");

            code.className =
                "shop-result-code";

            code.textContent =
                "車行代碼：" +
                shop.shop_code;

            item.appendChild(name);
            item.appendChild(code);

            item.addEventListener(
                "click",
                function() {
                    selectShop(shop);
                }
            );

            shopResults.appendChild(item);
        }
    );

    shopResults.style.display =
        "block";
}

function selectShop(shop) {

    shopId.value =
        shop.id;

    selectedShopName.textContent =
        shop.shop_name;

    selectedShopCode.textContent =
        "車行代碼：" +
        shop.shop_code;

    const searchWrap =
        document.getElementById("shopSearchWrap");

    if (searchWrap) {
        searchWrap.classList.add("d-none");
    }

    selectedShopBox.classList.remove("d-none");

    hideResults();

    document
        .getElementById("plate")
        .focus();
}

function changeShop() {

    if (!shopSearch) {
        window.location.href = "index.php";
        return;
    }

    shopId.value = "";
    shopSearch.value = "";

    selectedShopBox.classList.add("d-none");

    const searchWrap =
        document.getElementById("shopSearchWrap");

    if (searchWrap) {
        searchWrap.classList.remove("d-none");
    }

    shopSearch.focus();
}

function hideResults() {
    if (shopResults) {
        shopResults.style.display = "none";
    }
}

document.addEventListener(
    "click",
    function(event) {

        const wrap =
            document.getElementById("shopSearchWrap");

        if (
            wrap &&
            !wrap.contains(event.target)
        ) {
            hideResults();
        }
    }
);

customerQueryForm.addEventListener(
    "submit",
    function(event) {

        if (!shopId.value) {

            event.preventDefault();

            alert(
                "請先搜尋並選擇維修車行"
            );

            if (shopSearch) {
                shopSearch.focus();
            }
        }
    }
);
</script>

</body>
</html>
