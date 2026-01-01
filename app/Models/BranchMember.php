<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMember extends Model
{
    protected $table = 'branch_members';

    protected $fillable = [
        'branch_id',
        'user_id',
        'role',
        'joining_date',
        'leaving_date',
        'remarks',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'leaving_date' => 'date',
    ];

    /**
     * Get the branch that the member belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user that is a member of the branch.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
