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

$name = "";
$type = "";
$stock = "";
$cost = "";
$price = "";

if (isset($_POST["submit"])) {

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

    if (
        $name === "" ||
        $type === "" ||
        $stock < 0 ||
        $cost < 0 ||
        $price < 0
    ) {
        $message = "請填寫完整且正確的零件資料";

    } elseif ($price < $cost) {

        $message = "售價不能低於成本價";

    } else {

        /*
            只檢查「目前車行」是否已有同名稱、同類型零件。
            不同車行可以有相同的零件名稱。
        */
        $check_sql = "
            SELECT id
            FROM parts
            WHERE shop_id = ?
              AND name = ?
              AND type = ?
            LIMIT 1
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {

            $message = "檢查零件失敗：" . $conn->error;

        } else {

            $check_stmt->bind_param(
                "iss",
                $shop_id,
                $name,
                $type
            );

            $check_stmt->execute();

            $check_result =
                $check_stmt->get_result();

            if ($check_result->num_rows > 0) {

                $message = "此零件已存在於目前車行，請勿重複新增";

                $check_stmt->close();

            } else {

                $check_stmt->close();

                /*
                    新增時一定寫入目前登入的 shop_id
                */
                $insert_sql = "
                    INSERT INTO parts
                    (
                        shop_id,
                        name,
                        type,
                        stock,
                        cost,
                        price
                    )
                    VALUES
                    (
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

                    $message =
                        "建立新增指令失敗：" .
                        $conn->error;

                } else {

                    $insert_stmt->bind_param(
                        "issidd",
                        $shop_id,
                        $name,
                        $type,
                        $stock,
                        $cost,
                        $price
                    );

                    if ($insert_stmt->execute()) {

                        $insert_stmt->close();

                        header(
                            "Location: stock.php?status=added"
                        );
                        exit();

                    } else {

                        $message =
                            "新增失敗：" .
                            $insert_stmt->error;

                        $insert_stmt->close();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>新增零件</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#f4f4f4;
    font-family:"Microsoft JhengHei", sans-serif;
}

.card-box {
    border-radius:20px;
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
            新增零件
        </span>

        <a href="stock.php" class="btn btn-light btn-sm">
            回庫存
        </a>
    </div>
</nav>

<div class="container py-5">

    <div class="card shadow-sm border-0 mx-auto card-box"
         style="max-width:600px;">

        <div class="card-body p-4">

            <h3 class="fw-bold mb-3 text-center">
                新增零件資料
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

                <div class="alert alert-danger text-center">

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

                <div class="mb-3">

                    <label class="form-label">
                        名稱
                    </label>

                    <select
                        name="name"
                        class="form-select"
                        required
                    >

                        <option value="">
                            請選擇零件名稱
                        </option>

                        <?php
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

                        foreach ($name_options as $option) {
                        ?>

                            <option
                                value="<?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>"
                                <?php echo ($name === $option) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        類型
                    </label>

                    <select
                        name="type"
                        class="form-select"
                        required
                    >

                        <option value="">
                            請選擇零件類型
                        </option>

                        <?php
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

                        foreach ($type_options as $option) {
                        ?>

                            <option
                                value="<?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>"
                                <?php echo ($type === $option) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($option, ENT_QUOTES, "UTF-8"); ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        庫存
                    </label>

                    <input
                        type="number"
                        name="stock"
                        class="form-control"
                        min="0"
                        step="1"
                        value="<?php echo ($stock === "") ? "" : (int)$stock; ?>"
                        required
                    >

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        成本價
                    </label>

                    <input
                        type="number"
                        name="cost"
                        class="form-control"
                        min="0"
                        step="0.01"
                        value="<?php echo ($cost === "") ? "" : htmlspecialchars((string)$cost); ?>"
                        required
                    >

                </div>

                <div class="mb-4">

                    <label class="form-label">
                        售價
                    </label>

                    <input
                        type="number"
                        name="price"
                        class="form-control"
                        min="0"
                        step="0.01"
                        value="<?php echo ($price === "") ? "" : htmlspecialchars((string)$price); ?>"
                        required
                    >

                </div>

                <button
                    type="submit"
                    name="submit"
                    class="btn btn-success w-100"
                >
                    新增零件
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>
