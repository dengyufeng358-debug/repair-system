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

$message = "";

/*
    只讀取目前車行自己的零件
*/
$parts_sql = "
    SELECT
        id,
        name,
        type,
        stock,
        cost,
        price
    FROM parts
    WHERE shop_id = ?
    ORDER BY name ASC, type ASC, id ASC
";

$parts_stmt = $conn->prepare($parts_sql);
$parts_result = null;

if ($parts_stmt) {

    $parts_stmt->bind_param(
        "i",
        $shop_id
    );

    $parts_stmt->execute();

    $parts_result =
        $parts_stmt->get_result();
}

if (isset($_POST["submit"])) {

    $part_id = isset($_POST["part_id"])
        ? (int)$_POST["part_id"]
        : 0;

    $quantity = isset($_POST["quantity"])
        ? (int)$_POST["quantity"]
        : 0;

    $repair_result = isset($_POST["repair_result"])
        ? trim($_POST["repair_result"])
        : "";

    $repair_date = isset($_POST["repair_date"])
        ? trim($_POST["repair_date"])
        : "";

    if ($part_id <= 0) {

        $message = "請選擇零件";

    } elseif ($quantity <= 0) {

        $message = "使用數量必須大於 0";

    } elseif (
        $repair_result === "" ||
        $repair_date === ""
    ) {

        $message = "請填寫完整維修資料";

    } else {

        try {

            /*
                庫存扣除 + 維修紀錄新增
                使用交易，兩個動作必須一起成功。
            */
            $conn->begin_transaction();

            /*
                驗證該零件確實屬於目前登入車行。
                shop2 無法透過改網址或 POST 使用 admin 的零件。
            */
            $check_sql = "
                SELECT
                    name,
                    stock,
                    cost,
                    price
                FROM parts
                WHERE id = ?
                  AND shop_id = ?
                FOR UPDATE
            ";

            $check_stmt =
                $conn->prepare($check_sql);

            if (!$check_stmt) {
                throw new Exception(
                    "無法讀取零件資料"
                );
            }

            $check_stmt->bind_param(
                "ii",
                $part_id,
                $shop_id
            );

            $check_stmt->execute();

            $check_result =
                $check_stmt->get_result();

            if ($check_result->num_rows <= 0) {

                $check_stmt->close();

                throw new Exception(
                    "找不到零件資料，或此零件不屬於目前車行"
                );
            }

            $part =
                $check_result->fetch_assoc();

            $check_stmt->close();

            $current_stock =
                (int)$part["stock"];

            $unit_cost =
                (float)$part["cost"];

            $unit_price =
                (float)$part["price"];

            if ($current_stock < $quantity) {

                throw new Exception(
                    "庫存不足，目前庫存為：" .
                    $current_stock
                );
            }

            /*
                寫入維修紀錄時也寫入 shop_id
            */
            $insert_sql = "
                INSERT INTO repair_records
                (
                    shop_id,
                    part_id,
                    quantity,
                    unit_cost,
                    unit_price,
                    repair_result,
                    repair_date
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $insert_stmt =
                $conn->prepare($insert_sql);

            if (!$insert_stmt) {

                throw new Exception(
                    "無法建立維修紀錄"
                );
            }

            $insert_stmt->bind_param(
                "iiiddss",
                $shop_id,
                $part_id,
                $quantity,
                $unit_cost,
                $unit_price,
                $repair_result,
                $repair_date
            );

            if (!$insert_stmt->execute()) {

                $error =
                    $insert_stmt->error;

                $insert_stmt->close();

                throw new Exception(
                    "新增維修紀錄失敗：" .
                    $error
                );
            }

            $insert_stmt->close();

            /*
                只扣目前車行的零件庫存
            */
            $update_sql = "
                UPDATE parts
                SET stock = stock - ?
                WHERE id = ?
                  AND shop_id = ?
                  AND stock >= ?
            ";

            $update_stmt =
                $conn->prepare($update_sql);

            if (!$update_stmt) {

                throw new Exception(
                    "無法更新零件庫存"
                );
            }

            $update_stmt->bind_param(
                "iiii",
                $quantity,
                $part_id,
                $shop_id,
                $quantity
            );

            $update_stmt->execute();

            if ($update_stmt->affected_rows <= 0) {

                $update_stmt->close();

                throw new Exception(
                    "庫存扣除失敗，請重新確認庫存"
                );
            }

            $update_stmt->close();

            /*
                全部成功才正式儲存
            */
            $conn->commit();

            header(
                "Location: repair_list.php?status=added"
            );
            exit();

        } catch (Throwable $error) {

            $conn->rollback();

            $message =
                $error->getMessage();
        }
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

    <title>新增維修紀錄</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .shop-box {
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:10px;
            padding:12px 15px;
        }

    </style>

</head>

<body class="bg-light">

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-success shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1">
            新增維修紀錄
        </span>

        <a
            href="repair_list.php"
            class="btn btn-light btn-sm"
        >
            回維修紀錄
        </a>

    </div>

</nav>

<div class="container py-5">

    <div
        class="card shadow-sm border-0 mx-auto"
        style="max-width:700px;"
    >

        <div class="card-body p-4">

            <h3 class="fw-bold mb-3">
                填寫維修資料
            </h3>

            <div class="shop-box mb-4">

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

            <?php if (
                !$parts_result ||
                $parts_result->num_rows <= 0
            ) { ?>

                <div class="alert alert-warning">

                    目前車行還沒有任何零件，
                    請先到「新增零件」建立庫存資料。

                </div>

                <a
                    href="add_part.php"
                    class="btn btn-primary w-100"
                >
                    前往新增零件
                </a>

            <?php } else { ?>

                <form method="post">

                    <div class="mb-3">

                        <label class="form-label">
                            零件名稱
                        </label>

                        <select
                            name="part_id"
                            id="part_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                請選擇零件
                            </option>

                            <?php while (
                                $part =
                                $parts_result->fetch_assoc()
                            ) { ?>

                                <option
                                    value="<?php
                                        echo (int)$part["id"];
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $part["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                    ／類型：
                                    <?php
                                    echo htmlspecialchars(
                                        $part["type"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                    ／庫存：
                                    <?php
                                    echo (int)$part["stock"];
                                    ?>

                                    ／成本：
                                    NT$
                                    <?php
                                    echo number_format(
                                        $part["cost"],
                                        0
                                    );
                                    ?>

                                    ／售價：
                                    NT$
                                    <?php
                                    echo number_format(
                                        $part["price"],
                                        0
                                    );
                                    ?>

                                </option>

                            <?php } ?>

                        </select>

                        <div class="form-text">
                            只會顯示目前車行自己的零件。
                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            使用數量
                        </label>

                        <input
                            type="number"
                            name="quantity"
                            class="form-control"
                            min="1"
                            placeholder="請輸入使用數量"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            維修結果
                        </label>

                        <select
                            name="repair_result"
                            class="form-select"
                            required
                        >

                            <option value="">
                                請選擇維修結果
                            </option>

                            <option value="維修完成">
                                維修完成
                            </option>

                            <option value="更換完成">
                                更換完成
                            </option>

                            <option value="保養完成">
                                保養完成
                            </option>

                            <option value="待料中">
                                待料中
                            </option>

                            <option value="檢查中">
                                檢查中
                            </option>

                            <option value="無法維修">
                                無法維修
                            </option>

                        </select>

                    </div>

                    <div class="mb-4">

                        <label class="form-label">
                            維修日期
                        </label>

                        <input
                            type="date"
                            name="repair_date"
                            class="form-control"
                            value="<?php echo date("Y-m-d"); ?>"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        name="submit"
                        class="btn btn-success w-100"
                    >
                        新增維修紀錄
                    </button>

                </form>

            <?php } ?>

        </div>

    </div>

</div>

</body>
</html>
