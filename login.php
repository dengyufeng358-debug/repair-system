<?php

session_start();
include "db.php";

$message = "";

/*
    已登入就直接進首頁
*/
if (
    isset($_SESSION["username"]) &&
    isset($_SESSION["shop_id"])
) {
    header("Location: dashboard.php");
    exit();
}

/*
    登入
*/
if (isset($_POST["login"])) {

    $username = isset($_POST["username"])
        ? trim($_POST["username"])
        : "";

    $password = isset($_POST["password"])
        ? trim($_POST["password"])
        : "";

    if ($username === "" || $password === "") {

        $message = "請輸入帳號與密碼";

    } else {

        /*
            同時取得帳號所屬車行
        */
        $sql = "
            SELECT
                users.id,
                users.username,
                users.password,
                users.role,
                users.shop_id,
                shops.shop_name

            FROM users

            INNER JOIN shops
            ON users.shop_id = shops.id

            WHERE users.username = ?
              AND users.password = ?

            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $message =
                "登入系統錯誤：" .
                $conn->error;

        } else {

            $stmt->bind_param(
                "ss",
                $username,
                $password
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if (
                $result &&
                $result->num_rows > 0
            ) {

                $user = $result->fetch_assoc();

                /*
                    使用者資料
                */
                $_SESSION["user_id"] =
                    (int)$user["id"];

                $_SESSION["username"] =
                    $user["username"];

                $_SESSION["role"] =
                    $user["role"];

                /*
                    ★ 多車行核心
                */
                $_SESSION["shop_id"] =
                    (int)$user["shop_id"];

                $_SESSION["shop_name"] =
                    $user["shop_name"];

                $stmt->close();

                header(
                    "Location: dashboard.php"
                );

                exit();

            } else {

                $message =
                    "帳號或密碼錯誤";

                $stmt->close();
            }
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

<title>機車維修智慧管理系統－登入</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<style>

body {
    background:
        linear-gradient(
            135deg,
            #eef2f7,
            #dbe7f5
        );

    font-family:
        "Microsoft JhengHei",
        sans-serif;
}

.login-card {
    width: 100%;
    max-width: 430px;

    border: none;
    border-radius: 22px;

    overflow: hidden;

    box-shadow:
        0 15px 40px
        rgba(0,0,0,.15);
}

.login-header {
    background: #1f3f6d;
    color: white;

    text-align: center;

    padding: 30px 20px;
}

.login-header h2 {
    font-weight: bold;
    margin-bottom: 8px;
}

.login-header p {
    margin-bottom: 0;
    color: #dbeafe;
}

.login-body {
    background: white;
    padding: 32px;
}

.form-control {
    padding: 12px;
    border-radius: 10px;
}

.login-button {
    padding: 12px;
    border-radius: 10px;
    font-weight: bold;
}

.system-note {
    font-size: 13px;
    color: #64748b;

    text-align: center;

    margin-top: 18px;
}

</style>

</head>

<body>

<div
    class="container d-flex justify-content-center align-items-center min-vh-100"
>

    <div class="card login-card">

        <div class="login-header">

            <h2>
                🏍️ 維修管理系統
            </h2>

            <p>
                多車行智慧管理平台
            </p>

        </div>

        <div class="login-body">

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

                <div class="mb-3">

                    <label class="form-label fw-bold">
                        帳號
                    </label>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="請輸入帳號"
                        autocomplete="username"
                        required
                    >

                </div>

                <div class="mb-4">

                    <label class="form-label fw-bold">
                        密碼
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="請輸入密碼"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <button
                    type="submit"
                    name="login"
                    class="btn btn-primary login-button w-100"
                >
                    登入系統
                </button>

            </form>

            <div class="system-note">
                系統會依登入帳號載入各自車行資料
            </div>

        </div>

    </div>

</div>

</body>
</html>
