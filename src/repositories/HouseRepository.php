<?php
/** Реестр домов (адреса из базы). */
final class HouseRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Все дома, опционально поиск по ID или адресу. */
    public function all(?string $q = null): array
    {
        $sql = 'SELECT h.*,
                       (SELECT COUNT(*) FROM outages o
                         WHERE o.house_id = h.id AND o.is_active = 1) AS active_outages
                FROM houses h';
        $args = [];
        if ($q !== null && trim($q) !== '') {
            $q = trim($q);
            $sql .= ' WHERE CAST(h.id AS CHAR) = ? OR h.address LIKE ?';
            $args = [$q, '%' . $q . '%'];
        }
        $sql .= ' ORDER BY h.address ASC';
        $st = $this->db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM houses WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function update(int $id, string $address, ?float $lat, ?float $lng): void
    {
        $st = $this->db->prepare('UPDATE houses SET address = ?, lat = ?, lng = ? WHERE id = ?');
        $st->execute([$address, $lat, $lng, $id]);
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare('DELETE FROM houses WHERE id = ?');
        $st->execute([$id]);
    }
}
