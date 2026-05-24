<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Admin;

use AutoAcceptOrders\Contract\CsrfInterface;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Service\AutoAcceptService;

/**
 * Handles the addon admin output page: filter, pagination, and Reprocess action.
 */
class OutputController
{
    /** @var LogRepository */
    private $logRepo;

    /** @var AutoAcceptService */
    private $service;

    /** @var CsrfInterface */
    private $csrf;

    public function __construct(LogRepository $logRepo, AutoAcceptService $service, CsrfInterface $csrf)
    {
        $this->logRepo = $logRepo;
        $this->service = $service;
        $this->csrf    = $csrf;
    }

    /**
     * Handle the current request and emit HTML output.
     *
     * @param array $vars  WHMCS $vars passed to auto_accept_orders_output()
     * @return void
     */
    public function handle(array $vars): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($vars);
            return;
        }

        $this->renderGet($vars);
    }

    private function handlePost(array $vars): void
    {
        $action  = isset($_POST['aao_action']) ? (string) $_POST['aao_action'] : '';
        $adminId = isset($vars['adminid']) ? (int) $vars['adminid'] : 0;

        if ($adminId <= 0) {
            $this->sendForbidden('Forbidden: no admin session.');
            return;
        }

        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        if (!$this->csrf->validate($token)) {
            $this->sendForbidden('Forbidden: CSRF token mismatch.');
            return;
        }

        if ($action === 'reprocess') {
            $logId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($logId > 0) {
                $this->service->reprocess($logId);
            }
        }

        // Redirect back preserving filters (PRG pattern).
        $qs          = http_build_query($this->preservedFilters());
        $redirectUrl = ($vars['modulelink'] ?? '') . ($qs ? '&' . $qs : '');
        header('Location: ' . $redirectUrl);
        exit();
    }

    private function renderGet(array $vars): void
    {
        $query    = LogQuery::fromRequest($_GET);
        $logs     = $this->logRepo->query($query);
        $total    = $this->logRepo->count($query);
        $csrf     = $this->csrf->generate();
        $baseLink = $vars['modulelink'] ?? '';

        $templateVars = [
            'logs'     => $logs,
            'query'    => $query,
            'total'    => $total,
            'csrf'     => $csrf,
            'baseLink' => $baseLink,
        ];

        $template = __DIR__ . '/View/output.phtml';
        extract($templateVars, EXTR_SKIP);
        include $template;
    }

    /**
     * Emit a 403 response. Guards against headers-already-sent (e.g. test environments).
     *
     * @param string $message
     * @return void
     */
    private function sendForbidden(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }
        echo $message;
    }

    /**
     * Collect active filter params from GET to preserve across the POST redirect.
     *
     * @return array
     */
    private function preservedFilters(): array
    {
        $filters = [];
        foreach (['page', 'per_page', 'trigger', 'status', 'order_id'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = $_GET[$key];
            }
        }
        return $filters;
    }
}
