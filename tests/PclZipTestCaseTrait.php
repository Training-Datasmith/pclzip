<?php

namespace PclZip\Tests;

trait PclZipTestCaseTrait
{
    /** @var Workspace */
    protected $ws;

    protected function pclzipSetUp()
    {
        CallbackProbe::reset();
        unset($GLOBALS['pclzip_abort_count']);
        $this->ws = new Workspace();
    }

    protected function pclzipTearDown()
    {
        if ($this->ws) {
            $this->ws->cleanup();
        }
    }

    protected function archive($name = 'test.zip')
    {
        return new \PclZip($this->ws->path($name));
    }

    protected function virtualFile($name, $content, $mtime = null)
    {
        $entry = array(
            \PCLZIP_ATT_FILE_NAME => $name,
            \PCLZIP_ATT_FILE_CONTENT => $content,
        );
        if ($mtime !== null) {
            $entry[\PCLZIP_ATT_FILE_MTIME] = $mtime;
        }

        return $entry;
    }

    protected function assertIsArrayCompat($value, $message = '')
    {
        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($value, $message);
        } else {
            $this->assertInternalType('array', $value, $message);
        }
    }

    protected function assertStringContainsCompat($needle, $haystack, $message = '')
    {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
        } else {
            $this->assertContains($needle, $haystack, $message);
        }
    }
}
