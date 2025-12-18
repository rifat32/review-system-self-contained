<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'is_active',
        'is_default',
        'business_id',
        'parent_question_category_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'parent_question_category_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(QuestionCategory::class, 'parent_question_category_id');
    }

    /**
     * Get the user who created this category.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the business this category belongs to.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Get all questions belonging to this category.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_category_id');
    }


    /**
     * Check if this category can have child categories.
     * Only root categories (no parent) can have children due to one-level restriction.
     *
     * @return bool
     */
    public function canHaveChildren(): bool
    {
        return is_null($this->parent_question_category_id);
    }

    /**
     * Check if this category can be a parent.
     * Alias for canHaveChildren() for semantic clarity.
     *
     * @return bool
     */
    public function isRootCategory(): bool
    {
        return $this->canHaveChildren();
    }


    /**
     * Dynamic scope filter that applies filters from the current request.
     * Automatically reads filter parameters from the HTTP request.
     *
     * Supported filters:
     * - is_active: Filter by active status (0/1 or true/false)
     * - is_default: Filter by default status (0/1 or true/false)
     * - business_id: Filter by business ID
     * - parent_id: Filter by parent category ID
     * - created_by: Filter by creator user ID
     * - search: Search in title and description
     * - has_children: Filter categories with/without children (true/false)
     * - has_questions: Filter categories with/without questions (true/false)
     * - sort_by: Sort field (title, created_at, etc.)
     * - sort_direction: Sort direction (asc/desc)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilters($query, $businessId)
    {
        $request = request();

        return $query->where(function ($q) use ($request, $businessId) {
            $q->where(function ($sq) use ($request) {
                $sq->where('is_default', true)
                    ->whereNull('business_id');
            })->orWhere(function ($sq) use ($request, $businessId) {
                $sq->where('business_id', $businessId)
                    ->where('is_default', false);
            });
        })
            ->when(
                $request->filled('is_active'),
                fn($q) => $q->where('is_active', $request->boolean('is_active'))
            )->when(
                $request->filled('parent_id'),
                fn($q) => $q->where('parent_question_category_id', $request->parent_id)
            )->when(
                $request->filled('created_by'),
                fn($q) => $q->where('created_by', $request->created_by)
            )->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($sq) use ($request) {
                    $searchTerm = $request->search;
                    $sq->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('description', 'like', '%' . $searchTerm . '%');
                })
            )->when(
                $request->filled('has_children'),
                fn($q) => $request->boolean('has_children') ? $q->has('children') : $q->doesntHave('children')
            )->when(
                $request->filled('has_questions'),
                fn($q) => $request->boolean('has_questions') ? $q->has('questions') : $q->doesntHave('questions')
            )->when(
                $request->filled('exclude_parent'),
                fn($q) => $request->boolean('exclude_parent') ? $q->whereNull('parent_question_category_id') : $q
            )->when(
                $request->filled('sort_by'),
                function ($q) use ($request) {
                    $sortField = $request->sort_by;
                    $sortDirection = $request->sort_direction ?? 'asc';
                    // Only allow safe sort fields
                    $allowedSortFields = ['title', 'created_at', 'updated_at', 'is_active', 'is_default'];
                    if (in_array($sortField, $allowedSortFields)) {
                        return $q->orderBy($sortField, $sortDirection);
                    }
                    return $q;
                },
                fn($q) => $q->orderBy('title', 'asc') // default ordering
            );
    }
}
