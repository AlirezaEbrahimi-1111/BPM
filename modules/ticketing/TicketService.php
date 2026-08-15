<?php
/**
 * TicketService - Business Logic Layer
 */

class TicketService
{
    private TicketRepository $repo;
    private PDO $db;
    private int $organizationId;
    private int $userId;
    private string $uploadPath;

    public function __construct(
        PDO $db,
        TicketRepository $repo,
        int $organizationId,
        int $userId
    ) {
        $this->db             = $db;
        $this->repo           = $repo;
        $this->organizationId = $organizationId;
        $this->userId         = $userId;

        $this->uploadPath = __DIR__ . '/../../uploads/tickets/';

        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    // ─────────────────────────────────────────
    // Create Ticket
    // ─────────────────────────────────────────

    public function createTicket(array $data, array $files = []): array
    {
        if (empty(trim($data['subject'] ?? ''))) {
            return ['success' => false, 'error' => 'عنوان الزامی است'];
        }

        if (empty(trim($data['description'] ?? ''))) {
            return ['success' => false, 'error' => 'توضیحات الزامی است'];
        }

        try {

            $this->db->beginTransaction();

            $ticketNumber = $this->repo->generateTicketNumber(
                $this->getShamsiYear()
            );

            $ticketId = $this->repo->createTicket([
                'organization_id' => $this->organizationId,
                'ticket_number'   => $ticketNumber,
                'subject'         => trim($data['subject']),
                'status_id'       => 1,
                'priority_id'     => (int)($data['priority_id'] ?? 2),
                'category_id'     => !empty($data['category_id']) ? (int)$data['category_id'] : null,
                'created_by'      => $this->userId,
                'source_type'     => $data['source_type'] ?? null,
                'source_id'       => !empty($data['source_id']) ? (int)$data['source_id'] : null,
            ]);

            $messageId = $this->repo->createMessage([
                'ticket_id'   => $ticketId,
                'user_id'     => $this->userId,
                'message'     => trim($data['description']),
                'is_internal' => 0,
            ]);

            if (!empty($files['attachments'])) {
                $this->saveAttachments(
                    $ticketId,
                    $messageId,
                    $files['attachments']
                );
            }

            $this->repo->logHistory(
                $ticketId,
                $this->userId,
                'created',
                null,
                $ticketNumber
            );

            $this->db->commit();

        if (!empty($assignedTo) && $assignedTo != $this->userId) {
            $notif = new Notification($this->db);
            $notif->create([
                'to_user_id'   => $assignedTo,
                'title'        => 'تیکت جدید: ' . $ticketNumber,
                'message'      => 'تیکت «' . trim($data['subject']) . '» ثبت شد',
                'type'         => 'info',
                'link'         => '/pages/ticket-detail.php?id=' . $ticketId,
                'related_type' => 'ticket',
                'related_id'   => $ticketId,
                'sms_pattern'  => 'ticket_created',
                'sms_args'     => [$ticketNumber, $creatorName],
            ]);
        }
            return [
                'success'       => true,
                'ticket_id'     => $ticketId,
                'ticket_number' => $ticketNumber
            ];

        } catch (Exception $e) {

            $this->db->rollBack();

            error_log($e->getMessage());

            return [
                'success' => false,
                'error'   => 'خطا در ایجاد تیکت'
            ];
        }
    }

    // ─────────────────────────────────────────
    // Add Message
    // ─────────────────────────────────────────

    public function addMessage(
        int $ticketId,
        string $message,
        bool $isInternal = false,
        array $files = []
    ): array {

        $ticket = $this->repo->getTicketById(
            $ticketId,
            $this->organizationId
        );

        if (!$ticket) {
            return ['success' => false, 'error' => 'تیکت یافت نشد'];
        }

        if (empty(trim($message)) && empty($files['attachments'])) {
            return ['success' => false, 'error' => 'پیام الزامی است'];
        }

        try {

            $this->db->beginTransaction();

            $messageId = $this->repo->createMessage([
                'ticket_id'   => $ticketId,
                'user_id'     => $this->userId,
                'message'     => trim($message),
                'is_internal' => $isInternal ? 1 : 0,
            ]);

            if (!empty($files['attachments'])) {
                $this->saveAttachments(
                    $ticketId,
                    $messageId,
                    $files['attachments']
                );
            }

            if (!$isInternal) {
                $this->repo->updateTicket($ticketId, [
                    'status_id' => 3
                ]);
            }

            $this->repo->logHistory(
                $ticketId,
                $this->userId,
                $isInternal ? 'internal_note' : 'reply',
                null,
                null
            );

            $this->db->commit();

            return ['success' => true];

        } catch (Exception $e) {

            $this->db->rollBack();

            error_log($e->getMessage());

            return [
                'success' => false,
                'error'   => 'خطا در ثبت پاسخ'
            ];
        }
    }

    // ─────────────────────────────────────────
    // Update Ticket
    // ─────────────────────────────────────────

    public function updateTicket(int $ticketId, array $data): array
    {
        $ticket = $this->repo->getTicketById(
            $ticketId,
            $this->organizationId
        );

        if (!$ticket) {
            return ['success' => false, 'error' => 'تیکت یافت نشد'];
        }

        $updated = $this->repo->updateTicket($ticketId, $data);

        if (!$updated) {
            return ['success' => false, 'error' => 'بروزرسانی انجام نشد'];
        }

        return ['success' => true];
    }

    // ─────────────────────────────────────────
    // Uploads
    // ─────────────────────────────────────────

    private function saveAttachments(
        int $ticketId,
        ?int $messageId,
        array $files
    ): void {

        $allowedExtensions = [
            'jpg', 'jpeg', 'png',
            'pdf', 'txt', 'zip'
        ];

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'text/plain',
            'application/zip',
            'application/x-zip-compressed'
        ];

        foreach ($files['name'] as $index => $originalName) {

            if ($files['error'][$index] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpPath = $files['tmp_name'][$index];

            $extension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            if (!in_array($extension, $allowedExtensions)) {
                continue;
            }

            $mimeType = mime_content_type($tmpPath);

            if (!in_array($mimeType, $allowedMimeTypes)) {
                continue;
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;

            move_uploaded_file(
                $tmpPath,
                $this->uploadPath . $storedName
            );

            $this->repo->createAttachment([
                'ticket_id'    => $ticketId,
                'message_id'   => $messageId,
                'user_id'      => $this->userId,
                'original_name'=> $originalName,
                'stored_name'  => $storedName,
                'mime_type'    => $mimeType,
                'file_size'    => $files['size'][$index],
            ]);
        }
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    private function getShamsiYear(): int
    {
        return 1405;
    }
}
