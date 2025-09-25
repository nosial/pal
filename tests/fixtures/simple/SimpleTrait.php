<?php
namespace TestNamespace;

trait SimpleTrait
{
    public function getTraitName(): string
    {
        return __TRAIT__;
    }
}
