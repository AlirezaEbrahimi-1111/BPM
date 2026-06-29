<?php
/**
 * TicketRepository - لایه دسترسی به داده
 * مسئولیت: تمام کوئری‌های دیتابیس مربوط به تیکت
 */
class TicketRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ─── Ticket Number ────────────────────────────────────────────────────────

    /**
     * تولید شماره تیکت یکتا: TKT-1405-000123
     */
    public function generateTicketNumber(int $shamsiYear): string
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tickets WHERE ticket_number LIKE ?"
        );
        $stmt->execute(["TKT-{$shamsiYear}-%"]);
        $seq = (int)$stmt->fetchColumn() + 1;

        return sprintf("TKT-%d-%06d", $shamsiYear, $seq);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function createTicket(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tickets(organization_id, ticket_number, subject, status_id, priority_id,
                 category_id, created_by, source_type, source_id)
            VALUES(:organization_id, :ticket_number, :subject, :status_id, :priority_id,
                 :category_id, :created_by, :source_type, :source_id)
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function createMessage(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal)
            VALUES (:ticket_id, :user_id, :message, :is_internal)
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function createAttachment(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_attachments
                (ticket_id, message_id, user_id, original_name, stored_name, mime_type, file_size)
            VALUES
                (:ticket_id, :message_id, :user_id, :original_name, :stored_name, :mime_type, :file_size)
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function logHistory(int $ticketId, int $userId, string $action, ?string $old, ?string $new): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
            VALUES (:ticket_id, :user_id, :action, :old_value, :new_value)
        ");
        $stmt->execute([
            'ticket_id' => $ticketId,
            'user_id'   => $userId,
            'action'    => $action,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    /**
     * لیست تیکت‌ها با pagination، search، sort
     */
    public function getTickets(int $orgId, array $filters = []): array
    {
        $where  = ['t.organization_id = :org_id', 't.deleted_at IS NULL'];
        $params = ['org_id' => $orgId];

        if (!empty($filters['search'])) {
            $where[]= '(t.ticket_number LIKE :search OR t.subject LIKE :search)';
            $params['search']     = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status_id'])) {
            $where[]              = 't.status_id = :status_id';
            $params['status_id']  = $filters['status_id'];
        }
        if (!empty($filters['priority_id'])) {
            $where[]               = 't.priority_id = :priority_id';
            $params['priority_id'] = $filters['priority_id'];
        }

        $allowedSort = ['created_at', 'updated_at', 'priority_id', 'status_id'];
        $sortCol     = in_array($filters['sort'] ?? '', $allowedSort) ? $filters['sort'] : 'updated_at';
        $sortDir     = ($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $limit  = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "
            SELECT
                t.id, t.ticket_number, t.subject, t.created_at, t.updated_at,
                ts.label  AS status_label,  ts.color  AS status_color,
                tp.label  AS priority_label, tp.color AS priority_color,
                u.name    AS created_by_name
            FROM tickets t
            JOIN ticket_statuses   ts ON ts.id = t.status_id
            JOIN ticket_priorities tp ON tp.id = t.priority_id
            JOIN users             u  ON u.id  = t.created_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.{$sortCol} {$sortDir}
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTickets(int $orgId, array $filters = []): int
    {
        $where  = ['t.organization_id = :org_id', 't.deleted_at IS NULL'];
        $params = ['org_id' => $orgId];

        if (!empty($filters['search'])) {
            $where[]          = '(t.ticket_number LIKE :search OR t.subject LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status_id'])) {
            $where[]              = 't.status_id = :status_id';
            $params['status_id']  = $filters['status_id'];
        }
        if (!empty($filters['priority_id'])) {
            $where[]               = 't.priority_id = :priority_id';
            $params['priority_id'] = $filters['priority_id'];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tickets t WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getTicketById(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                ts.name   AS status_name,  ts.label  AS status_label,  ts.color AS status_color,
                tp.name   AS priority_name, tp.label AS priority_label, tp.color AS priority_color,
                tc.name   AS category_name,uc.name   AS created_by_name,
                ua.name   AS assigned_to_name
            FROM tickets t
            JOIN ticket_statuses   ts ON ts.id = t.status_id
            JOIN ticket_priorities tp ON tp.id = t.priority_id
            LEFT JOIN ticket_categories tc ON tc.id = t.category_id
            LEFT JOIN users             uc ON uc.id = t.created_by
            LEFT JOIN users             ua ON ua.id = t.assigned_to
            WHERE t.id = :id AND t.organization_id = :org_id AND t.deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getTicketByNumber(string $number, int $orgId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id FROM tickets
            WHERE ticket_number = :number AND organization_id = :org_id AND deleted_at IS NULL
        ");
        $stmt->execute(['number' => $number, 'org_id' => $orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->getTicketById((int)$row['id'], $orgId) : null;
    }

    public function getMessages(int $ticketId, bool $includeInternal = false): array
    {
        $sql = "
            SELECT m.*, u.name AS user_name
            FROM ticket_messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.ticket_id = :ticket_id
        ";
        if (!$includeInternal) {
            $sql .= " AND m.is_internal = 0";
        }
        $sql .= " ORDER BY m.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttachments(int $ticketId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name AS user_name
            FROM ticket_attachments a
            JOIN users u ON u.id = a.user_id
            WHERE a.ticket_id = :ticket_id
            ORDER BY a.created_at ASC
        ");
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistory(int $ticketId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.name AS user_name
            FROM ticket_history h
            JOIN users u ON u.id = h.user_id
            WHERE h.ticket_id = :ticket_id
            ORDER BY h.created_at ASC
        ");
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function updateTicket(int $id, array $data): bool
    {
        $allowed = ['status_id', 'priority_id', 'category_id', 'assigned_to'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]       = "{$col} = :{$col}";
                $params[$col] = $data[$col];
            }
        }
        if (empty($sets)) return false;

        $stmt = $this->db->prepare(
            "UPDATE tickets SET " . implode(', ', $sets) . " WHERE id = :id"
        );
        return $stmt->execute($params);
    }

    // ─── Delete (soft) ────────────────────────────────────────────────────────

    public function softDeleteTicket(int $id, int $orgId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tickets SET deleted_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");
        return $stmt->execute(['id' => $id, 'org_id' => $orgId]);
    }

    // ─── Lookups ──────────────────────────────────────────────────────────────

    public function getStatuses(): array
    {
        return $this->db->query("SELECT * FROM ticket_statuses ORDER BY sort_order")        ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPriorities(): array
    {
        return $this->db->query("SELECT * FROM ticket_priorities ORDER BY sort_order")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(int $orgId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ticket_categories
            WHERE organization_id = :org_id AND is_active = 1
            ORDER BY name
        ");
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
