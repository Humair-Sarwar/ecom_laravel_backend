<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SiteMenu;
use Illuminate\Http\Request;

class SiteMenuController extends Controller
{
    public function getVendorMenu(Request $request){
        $menu = SiteMenu::where('business_id', auth()->user()->id)->where('key', $request->key)->first();
        
        return response()->json([
            'status' => 200,
            'message' => 'Vendor menu fetched successfully!',
            'data' => $menu
        ], 200);
    }

    public function save(Request $request)
    {
        $menu = SiteMenu::updateOrCreate(
            [
                'business_id' => auth()->user()->id,
                'key' => $request->key,
            ],
            [
                'menu' => $request->menu,
                'menu_title' => $request->menu_title,
                'is_active' => $request->is_active,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Menu saved successfully!',
            'data' => $menu,
        ], 200);
    }

    // Site Menu Get
    public function siteMenuGet(Request $request){
        $menu = SiteMenu::where('key', $request->key)->first();
        if ($menu) {
            $menu->menu = json_decode($menu->menu, true);
        }
        return response()->json([
            'status' => true,
            'message' => 'Site Menu fetched successfully!',
            'data' => $menu
        ], 200);
    }
}
