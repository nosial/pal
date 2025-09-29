<?php
// Static file with standalone functions for disabled test

function pal_test_disabled_function_one(): string
{
    return 'disabled_function_one_result';
}

function pal_test_disabled_function_two(int $value): int
{
    return $value * 3;
}
