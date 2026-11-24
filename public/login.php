<?php
require __DIR__ . '/../src/bootstrap.php';

if (auth_user() !== null) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

$next = $_GET['next'] ?? BASE . '/index.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $next = trim((string)($_POST['next'] ?? $next));

    if ($login === '' || $password === '') {
        $error = 'Укажите логин и пароль.';
    } else {
        $user = (new AccountRepository())->verify($login, $password);
        if ($user === null) {
            $error = 'Неверный логин или пароль.';
        } else {
            login_user($user);
            $redirect = $next !== '' ? $next : BASE . '/index.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход в систему · Реестр АОТК</title>
    <link rel="icon" href="logo/Итоговый_ImgID1.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700;800&family=Unbounded:wght@500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f4f5;
            --panel: #ffffff;
            --ink: #131316;
            --muted: #6d6d76;
            --line: #e3e3e7;
            --primary: #d71920;
            --primary-deep: #9f1118;
            --danger: #d71920;
            --font-body: "Golos Text", "Segoe UI", system-ui, sans-serif;
            --font-display: "Unbounded", "Golos Text", sans-serif;
            --shadow: 0 18px 35px rgba(19,19,22,.14);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: var(--font-body);
            background:
                radial-gradient(700px 380px at 88% -10%, rgba(215,25,32,.06), transparent 60%),
                radial-gradient(circle at top, #fafafa 0%, var(--bg) 45%, #ededef 100%);
            color: var(--ink);
            display: grid; place-items: center;
            -webkit-font-smoothing: antialiased;
        }
        .auth-card {
            width: min(440px, calc(100vw - 30px));
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px 28px 22px;
            position: relative;
            overflow: hidden;
        }
        .auth-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-deep) 70%, transparent 130%);
        }
        .brand {
            display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
        }
        .brand-mark {
            width: 54px; height: 54px; border-radius: 13px; flex: none;
            background: #fff url('logo/Итоговый_ImgID1.png') center / contain no-repeat;
            border: 1px solid var(--line);
            box-shadow: 0 4px 12px rgba(19,19,22,.12);
        }
        h1 {
            margin: 0; font-size: 17px; letter-spacing: .06em;
            font-family: var(--font-display); font-weight: 700;
        }
        .auth-form {
            display: flex; flex-direction: column; gap: 16px;
        }
        label {
            display: flex; flex-direction: column; gap: 8px;
            font-size: 13px; color: var(--muted); font-weight: 600;
        }
        input {
            width: 100%; padding: 11px 12px; border-radius: 11px; border: 1px solid var(--line);
            font-size: 15px; background: #fff; font-family: var(--font-body); color: var(--ink);
            transition: border-color .15s, box-shadow .15s;
        }
        input::placeholder { color: #a3a3ab; }
        input:focus {
            outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(215,25,32,.12);
        }
        button {
            border: none; border-radius: 11px; padding: 12px 16px; background: var(--primary);
            color: #fff; font-weight: 700; font-size: 14.5px; font-family: var(--font-body);
            cursor: pointer; margin-top: 6px; letter-spacing: .02em;
            box-shadow: 0 4px 14px rgba(215,25,32,.28);
            transition: background .15s, transform .15s, box-shadow .15s;
        }
        button:hover { background: var(--primary-deep); box-shadow: 0 6px 18px rgba(215,25,32,.34); }
        button:active { transform: scale(.98); }
        .err {
            background: #fdecec; color: #8f2f2b; border: 1px solid #f4c5c0;
            border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">
            <div class="brand-mark"></div>
            <div>
                <h1>РЕЕСТР АОТК</h1>
            </div>
        </div>
        <?php if ($error !== ''): ?>
            <div class="err"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <label>
                Логин
                <input type="text" name="login" value="" autocomplete="username" required>
            </label>
            <label>
                Пароль
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Войти</button>
        </form>

    </div>
</body>
</html>
