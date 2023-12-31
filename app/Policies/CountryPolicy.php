<?php

namespace App\Policies;

use App\Constants\PermissionModel;

class CountryPolicy extends BasePolicy
{
    public function __construct()
    {
        parent::__construct(PermissionModel::COUNTRY);
    }
}
