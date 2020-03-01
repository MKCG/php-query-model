<?php

namespace MKCG\Model\DBAL;

class ScrollContext
{
    private $state = 1;

    public $data = [];

    public function stop()
    {
        $this->state = 0;
        return $this;
    }

    public function canScroll()
    {
        return $this->state === 1;
    }
}
