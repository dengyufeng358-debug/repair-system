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
    建立刪除操作使用的安全權杖
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

/*
    刪除零件
    只能刪除目前登入車行自己的零件
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_part"])
) {
    $id = isset($_POST["id"])
        ? (int)$_POST["id"]
        : 0;

    $csrf_token =
        $_POST["csrf_token"] ?? "";

    if (
        $id <= 0 ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $csrf_token
        )
    ) {
        header(
            "Location: stock.php?status=invalid"
        );
        exit();
    }

    $delete_sql = "
        DELETE FROM parts
        WHERE id = ?
          AND shop_id = ?
    ";

    $delete_stmt =
        $conn->prepare($delete_sql);

    if ($delete_stmt) {

        $delete_stmt->bind_param(
            "ii",
            $id,
            $shop_id
        );

        if ($delete_stmt->execute()) {

            $affected =
                $delete_stmt->affected_rows;

            $delete_stmt->close();

            if ($affected > 0) {
                header(
                    "Location: stock.php?status=deleted"
                );
            } else {
                header(
                    "Location: stock.php?status=not_found"
                );
            }

            exit();

        } else {

            $delete_stmt->close();

            header(
                "Location: stock.php?status=delete_failed"
            );
            exit();
        }

    } else {

        header(
            "Location: stock.php?status=delete_failed"
        );
        exit();
    }
}

/*
    顯示操作結果
*/
$status = isset($_GET["status"])
    ? trim($_GET["status"])
    : "";

/*
    讀取目前車行的零件庫存
*/
$sql = "
    SELECT
        id,
        name,
        type,
        stock,
        cost,
        price
    FROM parts
    WHERE shop_id = ?
    ORDER BY
        name ASC,
        type ASC,
        id ASC
";

$list_stmt = $conn->prepare($sql);
$result = null;

if ($list_stmt) {

    $list_stmt->bind_param(
        "i",
        $shop_id
    );

    $list_stmt->execute();

    $result =
        $list_stmt->get_result();
}

/*
    計算目前車行的庫存統計
*/
$summary_sql = "
    SELECT
        COUNT(*) AS part_count,

        COALESCE(
            SUM(stock),
            0
        ) AS total_quantity,

        COALESCE(
            SUM(stock * cost),
            0
        ) AS total_cost,

        COALESCE(
            SUM(stock * price),
            0
        ) AS total_price,

        COALESCE(
            SUM(stock * (price - cost)),
            0
        ) AS total_profit

    FROM parts
    WHERE shop_id = ?
";

$summary_stmt =
    $conn->prepare($summary_sql);

$part_count = 0;
$total_quantity = 0;
$all_total_cost = 0;
$all_total_price = 0;
$all_total_profit = 0;

