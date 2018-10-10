<?php

namespace tomlankhorst\EuroFXRefCurrencyProvider;

use Brick\Math\BigDecimal;
use Brick\Money\Exception\CurrencyConversionException;
use Brick\Money\ExchangeRateProvider;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

class EuroProvider implements ExchangeRateProvider
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var string cache prefix
     */
    protected $cachePrefix = self::class;

    /**
     * @var null|int|\DateInterval $ttl
     */
    protected $ttl;

    /**
     * @var string URI to Euro Foreign Exchange Reference daily XML file
     */
    protected $euroFXRefUri = "https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml";

    /**
     * EuroFXRefProvider constructor.
     * @param ClientInterface $client
     * @param CacheInterface $cache
     * @param null|int|\DateInterval $ttl
     */
    public function __construct(ClientInterface $client, CacheInterface $cache = null, $ttl = null)
    {
        $this->cache = $cache;
        $this->client = $client;
        $this->ttl = $ttl;
    }

    /**
     * Get the exchange rate
     *
     * @param string $sourceCurrencyCode
     * @param string $targetCurrencyCode
     * @return BigDecimal
     * @throws CurrencyConversionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode)
    {
        return $this->getCachedExchangeRate($sourceCurrencyCode, $targetCurrencyCode);
    }

    /**
     * Looks up the exchange rate through the provided cache
     *
     * @param string $sourceCurrencyCode typically, 'EUR'
     * @param string $targetCurrencyCode
     * @return BigDecimal
     * @throws CurrencyConversionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function getCachedExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode)
    {
        $rates = $this->cache ?
            $this->cache->get($this->getCacheKey('rates')) :
            null;

        if($rates === null)
            $rates = $this->getCurrentExchangeRates();

        if(!array_key_exists($sourceCurrencyCode, $rates))
            throw new CurrencyConversionException("Source exchange rate not available.", $sourceCurrencyCode, $targetCurrencyCode);

        $sourceRates = $rates[$sourceCurrencyCode];

        if(!array_key_exists($targetCurrencyCode, $sourceRates))
            throw new CurrencyConversionException("Target exchange rate not available.", $sourceCurrencyCode, $targetCurrencyCode);

        return BigDecimal::of($sourceRates[$targetCurrencyCode]);
    }

    /**
     * Gets current Euro exchange rates
     *
     * @param bool $cache_result
     * @return array
     * @throws CurrencyConversionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function getCurrentExchangeRates($cache_result = true)
    {
        try {
            $response = $this->client->send(
                $this->makeEuroFXRefRequest()
            );
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            throw new CurrencyConversionException("Currency-rates not available at this time (HTTP $status).", '', '');
        } catch (RequestException $e) {
            $msg = $e->getMessage();
            throw new CurrencyConversionException("Currency-rates not available at this time ($msg).", '', '');
        }

        $rates = ['EUR' => $this->parseEuroFXRefResponse($response)];

        if($this->cache && $cache_result)
            $this->cache->set( $this->getCacheKey('rates'), $rates, $this->ttl );

        return $rates;
    }

    /**
     * Returns associative array of exchange rates
     *
     * @param ResponseInterface $response
     * @return array
     * @throws CurrencyConversionException
     */
    protected function parseEuroFXRefResponse(ResponseInterface $response)
    {
        $rates = ['EUR' => '1.0'];

        $doc = new \SimpleXMLElement(
            $response->getBody()
        );

        foreach($doc->Cube->Cube->Cube as $rate)
            $rates[(string)$rate['currency']] = (string)$rate['rate'];

        return $rates;
    }

    /**
     * Create a PSR-7 Request
     *
     * @return Request
     */
    protected function makeEuroFXRefRequest() : Request
    {
        return new Request('GET', $this->getEuroFXRefUri());
    }

    /**
     * Return the URI for the EuroFXRef request
     *
     * @return string
     */
    public function getEuroFXRefUri() : string
    {
        return $this->euroFXRefUri;
    }

    /**
     * @return string
     */
    public function getCachePrefix() : string
    {
        return $this->cachePrefix;
    }

    /**
     * @param string $prefix
     */
    public function setCachePrefix(string $prefix)
    {
        $this->cachePrefix = $prefix;
    }

    /**
     * Return the cache key
     *
     * @param string $key
     * @return string
     */
    public function getCacheKey(string $key)
    {
        return $this->getCachePrefix().'/'.$key;
    }
}
