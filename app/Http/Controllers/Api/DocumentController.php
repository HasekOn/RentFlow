<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);
        $user = $request->user();

        $query = $property->documents()
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc');

        // Filter by visibility based on role
        // Landlord always sees everything — no filter needed
        if ($user->role === 'tenant') {
            // Tenant sees: landlord_tenant + all
            $query->whereIn('visibility', ['landlord_tenant', 'all']);
        } elseif ($user->role === 'manager') {
            // Manager sees: landlord_manager + all
            $query->whereIn('visibility', ['landlord_manager', 'all']);
        }

        $documents = $query->get();

        return response()->json(DocumentResource::collection($documents));
    }

    public function store(StoreDocumentRequest $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only upload documents to your own properties.',
            ], 403);
        }

        // Validate visibility value
        $visibility = $request->input('visibility', 'landlord_only');
        if (! in_array($visibility, Document::VISIBILITY_OPTIONS)) {
            $visibility = 'landlord_only';
        }

        $path = $request->file('file')->store(
            'documents/'.$property->id,
            'public'
        );

        $document = Document::query()->create([
            'property_id' => $property->id,
            'document_type' => $request->validated('document_type'),
            'name' => $request->validated('name'),
            'file_path' => $path,
            'visibility' => $visibility,
            'uploaded_by' => $request->user()->id,
        ]);

        $document->load('uploadedBy');

        return response()->json(new DocumentResource($document), 201);
    }

    public function download(Request $request, string $id)
    {
        $document = Document::query()->with('property')->findOrFail($id);
        $user = $request->user();

        // Landlord always has access
        if ($user->role !== 'landlord') {
            $allowed = match ($user->role) {
                'tenant' => in_array($document->visibility, ['landlord_tenant', 'all']),
                'manager' => in_array($document->visibility, ['landlord_manager', 'all']),
                default => false,
            };

            if (! $allowed) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->name
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $document = Document::query()->findOrFail($id);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }
}
