<?php

namespace App\Mcp\Tools;

use App\Reporting\MonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('A client\'s numbers for one month, as the monthly report builds them: every post with its reach, views, likes, comments, shares and saves, and totals per platform.')]
class MonthNumbers extends Tool
{
    use ScopedToToken;

    public function handle(Request $request, MonthlyReport $report): Response
    {
        $data = $request->validate(['client' => ['required'], 'month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($data['month']) ? CarbonImmutable::createFromFormat('Y-m-d', $data['month'].'-01')->startOfMonth() : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        return Response::text($report->numbers($this->client($data['client']), $month));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client' => $schema->string()->description('Client id or slug')->required(),
            'month' => $schema->string()->description('YYYY-MM, default last month'),
        ];
    }
}
