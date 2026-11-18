<?php
/** Отключения: ГВС (hot_water) и отопление (heating). */
final class OutageRepository
{
    private const TYPES = ['hot_water', 'heating'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Список с фильтрами: поиск (ID/адрес/тип/причина), тип, статус. */
    public function list(?string $q = null, string $type = 'all', string $status = 'all'): array
    {
        $sql    = 'SELECT o.*, h.address, h.lat, h.lng
                   FROM outages o
                   JOIN houses h ON h.id = o.house_id';
        $conds  = [];
        $args   = [];

        if ($type !== 'all' && in_array($type, self::TYPES, true)) {
            $conds[] = 'o.type = ?';
            $args[]  = $type;
        }
        if ($status === 'restored') {
            $conds[] = 'o.is_active = 0';
        } elseif ($status !== 'all') {
            $conds[] = 'o.is_active = 1';
        }

        if ($q !== null && trim($q) !== '') {
            $q     = trim($q);
            $cond  = 'CAST(o.id AS CHAR) = ? OR h.address LIKE ? OR o.reason LIKE ?';
            $extra = [$q, '%' . $q . '%', '%' . $q . '%'];

            // поиск по русским словам типа: «вод…», «отопл…»
            $hit = [];
            if (strpos($q, 'вод') !== false)                                        $hit[] = 'hot_water';
            if (strpos($q, 'отопл') !== false || strpos($q, 'отопит') !== false)    $hit[] = 'heating';
            if ($hit) {
                $cond  .= ' OR o.type IN (' . implode(',', array_fill(0, count($hit), '?')) . ')';
                $extra  = array_merge($extra, $hit);
            }
            $conds[] = '(' . $cond . ')';
            $args    = array_merge($args, $extra);
        }

        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY o.is_active DESC, o.actual_start DESC';

        $st = $this->db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** Активные отключения — для карты. */
    public function active(): array
    {
        $st = $this->db->query(
            'SELECT o.id, o.type, o.reason, o.planned_start, o.actual_start, o.planned_end, h.address, h.lat, h.lng
             FROM outages o JOIN houses h ON h.id = o.house_id
             WHERE o.is_active = 1'
        );
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare(
            'SELECT o.*, h.address, h.lat, h.lng
             FROM outages o JOIN houses h ON h.id = o.house_id
             WHERE o.id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function create(int $houseId, string $type, ?string $plannedStart, string $actualStart, ?string $reason, ?string $plannedEnd): int
    {
        $st = $this->db->prepare(
            'INSERT INTO outages (house_id, type, planned_start, actual_start, reason, planned_end) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$houseId, $type, $plannedStart, $actualStart, $reason, $plannedEnd]);
        return (int)$this->db->lastInsertId();
    }

    /** Частичное обновление: type / reason / planned_start / actual_start / planned_end / is_active. */
    public function update(array $in): void
    {
        $id  = (int)($in['id'] ?? 0);
        $set = [];
        $args = [];

        if (isset($in['type']) && in_array($in['type'], self::TYPES, true)) { $set[] = 'type = ?';        $args[] = $in['type']; }
        if (isset($in['house_id']) && (int)$in['house_id'] > 0) { $set[] = 'house_id = ?'; $args[] = (int)$in['house_id']; }
        if (array_key_exists('reason', $in))        { $set[] = 'reason = ?';      $args[] = $in['reason'] !== '' ? $in['reason'] : null; }
        if (array_key_exists('planned_start', $in)) { $set[] = 'planned_start = ?'; $args[] = $in['planned_start'] !== '' ? $in['planned_start'] : null; }
        if (array_key_exists('actual_start', $in))  { $set[] = 'actual_start = ?'; $args[] = $in['actual_start']; }
        if (array_key_exists('planned_end', $in))   { $set[] = 'planned_end = ?'; $args[] = $in['planned_end'] !== '' ? $in['planned_end'] : null; }
        if (array_key_exists('is_active', $in))     { $set[] = 'is_active = ?';   $args[] = (int)(bool)$in['is_active']; }

        if (!$set) {
            return;
        }
        $args[] = $id;
        $st = $this->db->prepare('UPDATE outages SET ' . implode(', ', $set) . ' WHERE id = ?');
        $st->execute($args);
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare('DELETE FROM outages WHERE id = ?');
        $st->execute([$id]);
    }
}
