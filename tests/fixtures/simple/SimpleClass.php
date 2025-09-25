<?php
namespace TestNamespace;

class SimpleClass
{
    public function __construct()
    {
        // Simple test class
    }
    
    public function getClassName(): string
    {
        return __CLASS__;
    }
}
