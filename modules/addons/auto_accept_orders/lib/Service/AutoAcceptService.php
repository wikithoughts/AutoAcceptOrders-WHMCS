<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Service;

use AutoAcceptOrders\Contract\ApiInterface;
use AutoAcceptOrders\Contract\LoggerInterface;
use AutoAcceptOrders\Repository\AdminRepository;
use AutoAcceptOrders\Repository\ConfigRepository;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Repository\OrderRepository;

/**
 * Core orchestration: claim, accept, finalize, reprocess.
 */
class AutoAcceptService
{
    private const MODULE              = 'auto_accept_orders';
    private const FREE_ORDER_TRIGGER  = 'FreeOrder';
    private const INVOICE_TRIGGER     = 'InvoicePaid';
    private const FREE_ORDER_TOLERANCE = 0.005;

    /** @var ConfigRepository */
    private $config;

    /** @var AdminRepository */
    private $adminRepo;

    /** @var LogRepository */
    private $logRepo;

    /** @var OrderRepository */
    private $orderRepo;

    /** @var ApiInterface */
    private $api;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ConfigRepository $config,
        AdminRepository $adminRepo,
        LogRepository $logRepo,
        OrderRepository $orderRepo,
        ApiInterface $api,
        LoggerInterface $logger
    ) {
        $this->config    = $config;
        $this->adminRepo = $adminRepo;
        $this->logRepo   = $logRepo;
        $this->orderRepo = $orderRepo;
        $this->api       = $api;
        $this->logger    = $logger;
    }

    /**
     * Process all pending orders linked to a paid invoice.
     *
     * @param int $invoiceId
     * @return void
     */
    public function processInvoicePaid(int $invoiceId): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $adminUsername = $this->adminRepo->resolveAdminUsername($this->config->get('admin_username'));
        if ($adminUsername === null) {
            return;
        }

        $orders = $this->orderRepo->getPendingOrdersByInvoice($invoiceId);

        foreach ($orders as $order) {
            $orderId = (int) $order->id;
            $logId   = $this->logRepo->claimOrder($orderId, self::INVOICE_TRIGGER);
            if ($logId === null) {
                continue;
            }

            $this->acceptAndFinalize($orderId, $logId, $adminUsername);
        }
    }

    /**
     * Process a free (zero-amount) order from the checkout flow.
     *
     * @param int $orderId
     * @return void
     */
    public function processFreeOrder(int $orderId): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $adminUsername = $this->adminRepo->resolveAdminUsername($this->config->get('admin_username'));
        if ($adminUsername === null) {
            return;
        }

        $order = $this->orderRepo->findById($orderId);

        if ($order === null || $order->status !== 'Pending') {
            return;
        }

        if (abs((float) $order->amount) >= self::FREE_ORDER_TOLERANCE) {
            return;
        }

        $logId = $this->logRepo->claimOrder($orderId, self::FREE_ORDER_TRIGGER);
        if ($logId === null) {
            return;
        }

        $this->acceptAndFinalize($orderId, $logId, $adminUsername);
    }

    /**
     * Re-run AcceptOrder for a specific log row (admin "Reprocess" action).
     *
     * @param int $logId
     * @return void
     */
    public function reprocess(int $logId): void
    {
        $log = $this->logRepo->findById($logId);
        if ($log === null) {
            return;
        }

        $adminUsername = $this->adminRepo->resolveAdminUsername($this->config->get('admin_username'));
        if ($adminUsername === null) {
            return;
        }

        $request  = ['orderid' => (int) $log->order_id];
        $response = $this->api->call('AcceptOrder', $request, $adminUsername);
        $this->logRepo->finalizeLog($logId, $response);

        if ($this->config->isVerboseLoggingEnabled()) {
            $this->logger->log(self::MODULE, 'accept_success', $request, $response);
        }
    }

    /**
     * Call the WHMCS AcceptOrder API and finalize the log row.
     *
     * @param int    $orderId
     * @param int    $logId
     * @param string $adminUsername
     * @return void
     */
    private function acceptAndFinalize(int $orderId, int $logId, string $adminUsername): void
    {
        $request  = ['orderid' => $orderId];
        $response = $this->api->call('AcceptOrder', $request, $adminUsername);
        $this->logRepo->finalizeLog($logId, $response);

        if ($this->config->isVerboseLoggingEnabled()) {
            $this->logger->log(self::MODULE, 'accept_success', $request, $response);
        }
    }
}
