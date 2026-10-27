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

$shop_id = (int)$_SESSION["shop_id"];
$shop_name = $_SESSION["shop_name"] ?? "未設定車行";

/*
    建立安全權杖
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

$message = "";

/*
    GET：從庫存頁進入修改
    POST：送出修改表單
*/
$id = isset($_POST["id"])
    ? (int)$_POST["id"]
    : (
        isset($_GET["id"])
        ? (int)$_GET["id"]
        : 0
    );

if ($id <= 0) {
    header("Location: stock.php");
    exit();
}

/*
    只允許讀取目前車行自己的零件
*/
$select_sql = "
    SELECT
        id,
        name,
        type,
        stock,
        cost,
        price
    FROM parts
    WHERE id = ?
      AND shop_id = ?
";

$select_stmt =
    $conn->prepare($select_sql);

if (!$select_stmt) {
    die(
        "資料讀取失敗：" .
        $conn->error
    );
}

$select_stmt->bind_param(
    "ii",
    $id,
    $shop_id
);

$select_stmt->execute();

$result =
    $select_stmt->get_result();

if ($result->num_rows <= 0) {

    $select_stmt->close();

    header(
        "Location: stock.php?status=not_found"
    );
    exit();
}

$row = $result->fetch_assoc();

$select_stmt->close();

/*
    原本資料
*/
$original_name = $row["name"];
$original_type = $row["type"];

/*
    表單預設值
*/
$name = $row["name"];
$type = $row["type"];
$stock = (int)$row["stock"];
$cost = (float)$row["cost"];
$price = (float)$row["price"];

/*
    零件名稱下拉選項
*/
$name_options = [
    "機油",
    "齒輪油",
    "煞車皮",
    "輪胎",
    "電瓶",
    "火星塞",
    "空氣濾清器",
    "傳動皮帶",
    "普利珠",
    "離合器",
    "大燈燈泡",
    "方向燈"
];

if (
    !in_array(
        $original_name,
        $name_options,
        true
    )
) {
    $name_options[] =
        $original_name;
}

/*
    零件類型下拉選項
*/
$type_options = [
    "保養",
    "引擎",
    "煞車",
    "輪胎",
    "電系",
    "傳動",
    "外觀",
    "其他"
];

if (
    !in_array(
        $original_type,
        $type_options,
        true
    )
) {
    $type_options[] =
        $original_type;
}

/*
    按下儲存修改
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $csrf_token =
        $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $csrf_token
        )
    ) {
        $message =
            "操作驗證失敗，請重新整理頁面";
    }

    $name = isset($_POST["name"])
        ? trim($_POST["name"])
        : "";

    $type = isset($_POST["type"])
        ? trim($_POST["type"])
        : "";

    $stock = isset($_POST["stock"])
        ? (int)$_POST["stock"]
        : -1;

    $cost = isset($_POST["cost"])
        ? (float)$_POST["cost"]
        : -1;

    $price = isset($_POST["price"])
        ? (float)$_POST["price"]
        : -1;

    if ($message === "") {

        if ($name === "") {

            $message =
                "請選擇零件名稱";

        } elseif (
            !in_array(
                $name,
                $name_options,
                true
            )
        ) {

            $message =
                "零件名稱不正確";

        } elseif ($type === "") {

            $message =
                "請選擇零件類型";

        } elseif (
            !in_array(
                $type,
                $type_options,
                true
            )
        ) {

            $message =
                "零件類型不正確";

        } elseif ($stock < 0) {

            $message =
                "庫存數量不能小於 0";

        } elseif ($cost < 0) {

            $message =
                "成本價不能小於 0";

        } elseif ($price < 0) {

            $message =
                "售價不能小於 0";

        } elseif ($price < $cost) {

            $message =
                "售價不能低於成本價";
        }
    }

    /*
        重複檢查只檢查目前車行
    */
    if ($message === "") {

        $duplicate_sql = "
            SELECT id
            FROM parts
            WHERE shop_id = ?
              AND name = ?
              AND type = ?
              AND id <> ?
            LIMIT 1
        ";

        $duplicate_stmt =
            $conn->prepare(
                $duplicate_sql
            );

        if (!$duplicate_stmt) {

            $message =
                "無法檢查重複資料";

        } else {

            $duplicate_stmt->bind_param(
                "issi",
                $shop_id,
                $name,
                $type,
                $id
            );

            $duplicate_stmt->execute();

            $duplicate_result =
                $duplicate_stmt->get_result();

            if (
                $duplicate_result->num_rows > 0
            ) {
                $message =
                    "目前車行已經有相同名稱與類型的零件";
            }

            $duplicate_stmt->close();
        }
    }

    /*
        更新時同時限制 shop_id，
        避免修改到別家車行資料
    */
    if ($message === "") {

        $update_sql = "
            UPDATE parts
            SET
                name = ?,
                type = ?,
                stock = ?,
                cost = ?,
                price = ?
            WHERE id = ?
              AND shop_id = ?
        ";

        $update_stmt =
            $conn->prepare($update_sql);

        if (!$update_stmt) {

            $message =
                "無法建立修改指令：" .
                $conn->error;

        } else {

            $update_stmt->bind_param(
                "ssiddii",
                $name,
                $type,
                $stock,
                $cost,
                $price,
                $id,
                $shop_id
            );

            if ($update_stmt->execute()) {

                $affected =
                    $update_stmt->affected_rows;

                $update_stmt->close();

                /*
                    affected_rows = 0 也可能只是資料沒有變動，
                    因此仍視為正常儲存。
                */
                header(
                    "Location: stock.php?status=updated"
                );
                exit();

            } else {

                $message =
                    "修改失敗：" .
                    $update_stmt->error;

                $update_stmt->close();
            }
        }
    }
}

