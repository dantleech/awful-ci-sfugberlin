<?php

use Amp\Loop;
use Amp\Process\Process;
use Amp\Promise;
use Phpactor\TestUtils\Workspace;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {

    $workspace = Workspace::create(__DIR__ . '/../Workspace');
    $workspace->reset();

    $repos = [
        'git@github.com:dantleech/fink',
        'git@github.com:phpbench/phpbench',
        'git@github.com:phpactor/config-loader',
    ];

    $builder = new Builder($workspace);

    $promises = [];
    foreach ($repos as $repo) {
        $promises[] = Amp\call(function () use ($repo, $builder) {

            return yield from $builder->build($repo);

        });
    }

    $exitCodes = yield $promises;

    foreach ($repos as $index => $repo) {
        echo PHP_EOL . $repo . ' has exit code  ' .$exitCodes[$index] . PHP_EOL . PHP_EOL;
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

    public function build(string $repo): Generator
    {
        $subPath = substr($repo, strrpos($repo, '/') + 1);

        $exitCode = 0;
        $exitCode =+ yield from $this->execute(sprintf('git clone %s', $repo));
        $exitCode =+ yield from $this->execute(sprintf('composer install', $repo), $subPath);
        $exitCode =+ yield from $this->execute(sprintf('./vendor/bin/phpstan analyse --level=1 lib/', $repo), $subPath);

        return $exitCode;
    }

    private function execute(string $command, string $subPath = '/'): Generator
    {
        echo $command . PHP_EOL;
        $process = new Process($command, $this->workspace->path($subPath));
        $pid = yield $process->start();
        
        $out = $process->getStderr();
        
        while ($buffer = yield $out->read()) {
            echo $buffer;
        }
        
        return yield $process->join();
    }
}

