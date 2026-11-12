<?php
/** Пользовательские аккаунты для входа в систему. */
final class AccountRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function findByLogin(string $login): ?array
    {
        $st = $this->db->prepare('SELECT * FROM accounts WHERE login = ? LIMIT 1');
        $st->execute([$login]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function verify(string $login, string $password): ?array
    {
        $user = $this->findByLogin($login);
        if ($user === null || (int)$user['is_active'] !== 1) {
            return null;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            return null;
        }

        return $user;
    }
}
