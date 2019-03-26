<?php

use Amp\Loop;
use Amp\Process\Process;
use Amp\Promise;
use Phpactor\TestUtils\Workspace;


require __DIR__ . '/../vendor/autoload.php';


Loop::run(function () {
    $repos = [
        'git@github.com:dantleech/fink',
        'git@github.com:phpbench/phpbench',
        'git@github.com:phpactor/config-loader',
    ];

    $workspace = Workspace::create(__DIR__ . '/../Workspace');
    $workspace->reset();

    $builder = new Builder($workspace);

    $promises = [];
    foreach ($repos as $repo) {
        $promises[] = $builder->build($repo);
    }

    $exitCodes = yield $promises;

    echo "\n\n";
    foreach ($repos as $index => $repo) {
        echo 'Repository ' . $repo . ' exited with ' . $exitCodes[$index] . PHP_EOL;
    }

    exit(array_sum($exitCodes));
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

    public function build(string $repo): Promise
    {
        return Amp\call(function () use ($repo) {
            $subPath = substr($repo, strrpos($repo, '/') + 1);

            $exitCode = 0;
            $exitCode += yield from $this->executeCommand(sprintf('git clone %s', $repo));
            $exitCode += yield from $this->executeCommand('composer install', $subPath);
            $exitCode += yield from $this->executeCommand('./vendor/bin/phpstan analyse --level=1 lib', $subPath);

            return $exitCode;

        });
    }

    private function executeCommand(string $command, string $path = '/'): Generator
    {
        $process = new Process($command, $this->workspace->path($path));
        $pid = yield $process->start();

        $out = $process->getStderr();

        while (null !== $buffer = yield $out->read()) {
            echo $buffer;
        }

        $exitCode = yield $process->join();

        return $exitCode;
    }
}

