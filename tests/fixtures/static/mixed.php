<?php
// Mixed file with both class and functions - should NOT be included as static

class MixedClass
{
    public function test(): string
    {
        return 'mixed';
    }
}

function mixed_function(): string
{
    return 'should_not_be_auto_included';
}
