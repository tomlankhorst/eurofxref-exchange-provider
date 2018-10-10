EuroFXRef Exchange Provider
======

[![Build Status](https://travis-ci.org/tomlankhorst/eurofxref-exchange-provider.svg?branch=master)](https://travis-ci.org/tomlankhorst/eurofxref-exchange-provider)
[![codecov](https://codecov.io/gh/tomlankhorst/eurofxref-exchange-provider/branch/master/graph/badge.svg)](https://codecov.io/gh/tomlankhorst/eurofxref-exchange-provider)

Conversion provider for [brick/money](https://github.com/brick/money) that uses ECB's daily reference rates.

    https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
    
Uses PSR-16 SimpleCache and PSR-7 HTTP-Messages.
Typically works with GuzzleHTTP and a cache of choice. 
