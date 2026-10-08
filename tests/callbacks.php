<?php

function pclzip_pre_add_skip($event, &$header)
{
    return 0;
}

function pclzip_pre_add_rename($event, &$header)
{
    $header['stored_filename'] = 'renamed.txt';

    return 1;
}

function pclzip_post_add_record($event, &$header)
{
    PclZip\Tests\CallbackProbe::record($event, $header['stored_filename']);

    return 1;
}

function pclzip_pre_extract_skip($event, &$header)
{
    return 0;
}

function pclzip_pre_extract_abort_first($event, &$header)
{
    if (!isset($GLOBALS['pclzip_abort_count'])) {
        $GLOBALS['pclzip_abort_count'] = 0;
    }
    $GLOBALS['pclzip_abort_count']++;
    if ($GLOBALS['pclzip_abort_count'] === 1) {
        return 2;
    }

    return 1;
}

function pclzip_post_extract_edit($event, &$header)
{
    if (isset($header['content'])) {
        $header['content'] = 'edited';
    }

    return 1;
}
