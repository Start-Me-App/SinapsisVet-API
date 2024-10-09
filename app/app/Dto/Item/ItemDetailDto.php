<?php

declare(strict_types=1);

namespace App\Dto\Item;

class ItemDetailDto
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
    public array $histogram;

    public function __construct(
        $id,
        $resellerId,
        $resellerDescription,
        $brandId,
        $brandDescription,
        $description,
        $price,
        $lastPrice,
        $percentage,
        $destinyUrl,
        $defaultImgUrl,
        $histogram
    ) {
        $this->id                  = (int)$id;
        $this->resellerId          = (int)$resellerId;
        $this->resellerDescription = (string)$resellerDescription;
        $this->brandId             = (int)$brandId;
        $this->brandDescription    = (string)$brandDescription;
        $this->description         = (string)$description;
        $this->price               = (float)$price;
        $this->lastPrice           = (float)$lastPrice;
        $this->percentage          = (float)$percentage;
        $this->destinyUrl          = (string)$destinyUrl;
        $this->defaultImgUrl       = (string)$defaultImgUrl;
        $this->histogram           = (array)$histogram;
    }
}
