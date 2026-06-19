<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PropositionStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Proposition;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;
        $now = now();

        $totalContacts = Contact::where('tenant_id', $tenantId)->count();

        $growthThisMonth = Contact::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();

        $birthdaysToday = Contact::where('tenant_id', $tenantId)
            ->whereMonth('birth_date', $now->month)
            ->whereDay('birth_date', $now->day)
            ->count();

        $closedStatuses = [
            ServiceRequestStatus::Resolved->value,
            ServiceRequestStatus::Archived->value,
        ];

        $openRequests = ServiceRequest::where('tenant_id', $tenantId)
            ->whereIn('status', [
                ServiceRequestStatus::New->value,
                ServiceRequestStatus::InProgress->value,
                ServiceRequestStatus::Waiting->value,
            ])
            ->count();

        $overdueRequests = ServiceRequest::where('tenant_id', $tenantId)
            ->whereNotIn('status', $closedStatuses)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $now->toDateString())
            ->count();

        $avgResolutionHours = ServiceRequest::where('tenant_id', $tenantId)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at) / 3600) as avg_hours')
            ->value('avg_hours');

        $sentThisMonth = Campaign::where('tenant_id', $tenantId)
            ->where('status', 'sent')
            ->where('completed_at', '>=', $now->copy()->subDays(30))
            ->count();

        $totalActive = Proposition::where('tenant_id', $tenantId)
            ->whereNotIn('status', [
                PropositionStatus::Archived->value,
                PropositionStatus::Approved->value,
                PropositionStatus::Vetoed->value,
            ])
            ->count();

        $approvedCount = Proposition::where('tenant_id', $tenantId)
            ->where('status', PropositionStatus::Approved->value)
            ->count();

        return response()->json([
            'contacts' => [
                'total'            => $totalContacts,
                'growthThisMonth'  => $growthThisMonth,
                'birthdaysToday'   => $birthdaysToday,
            ],
            'serviceRequests' => [
                'open'                => $openRequests,
                'overdue'             => $overdueRequests,
                'avgResolutionHours'  => $avgResolutionHours !== null ? round((float) $avgResolutionHours, 1) : null,
            ],
            'campaigns' => [
                'sentThisMonth' => $sentThisMonth,
                'avgOpenRate'   => null,
            ],
            'propositions' => [
                'totalActive' => $totalActive,
                'approved'    => $approvedCount,
            ],
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $rows = DB::table('contacts')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function serviceRequests(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $byStatus = DB::table('service_requests')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $byCategory = DB::table('service_requests')
            ->join('service_categories', 'service_requests.category_id', '=', 'service_categories.id')
            ->where('service_requests.tenant_id', $tenantId)
            ->whereNull('service_requests.deleted_at')
            ->selectRaw('service_categories.name as category, COUNT(*) as total')
            ->groupBy('service_categories.name')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'by_status' => $byStatus,
            'by_category' => $byCategory,
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $campaigns = Campaign::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (Campaign $campaign) {
                $sentCount = (int) $campaign->sent_count;
                $openedCount = (int) $campaign->opened_count;

                return [
                    'id' => $campaign->id,
                    'ulid' => $campaign->ulid,
                    'name' => $campaign->name,
                    'channel' => $campaign->channel?->value,
                    'status' => $campaign->status?->value,
                    'sent_count' => $sentCount,
                    'open_rate' => $sentCount > 0 ? round(($openedCount / $sentCount) * 100, 1) : 0.0,
                    'completed_at' => $campaign->completed_at?->toIso8601String(),
                ];
            });

        return response()->json(['data' => $campaigns]);
    }

    public function propositions(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $byType = DB::table('propositions')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->get();

        $byStatus = DB::table('propositions')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'by_type' => $byType,
            'by_status' => $byStatus,
        ]);
    }

    public function team(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $users = User::where('tenant_id', $tenantId)
            ->select('id', 'name', 'role')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->value,
            ]);

        return response()->json(['data' => $users]);
    }
}
