<?php

namespace Nemo64\DbalRdsData\Tests\TestClasses;


class ClassWithConstructor
{
    protected $col1;

    private $col2;

    public $dataDuringConstruct;

    public $dataPassedToConstructor;

    public function __construct()
    {
        $this->dataDuringConstruct = [$this->col1, $this->col2];
        $this->dataPassedToConstructor = func_get_args();
    }

    public function set($col1, $col2)
    {
        $this->col1 = $col1;
        $this->col2 = $col2;
    }
}
