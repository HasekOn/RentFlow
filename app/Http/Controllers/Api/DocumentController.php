<?php

namespace App\Http\Controllers\Api;

use App\Actions\Documents\DeleteDocument;
use App\Actions\Documents\StoreDocument;
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
            ->visibleTo($this->currentUser($request))
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(DocumentResource::collection($documents));
    }

    public function store(StoreDocumentRequest $request, string $propertyId, StoreDocument $store): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [Document::class, $property]);

        $document = $store->handle($property, $this->currentUser($request), $request->file('file'), $request->safe()->except('file'));

        $document->load('uploadedBy');

        return response()->json(new DocumentResource($document), 201);
    }

    public function download(string $id): StreamedResponse
    {
        $document = $this->findDocument($id);
        $this->authorize('view', $document);

        return PrivateFile::download($document->file_path, $document->name);
    }

    public function destroy(string $id, DeleteDocument $delete): JsonResponse
    {
        $document = $this->findDocument($id);
        $this->authorize('delete', $document);

        $delete->handle($document);

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
