# ProcessManager

[![Build Status](https://github.com/Innmind/ProcessManager/workflows/CI/badge.svg?branch=master)](https://github.com/Innmind/ProcessManager/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/Innmind/ProcessManager/branch/develop/graph/badge.svg)](https://codecov.io/gh/Innmind/ProcessManager)
[![Type Coverage](https://shepherd.dev/github/Innmind/ProcessManager/coverage.svg)](https://shepherd.dev/github/Innmind/ProcessManager)

Simple library to execute code in parallel thanks to `pcntl_fork`.

## Installation

```sh
composer require innmind/process-manager
```

## Usage

```php
use Innmind\ProcessManager\{
    Manager\Parallel,
    Runner\SubProcess,
};
use Innmind\OperatingSystem\Factory;
use Innmind\Immutable\{
    Sequence,
    Str,
};
use GuzzleHttp\Client;

$urls = Sequence::strings(
    'http://google.com',
    'http://github.com',
    'http://wikipedia.org'
);
$http = new Client;
$os = Factory::buid();
$runner = new SubProcess($os->process());
$crawl = $urls->reduce(
    Parallel::of($runner),
    static function(Parallel $parallel, string $url) use ($http): Parallel {
        return $parallel->schedule(static function() use ($http, $url): void {
            \file_put_contents(
                '/tmp/'.md5($url),
                (string) $http->get($url)->getBody(),
            );
        });
    }
);
$crawling = $crawl->start()->match(
    static fn($crawling) => $crawling,
    static fn() => throw new \RuntimeException('Failed to start crawlers'),
);
echo 'These urls are being crawled in parallel: '.Str::of(', ')->join($urls);
$crawling->wait()->match(
    static fn() => null, // finished
    static fn() => throw new \RuntimeException('A process failed'),
);
```

This sample will crawl the 3 urls in parallel via sub processes.

**Important**: with this code you cannot return values, if you want to return content to the parent process you need to implement IPC over socket or shared memory (this may be implemented in future versions).

### `Pool`

`Pool` implements the same interface as `Parallel`, but you need to specify the maximum number of sub processes you want to allow, ie `Pool::of(2, $runner, $sockets)` will allow at most 2 sub processes in parallel.

**Important**: when you start your pool only the first `n` scheduled functions will be called, you absolutely need to call the `wait` method so the remaining functions are called.

Example:

```php
use Innmind\ProcessManager\Manager\Pool;

$pool = Pool::of(2, $runner, $os->sockets())
    ->schedule(function() {
        sleep(10);
    })
    ->schedule(function() {
        sleep(5);
    })
    ->schedule(function() {
        sleep(60);
    });
// no process started yet, same behaviour as Parallel
$running = $pool->start()->match(
    static fn($running) => $running,
    static fn() => throw new \RuntimeException,
);
// first two functions are started as sub processes
/*
do some code that last more than 10 seconds...
 */
// third function still not started
$pool->wait()->match( // this will run all the remaining functions
    static fn() => null, // finished
    static fn() => throw new \RuntimeException,
);
```
