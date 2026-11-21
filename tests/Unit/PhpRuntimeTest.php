<?php

namespace Tests\Unit;

use App\Support\PhpRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PhpRuntimeTest extends TestCase
{
    #[DataProvider('versions')]
    public function test_deployment_compatibility_boundary(string $version, bool $supported): void
    {
        $this->assertSame($supported, PhpRuntime::supports($version));
    }

    public static function versions(): array
    {
        return [
            'PHP 8.2 is too old' => ['8.2.30', false],
            'below tested CLI minimum' => ['8.3.20', false],
            'prerelease is below minimum' => ['8.3.21RC1', false],
            'Metanet CLI' => ['8.3.21', true],
            'later 8.3 patch' => ['8.3.22', true],
            'Metanet web' => ['8.3.36', true],
            'Composer-compatible later minor' => ['8.4.27', true],
            'next major is not supported' => ['9.0.0', false],
        ];
    }

    public function test_composer_resolves_for_the_tested_cli_minimum(): void
    {
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('^'.PhpRuntime::MINIMUM_VERSION, $manifest['require']['php']);
        $this->assertSame(PhpRuntime::MINIMUM_VERSION, $manifest['config']['platform']['php']);
    }

    public function test_upgrade_entrypoint_boots_with_the_current_php_binary(): void
    {
        $process = new Process([PHP_BINARY, 'ops/metanet/upgrade.php', '--help'], dirname(__DIR__, 2));
        $process->mustRun();
        $this->assertStringContainsString('--expect-database', $process->getOutput());
        $this->assertStringContainsString('--backup-confirmed', $process->getOutput());
    }
}
