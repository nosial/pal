<?php
namespace MultiDir\A;

class ClassA
{
    public function getSource(): string
    {
        return 'Directory A';
    }
    
    public function getClassName(): string
    {
        return __CLASS__;
    }
}
