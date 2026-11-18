<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductOptionValue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductOptionController extends Controller
{
    protected const ALLOWED_TYPES = [
        'series',
        'color',
        'quality',
        'type',
        'storage',
        'ram',
        'processor',
    ];

    protected function ensureTableExists(): void
    {
        if (!Schema::hasTable('product_option_values')) {
            Schema::create('product_option_values', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->string('option_type', 50)->index();
                $table->string('title', 300);
                $table->string('slug', 350)->nullable();
                $table->integer('sort_order')->default(1);
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();

                $table->index(['business_id', 'option_type', 'is_deleted'], 'idx_pov_biz_type_del');
            });
        }
    }

    protected function validateOptionType(string $optionType): ?string
    {
        $normalized = strtolower(trim($optionType));
        return in_array($normalized, self::ALLOWED_TYPES, true) ? $normalized : null;
    }

    public function index(Request $request, string $type)
    {
        $optionType = $this->validateOptionType($type);
        if (!$optionType) {
            return response()->json(['status' => false, 'message' => 'Invalid option type.'], 422);
        }

        $this->ensureTableExists();
        $businessId = (string) auth()->id();
        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min(500, (int) $request->query('per_page', 10)));

        $query = ProductOptionValue::where('business_id', $businessId)
            ->where('option_type', $optionType)
            ->where('is_deleted', false);

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->whereRaw('LOWER(title) LIKE ?', [$like]);
        }

        $items = $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $total = $items->total();
        $from = $total > 0 ? (($items->currentPage() - 1) * $items->perPage() + 1) : 0;
        $to = $total > 0 ? min($items->currentPage() * $items->perPage(), $total) : 0;

        return response()->json([
            'status' => true,
            'message' => ucfirst($optionType) . ' options fetched successfully!',
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $total,
            ],
        ], 200);
    }

    public function store(Request $request, string $type)
    {
        $optionType = $this->validateOptionType($type);
        if (!$optionType) {
            return response()->json(['status' => false, 'message' => 'Invalid option type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->ensureTableExists();
        $businessId = (string) auth()->id();
        $title = trim((string) $request->input('title'));

        $exists = ProductOptionValue::where('business_id', $businessId)
            ->where('option_type', $optionType)
            ->where('is_deleted', false)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This ' . $optionType . ' option already exists.',
            ], 422);
        }

        $nextOrder = ProductOptionValue::where('business_id', $businessId)
            ->where('option_type', $optionType)
            ->where('is_deleted', false)
            ->count() + 1;

        $item = ProductOptionValue::create([
            'business_id' => $businessId,
            'option_type' => $optionType,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)),
            'sort_order' => $nextOrder,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => ucfirst($optionType) . ' created successfully!',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, string $type, string $id)
    {
        $optionType = $this->validateOptionType($type);
        if (!$optionType) {
            return response()->json(['status' => false, 'message' => 'Invalid option type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->ensureTableExists();
        $businessId = (string) auth()->id();

        $item = ProductOptionValue::where('business_id', $businessId)
            ->where('option_type', $optionType)
            ->where('id', $id)
            ->where('is_deleted', false)
            ->first();

        if (!$item) {
            return response()->json(['status' => false, 'message' => ucfirst($optionType) . ' not found.'], 404);
        }

        $title = trim((string) $request->input('title'));
        $item->update([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)),
        ]);

        return response()->json([
            'status' => true,
            'message' => ucfirst($optionType) . ' updated successfully!',
            'data' => $item->fresh(),
        ], 200);
    }

    public function destroy(string $type, string $id)
    {
        $optionType = $this->validateOptionType($type);
        if (!$optionType) {
            return response()->json(['status' => false, 'message' => 'Invalid option type.'], 422);
        }

        $this->ensureTableExists();
        $businessId = (string) auth()->id();

        $item = ProductOptionValue::where('business_id', $businessId)
            ->where('option_type', $optionType)
            ->where('id', $id)
            ->where('is_deleted', false)
            ->first();

        if (!$item) {
            return response()->json(['status' => false, 'message' => ucfirst($optionType) . ' not found.'], 404);
        }

        $item->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => ucfirst($optionType) . ' deleted successfully!',
        ], 200);
    }
}
