<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function create(): View
    {
        return view('leads.form', ['lead' => null]);
    }

    public function index(): View
    {
        return view('leads.index');
    }

    public function edit(Lead $lead): View
    {
        return view('leads.form', ['lead' => $lead]);
    }

    public function organizations(): View
    {
        return view('organizations.index');
    }

    public function branches(): View
    {
        return view('branches.index');
    }

    public function employees(): View
    {
        return view('employees.index');
    }
}
