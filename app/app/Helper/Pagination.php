<?php

declare(strict_types=1);

namespace App\Helper;

final class Pagination
{

    public static function setPagination($params): object
    {
        $params = is_array($params) ? $params : [];

        $offset = $params['offset'] ?? 0;

        $limit = (int)env('LIMIT_DEFAULT');

        return self::response($limit, $offset);
    }

    private static function response($limit, $offset): object
    {
        return (object)[
            'limit'  => $limit,
            'offset' => $offset ?? 0
        ];
    }
}
