<?php

namespace Lineage\Tests\Feature;

use Lineage\Client;
use Lineage\Facades\LineageFacade as Lineage;
use Lineage\Tests\TestCase;

class LineageServiceTest extends TestCase
{
    public function test_fetch_balance_delegates_to_the_client(): void
    {
        $addresses = ['aaa111', 'bbb222'];
        $balance = ['total' => ['tokens' => 1000, 'items' => []]];

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('fetchBalance')
            ->with($addresses)
            ->willReturn($balance);

        $this->app->singleton(Client::class, fn () => $mockClient);

        $result = Lineage::fetchBalance($addresses);

        $this->assertSame($balance, $result);
    }
}
