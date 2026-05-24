<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Unit;

use AutoAcceptOrders\Repository\ConfigRepository;
use AutoAcceptOrders\Tests\Stub\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AutoAcceptOrders\Repository\ConfigRepository
 */
class ConfigRepositoryTest extends TestCase
{
    private function makeDb(array $settings = []): InMemoryDatabase
    {
        $db = new InMemoryDatabase();
        $rows = [];
        $id = 1;
        foreach ($settings as $key => $value) {
            $rows[] = ['id' => $id++, 'module' => 'auto_accept_orders', 'setting' => $key, 'value' => $value];
        }
        if ($rows) {
            $db->seed('tbladdonmodules', $rows);
        }
        return $db;
    }

    /** @dataProvider enabledValueProvider */
    public function testIsEnabled_returnsTrue_forTruthyValues(string $value): void
    {
        $repo = new ConfigRepository($this->makeDb(['enabled' => $value]));
        self::assertTrue($repo->isEnabled());
    }

    public function enabledValueProvider(): array
    {
        return [['1'], ['on'], ['yes'], ['true'], ['ON'], ['YES'], ['TRUE']];
    }

    /** @dataProvider disabledValueProvider */
    public function testIsEnabled_returnsFalse_forFalsyValues(string $value): void
    {
        $repo = new ConfigRepository($this->makeDb(['enabled' => $value]));
        self::assertFalse($repo->isEnabled());
    }

    public function disabledValueProvider(): array
    {
        return [['0'], ['off'], ['no'], ['false'], ['']];
    }

    public function testIsEnabled_returnsFalse_whenSettingMissing(): void
    {
        $repo = new ConfigRepository($this->makeDb());
        self::assertFalse($repo->isEnabled());
    }

    public function testGet_returnsValue_whenSettingExists(): void
    {
        $repo = new ConfigRepository($this->makeDb(['admin_username' => 'admin1']));
        self::assertSame('admin1', $repo->get('admin_username'));
    }

    public function testGet_returnsEmptyString_whenSettingMissing(): void
    {
        $repo = new ConfigRepository($this->makeDb());
        self::assertSame('', $repo->get('admin_username'));
    }

    public function testIsVerboseLoggingEnabled_returnsTrue_whenOn(): void
    {
        $repo = new ConfigRepository($this->makeDb(['verbose_logging' => 'on']));
        self::assertTrue($repo->isVerboseLoggingEnabled());
    }

    public function testIsVerboseLoggingEnabled_returnsFalse_whenOff(): void
    {
        $repo = new ConfigRepository($this->makeDb(['verbose_logging' => '0']));
        self::assertFalse($repo->isVerboseLoggingEnabled());
    }

    public function testIsVerboseLoggingEnabled_returnsFalse_whenMissing(): void
    {
        $repo = new ConfigRepository($this->makeDb());
        self::assertFalse($repo->isVerboseLoggingEnabled());
    }
}
