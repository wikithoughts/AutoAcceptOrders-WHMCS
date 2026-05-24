<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Stub;

use AutoAcceptOrders\Contract\ApiInterface;

/**
 * Programmable API stub for tests.
 */
class ArrayApi implements ApiInterface
{
    /** @var array */
    private $calls = [];

    /** @var array */
    private $response;

    public function __construct(array $response = ['result' => 'success'])
    {
        $this->response = $response;
    }

    public function call(string $command, array $params, string $adminUsername): array
    {
        $this->calls[] = compact('command', 'params', 'adminUsername');
        return $this->response;
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function count(): int
    {
        return count($this->calls);
    }
}
