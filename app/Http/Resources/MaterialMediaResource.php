<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'file_url'    => $this->file_url,
            'page_number' => $this->page_number,
            'order'       => $this->order,
        ];
    }
}
