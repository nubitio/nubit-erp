<?php

namespace App\Util;

use Symfony\Component\String\Slugger\AsciiSlugger;

class SluggerFactory
{
    public static function createSlugger(): AsciiSlugger
    {
        return new AsciiSlugger();
    }
}
