<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SanitizeTest extends TestCase
{
    public function testSanitizeEscapesHtml(): void
    {
        $this->assertStringContainsString('&lt;', sanitize('<b>x</b>'));
        $this->assertStringContainsString('&gt;', sanitize('<b>x</b>'));
    }

    public function testSanitizeTrims(): void
    {
        $this->assertSame('a', sanitize('  a  '));
    }
}
