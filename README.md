EuroFXRef Exchange Provider
======

[![Build Status](https://travis-ci.org/tomlankhorst/eurofxref-exchange-provider.svg?branch=master)](https://travis-ci.org/tomlankhorst/eurofxref-exchange-provider)
[![codecov](https://codecov.io/gh/tomlankhorst/eurofxref-exchange-provider/branch/master/graph/badge.svg)](https://codecov.io/gh/tomlankhorst/eurofxref-exchange-provider)

Conversion provider for [brick/money](https://github.com/brick/money) that uses ECB's daily reference rates.

    https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
    
Uses PSR-16 SimpleCache and PSR-7 HTTP-Messages.
Typically works with GuzzleHTTP and a cache of choice. 

Using the provider in your project
----

Require the package in your project, besides brick/money. 
```
composer require tomlankhorst/eurofxref-exchange-provider ^1.0
```

Register the `EuroProvider` as the `ExchangeRateProvider` of choise. In Laravel:
```php
<?php 

namespace App\Providers;

use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\ServiceProvider;
use Psr\SimpleCache\CacheInterface;
use tomlankhorst\EuroFXRefExchangeProvider\EuroProvider;

class MoneyServiceProvider extends ServiceProvider
{
    public function register()
    {
	// PSR-7 HTTP Messages
        $this->app->bind(ClientInterface::class, Client::class);
	// PSR-16 SimpleCache
        $this->app->bind(CacheInterface::class, Repository::class);

	// Bind the ExchangeProvider to the EuroProvider with a BaseCurrencyProvider
	// wrapper because it is a one-way conversion in this case.
        $this->app->bind(ExchangeRateProvider::class, function(Container $app){
            $provider = $app->makeWith(EuroProvider::class, [
		// Do not forget to set the TTL
                'ttl' => config('services.eurofxref.cache.ttl')
            ]);
            return new ExchangeRateProvider\BaseCurrencyProvider($provider, 'EUR');
        });

        $this->app->singleton(CurrencyConverter::class, function(Container $app){
            return new CurrencyConverter($app->make(ExchangeRateProvider::class));
        });
    }
}
```

Running Tests
-----

Install the dependencies and run PHPUnit

```
composer install
./vendor/bin/phpunit
```
