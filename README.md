# ProcessManager

[![Build Status](https://github.com/Innmind/ProcessManager/workflows/CI/badge.svg)](https://github.com/Innmind/ProcessManager/actions?query=workflow%3ACI)
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
use Innmind\Immutable\Sequence;
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
    new Parallel($runner),
    static function(Parallel $parallel, string $url) use ($http): Parallel {
        return $parallel->schedule(static function() use ($http, $url): void {
            \file_put_contents(
                '/tmp/'.md5($url),
                (string) $http->get($url)->getBody(),
            );
        });
    }
);
$crawl = $crawl();
echo 'These urls are being crawled in parallel: '.$urls->join(', ');
$crawl->wait();
```

This sample will crawl the 3 urls in parallel via sub processes.

**Important**: with this code you cannot return values, if you want to return content to the parent process you need to implement IPC over socket or shared memory (this may be implemented in future versions).

As you may have noticed `Parallel::__invoke()` return a new instance, this means that this code `$crawl() && $crawl->wait()` will not wait for the sub processes to finish. This behaviour is implemented so you can safely re-run a set a jobs, in other words you can do `$crawl()->wait() && $crawl()->wait()` (which will crawl each url twice).

### `Pool`

`Pool` implements the same interface as `Parallel`, but you need to specify the maximum number of sub processes you want to allow, ie `new Pool(2, $runner)` will allow at most 2 sub processes in parallel.

**Important**: when you `invoke` your pool only the first `n` scheduled functions will be called, you absolutely need to call the `wait` method so the remaining functions are called.

Example:

```php
use Innmind\ProcessManager\Manager\Pool;

$pool = (new Pool(2, $runner, $os->sockets()))
    ->schedule(function() {
        sleep(10);
    })
    ->schedule(function() {
        sleep(5);
    })
    ->schedule(function() {
        sleep(60);
    });
//no process started yet, same behaviour as Parallel
$pool = $pool();
//first two functions are started as sub processes
/*
do some code that last more than 10 seconds...
 */
//third function still not started
$pool->wait(); //this will run all the remaining functions
```
