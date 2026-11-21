<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Header\ParameterizedHeader;

class MimeCompatibilityTest extends TestCase
{
    #[DataProvider('invalidParameterNames')]
    public function test_mime_update_rejects_header_injection_and_invalid_tokens(string $name): void
    {
        $this->expectException(RfcComplianceException::class);
        new ParameterizedHeader('Content-Disposition', 'attachment', [$name => 'test.pdf']);
    }

    public static function invalidParameterNames(): array
    {
        return [["filename\r\nBcc: attacker@example.test"], ['file name'], ['file;name']];
    }

    public function test_normal_attachment_names_still_render(): void
    {
        $header = new ParameterizedHeader('Content-Disposition', 'attachment', ['filename' => 'Reise Zürich.pdf']);
        $this->assertSame('Reise Zürich.pdf', $header->getParameter('filename'));
        $this->assertStringContainsString('filename*=', $header->getBodyAsString());
    }
}
