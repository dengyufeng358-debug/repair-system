<?php
session_start();
include "db.php";

/*
    如果是後台已登入使用者，
    自動使用目前登入車行的 shop_id。

    如果是客戶直接開啟此頁，
    則從網址 / 表單取得 shop_id。
*/
$is_logged_in =
    isset($_SESSION["username"]) &&
    isset($_SESSION["shop_id"]);

$shop_id = $is_logged_in
    ? (int)$_SESSION["shop_id"]
    : (
        isset($_GET["shop_id"])
        ? (int)$_GET["shop_id"]
        : 0
    );

$shop_name = "";

if ($is_logged_in) {

    $shop_name =
        $_SESSION["shop_name"]
        ?? "目前車行";

} elseif ($shop_id > 0) {

    $shop_sql = "
        SELECT shop_name
        FROM shops
        WHERE id = ?
        LIMIT 1
    ";

    $shop_stmt = $conn->prepare($shop_sql);

    if ($shop_stmt) {

        $shop_stmt->bind_param("i", $shop_id);
        $shop_stmt->execute();

        $shop_result =
            $shop_stmt->get_result();

        if (
            $shop_result &&
            $shop_result->num_rows > 0
        ) {
            $shop_row =
                $shop_result->fetch_assoc();

            $shop_name =
                $shop_row["shop_name"];
        }

        $shop_stmt->close();
    }
}

/*
    未登入時，提供車行下拉選單。
*/
$shops = [];

if (!$is_logged_in) {

    $shops_result =
        $conn->query(
            "SELECT id, shop_name FROM shops ORDER BY id ASC"
        );

    if ($shops_result) {

        while (
            $shop =
            $shops_result->fetch_assoc()
        ) {
            $shops[] = $shop;
        }
    }
}

$plate = "";
$records = [];
$main_status = "";

if (isset($_GET["plate"])) {

    $plate =
        strtoupper(
            trim($_GET["plate"])
        );

    $plate =
        str_replace(
            [" ", "-"],
            "",
            $plate
        );

    /*
        一定要有 shop_id 才能查詢，
        避免不同車行相同車牌互相混在一起。
    */
    if (
        $plate !== "" &&
        $shop_id > 0
    ) {

        $sql = "
            SELECT
                id,
                item_name,
                status,
                created_at
            FROM maintenance_records
            WHERE shop_id = ?
              AND plate_number = ?
            ORDER BY id DESC
        ";

        $stmt =
            $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "is",
                $shop_id,
                $plate
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if (
                $result &&
                $result->num_rows > 0
            ) {

                while (
                    $row =
                    $result->fetch_assoc()
                ) {
                    $records[] = $row;
                }

                $has_pending = false;
                $has_processing = false;
                $has_waiting = false;
                $has_notified = false;

                foreach ($records as $record) {

                    if ($record["status"] === "待處理") {
                        $has_pending = true;
                    }

                    if ($record["status"] === "處理中") {
                        $has_processing = true;
                    }

                    if ($record["status"] === "待交車") {
                        $has_waiting = true;
                    }

                    if ($record["status"] === "已通知領車") {
                        $has_notified = true;
                    }
                }

                if ($has_pending) {

                    $main_status = "待處理";

                } elseif ($has_processing) {

                    $main_status = "處理中";

                } elseif ($has_waiting) {

                    $main_status = "待交車";

                } elseif ($has_notified) {

                    $main_status = "可領車";

                } else {

                    $main_status = "維修完畢";
                }
            }

            $stmt->close();
        }
    }
}

function statusBadge($status)
{
    if ($status === "待處理") {

        return "<span class='badge bg-secondary'>待處理</span>";

    } elseif ($status === "處理中") {

        return "<span class='badge bg-primary'>處理中</span>";

    } elseif ($status === "待交車") {

        return "<span class='badge bg-warning text-dark'>待交車</span>";

    } elseif (
        $status === "已通知領車" ||
        $status === "可領車"
    ) {

        return "<span class='badge bg-success'>可領車</span>";

    } else {

        return "<span class='badge bg-dark'>維修完畢</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客戶維修進度查詢</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
            font-family:"Microsoft JhengHei", sans-serif;
        }

        .query-card{
            max-width:1000px;
            margin:0 auto;
            border:0;
            border-radius:18px;
        }

        .shop-box{
            background:#eef6ff;
            border-left:5px solid #0d6efd;
            border-radius:10px;
            padding:12px 15px;
        }
    </style>
</head>

<body>

<?php if ($is_logged_in) { ?>
    <?php include "menu.php"; ?>
<?php } ?>

<div class="container py-5">

    <div class="card shadow-sm query-card">
        <div class="card-body p-4 p-md-5">

            <h1 class="text-center fw-bold mb-4">
                客戶維修進度查詢
            </h1>

            <?php if ($shop_name !== "") { ?>

                <div class="shop-box mb-4">
                    查詢車行：
                    <strong>
                        <?php echo htmlspecialchars($shop_name, ENT_QUOTES, "UTF-8"); ?>
                    </strong>
                </div>

            <?php } ?>

            <form method="get" class="mb-5">

                <?php if (!$is_logged_in) { ?>

                    <div class="mb-3">

                        <label class="form-label fw-bold">
                            選擇車行
                        </label>

                        <select
                            name="shop_id"
                            class="form-select form-select-lg"
                            required
                        >

                            <option value="">
                                請選擇車行
                            </option>

                            <?php foreach ($shops as $shop) { ?>

                                <option
                                    value="<?php echo (int)$shop["id"]; ?>"
                                    <?php
                                    echo (
                                        $shop_id ===
                                        (int)$shop["id"]
                                    )
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $shop["shop_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>
                                </option>

                            <?php } ?>

                        </select>

                    </div>

                <?php } ?>

                <div class="input-group">

                    <input
                        type="text"
                        name="plate"
                        class="form-control form-control-lg"
                        placeholder="請輸入車牌號碼"
                        value="<?php echo htmlspecialchars($plate, ENT_QUOTES, "UTF-8"); ?>"
                        required
                    >

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg"
                    >
                        查詢
                    </button>

                </div>

            </form>

            <?php if (count($records) > 0) { ?>

                <div class="alert alert-dark text-center fs-4 fw-bold">

                    目前整體狀態：
                    <?php echo statusBadge($main_status); ?>

                </div>

                <h3 class="fw-bold mb-4">
                    維修項目明細
                </h3>

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-warning">
                            <tr>
                                <th>ID</th>
                                <th>保養項目</th>
                                <th>狀態</th>
                                <th>建立時間</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($records as $row) { ?>

                                <tr>

                                    <td>
                                        <?php echo (int)$row["id"]; ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["item_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo statusBadge(
                                            $row["status"]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["created_at"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            <?php } elseif (
                isset($_GET["plate"]) &&
                $plate !== ""
            ) { ?>

                <?php if ($shop_id <= 0) { ?>

                    <div class="alert alert-warning text-center">
                        請先選擇車行
                    </div>

                <?php } else { ?>

                    <div class="alert alert-danger text-center">
                        查無此車牌資料
                    </div>

                <?php } ?>

            <?php } ?>

            <div class="text-center mt-4">

                <?php if ($is_logged_in) { ?>

                    <a
                        href="maintenance_list.php"
                        class="btn btn-success px-5"
                    >
                        返回保養列表
                    </a>

                <?php } else { ?>

                    <button
                        onclick="history.back()"
                        class="btn btn-success px-5"
                    >
                        返回
                    </button>

                <?php } ?>

            </div>

        </div>
    </div>

</div>

</body>
</html>
