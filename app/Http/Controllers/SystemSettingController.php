<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SystemSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = SystemSetting::all();
        return response()->json($settings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:system_settings,key|max:255',
            'value' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $setting = SystemSetting::create($validated);
        return response()->json($setting, 201);
    }

    public function show(SystemSetting $systemSetting): JsonResponse
    {
        return response()->json($systemSetting);
    }

    public function update(Request $request, SystemSetting $systemSetting): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'string|unique:system_settings,key,' . $systemSetting->id . '|max:255',
            'value' => 'string',
            'description' => 'nullable|string',
        ]);

        $systemSetting->update($validated);
        return response()->json($systemSetting);
    }
     public function destroy(SystemSetting $systemSetting): JsonResponse
    {
        $systemSetting->delete();
        return response()->json(['message' => 'System setting deleted successfully']);
    }
}