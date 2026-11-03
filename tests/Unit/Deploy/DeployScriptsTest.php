<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
| ADR-0019: the server scripts can't run here, but they must parse, stay executable and (where
| shellcheck is installed, as on CI images) pass it.
*/
dataset('deploy scripts', fn (): array => array_map(basename(...), glob(dirname(__DIR__, 3).'/deploy/*.sh') ?: []));

it('parses and is executable', function (string $script): void {
    $path = dirname(__DIR__, 3).'/deploy/'.$script;

    expect(is_executable($path))->toBeTrue()
        ->and((new Process(['bash', '-n', $path]))->run())->toBe(0);
})->with('deploy scripts');

it('passes shellcheck', function (): void {
    $shellcheck = (new ExecutableFinder)->find('shellcheck');
    if ($shellcheck === null) {
        $this->markTestSkipped('shellcheck is not installed');
    }

    $root = dirname(__DIR__, 3);
    $process = new Process([$shellcheck, '-x', ...glob($root.'/deploy/*.sh') ?: []], $root);

    expect($process->run())->toBe(0, $process->getOutput());
});

it('has a systemd unit for every timer it enables', function (): void {
    $root = dirname(__DIR__, 3).'/deploy';
    preg_match('/systemctl enable (mawred-[^>]+)>/', (string) file_get_contents($root.'/harden.sh'), $match);

    foreach (preg_split('/\s+/', trim($match[1] ?? '')) ?: [] as $timer) {
        expect(is_file($root.'/systemd/'.$timer))->toBeTrue($timer)
            ->and(is_file($root.'/systemd/'.str_replace('.timer', '.service', $timer)))->toBeTrue($timer);
    }
});
