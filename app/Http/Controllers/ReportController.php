<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Report;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Client $client): View
    {
        return view('reports.index', ['client' => $client, 'reports' => $client->reports()->latest()->get()]);
    }

    public function show(Report $report): View
    {
        return view('reports.show', [
            'report' => $report,
            'html' => Str::markdown($report->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }

    public function download(Report $report): Response
    {
        return response($report->body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($report->title).'.md"',
        ]);
    }
}
