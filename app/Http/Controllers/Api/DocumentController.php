<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        $documents = $property->documents()
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }

    public function store(StoreDocumentRequest $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only upload documents to your own properties.',
            ], 403);
        }

        $path = $request->file('file')->store(
            'documents/' . $property->id,
            'public'
        );

        $document = Document::query()->create([
            'property_id' => $property->id,
            'document_type' => $request->validated('document_type'),
            'name' => $request->validated('name'),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        $document->load('uploadedBy');

        return response()->json($document, 201);
    }

    public function download(string $id)
    {
        $document = Document::query()->findOrFail($id);

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
