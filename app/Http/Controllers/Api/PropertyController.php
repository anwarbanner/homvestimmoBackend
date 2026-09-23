<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 12);
        $perPage = max(1, min($perPage, 48));

        $query = Property::query()
            ->where('status', 'published')
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderByDesc('featured')
            ->orderByDesc('published_at');

        if (in_array($request->query('transactionType'), ['sale', 'rent'], true)) {
            $query->where('transaction_type', $request->query('transactionType'));
        }

        $properties = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($properties->items())->map(fn (Property $property) => $this->transform($property))->values(),
            'meta' => [
                'currentPage' => $properties->currentPage(),
                'lastPage' => $properties->lastPage(),
                'perPage' => $properties->perPage(),
                'total' => $properties->total(),
            ],
        ]);
    }

    public function show(Property $property): JsonResponse
    {
        if ($property->status !== 'published') {
            throw new NotFoundHttpException();
        }

        $property->load(['images' => fn ($q) => $q->orderBy('sort_order')]);

        return response()->json([
            'data' => $this->transform($property, detailed: true),
        ]);
    }

    private function transform(Property $property, bool $detailed = false): array
    {
        $data = [
            'id' => $property->id,
            'reference' => $property->reference,
            'title' => $property->title,
            'type' => $property->type,
            'transactionType' => $property->transaction_type,
            'rentalTerm' => $property->rental_term,
            'price' => (float) $property->price,
            'surface' => (float) $property->surface,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'city' => $property->city,
            'address' => $property->address,
            'featured' => $property->featured,
            'images' => $property->images->map(fn ($image) => [
                'url' => Storage::disk('s3')->url($image->path),
                'isMain' => $image->is_main,
            ])->values(),
        ];

        if ($detailed) {
            $data['description'] = $property->description;
            $data['latitude'] = $property->latitude !== null ? (float) $property->latitude : null;
            $data['longitude'] = $property->longitude !== null ? (float) $property->longitude : null;
        }

        return $data;
    }
}
