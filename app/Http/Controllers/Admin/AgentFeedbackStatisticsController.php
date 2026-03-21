<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentFeedbackStatisticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $period = $request->string('period')->toString();
        $allowedPeriods = ['7d', '30d', '90d', 'all'];

        if (! in_array($period, $allowedPeriods, true)) {
            $period = '30d';
        }

        $query = DB::table('agent_message_feedback as f')
            ->join('agent_conversation_messages as m', 'm.id', '=', 'f.message_id')
            ->join('agent_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('m.role', 'assistant');

        if ($period !== 'all') {
            $days = match ($period) {
                '7d' => 7,
                '90d' => 90,
                default => 30,
            };

            $query->where('f.created_at', '>=', now()->subDays($days));
        }

        $rows = $query
            ->selectRaw("
                c.context,
                m.agent,
                COUNT(*) as total_feedback,
                SUM(CASE WHEN f.feedback = 'up' THEN 1 ELSE 0 END) as up_count,
                SUM(CASE WHEN f.feedback = 'down' THEN 1 ELSE 0 END) as down_count
            ")
            ->groupBy('c.context', 'm.agent')
            ->orderByDesc('total_feedback')
            ->orderBy('c.context')
            ->orderBy('m.agent')
            ->get();

        $summaryRows = $query
            ->selectRaw("
                c.context,
                COUNT(*) as total_feedback,
                SUM(CASE WHEN f.feedback = 'up' THEN 1 ELSE 0 END) as up_count,
                SUM(CASE WHEN f.feedback = 'down' THEN 1 ELSE 0 END) as down_count
            ")
            ->groupBy('c.context')
            ->orderByDesc('total_feedback')
            ->orderBy('c.context')
            ->get();

        return Inertia::render('admin/AgentStatistics', [
            'period' => $period,
            'summary_rows' => $summaryRows->map(fn ($row) => [
                'context' => $row->context,
                'up_count' => (int) $row->up_count,
                'down_count' => (int) $row->down_count,
                'total_feedback' => (int) $row->total_feedback,
            ])->values()->all(),
            'rows' => $rows->map(fn ($row) => [
                'context' => $row->context,
                'agent' => $row->agent,
                'up_count' => (int) $row->up_count,
                'down_count' => (int) $row->down_count,
                'total_feedback' => (int) $row->total_feedback,
            ])->values()->all(),
        ]);
    }
}
