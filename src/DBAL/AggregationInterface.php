<?php

namespace MKCG\Model\DBAL;

interface AggregationInterface
{
    const AGG_TERMS_CARDINALITY = 'terms_cardinality';
    const AGG_FACET = 'facet';
    const AGG_AVERAGE = 'average';
    const AGG_MIN = 'min';
    const AGG_MAX = 'max';
}
