<?php

namespace Lineage\Tests\Feature;

use Lineage\Client;
use Lineage\Exceptions\NotImplemented;
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

    public function test_create_trade_request_throws_not_implemented(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->never())->method('createTradeRequest');

        $this->app->singleton(Client::class, fn () => $mockClient);

        $this->expectException(NotImplemented::class);

        Lineage::createTradeRequest(
            otherPartyAddress: 'other-address',
            myAsset: null,
            myAddress: 'my-address',
            otherPartyAsset: null,
        );
    }
}
