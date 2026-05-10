<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Hook;

use AutoAcceptOrders\Service\AutoAcceptService;

/**
 * Handles the WHMCS ShoppingCartCheckoutComplete hook for free (zero-amount) orders.
 */
class FreeOrderHandler
{
    /** @var AutoAcceptService */
    private $service;

    public function __construct(AutoAcceptService $service)
    {
        $this->service = $service;
    }

    /**
     * @param array $vars  WHMCS hook vars (must contain 'orderid')
     * @return array  always empty — must not break checkout flow
     */
    public function handle(array $vars): array
    {
        try {
            $orderId = isset($vars['orderid']) ? (int) $vars['orderid'] : 0;
            if ($orderId > 0) {
                $this->service->processFreeOrder($orderId);
            }
        } catch (\Throwable $e) {
            // Never interrupt the checkout flow.
        }
        return [];
    }
}
