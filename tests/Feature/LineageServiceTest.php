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

    public function test_fetch_balance_enriches_by_default(): void
    {
        $addresses = ['aaa111', 'bbb222'];
        $enrichedBalance = [
            'total' => ['tokens' => 0, 'items' => ['genesis0abc' => 5]],
            'address_list' => [
                'aaa111' => [[
                    'out_point' => ['t_hash' => 't0', 'n' => 0],
                    'value' => ['Item' => ['amount' => 5, 'genesis_hash' => 'genesis0abc', 'metadata' => 'ticket #1']],
                ]],
            ],
        ];

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('fetchBalance')
            ->with($addresses, true)
            ->willReturn($enrichedBalance);

        $this->app->singleton(Client::class, fn () => $mockClient);

        $result = Lineage::fetchBalance($addresses);

        $this->assertSame($enrichedBalance, $result);
        $this->assertSame(
            'ticket #1',
            $result['address_list']['aaa111'][0]['value']['Item']['metadata'],
        );
    }

    public function test_fetch_balance_opt_out_forwards_enrich_false(): void
    {
        $addresses = ['aaa111'];
        $rawBalance = ['total' => ['tokens' => 0, 'items' => []], 'address_list' => []];

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('fetchBalance')
            ->with($addresses, false)
            ->willReturn($rawBalance);

        $this->app->singleton(Client::class, fn () => $mockClient);

        $result = Lineage::fetchBalance($addresses, enrich: false);

        $this->assertSame($rawBalance, $result);
    }

    public function test_get_item_info_delegates_to_the_client(): void
    {
        $genesisHash = 'genesis0abc';
        $info = [
            'genesis_hash' => $genesisHash,
            'metadata' => 'ticket #1',
            'total_amount' => 1000,
            'created' => ['block_num' => 42, 'tx_hash' => $genesisHash],
            'creator_address' => 'addr_creator',
        ];

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('getItemInfo')
            ->with($genesisHash)
            ->willReturn($info);

        $this->app->singleton(Client::class, fn () => $mockClient);

        $result = Lineage::getItemInfo($genesisHash);

        $this->assertSame($info, $result);
    }
}
