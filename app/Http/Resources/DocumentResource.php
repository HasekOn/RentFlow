<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Support\PrivateFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'name' => $this->name,
            'visibility' => $this->visibility,
            'extension' => PrivateFile::extension($this->file_path),
            'download_url' => route('api.documents.download', $this->id),
            'created_at' => $this->created_at->toDateTimeString(),
            'uploaded_by' => UserResource::nested($this->whenLoaded('uploadedBy'), $request),
        ];
    }
}
