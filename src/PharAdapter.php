<?php

declare(strict_types=1);

namespace Steffendietz\Flysystem\Phar;

use FilesystemIterator;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Phar;
use PharData;

class PharAdapter implements FilesystemAdapter
{

    protected string $fileName;
    protected PharData $phar;

    /**
     * PharAdapter constructor.
     */
    public function __construct(string $fileName, int $format = Phar::ZIP)
    {
        $this->phar = new PharData(
            $fileName,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
            null,
            $format
        );
        $this->fileName = $fileName;
    }

    public function fileExists(string $path): bool
    {
        return file_exists($this->preparePharInternalPath($path));
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->phar->addFromString($path, $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string)stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        if (!$this->fileExists($path)) {
            throw new UnableToReadFile();
        }

        return file_get_contents($this->preparePharInternalPath($path));
    }

    public function readStream(string $path)
    {
        if (!$this->fileExists($path)) {
            throw new UnableToReadFile();
        }

        return fopen($this->preparePharInternalPath($path), 'r');
    }

    public function delete(string $path): void
    {
        if ($this->fileExists($path)) {
            $this->phar->delete($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        // TODO: Implement deleteDirectory() method.
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->phar->addEmptyDir($path);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // TODO: Implement setVisibility() method.
    }

    public function visibility(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw new UnableToRetrieveMetadata();
        }

        return new FileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw new UnableToRetrieveMetadata();
        }

        return new FileAttributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw new UnableToRetrieveMetadata();
        }

        return new FileAttributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw new UnableToRetrieveMetadata();
        }

        return new FileAttributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->phar;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        if (!$this->fileExists($source)) {
            throw new UnableToMoveFile();
        }

        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if (!$this->fileExists($source)) {
            throw new UnableToCopyFile();
        }
    }

    private function preparePharInternalPath(string $path): string
    {
        $fileName = 'phar://' . $this->fileName . '/' . ltrim($path, '/');
        return $fileName;
    }
}