<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// ADMIN side: view and update the resort profile
class AdminSettingController extends Controller
{
    // Show the settings form
    public function index()
    {
        // Build one object so the view can use $setting->resort_name, etc.
        $setting = (object) [
            'resort_name'    => Setting::getValue('resort_name'),
            'contact_number' => Setting::getValue('contact_number'),
            'email'          => Setting::getValue('email'),
            'address'        => Setting::getValue('address'),
            'logo'           => Setting::getValue('logo'),
        ];

        return view('Admin.dashboard', compact('setting'));
    }

    // Save the changes
    public function update(Request $request)
    {
        $data = $request->validate([
            'resort_name'    => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:500',
            'logo'           => 'nullable|file|mimes:svg,png,jpg,jpeg|max:2048',
        ]);

        // Save each text field as its own key/value row
        foreach (['resort_name', 'contact_number', 'email', 'address'] as $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $data[$key] ?? '']
            );
        }

        // If a new logo was uploaded, delete the old one and save the new one
        if ($request->hasFile('logo')) {
            $oldLogo = Setting::getValue('logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }

            $path = $request->file('logo')->store('logos', 'public');
            Setting::updateOrCreate(['key' => 'logo'], ['value' => $path]);
        }

        return back()->with('success', 'Settings updated.');
    }
}