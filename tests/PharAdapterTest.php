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
        return new PharAdapter();
    }
}
