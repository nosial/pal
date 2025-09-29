<?php
// Static file with standalone functions for disabled test

function pal_test_disabled_function(): string
{
    return 'disabled_function_result';
}

function pal_test_disabled_math(int $a, int $b): int
{
    return $a - $b;
}
