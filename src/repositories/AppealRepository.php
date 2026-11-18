<?php
/** Обращения граждан: контактные данные, состав, тип и статус. */
final class AppealRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Список обращений с поиском по выбранным полям. */
    public function list(?string $q = null, bool $todayOnly = false, array $fields = [], string $status = 'all'): array
    {
        $sql    = 'SELECT * FROM appeals';
        $conds  = [];
        $args   = [];

        if ($todayOnly) {
            $conds[] = 'DATE(call_at) = CURDATE()';
        }
        if (in_array($status, ['in_work', 'completed'], true)) {
            $conds[] = 'status = ?';
            $args[] = $status;
        }
        if ($q !== null && trim($q) !== '') {
            $q = trim($q);
            $allowed = ['id', 'address', 'fio', 'phone', 'subject', 'type', 'status', 'call_at', 'operator_name', 'created_at', 'updated_at'];
            $selected = array_values(array_intersect($allowed, $fields));
            if (!$selected) {
                $selected = ['address', 'fio', 'phone', 'subject', 'type', 'status', 'call_at', 'operator_name', 'created_at', 'updated_at'];
            }

            $search = [];
            foreach ($selected as $field) {
                if ($field === 'id') {
                    $search[] = 'CAST(id AS CHAR) = ?';
                    $args[] = $q;
                } elseif (in_array($field, ['address', 'fio', 'phone', 'subject', 'type', 'status', 'operator_name'], true)) {
                    $search[] = "$field LIKE ?";
                    $args[] = '%' . $q . '%';
                } else {
                    $search[] = "(CAST($field AS CHAR) LIKE ? OR DATE_FORMAT($field, '%d.%m.%y %H:%i') LIKE ?)";
                    $args[] = '%' . $q . '%';
                    $args[] = '%' . $q . '%';
                }
            }
            $conds[] = '(' . implode(' OR ', $search) . ')';
        }

        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY call_at DESC';

        $st = $this->db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM appeals WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $address, string $fio, string $phone, string $subject, string $type, string $callAt, string $operatorName, ?float $lat, ?float $lng): int
    {
        $status = in_array($type, ['information', 'false_call'], true) ? 'completed' : 'in_work';
        $st = $this->db->prepare(
            'INSERT INTO appeals (address, fio, phone, subject, type, status, call_at, operator_name, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$address, $fio, $phone, $subject, $type, $status, $callAt, $operatorName, $lat, $lng]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $address, string $fio, string $phone, string $subject, string $type, string $status, string $callAt, ?float $lat, ?float $lng): void
    {
        $st = $this->db->prepare(
            'UPDATE appeals SET address = ?, fio = ?, phone = ?, subject = ?, type = ?, status = ?, call_at = ?, lat = ?, lng = ? WHERE id = ?'
        );
        $st->execute([$address, $fio, $phone, $subject, $type, $status, $callAt, $lat, $lng, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        if ($id <= 0 || $status !== 'completed') {
            throw new InvalidArgumentException('Некорректный статус обращения.');
        }
        $st = $this->db->prepare('UPDATE appeals SET status = ? WHERE id = ?');
        $st->execute([$status, $id]);
        if ($st->rowCount() === 0) {
            $check = $this->db->prepare('SELECT 1 FROM appeals WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('Обращение не найдено.');
            }
        }
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare('DELETE FROM appeals WHERE id = ?');
        $st->execute([$id]);
    }
}
