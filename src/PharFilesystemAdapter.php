<?php

declare(strict_types=1);

namespace Steffendietz\Flysystem\Phar;

use DirectoryIterator;
use FilesystemIterator;
use Generator;
use IteratorIterator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PharFilesystemAdapter implements FilesystemAdapter
{

    protected string $fileName;
    protected PharData $phar;
    protected PathPrefixer $prefixer;
    protected MimeTypeDetector $mimeTypeDetector;
    protected VisibilityConverter $visibilityConverter;

    /**
     * PharAdapter constructor.
     */
    public function __construct(string $fileName, int $format = Phar::ZIP, ?MimeTypeDetector $mimeTypeDetector = null)
    {
        $this->phar = new PharData(
            $fileName,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
            null,
            $format
        );
        $this->fileName = $fileName;
        $this->prefixer = new PathPrefixer('phar://' . $this->fileName . '/');
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->visibilityConverter = new PortableVisibilityConverter();
    }

    public function fileExists(string $path): bool
    {
        if (($fileInfo = $this->getPharFileInfo($path)) !== null) {
            return $fileInfo->isFile();
        }
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        if (!$this->phar->isWritable()) {
            throw UnableToWriteFile::atLocation($path, 'PharData is not writable.');
        }

        $this->phar->addFromString($path, $contents);

        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->setVisibility($path, (string)$visibility);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        if (!$this->phar->isWritable()) {
            throw UnableToWriteFile::atLocation($path, 'PharData is not writable.');
        }

        $this->write($path, (string)stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        if (!$this->fileExists($path)) {
            throw UnableToReadFile::fromLocation($path);
        }

        return file_get_contents($this->prefixer->prefixPath($path));
    }

    public function readStream(string $path)
    {
        if (!$this->fileExists($path)) {
            throw UnableToReadFile::fromLocation($path);
        }

        return fopen($this->prefixer->prefixPath($path), 'r');
    }

    public function delete(string $path): void
    {
        $fileInfo = $this->getPharFileInfo($path);

        if ($fileInfo === null) {
            return;
        }

        if (!$this->phar->delete($path)) {
            throw UnableToDeleteFile::atLocation($path, error_get_last()['message'] ?? '');
        }
    }

    public function deleteDirectory(string $path): void
    {
        $fileInfo = $this->getPharFileInfo($path);

        if ($fileInfo === null || !$fileInfo->isDir()) {
            return;
        }

        $directoryContents = $this->recursiveGenerator(
            $this->prefixer->prefixPath($path),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($directoryContents as $item) {
            if ($item->isFile()) {
                $this->phar->delete($this->prefixer->stripPrefix($item->getPathname()));
            }
        }

        $fileInfo = $this->getPharFileInfo($path);

        if ($fileInfo !== null && $fileInfo->isDir() && !$this->phar->delete($path)) {
            throw UnableToDeleteDirectory::atLocation($path, error_get_last()['message'] ?? '');
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->phar->addEmptyDir($path);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if (!$this->fileExists($path)) {
            throw UnableToSetVisibility::atLocation($path);
        }

        $fileInfo = $this->getPharFileInfo($path);

        $permissions = $fileInfo->isDir()
            ? $this->visibilityConverter->forDirectory($visibility)
            : $this->visibilityConverter->forFile($visibility);

        $fileInfo->chmod($permissions);
    }

    public function visibility(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw UnableToRetrieveMetadata::visibility($path);
        }

        $permissions = octdec(substr(sprintf('%o', $this->getPharFileInfo($path)->getPerms()), -4));

        $visibility = $this->visibilityConverter->inverseForFile($permissions);

        return new FileAttributes($path, null, $visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        $fileInfo = $this->getPharFileInfo($path);

        if ($fileInfo->isDir()) {
            throw UnableToRetrieveMetadata::mimeType($path, 'directory');
        }

        $mimeType = $this->mimeTypeDetector->detectMimeTypeFromFile($this->prefixer->prefixPath($path));

        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return new FileAttributes($path, null, null, $this->getPharFileInfo($path)->getMTime());
    }

    public function fileSize(string $path): FileAttributes
    {
        if (!$this->fileExists($path)) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        $fileInfo = $this->getPharFileInfo($path);

        if ($fileInfo->isDir()) {
            throw UnableToRetrieveMetadata::fileSize($path, 'directory');
        }

        return new FileAttributes($path, $fileInfo->getSize());
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $location = $this->prefixer->prefixPath($path);

        if (!is_dir($location)) {
            return;
        }

        /** @var PharFileInfo[] $iterator */
        $iterator = $deep === true
            ? $this->recursiveGenerator($location)
            : $this->flatGenerator($location);

        foreach ($iterator as $fileInfo) {
            $path = $this->prefixer->stripPrefix($fileInfo->getPathname());
            $lastModified = $fileInfo->getMTime();

            yield $fileInfo->isDir()
                ? new DirectoryAttributes($path, null, $lastModified)
                : new FileAttributes($path, $fileInfo->getSize(), null, $lastModified);
        }
    }

    private function recursiveGenerator(string $location, int $mode = RecursiveIteratorIterator::SELF_FIRST): Generator
    {
        yield from new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($location, FilesystemIterator::SKIP_DOTS), $mode
        );
    }

    private function flatGenerator(string $location): Generator
    {
        yield from new IteratorIterator(new DirectoryIterator($location));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        if (!$this->fileExists($source)) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }

        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if (!$this->fileExists($source)) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        $sourceStream = $this->readStream($source);
        $this->writeStream($destination, $sourceStream, $config);
    }

    private function getPharFileInfo(string $path): ?PharFileInfo
    {
        if (isset($this->phar[$path])) {
            return $this->phar[$path];
        }
        return null;
    }
}
