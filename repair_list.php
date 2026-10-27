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
    建立刪除功能使用的安全權杖
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

/*
    日期驗證
*/
function isValidDate($date)
{
    if ($date === "") {
        return true;
    }

    $date_object =
        DateTime::createFromFormat(
            "Y-m-d",
            $date
        );

    return $date_object &&
           $date_object->format("Y-m-d") === $date;
}

/*
    取得日期篩選資料
*/
$start_date =
    isset($_GET["start_date"])
    ? trim($_GET["start_date"])
    : "";

$end_date =
    isset($_GET["end_date"])
    ? trim($_GET["end_date"])
    : "";

$status =
    isset($_GET["status"])
    ? trim($_GET["status"])
    : "";

$date_error = "";

if (!isValidDate($start_date)) {
    $date_error =
        "開始日期格式不正確";
    $start_date = "";
}

if (!isValidDate($end_date)) {
    $date_error =
        "結束日期格式不正確";
    $end_date = "";
}

if (
    $start_date !== "" &&
    $end_date !== "" &&
    $end_date < $start_date
) {
    $date_error =
        "結束日期不能早於開始日期";
}

/*
    =========================
    建立查詢條件
    第一個條件永遠是目前車行 shop_id
    =========================
*/
$where_sql = "
    WHERE repair_records.shop_id = $shop_id
";

$filter_text =
    "全部維修紀錄";

if ($date_error === "") {

    if (
        $start_date !== "" &&
        $end_date !== ""
    ) {

        $safe_start_date =
            $conn->real_escape_string(
                $start_date
            );

        $safe_end_date =
            $conn->real_escape_string(
                $end_date
            );

        $where_sql .= "
            AND repair_records.repair_date
            BETWEEN '$safe_start_date'
            AND '$safe_end_date'
        ";

        $filter_text =
            $start_date .
            " ～ " .
            $end_date;

    } elseif ($start_date !== "") {

        $safe_start_date =
            $conn->real_escape_string(
                $start_date
            );

        $where_sql .= "
            AND repair_records.repair_date
            >= '$safe_start_date'
        ";

        $filter_text =
            $start_date .
            " 之後";

    } elseif ($end_date !== "") {

        $safe_end_date =
            $conn->real_escape_string(
                $end_date
            );

        $where_sql .= "
            AND repair_records.repair_date
            <= '$safe_end_date'
        ";

        $filter_text =
            $end_date .
            " 之前";
    }
}

/*
    =========================
    讀取目前車行的維修紀錄
    =========================
*/
$sql = "
    SELECT
        repair_records.id,
        repair_records.quantity,
        repair_records.unit_cost,
        repair_records.unit_price,
        repair_records.repair_result,
        repair_records.repair_date,
        parts.name AS part_name,
        parts.type AS part_type

    FROM repair_records

    LEFT JOIN parts
        ON repair_records.part_id = parts.id
       AND repair_records.shop_id = parts.shop_id

    $where_sql

    ORDER BY
        repair_records.repair_date DESC,
        repair_records.id DESC
";

$result =
    $conn->query($sql);

/*
    =========================
    計算目前車行、篩選期間統計
    =========================
*/
$total_sql = "
    SELECT
        COUNT(*) AS total_records,

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
                quantity *
                (unit_price - unit_cost)
            ),
            0
        ) AS total_profit

    FROM repair_records

    $where_sql
";

$total_result =
    $conn->query($total_sql);

$total_records = 0;
$total_cost = 0;
$total_income = 0;
$total_profit = 0;

if (
    $total_result &&
    $total_result->num_rows > 0
) {
    $total_row =
        $total_result->fetch_assoc();

    $total_records =
        (int)$total_row["total_records"];

    $total_cost =
        (float)$total_row["total_cost"];

    $total_income =
        (float)$total_row["total_income"];

    $total_profit =
        (float)$total_row["total_profit"];
}

