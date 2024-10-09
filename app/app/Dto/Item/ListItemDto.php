<?php

declare(strict_types=1);

namespace App\Dto\Item;

final class ListItemDto
{
    public int $id;
    public int $resellerId;
    public string $resellerDescription;
    public int $brandId;
    public string $brandDescription;
    public string $description;
    public float $price;
    public float $lastPrice;
    public float $percentage;
    public string $destinyUrl;
    public string $defaultImgUrl;

    public function __construct($data)
    {
        $this->id                  = (int)$data->id;
        $this->resellerId          = (int)$data->resellerId;
        $this->resellerDescription = (string)$data->resellerDescription;
        $this->brandId             = (int)$data->brandId;
        $this->brandDescription    = (string)$data->brandDescription;
        $this->description         = (string)$data->description;
        $this->price               = (float)$data->price;
        $this->lastPrice           = (float)$data->lastPrice;
        $this->percentage          = (float)$data->percentage;
        $this->destinyUrl          = (string)$data->destinyUrl;
        $this->defaultImgUrl       = (string)$data->defaultImgUrl;
    }

    public static function fromArray($items): array
    {
        $list = [];
        foreach ($items as $item) {
            $list[] = new self($item);
        }

        return $list;
    }

}
