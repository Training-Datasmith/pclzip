<?php

namespace PclZip\Tests;

class PclZipUtilTest extends PclZipTestCase
{
    public function testPathReduction()
    {
        $this->assertSame('a/c', PclZipUtilPathReduction('a/b/../c'));
        $this->assertSame('a/b', PclZipUtilPathReduction('a/./b'));
        $this->assertSame('foo/bar', PclZipUtilPathReduction('foo//bar'));
        $this->assertSame('/foo', PclZipUtilPathReduction('/foo'));
        $this->assertSame('foo/bar/', PclZipUtilPathReduction('foo/bar/'));
        $this->assertSame('../../a', PclZipUtilPathReduction('../../a'));
        $this->assertSame('', PclZipUtilPathReduction(''));
    }

    public function testPathInclusion()
    {
        $this->assertSame(1, PclZipUtilPathInclusion('/base', '/base/a/b'));
        $this->assertSame(2, PclZipUtilPathInclusion('/base/a', '/base/a'));
        $this->assertSame(0, PclZipUtilPathInclusion('/base/a', '/base/b'));
        $this->assertSame(0, PclZipUtilPathInclusion('/base/foo', '/base/foobar'));
        $this->assertSame(1, PclZipUtilPathInclusion('/base/', '/base/child'));
    }

    public function testPathInclusionDot()
    {
        $cwd = getcwd();
        $this->assertSame(1, PclZipUtilPathInclusion('.', $cwd . '/child'));
    }

    public function testCopyBlock()
    {
        $payload = str_repeat('q', 3000);
        $src = fopen('php://memory', 'rb+');
        fwrite($src, $payload);
        rewind($src);
        $dest = fopen('php://memory', 'wb+');
        PclZipUtilCopyBlock($src, $dest, strlen($payload), 0);
        rewind($dest);
        $this->assertSame($payload, stream_get_contents($dest));
        fclose($src);
        fclose($dest);

        $plain = $this->ws->path('plain.bin');
        $gzPath = $this->ws->path('out.gz');
        file_put_contents($plain, $payload);
        $src2 = fopen($plain, 'rb');
        $gzDest = gzopen($gzPath, 'wb');
        PclZipUtilCopyBlock($src2, $gzDest, strlen($payload), 2);
        fclose($src2);
        gzclose($gzDest);
        $this->assertSame($payload, gzdecode(file_get_contents($gzPath)));
    }

    public function testRename()
    {
        $src = $this->ws->path('rename-src.txt');
        $dest = $this->ws->path('rename-dest.txt');
        file_put_contents($src, 'moved');
        $this->assertSame(1, PclZipUtilRename($src, $dest));
        $this->assertFileExists($dest);
        $this->assertSame('moved', file_get_contents($dest));
        $this->assertFileNotExists($src);
    }

    public function testOptionText()
    {
        $this->assertSame('PCLZIP_OPT_BY_NAME', PclZipUtilOptionText(PCLZIP_OPT_BY_NAME));
        $this->assertSame('Unknown', PclZipUtilOptionText(99999999));
        $tempOn = PclZipUtilOptionText(PCLZIP_OPT_TEMP_FILE_ON);
        $this->assertTrue(
            $tempOn === 'PCLZIP_OPT_TEMP_FILE_ON' || $tempOn === 'PCLZIP_OPT_ADD_TEMP_FILE_ON'
        );
    }

    public function testTranslateWinPathOnLinux()
    {
        $this->assertSame('C:\\foo\\bar', PclZipUtilTranslateWinPath('C:\\foo\\bar'));
    }
}
