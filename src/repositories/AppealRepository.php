<?php
/** Обращения граждан: адрес, ФИО, телефон. */
final class AppealRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Список обращений с поиском по выбранным полям. */
    public function list(?string $q = null, bool $todayOnly = false, array $fields = []): array
    {
        $sql    = 'SELECT * FROM appeals';
        $conds  = [];
        $args   = [];

        if ($todayOnly) {
            $conds[] = 'DATE(created_at) = CURDATE()';
        }
        if ($q !== null && trim($q) !== '') {
            $q = trim($q);
            $allowed = ['id', 'address', 'fio', 'phone', 'created_at', 'updated_at'];
            $selected = array_values(array_intersect($allowed, $fields));
            if (!$selected) {
                $selected = ['address', 'fio', 'phone', 'created_at', 'updated_at'];
            }

            $search = [];
            foreach ($selected as $field) {
                if ($field === 'id') {
                    $search[] = 'CAST(id AS CHAR) = ?';
                    $args[] = $q;
                } elseif (in_array($field, ['address', 'fio', 'phone'], true)) {
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
        $sql .= ' ORDER BY created_at DESC';

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

    public function create(string $address, string $fio, string $phone, ?float $lat, ?float $lng): int
    {
        $st = $this->db->prepare(
            'INSERT INTO appeals (address, fio, phone, lat, lng) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$address, $fio, $phone, $lat, $lng]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $address, string $fio, string $phone, ?float $lat, ?float $lng): void
    {
        $st = $this->db->prepare(
            'UPDATE appeals SET address = ?, fio = ?, phone = ?, lat = ?, lng = ? WHERE id = ?'
        );
        $st->execute([$address, $fio, $phone, $lat, $lng, $id]);
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare('DELETE FROM appeals WHERE id = ?');
        $st->execute([$id]);
    }
}
