<?php

use Amp\Coroutine;
use Amp\Loop;
use Amp\Process\Process;
use Phpactor\TestUtils\Workspace;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tests\Helper\ProcessHelperTest;


require __DIR__ . '/../vendor/autoload.php';

$repos = [
    'git@github.com:phpactor/worse-reflection',
    'git@github.com:phpactor/completion',
    'git@github.com:dantleech/fink',
    'git@github.com:phpactor/worse-reflection-extension',
    'git@github.com:phpactor/config-loader',
    'git@github.com:phpactor/class-to-file',
    'git@github.com:phpactor/class-to-file-extension',
];

Loop::run(function () use ($repos) {
    $workspace = Workspace::create(__DIR__ . '/../Workspace');
    $workspace->reset();

    $builder = new Builder($workspace);

    $promises = [];
    foreach ($repos as $url) {
        $promises[] = Amp\call(function () use ($url, $builder) {
            return yield from $builder->build($url);
        });
    }

    $results = yield Amp\Promise\all($promises);

    echo PHP_EOL . PHP_EOL . PHP_EOL . 'THE RESULTS ARE IN!!!' . PHP_EOL . PHP_EOL;

    foreach ($repos as $index => $url) {
        echo $url . ' exited with ' . $results[$index] . PHP_EOL;
    }
});

class Builder
{
    /**
     * @var Workspace
     */
    private $workspace;

    public function __construct(Workspace $workspace) 
    {
        $this->workspace = $workspace;
    }

    public function build(string $url): Generator
    {
        $destination = substr($url, strpos($url, '/') + 1);

        $status = 0;
        $status += yield from $this->runCommand(sprintf('git clone %s %s', $url, $destination));
        $status += yield from $this->runCommand(sprintf('composer install --quiet'), $destination);
        $status += yield from $this->runCommand(sprintf('./vendor/bin/phpunit'), $destination);
        $status += yield from $this->runCommand(sprintf('./vendor/bin/phpstan analyse --level=1 lib'), $destination);

        if ($status === 0) {
            return $status;
        }

        return $status;
    }

    private function runCommand(string $command, $path = '/'): Generator
    {
        echo '>> ' . $path . ': Running ' . $command . PHP_EOL;
        $process = new Process($command, $this->workspace->path($path));
        
        $result = yield $process->start();
        $err = $process->getStderr();
        $std = $process->getStdout();

        foreach ([$err, $std] as $output) {
            while ($buffer = yield $output->read()) {
                echo $buffer;
            }
        }

        return yield $process->join();
    }
}
