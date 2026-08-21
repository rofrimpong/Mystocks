<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403, 'Platform administrator access required.');
    }

    public function summary(Request $request): JsonResponse
    {
        $this->guard($request);
        return response()->json(['data' => [
            'users_total' => User::withTrashed()->count(),
            'users_active' => User::where('is_active', true)->count(),
            'users_suspended' => User::where('is_active', false)->count(),
            'businesses_total' => Business::withTrashed()->count(),
            'businesses_active' => Business::whereIn('status', ['active','trial'])->count(),
            'businesses_suspended' => Business::where('status', 'suspended')->count(),
        ]]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->guard($request);
        $q = User::query()->orderByDesc('created_at');
        if ($request->filled('search')) { $term=(string) $request->input('search'); $q->where(fn($x)=>$x->where('name','like',"%{$term}%")->orWhere('email','like',"%{$term}%")->orWhere('phone','like',"%{$term}%")); }
        $rows=$q->paginate($request->integer('per_page',25));
        return response()->json(['data'=>UserResource::collection($rows),'meta'=>['total'=>$rows->total()]]);
    }

    public function updateUser(Request $request, string $id): JsonResponse
    {
        $this->guard($request);
        $user=User::findOrFail($id);
        abort_if($user->id === $request->user()->id && $request->has('is_active') && ! $request->boolean('is_active'), 422, 'You cannot suspend your own administrator account.');
        $data=$request->validate(['is_active'=>['sometimes','boolean'],'is_platform_admin'=>['sometimes','boolean']]);
        $user->update($data);
        if (array_key_exists('is_active',$data) && ! $data['is_active']) $user->tokens()->delete();
        return response()->json(['message'=>'User updated.','data'=>new UserResource($user->fresh())]);
    }

    public function businesses(Request $request): JsonResponse
    {
        $this->guard($request);
        $rows=Business::withCount('users')->orderByDesc('created_at')->paginate($request->integer('per_page',25));
        return response()->json(['data'=>$rows->items(),'meta'=>['total'=>$rows->total()]]);
    }

    public function updateBusiness(Request $request, string $id): JsonResponse
    {
        $this->guard($request);
        $data=$request->validate([
            'status'=>['sometimes', Rule::in(['active','suspended','trial','cancelled'])],
            'plan'=>['sometimes', Rule::in(['free','starter','business','pro','enterprise'])],
        ]);
        $business=Business::findOrFail($id); $business->update($data);
        return response()->json(['message'=>'Business updated.','data'=>$business->fresh()]);
    }
    public function plans(Request $request): JsonResponse
    {
        $this->guard($request);
        $plans = DB::table('plans')->orderBy('sort_order')->get();
        return response()->json(['data' => $plans]);
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_yearly' => ['sometimes', 'numeric', 'min:0'],
            'max_products' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_users' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_branches' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'has_reports' => ['sometimes', 'boolean'],
            'has_multi_branch' => ['sometimes', 'boolean'],
            'has_api_access' => ['sometimes', 'boolean'],
            'has_priority_support' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $plan = DB::table('plans')->where('id', $id)->first();
        abort_unless($plan, 404, 'Pricing plan not found.');
        DB::table('plans')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        return response()->json(['message' => 'Pricing plan updated.', 'data' => DB::table('plans')->where('id', $id)->first()]);
    }

}
