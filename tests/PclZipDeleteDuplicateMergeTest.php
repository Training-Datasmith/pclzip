<?php

namespace PclZip\Tests;

class PclZipDeleteDuplicateMergeTest extends PclZipTestCase
{
    public function testDeleteByNameAndPreg()
    {
        $zip = $this->archive();
        $zip->create(array(
            $this->virtualFile('a.txt', 'A'),
            $this->virtualFile('b.txt', 'B'),
        ));
        $left = $zip->delete(PCLZIP_OPT_BY_NAME, 'a.txt');
        $this->assertCount(1, $left);
        $this->assertSame('b.txt', $left[0]['stored_filename']);
        $this->assertCount(1, $zip->listContent());

        $zip2 = $this->archive('dir-del.zip');
        $zip2->create(array(
            $this->virtualFile('docs/a.txt', 'A'),
            $this->virtualFile('docs/b.txt', 'B'),
            $this->virtualFile('top.txt', 'T'),
        ));
        $zip2->delete(PCLZIP_OPT_BY_NAME, 'docs/');
        $names = array();
        foreach ($zip2->listContent() as $row) {
            $names[] = $row['stored_filename'];
        }
        $this->assertSame(array('top.txt'), $names);

        $zip3 = $this->archive('preg.zip');
        $zip3->create(array(
            $this->virtualFile('a.txt', 'A'),
            $this->virtualFile('b.txt', 'B'),
        ));
        $zip3->delete(PCLZIP_OPT_BY_PREG, '/^a/');
        $this->assertSame('b.txt', $zip3->listContent()[0]['stored_filename']);
    }

    public function testDeleteByIndexAndAll()
    {
        $zip = $this->archive();
        $zip->create(array(
            $this->virtualFile('0.txt', '0'),
            $this->virtualFile('1.txt', '1'),
        ));
        $left = $zip->deleteByIndex('0');
        $this->assertSame('1.txt', $left[0]['stored_filename']);
        $this->assertSame(0, $zip->listContent()[0]['index']);

        $zip2 = $this->archive('empty.zip');
        $zip2->create(array($this->virtualFile('only.txt', 'x')));
        $zip2->delete();
        $this->assertSame(0, $zip2->properties()['nb']);
        $this->assertSame(array(), $zip2->listContent());
        $this->assertSame(PCLZIP_ERR_NO_ERROR, $zip2->errorCode());
    }

    public function testDuplicate()
    {
        $src = $this->archive('src.zip');
        $src->create(array($this->virtualFile('inner.txt', 'payload')));
        $dest = $this->archive('dest.zip');
        $this->assertSame(1, $dest->duplicate($this->ws->path('src.zip')));
        $out = $dest->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('payload', $out[0]['content']);

        $missing = $this->archive('dest2.zip');
        $this->assertSame(PCLZIP_ERR_MISSING_FILE, $missing->duplicate('no-such.zip'));
        $this->assertSame(PCLZIP_ERR_INVALID_PARAMETER, $missing->duplicate(42));
    }

    public function testDuplicateByObject()
    {
        $src = $this->archive('src.zip');
        $src->create(array($this->virtualFile('inner.txt', 'payload')));
        $dest = $this->archive('dest.zip');
        $this->assertSame(1, $dest->duplicate($src));
        $out = $dest->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        $this->assertSame('payload', $out[0]['content']);
    }

    public function testMerge()
    {
        $left = $this->archive('left.zip');
        $left->create(array($this->virtualFile('a.txt', 'A')), PCLZIP_OPT_COMMENT, 'left');
        $right = $this->archive('right.zip');
        $right->create(array($this->virtualFile('b.txt', 'B')), PCLZIP_OPT_COMMENT, 'right');
        $this->assertSame(1, $left->merge($this->ws->path('right.zip')));
        $this->assertSame('left right', $left->properties()['comment']);
        $names = array();
        foreach ($left->listContent() as $row) {
            $names[] = $row['stored_filename'];
        }
        $this->assertContains('a.txt', $names);
        $this->assertContains('b.txt', $names);

        $solo = $this->archive('solo.zip');
        $solo->create(array($this->virtualFile('only.txt', 'O')));
        $this->assertSame(1, $solo->merge('missing-merge.zip'));
        $this->assertCount(1, $solo->listContent());
    }

    public function testMergeByObject()
    {
        $left = $this->archive('left.zip');
        $left->create(array($this->virtualFile('a.txt', 'A')));
        $right = $this->archive('right.zip');
        $right->create(array($this->virtualFile('b.txt', 'B')));
        $this->assertSame(1, $left->merge($right));
        $this->assertCount(2, $left->listContent());
    }
}
