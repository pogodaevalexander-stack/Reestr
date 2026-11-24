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

    public function all(): array
    {
        return $this->db->query(
            'SELECT id, login, full_name, role, is_active, created_at FROM accounts ORDER BY full_name, login'
        )->fetchAll();
    }

    public function create(string $login, string $fullName, string $password, string $role): void
    {
        self::validateLogin($login);
        self::validateFullName($fullName);
        self::validatePassword($password);
        self::validateRole($role);

        $st = $this->db->prepare(
            'INSERT INTO accounts (login, full_name, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $st->execute([$login, $fullName, password_hash($password, PASSWORD_DEFAULT), $role]);
    }

    public function update(int $id, string $login, string $fullName, string $role, bool $isActive, string $password, int $actorId): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Некорректная учётная запись.');
        }
        self::validateLogin($login);
        self::validateFullName($fullName);
        self::validateRole($role);
        if ($password !== '') {
            self::validatePassword($password);
        }

        $this->db->beginTransaction();
        try {
            // Сериализуем изменения ролей, чтобы нельзя было случайно убрать последнего активного администратора.
            $this->db->query('SELECT id FROM accounts FOR UPDATE')->fetchAll();
            $st = $this->db->prepare('SELECT role, is_active FROM accounts WHERE id = ?');
            $st->execute([$id]);
            $current = $st->fetch();
            if ($current === false) {
                throw new InvalidArgumentException('Учётная запись не найдена.');
            }
            if ($id === $actorId && ($role !== 'admin' || !$isActive)) {
                throw new InvalidArgumentException('Нельзя отключить или понизить собственную учётную запись.');
            }

            $wasActiveAdmin = $current['role'] === 'admin' && (int)$current['is_active'] === 1;
            if ($wasActiveAdmin && ($role !== 'admin' || !$isActive)) {
                $admins = $this->db->prepare(
                    "SELECT COUNT(*) FROM accounts WHERE role = 'admin' AND is_active = 1 AND id <> ?"
                );
                $admins->execute([$id]);
                if ((int)$admins->fetchColumn() === 0) {
                    throw new InvalidArgumentException('Нельзя убрать последнего активного администратора.');
                }
            }

            if ($password !== '') {
                $st = $this->db->prepare(
                    'UPDATE accounts SET login = ?, full_name = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?'
                );
                $st->execute([$login, $fullName, $role, $isActive ? 1 : 0, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $st = $this->db->prepare(
                    'UPDATE accounts SET login = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?'
                );
                $st->execute([$login, $fullName, $role, $isActive ? 1 : 0, $id]);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function delete(int $id, int $actorId): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Некорректная учётная запись.');
        }
        if ($id === $actorId) {
            throw new InvalidArgumentException('Нельзя удалить собственную учётную запись.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->query('SELECT id FROM accounts FOR UPDATE')->fetchAll();
            $st = $this->db->prepare('SELECT role, is_active FROM accounts WHERE id = ?');
            $st->execute([$id]);
            $account = $st->fetch();
            if ($account === false) {
                throw new InvalidArgumentException('Учётная запись не найдена.');
            }
            if ($account['role'] === 'admin' && (int)$account['is_active'] === 1) {
                $admins = $this->db->query("SELECT COUNT(*) FROM accounts WHERE role = 'admin' AND is_active = 1");
                if ((int)$admins->fetchColumn() <= 1) {
                    throw new InvalidArgumentException('Нельзя удалить последнего активного администратора.');
                }
            }
            $st = $this->db->prepare('DELETE FROM accounts WHERE id = ?');
            $st->execute([$id]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private static function validateLogin(string $login): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $login)) {
            throw new InvalidArgumentException('Логин: 3–64 символа, латинские буквы, цифры, точка, дефис или подчёркивание.');
        }
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < 10) {
            throw new InvalidArgumentException('Пароль должен содержать не менее 10 символов.');
        }
    }

    private static function validateFullName(string $fullName): void
    {
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 255) {
            throw new InvalidArgumentException('Укажите ФИО длиной от 2 до 255 символов.');
        }
    }

    private static function validateRole(string $role): void
    {
        if (!in_array($role, ['operator', 'admin', 'user'], true)) {
            throw new InvalidArgumentException('Выберите допустимую роль.');
        }
    }
}
