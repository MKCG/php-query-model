<?php

namespace MKCG\Model;

class Model
{
    private $fromClass;
    private $alias;
    private $setType;
    private $withs = [];

    public function __construct(
        string $class,
        string $setType = '',
        string $alias = ''
    ) {
        $this->fromClass = $class;
        $this->alias = $alias;
        $this->setType = $setType;

        return $this;
    }

    public function getFromClass() : string
    {
        return $this->fromClass;
    }

    public function setAlias($alias) : self
    {
        $this->alias = $alias;
        return $this;
    }

    public function getAlias() : string
    {
        return $this->alias;
    }

    public function getSetType() : string
    {
        return $this->setType;
    }

    public function getWith() : array
    {
        return $this->withs;
    }

    public function with(Model $submodel) : self
    {
        foreach ($this->withs as $with) {
            if ($with === $submodel) {
                return $this;
            }
        }

        $this->withs[] = $submodel;
        return $this;
    }
}
