<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'phone' => ['nullable','string','max:30', Rule::unique('users','phone')->ignore($user->id)],
            'locale' => ['nullable','string','max:10'],
            'timezone' => ['nullable','string','max:50'],
        ]);
        $user->update($data);
        return response()->json(['message' => 'Profile updated successfully.', 'data' => new UserResource($user->fresh())]);
    }
}
