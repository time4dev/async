<?php

namespace Time4dev\Async\Tests;

class InvokableClass
{
    public function __invoke()
    {
        return 2;
    }
}
