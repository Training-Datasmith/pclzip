<?php

namespace PclZip\Tests;

class PclZipCallbackTest extends PclZipTestCase
{
    public function testPreAddSkip()
    {
        $zip = $this->archive();
        $list = $zip->create(
            array($this->virtualFile('skip.txt', 'x')),
            PCLZIP_CB_PRE_ADD,
            'pclzip_pre_add_skip'
        );
        $this->assertSame('skipped', $list[0]['status']);
        $this->assertCount(0, $zip->listContent());
    }

    public function testPreAddRename()
    {
        $zip = $this->archive();
        $zip->create(
            array($this->virtualFile('orig.txt', 'body')),
            PCLZIP_CB_PRE_ADD,
            'pclzip_pre_add_rename'
        );
        $this->assertSame('renamed.txt', $zip->listContent()[0]['stored_filename']);
        $this->assertSame('body', $zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING)[0]['content']);
    }

    public function testPostAdd()
    {
        $zip = $this->archive();
        $zip->create(
            array($this->virtualFile('tracked.txt', 't')),
            PCLZIP_CB_POST_ADD,
            'pclzip_post_add_record'
        );
        $this->assertCount(1, CallbackProbe::$calls);
        $this->assertSame(PCLZIP_CB_POST_ADD, CallbackProbe::$calls[0]['event']);
        $this->assertSame('tracked.txt', CallbackProbe::$calls[0]['name']);
    }

    public function testPreExtractSkip()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('skip.txt', 'x')));
        $list = $zip->extract(PCLZIP_CB_PRE_EXTRACT, 'pclzip_pre_extract_skip');
        $this->assertSame('skipped', $list[0]['status']);
        $this->assertFileNotExists($this->ws->path('skip.txt'));
    }

    public function testPreExtractAbort()
    {
        $zip = $this->archive();
        $zip->create(array(
            $this->virtualFile('first.txt', '1'),
            $this->virtualFile('second.txt', '2'),
        ));
        $list = $zip->extract(PCLZIP_CB_PRE_EXTRACT, 'pclzip_pre_extract_abort_first');
        $this->assertSame('skipped', $list[0]['status']);
        $this->assertCount(1, $list);
        $this->assertFileNotExists($this->ws->path('second.txt'));
    }

    public function testPostExtractStringEdit()
    {
        $zip = $this->archive();
        $zip->create(array($this->virtualFile('edit.txt', 'raw')));
        $list = $zip->extract(
            PCLZIP_OPT_EXTRACT_AS_STRING,
            PCLZIP_CB_POST_EXTRACT,
            'pclzip_post_extract_edit'
        );
        $this->assertSame('edited', $list[0]['content']);
    }

    public function testUnknownCallbackFunction()
    {
        $zip = $this->archive();
        $this->assertSame(0, $zip->create(
            array($this->virtualFile('x.txt', 'x')),
            PCLZIP_CB_PRE_ADD,
            'pclzip_no_such_function'
        ));
        $this->assertSame(PCLZIP_ERR_INVALID_OPTION_VALUE, $zip->errorCode());
        $this->assertStringContainsCompat('pclzip_no_such_function', $zip->errorInfo());
    }
}
