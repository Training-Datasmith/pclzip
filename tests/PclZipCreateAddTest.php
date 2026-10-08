<?php

namespace PclZip\Tests;

class PclZipCreateAddTest extends PclZipTestCase
{
    public function testVirtualFileRoundTrip()
    {
        $zip = $this->archive();
        $list = $zip->create(
            array($this->virtualFile('docs/note.txt', '123456789', Workspace::FIXED_MTIME)),
            PCLZIP_OPT_COMMENT,
            'file-comment'
        );
        $this->assertIsArrayCompat($list);
        $this->assertSame('ok', $list[0]['status']);
        $this->assertSame(9, $list[0]['size']);
        $this->assertGreaterThan(0, $list[0]['compressed_size']);
        $this->assertFalse($list[0]['folder']);

        $content = $zip->listContent();
        $this->assertSame(0, $content[0]['index']);
        $this->assertSame('docs/note.txt', $content[0]['stored_filename']);
        $this->assertSame(Workspace::unsignedCrc('123456789'), sprintf('%u', $content[0]['crc']));
        $this->assertSame('file-comment', $zip->properties()['comment']);

        $extracted = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('123456789', $extracted[0]['content']);
        $this->assertSame('123456789', Workspace::zipArchiveBytes($this->ws->path('test.zip'), 'docs/note.txt'));
    }

    public function testNoCompressionLargePayload()
    {
        $payload = str_repeat('x', 5000);
        $zip = $this->archive();
        $zip->create(
            array($this->virtualFile('big.bin', $payload)),
            PCLZIP_OPT_NO_COMPRESSION
        );
        $listed = $zip->listContent();
        $this->assertSame(5000, $listed[0]['size']);
        $this->assertSame(5000, $listed[0]['compressed_size']);
        $out = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame($payload, $out[0]['content']);
    }

    public function testEmptyPayload()
    {
        $zip = $this->archive();
        $list = $zip->create(array($this->virtualFile('empty.txt', '')));
        $this->assertSame('ok', $list[0]['status']);
        $this->assertSame(0, $list[0]['size']);
        $out = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('', $out[0]['content']);
    }

