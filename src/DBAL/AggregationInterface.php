<?php

namespace MKCG\Model\DBAL;

interface AggregationInterface
{
    const TERMS = 'terms';
    const FACET = 'facet';
    const AVERAGE = 'average';
    const MIN = 'min';
    const MAX = 'max';
}
