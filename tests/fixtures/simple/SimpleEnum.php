<?php
namespace TestNamespace;

enum SimpleEnum: string
{
    case OPTION_A = 'option_a';
    case OPTION_B = 'option_b';
    
    public function getDescription(): string
    {
        return match($this) {
            self::OPTION_A => 'Option A Description',
            self::OPTION_B => 'Option B Description',
        };
    }
}
