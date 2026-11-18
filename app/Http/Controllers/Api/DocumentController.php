<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentVisibility;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use FindsProperty;

    public function index(Request $request, string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('view', $property);

        $documents = $property->documents()
            ->visibleTo($request->user())
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(DocumentResource::collection($documents));
    }

    public function store(StoreDocumentRequest $request, string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [Document::class, $property]);

        $path = $request->file('file')->store('documents/'.$property->id, PrivateFile::DISK);

        $document = Document::query()->create([
            'property_id' => $property->id,
            'document_type' => $request->validated('document_type'),
            'name' => $request->validated('name'),
            'file_path' => $path,
            'visibility' => $request->validated('visibility', DocumentVisibility::LandlordOnly->value),
            'uploaded_by' => $request->user()->id,
        ]);

        $document->load('uploadedBy');

        return response()->json(new DocumentResource($document), 201);
    }

    public function download(string $id): StreamedResponse
    {
        $document = $this->findDocument($id);
        $this->authorize('view', $document);

        return PrivateFile::download($document->file_path, $document->name);
    }

    public function destroy(string $id): JsonResponse
    {
        $document = $this->findDocument($id);
        $this->authorize('delete', $document);

        PrivateFile::delete($document->file_path);
        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }

    private function findDocument(string $id): Document
    {
        $document = Document::query()->with('property')->find($id);
        if ($document === null) {
            abort(404);
        }

        return $document;
    }
}
