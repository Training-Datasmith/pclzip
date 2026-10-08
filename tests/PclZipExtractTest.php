<?php

namespace PclZip\Tests;

class PclZipExtractTest extends PclZipTestCase
{
    private function buildTwoFileArchive()
    {
        $zip = $this->archive();
        $zip->create(array(
            $this->virtualFile('docs/note.txt', '123456789', Workspace::FIXED_MTIME),
            $this->virtualFile('other.txt', 'OTHER'),
        ));

        return $zip;
    }

    public function testExtractToPath()
    {
        $zip = $this->buildTwoFileArchive();
        $dest = $this->ws->path('dest');
        mkdir($dest);
        $zip->extract(PCLZIP_OPT_PATH, $dest);
        $this->assertSame('123456789', file_get_contents($dest . '/docs/note.txt'));
    }

    public function testHistoricExtractRemovePath()
    {
        $zip = $this->buildTwoFileArchive();
        $dest = $this->ws->path('dest2');
        mkdir($dest);
        $zip->extract($dest, 'docs');
        $this->assertFileExists($dest . '/note.txt');
        $this->assertSame('123456789', file_get_contents($dest . '/note.txt'));
    }

    public function testExtractAsString()
    {
        $zip = $this->buildTwoFileArchive();
        $list = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING, PCLZIP_OPT_BY_NAME, 'docs/note.txt');
        $this->assertSame('123456789', $list[0]['content']);
        $this->assertFileNotExists($this->ws->path('docs/note.txt'));
    }

    public function testExtractInOutput()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('out.txt', 'stdout-data')));
        ob_start();
        $zip->extract(PCLZIP_OPT_EXTRACT_IN_OUTPUT);
        $buffer = ob_get_clean();
        $this->assertSame('stdout-data', $buffer);
    }

    public function testExtractByName()
    {
        $zip = $this->buildTwoFileArchive();
        $list = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING, PCLZIP_OPT_BY_NAME, array('docs/note.txt'));
        $this->assertCount(1, $list);
        $this->assertSame('123456789', $list[0]['content']);

        $zip2 = $this->archive('dir.zip');
        $zip2->create(array(
            $this->virtualFile('docs/a.txt', 'A'),
            $this->virtualFile('docs/b.txt', 'B'),
        ));
        $list2 = $zip2->extract(PCLZIP_OPT_EXTRACT_AS_STRING, PCLZIP_OPT_BY_NAME, 'docs/');
        $this->assertCount(2, $list2);
    }

    public function testExtractByPregAndEreg()
    {
        $zip = $this->buildTwoFileArchive();
        $byPreg = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING, PCLZIP_OPT_BY_PREG, '/^docs\\//');
        $this->assertCount(1, $byPreg);
        $byEreg = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING, PCLZIP_OPT_BY_EREG, '/^docs\\//');
        $this->assertCount(1, $byEreg);
    }

    public function testExtractByIndex()
    {
        $zip = $this->archive('idx.zip');
        $zip->create(array(
            $this->virtualFile('0.txt', '0'),
            $this->virtualFile('1.txt', '1'),
            $this->virtualFile('2.txt', '2'),
            $this->virtualFile('3.txt', '3'),
        ));
        $list = $zip->extractByIndex('0,2-3', PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertCount(3, $list);
        $names = array();
        foreach ($list as $row) {
            $names[] = $row['stored_filename'];
        }
        $this->assertNotContains('1.txt', $names);

        $one = $zip->extractByIndex(1, PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('1.txt', $one[0]['stored_filename']);

        $bad1 = $this->archive('bad-idx1.zip');
        $bad1->create(array($this->virtualFile('x.txt', 'x')));
        $this->assertSame(0, $bad1->extractByIndex('5,1'));
        $this->assertSame(PCLZIP_ERR_INVALID_OPTION_VALUE, $bad1->errorCode());
        $bad2 = $this->archive('bad-idx2.zip');
        $bad2->create(array($this->virtualFile('y.txt', 'y')));
        $this->assertSame(0, $bad2->extractByIndex('1-2-3'));
        $this->assertSame(PCLZIP_ERR_INVALID_OPTION_VALUE, $bad2->errorCode());

        $spaced = $zip->extractByIndex('0, 2', PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertCount(2, $spaced);
    }

    public function testRemoveAllPathOnExtract()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('docs/note.txt', 'n')));
        $dest = $this->ws->path('ra');
        mkdir($dest);
        $list = $zip->extract(PCLZIP_OPT_PATH, $dest, PCLZIP_OPT_REMOVE_ALL_PATH);
        $this->assertFileExists($dest . '/note.txt');
        foreach ($list as $row) {
            if ($row['folder']) {
                $this->assertSame('filtered', $row['status']);
            }
        }
    }

    public function testNewerExistAndReplace()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('f.txt', 'old', Workspace::FIXED_MTIME)));
        $path = $this->ws->path('f.txt');
        file_put_contents($path, 'newer');
        touch($path, Workspace::FIXED_MTIME + 1000);
        $list = $zip->extract();
        $this->assertSame('newer_exist', $list[0]['status']);
        $this->assertSame('newer', file_get_contents($path));

        $list2 = $zip->extract(PCLZIP_OPT_REPLACE_NEWER);
        $this->assertSame('ok', $list2[0]['status']);
        $this->assertSame('old', file_get_contents($path));
    }

    public function testStopOnErrorNewer()
    {
        $zip = $this->archive();
        $zip->create(array(
            $this->virtualFile('a.txt', 'A', Workspace::FIXED_MTIME),
            $this->virtualFile('b.txt', 'B', Workspace::FIXED_MTIME),
        ));
        $path = $this->ws->path('a.txt');
        file_put_contents($path, 'newer');
        touch($path, Workspace::FIXED_MTIME + 5000);
        $this->assertSame(0, $zip->extract(PCLZIP_OPT_STOP_ON_ERROR));
        $this->assertSame(PCLZIP_ERR_WRITE_OPEN_FAIL, $zip->errorCode());
        $this->assertFileNotExists($this->ws->path('b.txt'));
    }

    public function testAlreadyADirectory()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('conflict', 'data')));
        mkdir($this->ws->path('conflict'));
        $list = $zip->extract();
        $this->assertSame('already_a_directory', $list[0]['status']);

        $zip2 = $this->archive('conflict2.zip');
        $zip2->create(array($this->virtualFile('conflict2', 'data')));
        mkdir($this->ws->path('conflict2'));
        $this->assertSame(0, $zip2->extract(PCLZIP_OPT_STOP_ON_ERROR));
        $this->assertSame(PCLZIP_ERR_ALREADY_A_DIRECTORY, $zip2->errorCode());
        $this->assertSame('PCLZIP_ERR_ALREADY_A_DIRECTORY', $zip2->errorName());
        $this->assertSame('PCLZIP_ERR_ALREADY_A_DIRECTORY (-17)', $zip2->errorName(true));
    }

    public function testSetChmod()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('chmod.txt', 'c')));
        $zip->extract(PCLZIP_OPT_SET_CHMOD, 0640);
        $this->assertSame(0640, fileperms($this->ws->path('chmod.txt')) & 0777);
    }

    public function testDirectoryRestriction()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('safe.txt', 'ok')));
        $base = $this->ws->path('base');
        $sibling = $this->ws->path('sibling');
        mkdir($base);
        mkdir($sibling);
        $ok = $zip->extract(PCLZIP_OPT_PATH, $base, PCLZIP_OPT_EXTRACT_DIR_RESTRICTION, $base);
        $this->assertIsArrayCompat($ok);
        $this->assertFileExists($base . '/safe.txt');

        $bad = $zip->extract(PCLZIP_OPT_PATH, $base, PCLZIP_OPT_EXTRACT_DIR_RESTRICTION, $sibling);
        $this->assertSame(0, $bad);
        $this->assertSame(PCLZIP_ERR_DIRECTORY_RESTRICTION, $zip->errorCode());
    }

    public function testUnsupportedCompression()
    {
        $zip = $this->archive();
        $zip->create(array(
            $this->virtualFile('bad.txt', 'x'),
            $this->virtualFile('good.txt', 'y'),
        ));
        Workspace::patchFirstLocalCompression($this->ws->path('test.zip'), 99);

        $z = $this->archive();
        $this->assertSame(0, $z->extract(PCLZIP_OPT_STOP_ON_ERROR));
        $this->assertSame(PCLZIP_ERR_UNSUPPORTED_COMPRESSION, $z->errorCode());

        $z2 = $this->archive();
        $list = $z2->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $statuses = array();
        foreach ($list as $row) {
            $statuses[$row['stored_filename']] = $row['status'];
        }
        $this->assertSame('unsupported_compression', $statuses['bad.txt']);
        $this->assertSame('y', $list[1]['content']);
    }

    public function testUnsupportedEncryptionFlag()
    {
        $zip = $this->archive('enc.zip');
        $zip->create(array($this->virtualFile('secret.txt', 'plain')));
        Workspace::patchFirstEntryEncryptionFlag($this->ws->path('enc.zip'));

        $z = $this->archive('enc.zip');
        $list = $z->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('unsupported_encryption', $list[0]['status']);

        $zStop = $this->archive('enc.zip');
        $this->assertSame(0, $zStop->extract(PCLZIP_OPT_STOP_ON_ERROR));
        $this->assertSame(PCLZIP_ERR_UNSUPPORTED_ENCRYPTION, $zStop->errorCode());
    }

    public function testTrailingBytes()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('t.txt', 'trail')));
        file_put_contents($this->ws->path('test.zip'), file_get_contents($this->ws->path('test.zip')) . "\0\0\0\0", FILE_APPEND);
        $this->assertCount(1, $zip->listContent());
        $out = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('trail', $out[0]['content']);
    }

    public function testInvalidArchive()
    {
        file_put_contents($this->ws->path('bad.zip'), 'hello');
        $zip = new \PclZip($this->ws->path('bad.zip'));
        $this->assertSame(0, $zip->listContent());
        $this->assertSame(PCLZIP_ERR_BAD_FORMAT, $zip->errorCode());
        $this->assertStringContainsCompat('PCLZIP_ERR_BAD_FORMAT', $zip->errorInfo(true));
    }
}
