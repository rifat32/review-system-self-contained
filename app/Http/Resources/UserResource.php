<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // all DB columns on users table
        $data = $this->resource->toArray();

        // remove roles if it exists (we will replace with single role)
        unset($data['roles']);

        // add single role object (or null)
        $data['role'] = $this->whenLoaded('roles', function () {
            $r = $this->roles->first();
            return $r ? ['id' => $r->id, 'name' => $r->name] : null;
        });

        // keep branches as usual (collection)
        // $data['branches'] = $this->whenLoaded('branches', function () {
        //     return $this->branches; // or BranchResource::collection($this->branches)
        // });

        return $data;
    }
}
