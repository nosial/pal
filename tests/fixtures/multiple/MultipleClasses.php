<?php
// File with multiple classes
namespace MultipleClasses;

class FirstClass
{
    public function getName(): string
    {
        return 'first';
    }
}

class SecondClass
{
    public function getName(): string
    {
        return 'second';
    }
}

interface MultiInterface
{
    public function multi(): void;
}

trait MultiTrait
{
    public function multiMethod(): string
    {
        return 'multi';
    }
}
