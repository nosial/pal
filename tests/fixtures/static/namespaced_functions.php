<?php
namespace TestNamespace;

// Static file with namespaced functions

function pal_test_namespaced_function(): string
{
    return 'namespaced_function_result';
}

function pal_test_math_function(int $a, int $b): int
{
    return $a + $b;
}
