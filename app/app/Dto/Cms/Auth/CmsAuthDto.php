<?php

declare(strict_types=1);

namespace App\Dto\Cms\Auth;

class CmsAuthDto
{
    public int $id;
    public string $username;
    public string $correo;
    public int $roleId;
    public string $lastDateLogin;

    public function __construct($data)
    {
        $this->id            = (int)$data->id;
        $this->username      = (string)$data->username;
        $this->correo        = (string)$data->correo;
        $this->roleId        = (int)$data->roleId;
        $this->lastDateLogin = (string)$data->lastDateLogin;
    }
}