if ($summary_stmt) {

    $summary_stmt->bind_param(
        "i",
        $shop_id
    );

    $summary_stmt->execute();

    $summary_result =
        $summary_stmt->get_result();

    if (
        $summary_result &&
        $summary_result->num_rows > 0
    ) {
        $summary =
            $summary_result->fetch_assoc();

        $part_count =
            (int)$summary["part_count"];

        $total_quantity =
            (int)$summary["total_quantity"];

        $all_total_cost =
            (float)$summary["total_cost"];

        $all_total_price =
            (float)$summary["total_price"];

        $all_total_profit =
            (float)$summary["total_profit"];
    }

    $summary_stmt->close();
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

    <title>庫存管理</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
            font-family: "Microsoft JhengHei", sans-serif;
            color: #1f2937;
        }

        /*
            頁面標題
        */

        .page-navbar {
            background: #1769e0;
        }

        .page-title-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-weight: bold;
            margin: 0;
        }

        /*
            簡單統計資訊
        */

        .summary-strip {
            background: white;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 22px;
        }

        .summary-item {
            padding: 17px 20px;
            border-right: 1px solid #e5e7eb;
            height: 100%;
        }

        .summary-item:last-child {
            border-right: none;
        }

        .summary-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 20px;
            font-weight: bold;
            white-space: nowrap;
        }

        /*
            表格外框
        */

        .table-card {
            border: 0;
            border-radius: 15px;
            overflow: hidden;
        }

        .table-card .card-body {
            padding: 15px;
        }

        .stock-table {
            margin-bottom: 0;
            font-size: 15px;
        }

        .stock-table th,
        .stock-table td {
            vertical-align: middle;
            padding: 12px 10px;
        }

        .stock-table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .stock-table tbody tr:hover {
            background: #f1f5f9;
        }

        /*
            第一層分組表頭
        */

        .group-header th {
            color: #1f2937;
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #94a3b8;
            padding-top: 11px;
            padding-bottom: 11px;
        }

        .group-basic-header {
            background: #e5e7eb !important;
        }

        .group-unit-header {
            background: #bfdbfe !important;
        }

        .group-stock-header {
            background: #ddd6fe !important;
        }

        .group-action-header {
            background: #fde68a !important;
        }

        /*
            第二層欄位表頭
        */

        .column-header th {
            text-align: center;
            white-space: nowrap;
            font-size: 14px;
            color: #334155;
        }

        .basic-column {
            background: #f1f5f9 !important;
        }

        .unit-column {
            background: #eff6ff !important;
        }

        .stock-column {
            background: #f5f3ff !important;
        }

        /*
            分組分隔線
        */

        .group-start-unit {
            border-left: 3px solid #60a5fa !important;
        }

        .group-start-stock {
            border-left: 3px solid #a78bfa !important;
        }

        .group-start-action {
            border-left: 3px solid #fbbf24 !important;
        }

        /*
            資料欄位
        */

        .id-column {
            text-align: center;
            width: 55px;
            color: #64748b;
        }

        .part-name {
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .part-type {
            text-align: center;
            white-space: nowrap;
        }

        .type-badge {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .stock-column-value {
            text-align: center;
            white-space: nowrap;
        }

        .money {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        /*
            金額文字顏色
        */

        .cost-text {
            color: #475569;
            font-weight: 600;
        }

        .price-text {
            color: #1d4ed8;
            font-weight: 600;
        }

        .profit-positive {
            color: #15803d;
            font-weight: bold;
        }

        .profit-negative {
            color: #dc2626;
            font-weight: bold;
        }

        /*
            讓資料區也有非常淡的分組感
        */

        .unit-value {
            background: rgba(239, 246, 255, 0.45);
        }

        .stock-value {
            background: rgba(245, 243, 255, 0.45);
        }

        /*
            庫存警示
        */

        .stock-normal {
            font-weight: 600;
            color: #334155;
        }

        .stock-warning {
            display: inline-block;
            background: #fbbf24;
            color: #422006;
            border-radius: 7px;
            padding: 5px 9px;
            font-weight: bold;
        }

        .stock-danger {
            display: inline-block;
            background: #dc3545;
            color: white;
            border-radius: 7px;
            padding: 5px 9px;
            font-weight: bold;
        }

        /*
            操作按鈕
        */

        .action-column {
            text-align: center;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
        }

        .delete-form {
            margin: 0;
        }

        .action-buttons .btn {
            min-width: 52px;
        }

        /*
            表格最下方合計
        */

        .total-row td {
            background: #f8fafc !important;
            border-top: 2px solid #94a3b8;
            font-weight: bold;
        }

        /*
            手機及小螢幕
        */

        @media (max-width: 992px) {

            .summary-item {
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
            }

            .summary-item:last-child {
                border-bottom: none;
            }
        }

        @media (max-width: 768px) {

            .page-title-area {
                display: block;
            }

            .page-title-area .btn {
                width: 100%;
                margin-top: 12px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<!-- 上方標題 -->
<nav class="navbar navbar-dark page-navbar shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1">
            庫存管理
        </span>

        <a
            href="dashboard.php"
            class="btn btn-light btn-sm"
        >
            回首頁
        </a>

    </div>

</nav>

<div class="container py-4">

    <!-- 操作結果提示 -->
    <?php if ($status === "updated") { ?>

        <div class="alert alert-success">
            零件資料修改成功。
        </div>

    <?php } elseif ($status === "deleted") { ?>

        <div class="alert alert-success">
            零件資料刪除成功。
        </div>

    <?php } elseif ($status === "delete_failed") { ?>

        <div class="alert alert-danger">
            零件刪除失敗，可能仍有維修紀錄使用這個零件。
        </div>

    <?php } elseif ($status === "invalid") { ?>

        <div class="alert alert-danger">
            操作要求不正確，請重新操作。
        </div>

    <?php } elseif ($status === "not_found") { ?>

        <div class="alert alert-warning">
            找不到這筆零件，或這筆零件不屬於目前登入的車行。
        </div>

    <?php } ?>

    <!-- 頁面標題與新增按鈕 -->
    <div class="page-title-area mb-3">

        <div>
            <h3 class="page-title">
                零件庫存列表
            </h3>

            <div class="text-muted mt-1">
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
        </div>

        <a
            href="add_part.php"
            class="btn btn-success"
        >
            ＋ 新增零件
        </a>

    </div>

    <!-- 簡易庫存統計 -->
    <div class="card summary-strip">

        <div class="row g-0">

            <div class="col-lg">

                <div class="summary-item">

                    <div class="summary-label">
                        零件種類
                    </div>

                    <div class="summary-value">
                        <?php echo number_format($part_count); ?> 種
                    </div>

                </div>

            </div>

            <div class="col-lg">

                <div class="summary-item">

                    <div class="summary-label">
                        庫存總數量
                    </div>

                    <div class="summary-value">
                        <?php echo number_format($total_quantity); ?> 件
                    </div>

                </div>

            </div>

            <div class="col-lg">

                <div class="summary-item">

                    <div class="summary-label">
                        庫存總成本
                    </div>

                    <div class="summary-value cost-text">
                        NT$ <?php echo number_format($all_total_cost, 0); ?>
                    </div>

                </div>

            </div>

            <div class="col-lg">

                <div class="summary-item">

                    <div class="summary-label">
                        庫存預估售價
                    </div>

                    <div class="summary-value price-text">
                        NT$ <?php echo number_format($all_total_price, 0); ?>
                    </div>

                </div>

            </div>

            <div class="col-lg">

                <div class="summary-item">

                    <div class="summary-label">
                        庫存預估毛利
                    </div>

                    <div class="summary-value <?php
                        echo $all_total_profit >= 0
                            ? "profit-positive"
                            : "profit-negative";
                    ?>">
                        NT$ <?php echo number_format($all_total_profit, 0); ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- 庫存表格 -->
    <div class="card table-card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered stock-table">

                    <thead>

                        <!-- 第一層：欄位分組 -->
                        <tr class="group-header">

                            <th
                                colspan="4"
                                class="group-basic-header"
                            >
                                基本資料
                            </th>

                            <th
                                colspan="3"
                                class="group-unit-header group-start-unit"
                            >
                                單件價格
                            </th>

                            <th
                                colspan="3"
                                class="group-stock-header group-start-stock"
                            >
                                庫存金額
                            </th>

                            <th
                                rowspan="2"
                                class="group-action-header group-start-action"
                            >
                                操作
                            </th>

                        </tr>

                        <!-- 第二層：實際欄位名稱 -->
                        <tr class="column-header">

                            <th class="basic-column">
                                ID
                            </th>

                            <th class="basic-column">
                                名稱
                            </th>

                            <th class="basic-column">
                                類型
                            </th>

                            <th class="basic-column">
                                庫存
                            </th>

                            <th class="unit-column group-start-unit">
                                成本價
                            </th>

                            <th class="unit-column">
                                售價
                            </th>

                            <th class="unit-column">
                                單件毛利
                            </th>

                            <th class="stock-column group-start-stock">
                                庫存總成本
                            </th>

                            <th class="stock-column">
                                庫存預估售價
                            </th>

                            <th class="stock-column">
                                庫存預估毛利
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (
                        $result &&
                        $result->num_rows > 0
                    ) { ?>

                        <?php while (
                            $row = $result->fetch_assoc()
                        ) { ?>

                            <?php

                            $stock =
                                (int)$row["stock"];

                            $cost =
                                (float)$row["cost"];

                            $price =
                                (float)$row["price"];

                            /*
                                計算金額
                            */
                            $unit_profit =
                                $price - $cost;

                            $total_cost =
                                $cost * $stock;

                            $total_price =
                                $price * $stock;

                            $total_profit =
                                $unit_profit * $stock;

                            ?>

                            <tr>

                                <!-- ID -->
                                <td class="id-column">

                                    <?php
                                    echo (int)$row["id"];
                                    ?>

                                </td>

                                <!-- 名稱 -->
                                <td class="part-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $row["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <!-- 類型 -->
                                <td class="part-type">

                                    <span class="type-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["type"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- 庫存 -->
                                <td class="stock-column-value">

                                    <?php if ($stock < 5) { ?>

                                        <span class="stock-danger">

                                            庫存不足：
                                            <?php echo $stock; ?>

                                        </span>

                                    <?php } elseif ($stock < 10) { ?>

                                        <span class="stock-warning">

                                            即將不足：
                                            <?php echo $stock; ?>

                                        </span>

                                    <?php } else { ?>

                                        <span class="stock-normal">

                                            <?php echo $stock; ?>

                                        </span>

                                    <?php } ?>

                                </td>

                                <!-- 成本價 -->
                                <td class="money unit-value group-start-unit cost-text">

                                    NT$
                                    <?php
                                    echo number_format(
                                        $cost,
                                        0
                                    );
                                    ?>

                                </td>

                                <!-- 售價 -->
                                <td class="money unit-value price-text">

                                    NT$
                                    <?php
                                    echo number_format(
                                        $price,
                                        0
                                    );
                                    ?>

                                </td>

                                <!-- 單件毛利 -->
                                <td class="money unit-value">

                                    <span class="<?php
                                        echo $unit_profit >= 0
                                            ? "profit-positive"
                                            : "profit-negative";
                                    ?>">

                                        NT$
                                        <?php
                                        echo number_format(
                                            $unit_profit,
                                            0
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- 庫存總成本 -->
                                <td class="money stock-value group-start-stock cost-text">

                                    NT$
                                    <?php
                                    echo number_format(
                                        $total_cost,
                                        0
                                    );
                                    ?>

                                </td>

                                <!-- 庫存預估售價 -->
                                <td class="money stock-value price-text">

                                    NT$
                                    <?php
                                    echo number_format(
                                        $total_price,
                                        0
                                    );
                                    ?>

                                </td>

                                <!-- 庫存預估毛利 -->
                                <td class="money stock-value">

                                    <span class="<?php
                                        echo $total_profit >= 0
                                            ? "profit-positive"
                                            : "profit-negative";
                                    ?>">

                                        NT$
                                        <?php
                                        echo number_format(
                                            $total_profit,
                                            0
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- 操作 -->
                                <td class="action-column group-start-action">

                                    <div class="action-buttons">

                                        <a
                                            href="edit_part.php?id=<?php
                                                echo (int)$row["id"];
                                            ?>"
                                            class="btn btn-warning btn-sm"
                                        >
                                            修改
                                        </a>

                                        <form
                                            method="post"
                                            action="stock.php"
                                            class="delete-form"
                                            onsubmit="return confirmDelete();"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php
                                                    echo (int)$row["id"];
                                                ?>"
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

                                            <button
                                                type="submit"
                                                name="delete_part"
                                                class="btn btn-danger btn-sm"
                                            >
                                                刪除
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td
                                colspan="11"
                                class="text-center text-muted py-5"
                            >
                                目前沒有零件庫存資料
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                    <!-- 全部庫存合計 -->
                    <tfoot>

                        <tr class="total-row">

                            <td colspan="3" class="text-end">
                                全部庫存合計
                            </td>

                            <td class="text-center">
                                <?php echo number_format($total_quantity); ?>
                            </td>

                            <td
                                colspan="3"
                                class="text-center text-muted group-start-unit"
                            >
                                單件價格依各零件計算
                            </td>

                            <td class="money group-start-stock cost-text">

                                NT$
                                <?php
                                echo number_format(
                                    $all_total_cost,
                                    0
                                );
                                ?>

                            </td>

                            <td class="money price-text">

                                NT$
                                <?php
                                echo number_format(
                                    $all_total_price,
                                    0
                                );
                                ?>

                            </td>

                            <td class="money">

                                <span class="<?php
                                    echo $all_total_profit >= 0
                                        ? "profit-positive"
                                        : "profit-negative";
                                ?>">

                                    NT$
                                    <?php
                                    echo number_format(
                                        $all_total_profit,
                                        0
                                    );
                                    ?>

                                </span>

                            </td>

                            <td class="group-start-action"></td>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

    <!-- 說明 -->
    <div class="alert alert-light border mt-3 mb-0">

        <strong>欄位說明：</strong>

        藍色區域為單件價格，

        紫色區域為目前全部庫存的預估金額，

        綠色文字表示毛利為正數。

    </div>

</div>

<script>

function confirmDelete() {

    return confirm(
        "確定要刪除這個零件嗎？\n\n" +
        "刪除後將無法恢復。"
    );
}

</script>

</body>

</html>