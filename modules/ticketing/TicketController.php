<?php

class TicketController
{
    private TicketService $service;
    private TicketRepository $repo;

    public function __construct(
        TicketService $service,
        TicketRepository $repo
    ) {
        $this->service = $service;
        $this->repo    = $repo;
    }

    // ─────────────────────────────────────────
    // List
    // ─────────────────────────────────────────

    public function index(): void
    {
        $orgId = $_SESSION['organization_id'];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search'      => $_GET['search'] ?? '',
            'status_id'   => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'sort'        => $_GET['sort'] ?? 'updated_at',
            'dir'         => $_GET['dir'] ?? 'desc',
            'limit'       => $limit,
            'offset'      => $offset,
        ];

        $tickets = $this->repo->getTickets($orgId, $filters);
        $total   = $this->repo->countTickets($orgId, $filters);

        include __DIR__ . '/views/list.php';
    }

    // ─────────────────────────────────────────
    // Create Form
    // ─────────────────────────────────────────

    public function create(): void
    {
        $categories = $this->repo->getCategories(
            $_SESSION['organization_id']
        );

        $priorities = $this->repo->getPriorities();

        include __DIR__ . '/views/create.php';
    }

    // ─────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────

    public function store(): void
    {
        $this->validateCsrf();

        $result = $this->service->createTicket(
            $_POST,
            $_FILES
        );

        if (!$result['success']) {

            $_SESSION['error'] = $result['error'];

            header('Location: ?module=ticketing&action=create');
            exit;
        }

        $_SESSION['success'] =
            'تیکت با موفقیت ایجاد شد: ' .
            $result['ticket_number'];

        header(
            'Location: ?module=ticketing&action=view&id=' .
            $result['ticket_id']
        );
        exit;
    }

    // ─────────────────────────────────────────
    // View
    // ─────────────────────────────────────────

    public function view(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        $ticket = $this->repo->getTicketById(
            $id,
            $_SESSION['organization_id']
        );

        if (!$ticket) {
            http_response_code(404);
            exit('Ticket not found');
        }

        $messages = $this->repo->getMessages($id, true);
        $attachments = $this->repo->getAttachments($id);
        $history = $this->repo->getHistory($id);

        include __DIR__ . '/views/view.php';
    }

    // ─────────────────────────────────────────
    // Reply
    // ─────────────────────────────────────────

    public function reply(): void
    {
        $this->validateCsrf();

        $ticketId = (int)$_POST['ticket_id'];

        $result = $this->service->addMessage(
            $ticketId,
            $_POST['message'] ?? '',
            !empty($_POST['is_internal']),
            $_FILES
        );

        if (!$result['success']) {
            $_SESSION['error'] = $result['error'];
        }

        header(
            'Location: ?module=ticketing&action=view&id=' .
            $ticketId
        );
        exit;
    }

    // ─────────────────────────────────────────
    // CSRF
    // ─────────────────────────────────────────

    private function validateCsrf(): void
    {
        $token = $_POST['_token'] ?? '';

        if (
            empty($_SESSION['_token']) ||
            !hash_equals($_SESSION['_token'], $token)
        ) {
            http_response_code(403);
            exit('CSRF validation failed');
        }
    }
}
