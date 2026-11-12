<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function prompt_hidden(string $prompt): string
{
    if (PHP_OS_FAMILY !== 'Windows' || !function_exists('proc_open')) {
        echo $prompt;
        return rtrim((string)fgets(STDIN), "\r\n");
    }

    $powershell = '$secret = Read-Host "' . str_replace('"', '`"', $prompt) . '" -AsSecureString;'
        . ' $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret);'
        . ' try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)) }'
        . ' finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }';
    $process = proc_open(
        ['powershell.exe', '-NoLogo', '-NoProfile', '-Command', $powershell],
        [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Не удалось запустить ввод защищённого пароля.');
    }
    $value = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Не удалось прочитать пароль.');
    }
    return rtrim((string)$value, "\r\n");
}

try {
    echo "Создание аккаунта\n";
    echo "Логин: ";
    $login = trim((string)fgets(STDIN));
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $login)) {
        throw new RuntimeException('Логин должен содержать 3–64 символа: латинские буквы, цифры, точку, дефис или подчёркивание.');
    }

    $password = prompt_hidden('Пароль (не менее 10 символов):');
    $confirmation = prompt_hidden('Повторите пароль:');
    if (strlen($password) < 10) {
        throw new RuntimeException('Пароль должен содержать не менее 10 символов.');
    }
    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException('Пароли не совпадают.');
    }

    $pdo = Database::pdo();
    $check = $pdo->prepare('SELECT 1 FROM accounts WHERE login = ? LIMIT 1');
    $check->execute([$login]);
    if ($check->fetchColumn()) {
        throw new RuntimeException('Аккаунт с таким логином уже существует.');
    }

    $insert = $pdo->prepare('INSERT INTO accounts (login, password_hash, is_active) VALUES (?, ?, 1)');
    $insert->execute([$login, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Аккаунт успешно создан.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Ошибка: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