/*
    計算目前顯示數值
*/
$unit_profit =
    $price - $cost;

$stock_total_cost =
    $stock * $cost;

$stock_total_price =
    $stock * $price;

$stock_total_profit =
    $stock * $unit_profit;
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>修改零件</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
            font-family: "Microsoft JhengHei", sans-serif;
        }

        .edit-card {
            max-width: 760px;
            margin: 0 auto;
            border: none;
            border-radius: 18px;
        }

        .page-title {
            font-weight: bold;
            color: #1f2937;
        }

        .part-id-box {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 12px 15px;
            color: #475569;
        }

        .preview-box {
            border-radius: 14px;
            padding: 18px;
            height: 100%;
        }

        .preview-title {
            font-size: 14px;
            margin-bottom: 6px;
            color: #64748b;
        }

        .preview-value {
            font-size: 22px;
            font-weight: bold;
        }

        .profit-positive {
            color: #198754;
        }

        .profit-negative {
            color: #dc3545;
        }

        .form-label {
            font-weight: bold;
        }

        .help-text {
            color: #64748b;
            font-size: 14px;
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-warning shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1 text-dark fw-bold">
            修改零件
        </span>

        <a
            href="stock.php"
            class="btn btn-dark btn-sm"
        >
            回庫存
        </a>

    </div>

</nav>

<div class="container py-5">

    <div class="card edit-card shadow-sm">

        <div class="card-body p-4 p-md-5">

            <h3 class="page-title mb-3">
                編輯零件資料
            </h3>

            <div class="alert alert-primary py-2">
                目前車行：
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $shop_name,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </strong>
            </div>

            <div class="part-id-box mb-4">

                零件編號：

                <strong>
                    #<?php echo $id; ?>
                </strong>

            </div>

            <?php if ($message !== "") { ?>

                <div class="alert alert-danger">

                    <?php
                    echo htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php } ?>

            <form method="post">

                <input
                    type="hidden"
                    name="id"
                    value="<?php echo $id; ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                        echo htmlspecialchars(
                            $_SESSION["csrf_token"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                >

                <!-- 零件名稱 -->
                <div class="mb-3">

                    <label class="form-label">
                        零件名稱
                    </label>

                    <select
                        name="name"
                        class="form-select"
                        required
                    >

                        <option value="">
                            請選擇零件名稱
                        </option>

                        <?php foreach ($name_options as $option) { ?>

                            <option
                                value="<?php
                                    echo htmlspecialchars(
                                        $option,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                <?php
                                if ($name === $option) {
                                    echo "selected";
                                }
                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $option,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>

                <!-- 零件類型 -->
                <div class="mb-3">

                    <label class="form-label">
                        零件類型
                    </label>

                    <select
                        name="type"
                        class="form-select"
                        required
                    >

                        <option value="">
                            請選擇零件類型
                        </option>

                        <?php foreach ($type_options as $option) { ?>

                            <option
                                value="<?php
                                    echo htmlspecialchars(
                                        $option,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                <?php
                                if ($type === $option) {
                                    echo "selected";
                                }
                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $option,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>

                <!-- 庫存數量 -->
                <div class="mb-3">

                    <label class="form-label">
                        庫存數量
                    </label>

                    <input
                        type="number"
                        name="stock"
                        id="stock"
                        class="form-control"
                        min="0"
                        step="1"
                        value="<?php echo $stock; ?>"
                        required
                    >

                    <div class="help-text mt-1">
                        庫存不得輸入負數。
                    </div>

                </div>

                <!-- 成本價 -->
                <div class="mb-3">

                    <label class="form-label">
                        成本價
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            NT$
                        </span>

                        <input
                            type="number"
                            name="cost"
                            id="cost"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?php
                                echo htmlspecialchars(
                                    number_format(
                                        $cost,
                                        2,
                                        ".",
                                        ""
                                    )
                                );
                            ?>"
                            required
                        >

                    </div>

                </div>

                <!-- 售價 -->
                <div class="mb-4">

                    <label class="form-label">
                        售價
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            NT$
                        </span>

                        <input
                            type="number"
                            name="price"
                            id="price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?php
                                echo htmlspecialchars(
                                    number_format(
                                        $price,
                                        2,
                                        ".",
                                        ""
                                    )
                                );
                            ?>"
                            required
                        >

                    </div>

                    <div class="help-text mt-1">
                        售價必須大於或等於成本價。
                    </div>

                </div>

                <!-- 金額預覽 -->
                <h5 class="fw-bold mb-3">
                    📊 金額預覽
                </h5>

                <div class="row g-3 mb-4">

                    <div class="col-md-6">

                        <div class="preview-box bg-light">

                            <div class="preview-title">
                                單件毛利
                            </div>

                            <div
                                id="unitProfit"
                                class="preview-value <?php
                                    echo $unit_profit >= 0
                                        ? "profit-positive"
                                        : "profit-negative";
                                ?>"
                            >

                                NT$
                                <?php
                                echo number_format(
                                    $unit_profit,
                                    0
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-6">

                        <div class="preview-box bg-light">

                            <div class="preview-title">
                                庫存總成本
                            </div>

                            <div
                                id="stockTotalCost"
                                class="preview-value"
                            >

                                NT$
                                <?php
                                echo number_format(
                                    $stock_total_cost,
                                    0
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-6">

                        <div class="preview-box bg-light">

                            <div class="preview-title">
                                庫存預估售價
                            </div>

                            <div
                                id="stockTotalPrice"
                                class="preview-value"
                            >

                                NT$
                                <?php
                                echo number_format(
                                    $stock_total_price,
                                    0
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-6">

                        <div class="preview-box bg-light">

                            <div class="preview-title">
                                庫存預估毛利
                            </div>

                            <div
                                id="stockTotalProfit"
                                class="preview-value <?php
                                    echo $stock_total_profit >= 0
                                        ? "profit-positive"
                                        : "profit-negative";
                                ?>"
                            >

                                NT$
                                <?php
                                echo number_format(
                                    $stock_total_profit,
                                    0
                                );
                                ?>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="row g-2">

                    <div class="col-md-6">

                        <button
                            type="submit"
                            name="submit"
                            class="btn btn-warning w-100 fw-bold"
                        >
                            儲存修改
                        </button>

                    </div>

                    <div class="col-md-6">

                        <a
                            href="stock.php"
                            class="btn btn-secondary w-100"
                        >
                            取消修改
                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

function formatMoney(value) {

    return "NT$ " +
        Math.round(value).toLocaleString("zh-TW");
}

function updatePreview() {

    const stock =
        parseFloat(
            document.getElementById("stock").value
        ) || 0;

    const cost =
        parseFloat(
            document.getElementById("cost").value
        ) || 0;

    const price =
        parseFloat(
            document.getElementById("price").value
        ) || 0;

    const unitProfit = price - cost;
    const stockTotalCost = stock * cost;
    const stockTotalPrice = stock * price;
    const stockTotalProfit =
        stock * unitProfit;

    const unitProfitElement =
        document.getElementById("unitProfit");

    const stockTotalProfitElement =
        document.getElementById(
            "stockTotalProfit"
        );

    unitProfitElement.textContent =
        formatMoney(unitProfit);

    document
        .getElementById("stockTotalCost")
        .textContent =
        formatMoney(stockTotalCost);

    document
        .getElementById("stockTotalPrice")
        .textContent =
        formatMoney(stockTotalPrice);

    stockTotalProfitElement.textContent =
        formatMoney(stockTotalProfit);

    unitProfitElement.classList.remove(
        "profit-positive",
        "profit-negative"
    );

    stockTotalProfitElement.classList.remove(
        "profit-positive",
        "profit-negative"
    );

    unitProfitElement.classList.add(
        unitProfit >= 0
            ? "profit-positive"
            : "profit-negative"
    );

    stockTotalProfitElement.classList.add(
        stockTotalProfit >= 0
            ? "profit-positive"
            : "profit-negative"
    );
}

document
    .getElementById("stock")
    .addEventListener("input", updatePreview);

document
    .getElementById("cost")
    .addEventListener("input", updatePreview);

document
    .getElementById("price")
    .addEventListener("input", updatePreview);

</script>

</body>

</html>