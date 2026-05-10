<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Admin;

/**
 * Immutable value object representing filter + pagination state for log queries.
 */
class LogQuery
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    public const DEFAULT_PER_PAGE = 25;

    /** @var int */
    public $page;

    /** @var int */
    public $perPage;

    /** @var string '' means no filter */
    public $trigger;

    /** @var string '' means no filter; 'PENDING'|'OK'|'ERROR' */
    public $status;

    /** @var int|null null means no filter */
    public $orderId;

    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        string $trigger = '',
        string $status = '',
        ?int $orderId = null
    ) {
        $this->page    = max(1, $page);
        $this->perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
        $this->trigger = in_array($trigger, ['InvoicePaid', 'FreeOrder'], true) ? $trigger : '';
        $this->status  = in_array($status, ['PENDING', 'OK', 'ERROR'], true) ? $status : '';
        $this->orderId = $orderId !== null && $orderId > 0 ? $orderId : null;
    }

    /**
     * Build a LogQuery from raw GET parameters.
     *
     * @param array $params typically $_GET
     * @return self
     */
    public static function fromRequest(array $params): self
    {
        return new self(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['per_page']) ? (int) $params['per_page'] : self::DEFAULT_PER_PAGE,
            isset($params['trigger']) ? (string) $params['trigger'] : '',
            isset($params['status']) ? (string) $params['status'] : '',
            isset($params['order_id']) && $params['order_id'] !== '' ? (int) $params['order_id'] : null
        );
    }

    /**
     * Encode this query as a URL query string, optionally overriding specific keys.
     *
     * @param array $overrides
     * @return string  e.g. "page=2&per_page=25&trigger=InvoicePaid"
     */
    public function toQueryString(array $overrides = []): string
    {
        $params = [
            'page'     => $this->page,
            'per_page' => $this->perPage,
        ];

        if ($this->trigger !== '') {
            $params['trigger'] = $this->trigger;
        }
        if ($this->status !== '') {
            $params['status'] = $this->status;
        }
        if ($this->orderId !== null) {
            $params['order_id'] = $this->orderId;
        }

        return http_build_query(array_merge($params, $overrides));
    }
}
