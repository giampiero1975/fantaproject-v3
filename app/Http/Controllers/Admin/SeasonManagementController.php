<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class SeasonManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.seasons.index');
    }
}
