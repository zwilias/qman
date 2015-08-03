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

## Requirements

- PHP 5.5, PHP 5.6. [`ev`](https://pecl.php.net/package/ev) does not support PHP 7 yet.
- [`ev`](https://pecl.php.net/package/ev), an interface to libev, the high performance full-featured event-loop.
- one or more instances of beanstalkd.

## Use case

QMan is optimized for multiple beanstalkd instances and a pool of workers listening to all of those.

[![HTML View on Gliffy](http://www.gliffy.com/go/publish/image/8630261/L.png)](http://www.gliffy.com/go/publish/8630261)

## Examples

### Queueing a closure

Queueing a closure is quite possibly the easiest thing 

```php
use QMan\QMan;

$qMan = QMan::create(['localhost:11300']);
$qMan->queueClosure(function () {
    echo 'Hello world!';
});
```

Essentially, this is equivalent to the following:

```php
use QMan\QMan;
use QMan\ClosureCommand;

$qMan = QMan::create(['localhost:11300']);
$qMan->queue(ClosureCommand::create(function () {
    echo 'Hello world!';
}));
```

### Working the queue

Starting a worker with all the defaults injected, is easy:

```php
use Beanie\Beanie;
use QMan\WorkerBuilder;

$beanie = Beanie::pool(['localhost:11300']);
$worker = (new WorkerBuilder())
    ->build($beanie);
    
$worker->run();
```

The `WorkerBuilder` ensures the `QMan\Worker` is setup with all of its required dependencies and configuration.

### Queueing custom commands

The `ClosureCommand`, while convenient, comes with two major downsides:

- Serializing closures is rather expensive, in terms of computational power required
- Unit-testing closures quickly devolves into a huge mess

As such, you'll quickly be writing custom commands on a regular basis.

A command should implement `QMan\CommandInterface`, which is easily done through extending `QMan\AbstractCommand`:

```php
use QMan\AbstractCommand;

class CustomCommand extends AbstractCommand
{
    public function getType()
    {
        return 'my.custom.command';
    }
    
    public function execute()
    {
        echo $this->getData() * 5;
        return true;
    }
}
```

The `getType()` function should return a string which can be uniquely mapped to the class you want to execute. This
indirection is required in order to safely handle picking up stuff like renamed classes through a simple restart of the
worker.

In order for the QMan worker to pick up and execute your command, you'll need to make sure the instance of
`CommandSerializerInterface` will pick it up. QMan comes with a generic implementation of this interface, aptly named
`GenericCommandSerializer`. Let's make sure the class we created above is properly registered:

```php
use Beanie\Beanie;
use QMan\WorkerBuilder;
use QMan\GenericCommandSerializer;

$serializer = new GenericCommandSerializer();
$serializer->registerCommandType('my.custom.command', CustomCommand::class);

$beanie = Beanie::pool(['localhost:11300']);
$worker = (new WorkerBuilder())
    ->withCommandSerializer($serializer)
    ->build($beanie);
    
$worker->run();
```

You could easily futureproof your application by gathering this type <-> class mapping, and representing the types as
constants:

```php
final class Commands
{
    const TYPE_CUSTOM_COMMAND = 'my.custom.command';
    
    public static function $map = [
        self::TYPE_CUSTOM_COMMAND => CustomCommand::class
    ];
}
```

QMan's `GenericCommandSerializer` comes with a `registerCommandTypes($map)` function which can handle exactly the case
described above.

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
