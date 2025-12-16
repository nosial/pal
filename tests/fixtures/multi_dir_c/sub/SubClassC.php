<?php
namespace MultiDir\C\Sub;

class SubClassC
{
    public function getSource(): string
    {
        return 'Directory C Subdirectory';
    }
    
    public function getClassName(): string
    {
        return __CLASS__;
    }
}
