<?php

declare(strict_types=1);

namespace Steffendietz\Flysystem\Phar\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\FilesystemAdapter;
use Steffendietz\Flysystem\Phar\PharAdapter;

class PharAdapterTest extends FilesystemAdapterTestCase
{

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new PharAdapter(__DIR__ . '/test.zip');
    }

    protected function tearDown(): void
    {
        if(file_exists(__DIR__ . '/test.zip')) {
            unlink(__DIR__ . '/test.zip');
        }
    }
}
