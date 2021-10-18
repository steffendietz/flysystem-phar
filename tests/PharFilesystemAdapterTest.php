<?php

declare(strict_types=1);

namespace Steffendietz\Flysystem\Phar\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\MimeTypeDetection\EmptyExtensionToMimeTypeMap;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Phar;
use Steffendietz\Flysystem\Phar\PharFilesystemAdapter;

class PharFilesystemAdapterTest extends FilesystemAdapterTestCase
{

    public function testMyOwn()
    {
        $adapter = new PharFilesystemAdapter(__DIR__ . '/yetanother.zip');
        $adapter->write('blubb/hello.txt', 'world', new Config());
        $adapter->move('hello.txt', 'world.txt', new Config());
        $this->assertFalse($adapter->fileExists('hello.txt'));
    }

    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->useAdapter(new PharFilesystemAdapter(__DIR__ . '/other.zip', Phar::ZIP, new ExtensionMimeTypeDetector(new EmptyExtensionToMimeTypeMap())));

        parent::fetching_unknown_mime_type_of_a_file();
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new PharFilesystemAdapter(__DIR__ . '/test.zip');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        foreach (glob(__DIR__ . '/*.zip') as $file) {
            //unlink($file);
        }
    }
}
