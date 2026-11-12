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
    <title>Вход в систему · Реестр</title>
    <style>
        :root {
            --bg: #edf1f5;
            --panel: #ffffff;
            --ink: #152230;
            --muted: #617082;
            --line: #d9e2ec;
            --primary: #2b5fd9;
            --danger: #d94d45;
            --shadow: 0 18px 35px rgba(20,35,55,.12);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: Arial, sans-serif;
            background: radial-gradient(circle at top, #eff6ff 0%, var(--bg) 42%, #e8edf3 100%);
            color: var(--ink);
            display: grid; place-items: center;
        }
        .auth-card {
            width: min(440px, calc(100vw - 30px));
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 28px 28px 20px;
        }
        .brand {
            display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
        }
        .brand-mark {
            width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center;
            font-weight: 800; background: #f5b84c; color: #0d1f31;
        }
        h1 {
            margin: 0; font-size: 18px; letter-spacing: .04em;
        }
        .auth-form {
            display: flex; flex-direction: column; gap: 16px;
        }
        label {
            display: flex; flex-direction: column; gap: 8px;
            font-size: 13px; color: var(--muted); font-weight: 600;
        }
        input {
            width: 100%; padding: 11px 12px; border-radius: 10px; border: 1px solid var(--line);
            font-size: 15px; background: #fff;
        }
        input:focus {
            outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(43,95,217,.12);
        }
        button {
            border: none; border-radius: 10px; padding: 12px 16px; background: var(--primary);
            color: #fff; font-weight: 700; cursor: pointer; margin-top: 6px;
        }
        button:hover { filter: brightness(.98); }
        .err {
            background: #fde7e5; color: #8f2f2b; border: 1px solid #f4c5c0;
            border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">
            <div class="brand-mark">Р</div>
            <div>
                <h1>РЕЕСТР</h1>
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
