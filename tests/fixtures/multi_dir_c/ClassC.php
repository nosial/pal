<?php
namespace MultiDir\C;

class ClassC
{
    public function getSource(): string
    {
        return 'Directory C';
    }
    
    public function getClassName(): string
    {
        return __CLASS__;
    }
}
