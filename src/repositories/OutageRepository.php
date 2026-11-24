<?php
/** Отключения, повреждения и благоустройство. */
final class OutageRepository
{
    private const TYPES = ['shutdown', 'damage', 'improvement'];
    private const DETAILS = ['full_shutdown', 'supply_without_circulation', 'damage_without_shutdown'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Список с фильтрами: поиск (ID/адрес/тип/причина), тип, статус. */
    public function list(?string $q = null, string $type = 'all', string $status = 'all', ?string $from = null, ?string $to = null): array
    {
        $sql    = 'SELECT o.*, h.address, h.lat, h.lng
                   FROM outages o
                   JOIN houses h ON h.id = o.house_id';
        $conds  = [];
        $args   = [];

        if ($type === 'outage') {
            $conds[] = "o.type IN ('shutdown','damage')";
        } elseif ($type !== 'all' && in_array($type, self::TYPES, true)) {
            $conds[] = 'o.type = ?';
            $args[]  = $type;
        }
        if ($status === 'restored') {
            $conds[] = 'o.is_active = 0';
        } elseif ($status !== 'all') {
            $conds[] = 'o.is_active = 1';
        }
        if ($from !== null) { $conds[] = 'o.actual_start >= ?'; $args[] = $from; }
        if ($to !== null) { $conds[] = 'o.actual_start <= ?'; $args[] = $to; }

        if ($q !== null && trim($q) !== '') {
            $q     = trim($q);
            $cond  = 'CAST(o.id AS CHAR) = ? OR h.address LIKE ? OR o.reason LIKE ?';
            $extra = [$q, '%' . $q . '%', '%' . $q . '%'];

            // Поиск по названиям категорий и видов.
            $hit = [];
            if (mb_stripos($q, 'отключ') !== false) $hit[] = 'shutdown';
            if (mb_stripos($q, 'повреж') !== false) $hit[] = 'damage';
            if (mb_stripos($q, 'благо') !== false) $hit[] = 'improvement';
            if ($hit) {
                $cond  .= ' OR o.type IN (' . implode(',', array_fill(0, count($hit), '?')) . ')';
                $extra  = array_merge($extra, $hit);
            }
            if (mb_stripos($q, 'полное') !== false) { $cond .= ' OR o.detail_type = ?'; $extra[] = 'full_shutdown'; }
            if (mb_stripos($q, 'циркуляц') !== false) { $cond .= ' OR o.detail_type = ?'; $extra[] = 'supply_without_circulation'; }
            if (mb_stripos($q, 'без отключ') !== false) { $cond .= ' OR o.detail_type = ?'; $extra[] = 'damage_without_shutdown'; }
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
             WHERE o.is_active = 1 AND o.type IN (\'shutdown\',\'damage\')'
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

    public function create(int $houseId, string $type, string $detailType, ?string $plannedStart, string $actualStart, ?string $reason, ?string $plannedEnd, int $mkdCount, int $socialCount, int $otherCount, string $operatorName): int
    {
        $st = $this->db->prepare(
            'INSERT INTO outages (house_id, type, detail_type, planned_start, actual_start, reason, planned_end, mkd_count, social_count, other_count, operator_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$houseId, $type, $detailType, $plannedStart, $actualStart, $reason, $plannedEnd, $mkdCount, $socialCount, $otherCount, $operatorName]);
        return (int)$this->db->lastInsertId();
    }

    /** Частичное обновление полей отключения. */
    public function update(array $in): void
    {
        $id  = (int)($in['id'] ?? 0);
        $set = [];
        $args = [];

        if (isset($in['type']) && in_array($in['type'], self::TYPES, true)) { $set[] = 'type = ?';        $args[] = $in['type']; }
        if (isset($in['detail_type']) && in_array($in['detail_type'], self::DETAILS, true)) { $set[] = 'detail_type = ?'; $args[] = $in['detail_type']; }
        if (isset($in['house_id']) && (int)$in['house_id'] > 0) { $set[] = 'house_id = ?'; $args[] = (int)$in['house_id']; }
        if (array_key_exists('reason', $in))        { $set[] = 'reason = ?';      $args[] = $in['reason'] !== '' ? $in['reason'] : null; }
        if (array_key_exists('planned_start', $in)) { $set[] = 'planned_start = ?'; $args[] = $in['planned_start'] !== '' ? $in['planned_start'] : null; }
        if (array_key_exists('actual_start', $in))  { $set[] = 'actual_start = ?'; $args[] = $in['actual_start']; }
        if (array_key_exists('planned_end', $in))   { $set[] = 'planned_end = ?'; $args[] = $in['planned_end'] !== '' ? $in['planned_end'] : null; }
        if (array_key_exists('mkd_count', $in))     { $set[] = 'mkd_count = ?'; $args[] = (int)$in['mkd_count']; }
        if (array_key_exists('social_count', $in))  { $set[] = 'social_count = ?'; $args[] = (int)$in['social_count']; }
        if (array_key_exists('other_count', $in))   { $set[] = 'other_count = ?'; $args[] = (int)$in['other_count']; }
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

    public static function validType(string $type): bool { return in_array($type, self::TYPES, true); }
    public static function validDetail(string $detail): bool { return in_array($detail, self::DETAILS, true); }
}
