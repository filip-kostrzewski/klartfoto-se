<?php

namespace Tests\Feature;

use DOMDocument;
use FilesystemIterator;
use LibXMLError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class DemoSvgTest extends TestCase
{
    public function test_every_public_svg_parses_as_utf8_xml(): void
    {
        $paths = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(public_path('images'), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'svg') {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertTrue(mb_check_encoding($contents, 'UTF-8'), $path);

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $document = new DOMDocument;
            $loaded = $document->loadXML($contents, LIBXML_NONET);
            $errors = array_map(
                fn (LibXMLError $error): string => trim($error->message),
                libxml_get_errors(),
            );
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $this->assertTrue($loaded, $path.' '.implode(' ', $errors));
            $this->assertSame([], $errors, $path);
        }

        $before = file_get_contents(public_path('images/demo/kitchen-before.svg'));
        $after = file_get_contents(public_path('images/demo/kitchen-after.svg'));
        $this->assertIsString($before);
        $this->assertIsString($after);
        $this->assertStringContainsString('kök före städning', $before);
        $this->assertStringContainsString('kök efter städning', $after);
    }
}