    public function testBinaryPayload()
    {
        $payload = "a\0b\xff";
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('bin.dat', $payload)));
        $out = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame($payload, $out[0]['content']);
        $this->assertSame(strlen($payload), $out[0]['size']);
    }

    public function testDirectoryTree()
    {
        $this->ws->writeFile('tree/sub/a.txt', 'aaa');
        $this->ws->writeFile('tree/b.txt', 'bbb');
        $zip = $this->archive();
        $zip->create(array('tree'));
        $listed = $zip->listContent();
        $names = array();
        foreach ($listed as $row) {
            $names[] = $row['stored_filename'];
        }
        $this->assertContains('tree/sub/a.txt', $names);
        $this->assertContains('tree/b.txt', $names);
        $dest = $this->ws->path('out');
        mkdir($dest);
        $zip->extract(PCLZIP_OPT_PATH, $dest);
        $this->assertSame('aaa', file_get_contents($dest . '/tree/sub/a.txt'));
        $this->assertSame(Workspace::FIXED_MTIME, filemtime($dest . '/tree/sub/a.txt'));
    }

    public function testFileNamedZero()
    {
        $this->ws->writeFile('0', 'zero');
        $zip = $this->archive();
        $zip->create(array('0'));
        $listed = $zip->listContent();
        $this->assertSame('0', $listed[0]['stored_filename']);
        $out = $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('zero', $out[0]['content']);
    }

    public function testCommaSeparatedStringList()
    {
        $this->ws->writeFile('a.txt', 'A');
        $this->ws->writeFile('b.txt', 'B');
        $zip = $this->archive();
        $zip->create('a.txt,b.txt');
        $listed = $zip->listContent();
        $this->assertCount(2, $listed);
    }

    public function testHistoricCreateArguments()
    {
        $this->ws->writeFile('tree/sub/a.txt', 'data');
        $zip = $this->archive();
        $zip->create('tree/sub/a.txt', 'prefix', 'tree');
        $listed = $zip->listContent();
        $this->assertSame('prefix/sub/a.txt', $listed[0]['stored_filename']);
    }

    public function testRemoveAllPathAndAddPath()
    {
        $this->ws->writeFile('tree/sub/a.txt', 'x');
        $zip = $this->archive();
        $zip->create('tree/sub/a.txt', PCLZIP_OPT_REMOVE_ALL_PATH);
        $listed = $zip->listContent();
        $this->assertSame('a.txt', $listed[0]['stored_filename']);

        $zip2 = $this->archive('addpath.zip');
        $zip2->create('tree/sub/a.txt', PCLZIP_OPT_ADD_PATH, 'pkg', PCLZIP_OPT_REMOVE_PATH, 'tree');
        $listed2 = $zip2->listContent();
        $this->assertSame('pkg/sub/a.txt', $listed2[0]['stored_filename']);
    }

    public function testRenameAttributes()
    {
        $zip = $this->archive();
        $zip->create(array(
            array(
                PCLZIP_ATT_FILE_NAME => 'dir/old.txt',
                PCLZIP_ATT_FILE_NEW_SHORT_NAME => 'new.txt',
                PCLZIP_ATT_FILE_CONTENT => 's',
            ),
        ));
        $this->assertSame('dir/new.txt', $zip->listContent()[0]['stored_filename']);

        $zip2 = $this->archive('full.zip');
        $zip2->create(array(
            array(
                PCLZIP_ATT_FILE_NAME => 'ignored.txt',
                PCLZIP_ATT_FILE_NEW_FULL_NAME => 'full/path.txt',
                PCLZIP_ATT_FILE_CONTENT => 'f',
            ),
        ));
        $this->assertSame('full/path.txt', $zip2->listContent()[0]['stored_filename']);
    }

    public function testFilenameTooLong()
    {
        $long = str_repeat('a', 256);
        $zip = $this->archive();
        $list = $zip->create(array($this->virtualFile($long, 'x')));
        $this->assertSame('filename_too_long', $list[0]['status']);
        $this->assertCount(0, $zip->listContent());
    }

    public function testArchiveCommentsOnAdd()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('a.txt', 'a')), PCLZIP_OPT_COMMENT, 'left');
        $this->assertSame('left', $zip->properties()['comment']);
        $zip->add(array($this->virtualFile('b.txt', 'b')), PCLZIP_OPT_PREPEND_COMMENT, 'pre ', PCLZIP_OPT_ADD_COMMENT, ' +more');
        $this->assertSame('pre left +more', $zip->properties()['comment']);
    }

    public function testAddAppendsAndCreatesMissingArchive()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('a.txt', 'A')));
        $zip->add(array($this->virtualFile('b.txt', 'B')));
        $this->assertCount(2, $zip->listContent());
        $zip->add(array($this->virtualFile('a.txt', 'A2')));
        $this->assertCount(3, $zip->listContent());

        $zip2 = $this->archive('new.zip');
        $zip2->add(array($this->virtualFile('only.txt', '1')));
        $this->assertCount(1, $zip2->listContent());
    }

    public function testTempFileOptions()
    {
        $payload = str_repeat('z', 5000);
        $zipOn = $this->archive('temp-on.zip');
        $zipOn->create(array($this->virtualFile('big.bin', $payload)), PCLZIP_OPT_TEMP_FILE_ON);
        $this->assertSame($payload, $zipOn->extract(PCLZIP_OPT_EXTRACT_AS_STRING)[0]['content']);

        $zipTh = $this->archive('temp-th.zip');
        $zipTh->create(array($this->virtualFile('big.bin', $payload)), PCLZIP_OPT_TEMP_FILE_THRESHOLD, 0);
        $this->assertSame($payload, $zipTh->extract(PCLZIP_OPT_EXTRACT_AS_STRING)[0]['content']);

        $zipOff = $this->archive('temp-off.zip');
        $zipOff->create(array($this->virtualFile('big.bin', $payload)), PCLZIP_OPT_TEMP_FILE_OFF);
        $this->assertSame($payload, $zipOff->extract(PCLZIP_OPT_EXTRACT_AS_STRING)[0]['content']);

        $zipBad = $this->archive('bad-temp.zip');
        $this->assertSame(0, $zipBad->create(array($this->virtualFile('x.txt', 'x')), PCLZIP_OPT_TEMP_FILE_ON, PCLZIP_OPT_TEMP_FILE_OFF));
        $this->assertSame(PCLZIP_ERR_INVALID_PARAMETER, $zipBad->errorCode());
    }

    public function testOptionDefaultThreshold()
    {
        $threshold4m = $this->probeDefaultThresholdInChildProcess('4M');
        $probe2m = $this->probeDefaultThresholdInChildProcess('2M');
        $this->assertSame('1971322', $threshold4m);
        $this->assertSame('none', $probe2m);
    }

    /**
     * Run privOptionDefaultThreshold in an isolated CLI process with only pclzip.lib.php loaded.
     *
     * @param string $memoryLimit
     * @return string threshold value or the literal "none"
     */
    private function probeDefaultThresholdInChildProcess($memoryLimit)
    {
        $lib = dirname(__DIR__) . '/pclzip.lib.php';
        $code = sprintf(
            'require %s; ini_set("memory_limit", %s); $z = new PclZip("dummy.zip"); $opts = array();'
            . ' $z->privOptionDefaultThreshold($opts);'
            . ' echo isset($opts[PCLZIP_OPT_TEMP_FILE_THRESHOLD]) ? (string) $opts[PCLZIP_OPT_TEMP_FILE_THRESHOLD] : "none";',
            var_export($lib, true),
            var_export($memoryLimit, true)
        );

        $descriptor = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $cmdline = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -r ' . escapeshellarg($code);
        $process = proc_open($cmdline, $descriptor, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            $this->fail('Failed to start child PHP process for memory_limit probe');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->fail('Child PHP process failed (exit ' . $exitCode . '): ' . trim($stderr));
        }

        return trim($stdout);
    }

    public function testCreateErrors()
    {
        $zip = $this->archive();
        $this->assertSame(0, $zip->create('missing-file.txt'));
        $this->assertSame(PCLZIP_ERR_MISSING_FILE, $zip->errorCode());
        $this->assertSame(0, $zip->create(42));
        $this->assertSame(PCLZIP_ERR_INVALID_PARAMETER, $zip->errorCode());
        $this->ws->writeFile('one.txt', '1');
        $this->assertSame(0, $zip->create('one.txt', 'a', 'b', 'c'));
        $this->assertSame(PCLZIP_ERR_INVALID_PARAMETER, $zip->errorCode());
        $zip->create(array($this->virtualFile('ok.txt', 'ok')));
        $this->assertSame(PCLZIP_ERR_NO_ERROR, $zip->errorCode());
        $this->assertSame('PCLZIP_ERR_NO_ERROR', $zip->errorName());
    }
}
