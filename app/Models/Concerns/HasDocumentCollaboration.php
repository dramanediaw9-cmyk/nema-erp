<?php

namespace App\Models\Concerns;

use App\Modules\Core\Collaboration\Models\Attachment;
use App\Modules\Core\Collaboration\Models\InternalComment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocumentCollaboration
{
    public function internalComments(): MorphMany
    {
        return $this->morphMany(InternalComment::class, 'commentable')->latest('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }
}