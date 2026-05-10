<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Repository;

use AutoAcceptOrders\Contract\DatabaseInterface;

/**
 * Queries tblorders for order data.
 */
class OrderRepository
{
    /** @var DatabaseInterface */
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Return all Pending orders linked to a given invoice.
     *
     * @param int $invoiceId
     * @return array  array of stdClass{id, status}
     */
    public function getPendingOrdersByInvoice(int $invoiceId): array
    {
        return $this->db->getRows(
            'tblorders',
            ['invoiceid' => $invoiceId, 'status' => 'Pending'],
            ['id', 'status']
        );
    }

    /**
     * Return a single order by ID.
     *
     * @param int $orderId
     * @return object|null  stdClass{id, status, amount} or null
     */
    public function findById(int $orderId): ?object
    {
        $rows = $this->db->getRows(
            'tblorders',
            ['id' => $orderId],
            ['id', 'status', 'amount']
        );

        return !empty($rows) ? (object) $rows[0] : null;
    }
}
