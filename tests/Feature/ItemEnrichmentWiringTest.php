<?php

namespace Lineage\Tests\Feature;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Lineage\Client;
use Lineage\Exceptions\LineageApiException;
use Lineage\Facades\LineageFacade as Lineage;
use Lineage\Tests\TestCase;

class ItemEnrichmentWiringTest extends TestCase
{
    /**
     * @param list<Response> $responses
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface}> $history captured by reference
     */
    private function bindClientWithMockedHttp(array $responses, array &$history): void
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        // Real sdk-php Client, built exactly as the service provider builds it,
        // so it resolves against config('lineage.storage_host') == http://storage.test.
        $client = new Client(
            mempoolHost: config('lineage.mempool_host'),
            storageHost: config('lineage.storage_host'),
            apiKey: config('lineage.api_key'),
        );
        $client->setHttpClient(new HttpClient(['handler' => $stack]));

        $this->app->singleton(Client::class, fn () => $client);
    }

    private function balanceResponse(string $genesisHash, ?string $inlineMetadata = null): Response
    {
        return new Response(200, [], json_encode([
            'balance' => [
                'total' => ['tokens' => 0, 'items' => [$genesisHash => 5]],
                'address_list' => [
                    'addr1' => [[
                        'out_point' => ['t_hash' => 't0', 'n' => 0],
                        'value' => ['Item' => [
                            'amount' => 5,
                            'genesis_hash' => $genesisHash,
                            'metadata' => $inlineMetadata,
                        ]],
                    ]],
                    'addr2' => [[
                        'out_point' => ['t_hash' => 't1', 'n' => 0],
                        'value' => ['Item' => [
                            'amount' => 3,
                            'genesis_hash' => $genesisHash,
                            'metadata' => $inlineMetadata,
                        ]],
                    ]],
                ],
            ],
        ]));
    }

    private function itemInfoResponse(string $genesisHash): Response
    {
        return new Response(200, [], json_encode([
            'genesis_hash' => $genesisHash,
            'metadata' => 'ticket #1',
            'total_amount' => 1000,
            'created' => ['block_num' => 42, 'tx_hash' => $genesisHash],
            'creator_address' => 'addr_creator',
        ]));
    }

    /** @return list<string> the request target paths, in order */
    private function paths(array $history): array
    {
        return array_map(
            static fn ($tx) => $tx['request']->getUri()->getPath(),
            $history,
        );
    }

    public function test_fetch_balance_resolves_against_the_configured_storage_host_and_attaches_metadata(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        $this->bindClientWithMockedHttp(
            [$this->balanceResponse($genesisHash), $this->itemInfoResponse($genesisHash)],
            $history,
        );

        $balance = Lineage::fetchBalance(['addr1']);

        $this->assertSame('ticket #1', $balance['address_list']['addr1'][0]['value']['Item']['metadata']);

        // The resolver GET must have gone to the STORAGE host, not the mempool host.
        $resolverTx = $history[1];
        $this->assertSame('http://storage.test', (string) $resolverTx['request']->getUri()->withPath('')->withQuery(''));
        $this->assertSame("/v1/items/{$genesisHash}", $resolverTx['request']->getUri()->getPath());
    }

    public function test_distinct_genesis_hash_is_resolved_exactly_once_across_addresses_and_repeat_listings(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        // Two addresses share one genesis_hash, and we list twice: still ONE resolver call.
        $this->bindClientWithMockedHttp(
            [
                $this->balanceResponse($genesisHash), // 1st listing balance
                $this->itemInfoResponse($genesisHash), // the one and only resolver call
                $this->balanceResponse($genesisHash), // 2nd listing balance (served from cache, no resolver)
            ],
            $history,
        );

        Lineage::fetchBalance(['addr1', 'addr2']);
        Lineage::fetchBalance(['addr1', 'addr2']);

        $resolverCalls = array_filter(
            $this->paths($history),
            static fn (string $path) => str_starts_with($path, '/v1/items/'),
        );
        $this->assertCount(1, $resolverCalls, 'expected exactly one resolver call for the distinct genesis_hash');
    }

    public function test_resolver_failure_degrades_to_null_metadata_and_listing_still_succeeds(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        $this->bindClientWithMockedHttp(
            [$this->balanceResponse($genesisHash), new Response(500, [], '')],
            $history,
        );

        $balance = Lineage::fetchBalance(['addr1']);

        $this->assertNull($balance['address_list']['addr1'][0]['value']['Item']['metadata']);
    }

    public function test_resolve_miss_does_not_clobber_inline_metadata(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        // Item already carries inline metadata; resolver 404s (unknown) -> keep the inline value.
        $this->bindClientWithMockedHttp(
            [$this->balanceResponse($genesisHash, 'creator-inline'), new Response(404, [], '')],
            $history,
        );

        $balance = Lineage::fetchBalance(['addr1']);

        $this->assertSame('creator-inline', $balance['address_list']['addr1'][0]['value']['Item']['metadata']);
    }

    public function test_opt_out_issues_zero_resolver_calls(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        // Only the balance response is queued; a resolver call would exhaust the
        // MockHandler and throw, failing the test.
        $this->bindClientWithMockedHttp([$this->balanceResponse($genesisHash)], $history);

        $balance = Lineage::fetchBalance(['addr1'], enrich: false);

        $resolverCalls = array_filter(
            $this->paths($history),
            static fn (string $path) => str_starts_with($path, '/v1/items/'),
        );
        $this->assertCount(0, $resolverCalls, 'opt-out must issue no resolver calls');
        $this->assertNull($balance['address_list']['addr1'][0]['value']['Item']['metadata']);
    }

    public function test_get_item_info_returns_full_facts_and_caches_the_success(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        // One resolver response only: the second getItemInfo must be served from cache.
        $this->bindClientWithMockedHttp([$this->itemInfoResponse($genesisHash)], $history);

        $first = Lineage::getItemInfo($genesisHash);
        $second = Lineage::getItemInfo($genesisHash);

        $this->assertSame('ticket #1', $first['metadata']);
        $this->assertSame(1000, $first['total_amount']);
        $this->assertSame(42, $first['created']['block_num']);
        $this->assertSame('addr_creator', $first['creator_address']);
        $this->assertSame($first, $second);

        $resolverCalls = array_filter(
            $this->paths($history),
            static fn (string $path) => str_starts_with($path, '/v1/items/'),
        );
        $this->assertCount(1, $resolverCalls, 'a cached getItemInfo must not re-hit the network');
    }

    public function test_get_item_info_404_throws_and_is_not_cached(): void
    {
        $genesisHash = 'genesis0abc';
        $history = [];
        $this->bindClientWithMockedHttp([new Response(404, [], '')], $history);

        $this->expectException(LineageApiException::class);

        try {
            Lineage::getItemInfo($genesisHash);
        } catch (LineageApiException $e) {
            $this->assertSame(404, $e->getStatus());

            throw $e;
        }
    }
}
