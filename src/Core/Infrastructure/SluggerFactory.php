<?php

namespace App\Core\Infrastructure;

use Symfony\Component\String\Slugger\AsciiSlugger;

class SluggerFactory
{
    public static function createSlugger(): AsciiSlugger
    {
        return new AsciiSlugger();
    }
}
