<?php

namespace tests;

use Brick\Money\Exception\CurrencyConversionException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

use \Mockery as m;
use tomlankhorst\EuroFXRefCurrencyProvider\EuroProvider;

final class EuroProviderTest extends TestCase
{
    protected $repository = [];
    protected $client_history = [];

    public function testNoCache()
    {
        $provider = $this->getProvider(
            null,
            false
        );

        $rate = $provider->getExchangeRate('EUR', 'EUR');

        $this->assertEquals(1.0, $rate->toFloat());
    }

    public function testSetCachePrefix()
    {
        $provider = $this->getProvider();
        $provider->setCachePrefix($prefix = 'someprefix');
        $this->assertEquals($prefix, $provider->getCachePrefix());
        $this->assertStringStartsWith($prefix, $provider->getCacheKey('somekey'));
    }

    public function testHttpError()
    {
        $provider = $this->getProvider(
            $this->getClient([
                new Response(404)
            ])
        );

        $this->expectException(CurrencyConversionException::class);
        $provider->getExchangeRate('EUR', 'EUR');
    }

    public function testRequestError()
    {
        $provider = $this->getProvider(
            $this->getClient([
                new RequestException("Error Communicating with Server", new Request('GET', '/bogus'))
            ])
        );

        $this->expectException(CurrencyConversionException::class);
        $provider->getExchangeRate('EUR', 'EUR');
    }

    public function testCache()
    {
        $provider = $this->getProvider(null, null, $ttl = 1337);

        // The first call requests the XML file
        $provider->getExchangeRate('EUR', 'EUR');
        $this->assertEquals(1, count($this->client_history));

        // Test key existence
        $key = $provider->getCacheKey('rates');
        $this->assertArrayHasKey(
            $key,
            $this->repository
        );

        // Test TTL value
        $this->assertEquals($ttl, $this->repository[$key]['ttl'], 'TTL value not correctly set.');

        // The next call should cache
        $provider->getExchangeRate('EUR', 'EUR');
        $this->assertEquals(1, count($this->client_history));
    }

    public function testGetEurEur()
    {
        $rate = $this->getProvider()
            ->getExchangeRate('EUR', 'EUR');

        $this->assertEquals(1.0, $rate->toFloat());
    }

    public function testGetEurIdr()
    {
        $rate = $this->getProvider()
            ->getExchangeRate('EUR', 'IDR');

        $this->assertEquals(17486, $rate->toFloat());
    }

    public function testGetEurUsd()
    {
        $rate = $this->getProvider()
            ->getExchangeRate('EUR', 'USD');

        $this->assertEquals(1.1435, $rate->toFloat());
    }

    public function testGetUsdEur()
    {
        $this->expectException(CurrencyConversionException::class);

        $this->getProvider()
            ->getExchangeRate('USD', 'EUR');
    }

    public function testGetEurBogus()
    {
        $this->expectException(CurrencyConversionException::class);

        $this->getProvider()
            ->getExchangeRate('EUR', 'XXX');
    }

    protected function getProvider($client = null, $cache = null, $ttl = null) : EuroProvider
    {
        $cache = $cache     ?? $this->getCache();
        $client = $client   ?? $this->getClient();

        return new EuroProvider(
            $client ? $client : null,
            $cache ? $cache : null,
            $ttl
        );
    }

    protected function getCache()
    {
        $cache = m::mock(\Psr\SimpleCache\CacheInterface::class);

        $cache->shouldReceive('set')
            ->andReturnUsing(function($key, $value, $ttl = null) {
                $this->repository[$key] = compact('value', 'ttl');
            });

        $cache->shouldReceive('get')
            ->andReturnUsing(function($key, $default = null) {
                return $this->repository[$key]['value'] ?? $default;
            });

        return $cache;
    }

    protected function getClient($responses = null)
    {
        $eurofxref = file_get_contents('tests/eurofxref-daily.xml');

        $handler = HandlerStack::create(
            new MockHandler($responses ?? [
                new Response(200, [], $eurofxref)
            ])
        );

        $handler->push(Middleware::history($this->client_history));

        return new Client(compact('handler'));
    }
}