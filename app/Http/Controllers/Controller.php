<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * 基礎 Controller 類別
 * 
 * 所有控制器的基類
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
