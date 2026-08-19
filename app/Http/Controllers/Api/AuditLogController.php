<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request)
    {
        $filters = $request->validated();
        $query = AuditLog::query()->with('user')->latest('created_at')->latest('id');

        $query
            ->when($filters['event'] ?? null, fn ($builder, string $event) => $builder->where('event', $event))
            ->when($filters['user_id'] ?? null, fn ($builder, int $userId) => $builder->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($builder, string $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($builder, string $date) => $builder->whereDate('created_at', '<=', $date));

        return AuditLogResource::collection(
            $query->paginate($filters['per_page'] ?? 20)->withQueryString(),
        );
    }

    public function options(): JsonResponse
    {
        $userIds = AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        return response()->json(['data' => [
            'events' => collect(AuditEventCategory::cases())->map(fn (AuditEventCategory $event): array => [
                'value' => $event->value,
                'label' => $event->label(),
                'group' => $event->group(),
            ])->values(),
            'users' => User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'photo_path'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'has_photo' => (bool) $user->photo_path,
                ]),
        ]]);
    }
}
