<?php

namespace App\Domain\Administration\Services;

use RuntimeException;
use ZipArchive;

/** Zip extraction that refuses path traversal ("zip slip"), absolute paths and symlinks. */
class SafeZip
{
    public const MAX_ENTRIES = 100000;

    public static function isSafeEntryName(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
            return false;
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            return false;
        }
        foreach (explode('/', $name) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> entry names (validated) */
    public static function entries(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('The archive contains too many files.');
        }
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! self::isSafeEntryName($name)) {
                throw new RuntimeException("Unsafe path in archive: {$name}");
            }
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX) {
                $mode = ($attr >> 16) & 0170000;
                if ($mode === 0120000) {
                    throw new RuntimeException("Symbolic links are not allowed in archives: {$name}");
                }
            }
            $names[] = $name;
        }

        return $names;
    }

    /** Extracts every entry beneath $destination after validating all names. */
    public static function extract(string $archive, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Unable to open the archive.');
        }

        try {
            $names = self::entries($zip);
            if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
                throw new RuntimeException('Unable to create '.$destination);
            }
            $root = realpath($destination);

            foreach ($names as $i => $name) {
                $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (str_ends_with($name, '/')) {
                    if (! is_dir($target)) {
                        mkdir($target, 0775, true);
                    }

                    continue;
                }
                $dir = dirname($target);
                if (! is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $real = realpath($dir);
                if ($real === false || ($real !== $root && ! str_starts_with($real, $root.DIRECTORY_SEPARATOR))) {
                    throw new RuntimeException("Unsafe path in archive: {$name}");
                }
                $in = $zip->getStream($name);
                if ($in === false) {
                    throw new RuntimeException("Unable to read {$name} from the archive.");
                }
                $out = fopen($target, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
            }
        } finally {
            $zip->close();
        }
    }

    /** Adds a directory tree to an open archive under $prefix. */
    public static function addDirectory(ZipArchive $zip, string $directory, string $prefix, array $excludeNames = []): int
    {
        if (! is_dir($directory)) {
            return 0;
        }
        $count = 0;
        $base = rtrim(realpath($directory), DIRECTORY_SEPARATOR);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isLink() || in_array($file->getFilename(), $excludeNames, true)) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($base) + 1));
            if ($file->isDir()) {
                $zip->addEmptyDir(rtrim($prefix, '/').'/'.$relative);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), rtrim($prefix, '/').'/'.$relative);
                $count++;
            }
        }

        return $count;
    }

    public static function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() && ! $file->isLink() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($directory);
    }
}
