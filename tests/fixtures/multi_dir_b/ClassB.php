<?php
namespace MultiDir\B;

class ClassB
{
    public function getSource(): string
    {
        return 'Directory B';
    }
    
    public function getClassName(): string
    {
        return __CLASS__;
    }
}
