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
    public function list(?string $q = null, bool $todayOnly = false, array $fields = [], string $status = 'all', ?string $from = null, ?string $to = null): array
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
        if ($from !== null) { $conds[] = 'call_at >= ?'; $args[] = $from; }
        if ($to !== null) { $conds[] = 'call_at <= ?'; $args[] = $to; }
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
        $closedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $closedBy = $status === 'completed' ? $operatorName : null;
        $st = $this->db->prepare(
            'INSERT INTO appeals (address, fio, phone, subject, type, status, call_at, operator_name, lat, lng, closed_at, closed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$address, $fio, $phone, $subject, $type, $status, $callAt, $operatorName, $lat, $lng, $closedAt, $closedBy]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $address, string $fio, string $phone, string $subject, string $type, string $status, string $callAt, ?float $lat, ?float $lng, string $operatorName): void
    {
        $existing = $this->find($id);
        $closedAt = $status === 'completed' ? ($existing['closed_at'] ?? date('Y-m-d H:i:s')) : null;
        $closedBy = $status === 'completed' ? (($existing['closed_by'] ?? '') !== '' ? $existing['closed_by'] : $operatorName) : null;
        $st = $this->db->prepare(
            'UPDATE appeals SET address = ?, fio = ?, phone = ?, subject = ?, type = ?, status = ?, call_at = ?, lat = ?, lng = ?, closed_at = ?, closed_by = ? WHERE id = ?'
        );
        $st->execute([$address, $fio, $phone, $subject, $type, $status, $callAt, $lat, $lng, $closedAt, $closedBy, $id]);
    }

    public function setStatus(int $id, string $status, string $operatorName): void
    {
        if ($id <= 0 || $status !== 'completed') {
            throw new InvalidArgumentException('Некорректный статус обращения.');
        }
        $st = $this->db->prepare('UPDATE appeals SET status = ?, closed_at = NOW(), closed_by = ? WHERE id = ?');
        $st->execute([$status, $operatorName, $id]);
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

    public function operatorStats(?string $from, ?string $to): array
    {
        $createdConditions = [];
        $closedConditions = [];
        $createdArgs = [];
        $closedArgs = [];
        if ($from !== null) { $createdConditions[] = 'call_at >= ?'; $createdArgs[] = $from; $closedConditions[] = 'closed_at >= ?'; $closedArgs[] = $from; }
        if ($to !== null) { $createdConditions[] = 'call_at <= ?'; $createdArgs[] = $to; $closedConditions[] = 'closed_at <= ?'; $closedArgs[] = $to; }
        $createdWhere = $createdConditions ? ' WHERE ' . implode(' AND ', $createdConditions) : '';
        $closedWhere = $closedConditions ? ' WHERE ' . implode(' AND ', $closedConditions) . ' AND closed_at IS NOT NULL' : ' WHERE closed_at IS NOT NULL';
        $created = $this->db->prepare('SELECT operator_name AS name, COUNT(*) AS received FROM appeals' . $createdWhere . ' GROUP BY operator_name');
        $created->execute($createdArgs);
        $closed = $this->db->prepare('SELECT closed_by AS name, COUNT(*) AS closed FROM appeals' . $closedWhere . ' GROUP BY closed_by');
        $closed->execute($closedArgs);
        $result = [];
        foreach ($created->fetchAll() as $row) $result[$row['name']]['received'] = (int)$row['received'];
        foreach ($closed->fetchAll() as $row) $result[$row['name']]['closed'] = (int)$row['closed'];
        foreach ($result as $name => &$row) {
            $row = ['operator_name' => $name, 'received' => $row['received'] ?? 0, 'closed' => $row['closed'] ?? 0];
        }
        unset($row);
        return array_values($result);
    }
}
