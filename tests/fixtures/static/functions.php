<?php
// Static file with standalone functions

function pal_test_function_one(): string
{
    return 'function_one_result';
}

function pal_test_function_two(int $value): int
{
    return $value * 2;
}

function pal_test_function_three(): bool
{
    return true;
}
