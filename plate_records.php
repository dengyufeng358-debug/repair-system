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
    ★ 只讀取目前登入車行的車牌紀錄
*/
$sql = "
    SELECT
        id,
        plate_number,
        entry_time,
        note
    FROM plate_records
    WHERE shop_id = ?
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);
$result = null;

if ($stmt) {

    $stmt->bind_param(
        "i",
        $shop_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();
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

    <title>車牌登入紀錄</title>

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

        .plate-text {
            font-weight:bold;
            letter-spacing:.5px;
        }

        @media(max-width:768px) {

            .title-row {
                display:block !important;
            }

            .title-row .btn {
                width:100%;
                margin-top:12px;
            }
        }

    </style>

</head>

<body>

<?php include "menu.php"; ?>

<nav class="navbar navbar-dark bg-secondary shadow-sm">

    <div class="container">

        <span class="navbar-brand mb-0 h1">
            車牌登入紀錄
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

    <div
        class="d-flex justify-content-between align-items-center mb-3 title-row"
    >

        <h3 class="fw-bold mb-0">
            車牌登入紀錄列表
        </h3>

        <a
            href="plate_login.php"
            class="btn btn-dark"
        >
            ＋ 登記車牌
        </a>

    </div>

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table
                    class="table table-bordered table-hover align-middle mb-0"
                >

                    <thead class="table-secondary">

                        <tr>

                            <th>ID</th>

                            <th>
                                車牌號碼
                            </th>

                            <th>
                                登入時間
                            </th>

                            <th>
                                備註
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (
                        $result &&
                        $result->num_rows > 0
                    ) { ?>

                        <?php while (
                            $row =
                            $result->fetch_assoc()
                        ) { ?>

                            <tr>

                                <td>
                                    <?php
                                    echo (int)$row["id"];
                                    ?>
                                </td>

                                <td class="plate-text">

                                    <?php
                                    echo htmlspecialchars(
                                        $row["plate_number"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["entry_time"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["note"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted py-5"
                            >
                                目前車行尚無車牌紀錄
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>
