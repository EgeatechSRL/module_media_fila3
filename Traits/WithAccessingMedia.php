<?php

declare(strict_types=1);

namespace Modules\Media\Traits;

use Modules\Media\Models\Media;
use Spatie\MediaLibrary\HasMedia;

trait WithAccessingMedia
{
    protected function getMedia(string $name, HasMedia $model, string $collection): array
    {
        return old($name) ? old($name) : $model
            ->getMedia($collection)
            ->map(function (Media $media) {
                return [
                    'name' => $media->name,
                    'fileName' => $media->file_name,
                    'uuid' => $media->uuid,
                    'previewUrl' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : '',
                    'order' => $media->order_column,
                    'customProperties' => $media->custom_properties,
                    'extension' => $media->extension,
                    'size' => $media->size,
                    'createdAt' => $media->created_at->timestamp,
                ];
            })
            ->keyBy('uuid')
            ->toArray();
    }
}
