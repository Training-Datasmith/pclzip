<?php

namespace PclZip\Tests;

class Workspace
{
    const FIXED_MTIME = 1579092896; // gmmktime(12, 34, 56, 1, 15, 2020)

    /** @var string */
    private $previousCwd;

    /** @var string */
    public $dir;

    public function __construct($prefix = 'pclzip-test-')
    {
        $this->dir = sys_get_temp_dir() . '/' . $prefix . uniqid('', true);
        if (!mkdir($this->dir, 0777, true)) {
            throw new \RuntimeException('Cannot create workspace: ' . $this->dir);
        }
        $this->previousCwd = getcwd();
        if (!chdir($this->dir)) {
            throw new \RuntimeException('Cannot chdir into workspace');
        }
    }

    public function path($relative)
    {
        return $this->dir . '/' . $relative;
    }

    public function writeFile($relative, $content, $mtime = null)
    {
        $full = $this->path($relative);
        $parent = dirname($full);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        file_put_contents($full, $content);
        if ($mtime === null) {
            $mtime = self::FIXED_MTIME;
        }
        touch($full, $mtime);
    }

    public function cleanup()
    {
        if ($this->previousCwd !== false && is_dir($this->previousCwd)) {
            chdir($this->previousCwd);
        }
        $this->removeTree($this->dir);
    }

    private function removeTree($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public static function unsignedCrc($string)
    {
        return sprintf('%u', crc32($string));
    }

    public static function patchFirstLocalCompression($path, $method)
    {
        $data = file_get_contents($path);
        $data[8] = chr($method & 0xFF);
        $data[9] = chr(($method >> 8) & 0xFF);
        $cd = strpos($data, pack('V', 0x02014b50));
        if ($cd !== false) {
            $data[$cd + 10] = chr($method & 0xFF);
            $data[$cd + 11] = chr(($method >> 8) & 0xFF);
        }
        file_put_contents($path, $data);
    }

    public static function patchFirstEntryEncryptionFlag($path)
    {
        $data = file_get_contents($path);
        $flag = unpack('v', substr($data, 6, 2));
        $data = substr_replace($data, pack('v', $flag[1] | 1), 6, 2);
        $cd = strpos($data, pack('V', 0x02014b50));
        if ($cd !== false) {
            $cflag = unpack('v', substr($data, $cd + 8, 2));
            $data = substr_replace($data, pack('v', $cflag[1] | 1), $cd + 8, 2);
        }
        file_put_contents($path, $data);
    }

    public static function zipArchiveBytes($zipPath, $entryName)
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $data = $zip->getFromName($entryName);
        $zip->close();

        return $data;
    }
}
