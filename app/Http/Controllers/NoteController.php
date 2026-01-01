<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Http\Requests\NoteRequest;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Note",
 *     type="object",
 *     title="Note",
 *     description="Note model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="My Note Title"),
 *     @OA\Property(property="note_contents", type="string", example="This is the content of my note."),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 */
class NoteController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1.0/notes",
     *      operationId="createNote",
     *      tags={"notes"},
     *      summary="Create a new note",
     *      description="Create a new note for the specified user",
     *      security={{"bearerAuth": {}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"title","note_contents","user_id"},
     *              @OA\Property(property="title", type="string", example="My Note Title"),
     *              @OA\Property(property="note_contents", type="string", example="This is the content of my note."),
     *              @OA\Property(property="user_id", type="integer", example=1)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Note created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Note created successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/Note")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createNote(NoteRequest $request)
    {
        $validated = $request->validated();

        $note = Note::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully',
            'data' => $note
        ], 201);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/notes",
     *      operationId="getAllNotes",
     *      tags={"notes"},
     *      summary="Get all notes for a user",
     *      description="Retrieve all notes belonging to the specified user",
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="user_id",
     *          in="query",
     *          required=true,
     *          description="User ID",
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Notes retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notes retrieved successfully"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Note"))
     *          )
     *      )
     * )
     */
    public function getAllNotes(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $query = Note::where('user_id', $request->user_id);

        $notes = retrieve_data($query);

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'meta' => $notes['meta'],
            'data' => $notes['data'],
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/notes/{id}",
     *      operationId="getNoteById",
     *      tags={"notes"},
     *      summary="Get a specific note by ID",
     *      description="Retrieve a single note by its ID for the specified user",
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="Note ID",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="user_id",
     *          in="query",
     *          required=true,
     *          description="User ID",
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Note retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Note retrieved successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/Note")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Note not found")
     * )
     */
    public function getNoteById(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $note = Note::where('id', $id)->where('user_id', $request->user_id)->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Note retrieved successfully',
            'data' => $note
        ], 200);
    }

    /**
     * @OA\Put(
     *      path="/v1.0/notes/{id}",
     *      operationId="updateNote",
     *      tags={"notes"},
     *      summary="Update a note",
     *      description="Update an existing note for the specified user",
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="Note ID",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"title","note_contents","user_id"},
     *              @OA\Property(property="title", type="string", example="Updated Note Title"),
     *              @OA\Property(property="note_contents", type="string", example="Updated content of my note."),
     *              @OA\Property(property="user_id", type="integer", example=1)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Note updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Note updated successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/Note")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Note not found"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateNote(NoteRequest $request, $id)
    {
        $validated = $request->validated();

        $note = Note::where('id', $id)->where('user_id', $validated['user_id'])->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        $note->update([
            'title' => $validated['title'],
            'note_contents' => $validated['note_contents'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => $note
        ], 200);
    }

    /**
     * @OA\Delete(
     *      path="/v1.0/notes/delete/{ids}",
     *      operationId="deleteNotes",
     *      tags={"notes"},
     *      summary="Delete multiple notes",
     *      description="Delete multiple notes by comma-separated IDs for the specified user",
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          required=true,
     *          description="Comma-separated note IDs",
     *          @OA\Schema(type="string", example="1,2,3")
     *      ),
     *      @OA\Parameter(
     *          name="user_id",
     *          in="query",
     *          required=true,
     *          description="User ID",
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Notes deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notes deleted successfully"),
     *              @OA\Property(property="deleted_count", type="integer", example=3)
     *          )
     *      ),
     *      @OA\Response(response=400, description="Invalid IDs provided")
     * )
     */
    public function deleteNotes(Request $request, $ids)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $idArray = explode(',', $ids);

        // Validate that all IDs are integers
        $idArray = array_filter($idArray, function ($id) {
            return is_numeric($id) && (int)$id > 0;
        });

        if (empty($idArray)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IDs provided'
            ], 400);
        }

        // Verify that all notes belong to the specified user_id
        $existingNotes = Note::whereIn('id', $idArray)
            ->where('user_id', $request->user_id)
            ->pluck('id')
            ->toArray();

        $missingIds = array_diff($idArray, $existingNotes);

        if (!empty($missingIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some notes not found or do not belong to you: ' . implode(', ', $missingIds)
            ], 400);
        }

        // Delete the notes
        $deletedCount = Note::whereIn('id', $idArray)->where('user_id', $request->user_id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notes deleted successfully',
            'deleted_count' => $deletedCount
        ], 200);
    }
}
