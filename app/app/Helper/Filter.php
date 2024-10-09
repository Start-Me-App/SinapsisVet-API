<?php

declare(strict_types=1);

namespace App\Helper;

class Filter
{

    /**
     * Set filter for string.
     */
    public static function setFilter($args, $name = 'stringFilter'): ?string
    {
        if (is_array($args) && isset($args[$name])) {
            return str_replace("_", "%", $args[$name]);
        }

        return null;
    }

    /**
     * Add params filters to the query.
     */
    public static function addParamsFilters(array $params, array $list = []): array
    {
        $filters = [];
        foreach ($list as $key => $value) {
            if (isset($params[$value]) && $params[$value] !== '') {
                if (is_string($params[$value])) {
                    $filters[$value] = strtolower($params[$value]);
                } else {
                    $filters[$value] = $params[$value];
                }
            }
        }

        return $filters;
    }
}
