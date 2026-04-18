<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    public function index(Request $request){
        $perPage = $request->per_page ?? 10;
        $query = Media::where('business_id', auth()->user()->id);
        $medias = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $from = ($medias->currentPage() - 1) * $medias->perPage() + 1;
        $to = min($medias->currentPage() * $medias->perPage(), $medias->total());
        return response()->json([
            'status' => true,
            'message' => 'Media list retrieved successfully',
            'data' => $medias->items(),
            'pagination' => [
                'current_page' => $medias->currentPage(),
                'last_page' => $medias->lastPage(),
                'per_page' => $medias->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $medias->total()
            ]
        ], 200);
    }

    public function addNewMedia(Request $request){
        $file = $request->file('image');
        $validateMedia = Validator::make($request->all(), [
            'image' => 'required|file|mimes:png,jpg,jpeg|max:2000'
        ]);
        if($validateMedia->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'error' => $validateMedia->errors()->all()
            ], 422);
        }

        $fileName = time() . '_' . $file->getClientOriginalName();
        $path = $request->image->storeAs('images', $fileName, 'public');
        $media = Media::create(['media_path' => $path, 'business_id' => auth()->user()->id]);

        return response()->json([
                'status' => true,
                'message' => 'Media added successfully!',
                'data' => $media
            ], 200);
    }

    public function addMultipleUploads(Request $request){
        $validate = Validator::make($request->all(), [
            'images' => 'required',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2000'
        ]);
        $uploadedFiles = [];

    foreach ($request->file('images') as $file) {

    $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

    $path = $file->storeAs('images', $fileName, 'public');

    $media = Media::create([
        'media_path' => $path,
        'business_id' => auth()->id()
    ]);

    $uploadedFiles[] = [
        'id' => $media->id,
        'url' => asset('storage/' . $media->media_path)
    ];
}
return response()->json([
    'status' => true,
    'message' => 'Media uploaded successfully!',
    'data' => $uploadedFiles
], 200);
            }

    public function deleteMedia(Request $request, $id){
        $media = Media::find($id);
        if(!$media){
            return response()->json([
                'status' => false,
                'message' => 'Media not found!'
            ], 404);
        }
        $media->delete();

        $media_path = public_path("storage/") . $media->media_path;
        if(file_exists($media_path)){
            @unlink($media_path);
        }

        return response()->json([
            'status' => true,
            'message' => "Media deleted successfully!"
        ], 200);
    }
}
