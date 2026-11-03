<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateListeningReport;
use App\Jobs\GenerateMonthlyReport;
use App\Models\Client;
use App\Models\Report;
use App\Reporting\ListeningReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Client $client): View
    {
        return view('reports.index', ['client' => $client, 'reports' => $client->reports()->latest()->get()]);
    }

    public function monthly(Request $request, Client $client): RedirectResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m', 'before:'.now()->format('Y-m')]])['month'];
        GenerateMonthlyReport::dispatch($client, $month);

        return redirect()->route('clients.reports', $client)->with('status', 'report-queued');
    }

    public function listening(Client $client): RedirectResponse
    {
        abort_unless(ListeningReport::watches($client), 422, 'Add hashtags_to_watch or competitors to the research section of the profile first.');
        GenerateListeningReport::dispatch($client);

        return redirect()->route('clients.reports', $client)->with('status', 'report-queued');
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
