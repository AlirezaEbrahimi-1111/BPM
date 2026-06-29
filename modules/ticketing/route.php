<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../database.php';

require_once __DIR__ . '/TicketRepository.php';
require_once __DIR__ . '/TicketService.php';
require_once __DIR__ . '/TicketController.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}

$db = Database::getConnection();

$repo = new TicketRepository($db);

$service = new TicketService(
    $db,
    $repo,
    (int)$_SESSION['organization_id'],
    (int)$_SESSION['user_id']
);

$controller = new TicketController(
    $service,
    $repo
);

$action = $_GET['action'] ?? 'index';

switch ($action) {

    case 'create':
        $controller->create();
        break;

    case 'store':
        $controller->store();
        break;

    case 'view':
        $controller->view();
        break;

    case 'reply':
        $controller->reply();
        break;

    default:
        $controller->index();
        break;
}
