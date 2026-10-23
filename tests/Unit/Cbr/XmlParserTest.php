<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cbr;

use App\Cbr\Client\XmlParser;
use App\Exchange\Exception\SourceUnavailableException;
use PHPUnit\Framework\TestCase;

final class XmlParserTest extends TestCase
{
    /**
     * XML с внешними сущностями отклоняется, чтобы исключить чтение локальных файлов
     */
    public function testExternalEntitiesAreRejected(): void
    {
        $xml = '<!DOCTYPE ValCurs [<!ENTITY x SYSTEM "file:///etc/passwd">]><ValCurs Date="22.10.2026">&x;</ValCurs>';
        $this->expectException(SourceUnavailableException::class);

        (new XmlParser())->history($xml);
    }
}