$today = date("Y-m-d");
$month_start = date("Y-m-01");
$month_end = date("Y-m-t");
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>維修紀錄</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f4f4;
            font-family: "Microsoft JhengHei", sans-serif;
        }

        .summary-card {
            border: 0;
            border-radius: 15px;
            color: white;
            min-height: 130px;
            transition: 0.25s;
        }

        .summary-card:hover {
            transform: translateY(-4px);
        }

        .summary-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .summary-title {
            font-size: 17px;
            opacity: 0.9;
        }

        .summary-number {
            font-size: 30px;
            font-weight: bold;
            margin-top: 5px;
        }

        .summary-description {
            font-size: 14px;
            margin-top: 4px;
            margin-bottom: 0;
            opacity: 0.85;
        }

        .cost-card {
            background: #dc3545;
        }

        .income-card {
            background: #0d6efd;
        }

        .profit-card {
            background: #198754;
        }

        .filter-card {
            border: 0;
            border-radius: 15px;
        }

        .filter-title {
            font-weight: bold;
            color: #212529;
        }

        .filter-status {
            background: #e9f5ff;
            border-left: 5px solid #0d6efd;
            border-radius: 8px;
            padding: 12px 15px;
        }

        .table th {
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
        }

        .money {
            text-align: right;
            white-space: nowrap;
        }

        .profit-positive {
            color: #198754;
            font-weight: bold;
        }

        .profit-negative {
            color: #dc3545;
            font-weight: bold;
        }

        .quantity-column,
        .date-column,
        .result-column,
        .action-column {
            text-align: center;
        }

        .date-column {
            white-space: nowrap;
        }

        .zero-price {
            color: #999;
        }

        .delete-form {
            margin: 0;
        }

        @media (max-width: 768px) {

            .summary-number {
                font-size: 25px;
            }

            .page-title-area {
                display: block !important;
            }

            .page-title-area .btn {
                width: 100%;
                margin-top: 12px;
            }
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-success shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1">
            維修紀錄
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
    <?php if ($status === "added") { ?>

        <div class="alert alert-success">
            維修紀錄新增成功，零件庫存已自動扣除。
        </div>

    <?php } elseif ($status === "deleted") { ?>

        <div class="alert alert-success">
            維修紀錄已刪除，使用的零件數量已加回庫存。
        </div>

    <?php } elseif ($status === "deleted_no_part") { ?>

        <div class="alert alert-warning">
            維修紀錄已刪除，但原本的零件資料不存在，因此無法恢復庫存。
        </div>

    <?php } elseif ($status === "delete_failed") { ?>

        <div class="alert alert-danger">
            刪除失敗，資料與庫存都沒有變更。
        </div>

    <?php } elseif (
        $status === "invalid_id" ||
        $status === "invalid_request"
    ) { ?>

        <div class="alert alert-danger">
            刪除要求不正確，請重新操作。
        </div>

    <?php } ?>

    <!-- 日期錯誤提示 -->
    <?php if ($date_error !== "") { ?>

        <div class="alert alert-danger">
            <?php echo htmlspecialchars($date_error); ?>
        </div>

    <?php } ?>

    <!-- 日期篩選 -->
    <div class="card filter-card shadow-sm mb-4">

        <div class="card-body p-4">

            <h4 class="filter-title mb-3">
                📅 日期區間查詢
            </h4>

            <form
                method="get"
                action="repair_list.php"
            >

                <div class="row g-3 align-items-end">

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            開始日期
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $start_date
                                );
                            ?>"
                        >

                    </div>

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            結束日期
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $end_date
                                );
                            ?>"
                        >

                    </div>

                    <div class="col-lg-2 col-md-4">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            🔍 查詢
                        </button>

                    </div>

                    <div class="col-lg-2 col-md-4">

                        <a
                            href="repair_list.php?start_date=<?php
                                echo $today;
                            ?>&end_date=<?php echo $today; ?>"
                            class="btn btn-success w-100"
                        >
                            今日紀錄
                        </a>

                    </div>

                    <div class="col-lg-2 col-md-4">

                        <a
                            href="repair_list.php"
                            class="btn btn-secondary w-100"
                        >
                            清除篩選
                        </a>

                    </div>

                </div>

            </form>

            <div class="mt-3">

                <span class="text-muted me-2">
                    快速選擇：
                </span>

                <a
                    href="repair_list.php?start_date=<?php
                        echo $month_start;
                    ?>&end_date=<?php echo $month_end; ?>"
                    class="btn btn-outline-primary btn-sm"
                >
                    本月紀錄
                </a>

                <a
                    href="repair_list.php"
                    class="btn btn-outline-dark btn-sm"
                >
                    全部紀錄
                </a>

            </div>

        </div>

    </div>

    <!-- 顯示範圍 -->
    <div class="filter-status mb-4">

        <strong>目前車行：</strong>
        <?php
        echo htmlspecialchars(
            $shop_name,
            ENT_QUOTES,
            "UTF-8"
        );
        ?>

        ｜

        <strong>目前顯示：</strong>

        <?php echo htmlspecialchars($filter_text); ?>

        ，共

        <strong>
            <?php echo number_format($total_records); ?>
        </strong>

        筆維修紀錄。

    </div>

    <!-- 統計卡片 -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card summary-card cost-card shadow-sm">

                <div class="card-body">

                    <div class="summary-title">
                        篩選期間總成本
                    </div>

                    <div class="summary-number">
                        NT$
                        <?php echo number_format($total_cost, 0); ?>
                    </div>

                    <p class="summary-description">
                        共 <?php echo $total_records; ?> 筆紀錄
                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card summary-card income-card shadow-sm">

                <div class="card-body">

                    <div class="summary-title">
                        篩選期間總收入
                    </div>

                    <div class="summary-number">
                        NT$
                        <?php echo number_format($total_income, 0); ?>
                    </div>

                    <p class="summary-description">
                        零件售價合計
                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card summary-card profit-card shadow-sm">

                <div class="card-body">

                    <div class="summary-title">
                        篩選期間總毛利
                    </div>

                    <div class="summary-number">
                        NT$
                        <?php echo number_format($total_profit, 0); ?>
                    </div>

                    <p class="summary-description">
                        總收入減去總成本
                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- 標題與新增按鈕 -->
    <div class="d-flex justify-content-between align-items-center mb-3 page-title-area">

        <h3 class="fw-bold mb-0">
            維修紀錄列表
        </h3>

        <a
            href="add_repair.php"
            class="btn btn-primary"
        >
            ＋ 新增維修紀錄
        </a>

    </div>

    <!-- 維修紀錄表格 -->
    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle mb-0">

                    <thead class="table-success">

                        <tr>
                            <th>ID</th>
                            <th>零件名稱</th>
                            <th>零件類型</th>
                            <th>使用數量</th>
                            <th>單件成本</th>
                            <th>單件售價</th>
                            <th>成本小計</th>
                            <th>售價小計</th>
                            <th>毛利</th>
                            <th>維修結果</th>
                            <th>維修日期</th>
                            <th>操作</th>
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
                            $quantity =
                                (int)$row["quantity"];

                            $unit_cost =
                                (float)$row["unit_cost"];

                            $unit_price =
                                (float)$row["unit_price"];

                            $cost_subtotal =
                                $quantity * $unit_cost;

                            $income_subtotal =
                                $quantity * $unit_price;

                            $profit =
                                $income_subtotal -
                                $cost_subtotal;

                            $part_name =
                                $row["part_name"]
                                ?: "零件資料已刪除";

                            $part_type =
                                $row["part_type"]
                                ?: "無資料";
                            ?>

                            <tr>

                                <td class="text-center">
                                    <?php echo (int)$row["id"]; ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $part_name
                                    );
                                    ?>
                                </td>

                                <td class="text-center">
                                    <?php
                                    echo htmlspecialchars(
                                        $part_type
                                    );
                                    ?>
                                </td>

                                <td class="quantity-column">
                                    <?php echo $quantity; ?>
                                </td>

                                <td class="money">
                                    NT$
                                    <?php echo number_format($unit_cost, 0); ?>
                                </td>

                                <td class="money">
                                    NT$
                                    <?php echo number_format($unit_price, 0); ?>
                                </td>

                                <td class="money">
                                    NT$
                                    <?php echo number_format($cost_subtotal, 0); ?>
                                </td>

                                <td class="money">
                                    NT$
                                    <?php echo number_format($income_subtotal, 0); ?>
                                </td>

                                <td class="money">

                                    <?php if ($profit >= 0) { ?>

                                        <span class="profit-positive">
                                            NT$
                                            <?php echo number_format($profit, 0); ?>
                                        </span>

                                    <?php } else { ?>

                                        <span class="profit-negative">
                                            - NT$
                                            <?php echo number_format(abs($profit), 0); ?>
                                        </span>

                                    <?php } ?>

                                </td>

                                <td class="result-column">
                                    <?php
                                    echo htmlspecialchars(
                                        $row["repair_result"]
                                    );
                                    ?>
                                </td>

                                <td class="date-column">
                                    <?php
                                    echo htmlspecialchars(
                                        $row["repair_date"]
                                    );
                                    ?>
                                </td>

                                <!-- 刪除按鈕 -->
                                <td class="action-column">

                                    <form
                                        method="post"
                                        action="delete_repair.php"
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
                                                    $_SESSION["csrf_token"]
                                                );
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="start_date"
                                            value="<?php
                                                echo htmlspecialchars(
                                                    $start_date
                                                );
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="end_date"
                                            value="<?php
                                                echo htmlspecialchars(
                                                    $end_date
                                                );
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                        >
                                            刪除
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td
                                colspan="12"
                                class="text-center text-muted py-5"
                            >
                                找不到符合日期的維修紀錄
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                    <tfoot class="table-light">

                        <tr class="fw-bold">

                            <td
                                colspan="6"
                                class="text-end"
                            >
                                篩選結果合計：
                            </td>

                            <td class="money text-danger">
                                NT$
                                <?php echo number_format($total_cost, 0); ?>
                            </td>

                            <td class="money text-primary">
                                NT$
                                <?php echo number_format($total_income, 0); ?>
                            </td>

                            <td class="money text-success">
                                NT$
                                <?php echo number_format($total_profit, 0); ?>
                            </td>

                            <td colspan="3"></td>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

    <div class="alert alert-info mt-4 mb-0">

        <strong>計算方式：</strong>

        成本小計＝使用數量 × 單件成本；

        售價小計＝使用數量 × 單件售價；

        毛利＝售價小計 − 成本小計。

        刪除紀錄後，使用的零件數量會自動加回庫存。

    </div>

</div>

<script>

function confirmDelete() {

    return confirm(
        "確定要刪除這筆維修紀錄嗎？\n\n" +
        "刪除後，這筆紀錄使用的零件數量會加回庫存。"
    );
}

</script>

</body>

</html>