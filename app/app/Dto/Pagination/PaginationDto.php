<?php

declare(strict_types=1);

namespace App\Dto\Pagination;

class PaginationDto
{
    public int $total;
    public int $offset;
    public int $limit;
    public string $order;

    public function __construct($total, $offset, $limit, $order)
    {
        $this->total  = (int)$total;
        $this->offset = (int)$offset;
        $this->limit  = (int)$limit;
        $this->order  = (string)$order;
    }

    public static function default($pagination, $sort): array
    {
        return [
            'response'   => [],
            'pagination' => new self(
                total : 0,
                offset: $pagination->offset,
                limit : $pagination->limit,
                order : $sort
            )
        ];
    }
}
