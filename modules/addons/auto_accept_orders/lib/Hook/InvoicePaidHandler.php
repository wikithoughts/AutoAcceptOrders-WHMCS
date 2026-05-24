<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Hook;

use AutoAcceptOrders\Service\AutoAcceptService;

/**
 * Handles the WHMCS InvoicePaid hook.
 */
class InvoicePaidHandler
{
    /** @var AutoAcceptService */
    private $service;

    public function __construct(AutoAcceptService $service)
    {
        $this->service = $service;
    }

    /**
     * @param array $vars  WHMCS hook vars (must contain 'invoiceid')
     * @return void
     */
    public function handle(array $vars): void
    {
        $invoiceId = isset($vars['invoiceid']) ? (int) $vars['invoiceid'] : 0;
        if ($invoiceId <= 0) {
            return;
        }

        $this->service->processInvoicePaid($invoiceId);
    }
}
