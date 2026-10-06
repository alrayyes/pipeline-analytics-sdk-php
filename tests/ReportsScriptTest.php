<?php

declare(strict_types=1);

// scripts/assemble-reports.sh lays the test and coverage reports out under
// site/reports/ for the docs deploy, so apis.ryankes.eu can link them.

function assembleReports(string $workDir): int
{
    $script = __DIR__.'/../scripts/assemble-reports.sh';
    exec('cd '.escapeshellarg($workDir).' && GITHUB_SHA=abc1234 bash '.escapeshellarg($script).' 2>&1', $output, $status);

    return $status;
}

function reportsWorkDir(): string
{
    $dir = sys_get_temp_dir().'/reports-'.bin2hex(random_bytes(4));
    mkdir($dir.'/coverage-html', 0o777, true);
    file_put_contents($dir.'/junit.xml', '<testsuites/>');
    file_put_contents($dir.'/coverage.xml', '<coverage line-rate="1" version="0.4"/>');
    file_put_contents($dir.'/clover.xml', '<coverage generated="1"><project/></coverage>');
    file_put_contents($dir.'/coverage-html/index.html', '<html></html>');

    return $dir;
}

it('lays the reports out where the catalogue links them', function (): void {
    $dir = reportsWorkDir();

    expect(assembleReports($dir))->toBe(0);

    foreach ([
        'index.html',
        'tests/index.html',
        'tests/unit.xml',
        'coverage/index.html',
        'coverage/coverage.xml',
        'coverage/clover.xml',
    ] as $path) {
        expect(is_file($dir.'/site/reports/'.$path))->toBeTrue($path);
    }
});

it('publishes Cobertura as coverage.xml and keeps Clover beside it', function (): void {
    $dir = reportsWorkDir();

    expect(assembleReports($dir))->toBe(0);

    expect(file_get_contents($dir.'/site/reports/coverage/coverage.xml'))->toContain('line-rate');
    expect(file_get_contents($dir.'/site/reports/coverage/clover.xml'))->toContain('<project');
});

it('refuses a coverage.xml that is not Cobertura', function (): void {
    $dir = reportsWorkDir();
    copy($dir.'/clover.xml', $dir.'/coverage.xml');

    expect(assembleReports($dir))->not->toBe(0);
});

it('names the runner in the JUnit file and dates the index', function (): void {
    $dir = reportsWorkDir();

    expect(assembleReports($dir))->toBe(0);

    expect(is_file($dir.'/site/reports/tests/junit.xml'))->toBeFalse();
    $index = file_get_contents($dir.'/site/reports/index.html');
    expect($index)->toContain('abc1234')->toMatch('/\d{4}-\d{2}-\d{2}/');
});

it('fails without the native Clover file', function (): void {
    $dir = reportsWorkDir();
    unlink($dir.'/clover.xml');

    expect(assembleReports($dir))->not->toBe(0);
});

it('fails rather than publish a half-built report set', function (): void {
    $dir = reportsWorkDir();
    unlink($dir.'/junit.xml');

    expect(assembleReports($dir))->not->toBe(0);
});
