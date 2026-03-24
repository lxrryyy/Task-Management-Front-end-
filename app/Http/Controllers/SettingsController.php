<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Client\RequestException;

class SettingsController extends Controller{
    public function __construct(protected CsharpApiService $api) {}
}
