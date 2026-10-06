<?php

declare(strict_types=1);

// scripts/assemble-reports.sh lays the test and coverage reports out under
// site/reports/ for the docs deploy, so apis.ryankes.eu can link them.

function assembleReports(string $workDir): int
{
    $script = __DIR__.'/../scripts/assemble-reports.sh';
    exec('cd '.escapeshellarg($workDir).' && bash '.escapeshellarg($script).' 2>&1', $output, $status);

    return $status;
}

function reportsWorkDir(): string
{
    $dir = sys_get_temp_dir().'/reports-'.bin2hex(random_bytes(4));
    mkdir($dir.'/coverage-html', 0o777, true);
    file_put_contents($dir.'/junit.xml', '<testsuites/>');
    file_put_contents($dir.'/coverage.xml', '<coverage/>');
    file_put_contents($dir.'/coverage-html/index.html', '<html></html>');

    return $dir;
}

it('lays the reports out where the catalogue links them', function (): void {
    $dir = reportsWorkDir();

    expect(assembleReports($dir))->toBe(0);

    foreach ([
        'index.html',
        'tests/index.html',
        'tests/junit.xml',
        'coverage/index.html',
        'coverage/coverage.xml',
    ] as $path) {
        expect(is_file($dir.'/site/reports/'.$path))->toBeTrue($path);
    }
});

it('fails rather than publish a half-built report set', function (): void {
    $dir = reportsWorkDir();
    unlink($dir.'/junit.xml');

    expect(assembleReports($dir))->not->toBe(0);
});
