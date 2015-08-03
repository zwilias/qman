# QMan
> An evented beanstalkd queue manager.

[![Build Status](https://travis-ci.org/zwilias/qman.svg?branch=master)](https://travis-ci.org/zwilias/qman)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/zwilias/qman/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/zwilias/qman/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/zwilias/qman/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/zwilias/qman/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/zwilias/qman/v/stable)](https://packagist.org/packages/zwilias/qman) 
[![Total Downloads](https://poser.pugx.org/zwilias/qman/downloads)](https://packagist.org/packages/zwilias/qman) 
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/8a6ff0bb-5059-4cc2-96a4-e71d8f916826/mini.png)](https://insight.sensiolabs.com/projects/8a6ff0bb-5059-4cc2-96a4-e71d8f916826)

## Core features

- Sane defaults, highly extensible and configurable
- Allows effortless reserving from a connection pool
- Supports graceful shutdown upon receiving a signal
- Protects job-execution from unexpected intrusions
- Extensible job-failure handling
- Built-in support for fine-grained PSR-3 logging
- Built-in support for queueing closures

## Examples

[...]

## Contributing

Pull requests are appreciated. Make sure code-quality (according to [scrutinizer](https://scrutinizer-ci.com/)) doesn't 
suffer too badly and all code is thoroughly unit-tested.

Running the tests locally:

```
$ git clone https://github.com/zwilias/qman.git
$ cd qman
$ composer install
$ vendor/bin/phpunit
```

## License

Copyright (c) 2015 Ilias Van Peer

Released under the MIT License, see the enclosed `LICENSE` file.
