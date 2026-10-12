<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Uploads;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;

/** Branding and contact details, stored in the database instead of config.php. */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['org' => Tenancy::current(), 'staff' => User::orderBy('name')->get()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'email_from_name' => ['nullable', 'string', 'max:150'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'town' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'about' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'max:12288'],
        ]);
        unset($data['logo']);
        $org = Tenancy::current();
        if ($request->hasFile('logo')) {
            [$path, $err] = Uploads::storeImage($request->file('logo'), 'organizers', 600);
            if ($err) {
                return back()->withInput()->with('error', $err);
            }
            Uploads::delete($org->logo_path);
            $org->logo_path = $path;
        }
        $org->fill(array_map(fn ($v) => $v ?? '', $data))->save();

        return back()->with('status', 'Settings saved.');
    }
}
