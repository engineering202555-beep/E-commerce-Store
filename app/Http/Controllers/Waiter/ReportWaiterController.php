<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportWaiterController extends Controller
{
    public function ReportByWaiter(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|min:5'
        ]);

        $user = auth()->user();
        $waiterId = $user->waiter->id;

        $report = Report::create([
            'waiter_id' => $waiterId,
            'title' => $request->title,
            'body' => $request->body
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال التقرير بنجاح.',
            'report' => [
                'report_id' => $report->id,
                'title' => $report->title,
                'body' => $report->body,
                'by_waiter_id'=> $report->waiter_id,
                'date_and_time'=>$report->created_at->format('Y/m/d - H:i'),
            ]
        ] ,200);
    }

    public function getMyReports()
    {
        $user = auth()->user();
        $waiterId = $user->waiter->id;

        $reports = Report::where('waiter_id', $waiterId)
            ->orderByDesc('created_at')
            ->get(['title', 'body', 'created_at']);

        $formattedReports = $reports->map(function ($report) {
            return [
                'title' => $report->title,
                'body' => $report->body,
                'date' => $report->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'reports' => $formattedReports,
        ]);
    }

}
