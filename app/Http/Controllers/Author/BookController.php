<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookRequsestAuthor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function showAll()
    {
        $books = Book::all();
        return $books;
    }


    public function index()
    {
        $user = Auth::user();
        return User::findOrFail($user->id)->load('books');
    }

    public function getRequestes()
    {
         // $booksWithRequests = BookRequsestAuthor::where('', $user->id)
        //     ->whereHas('requestes')
        //     ->with('requestes.user')
        //     ->get();
        $user = Auth::user();

        $userBookIds = $user->books()->pluck('id');
        // foreach($userBookIds as $userBookId)
        // {
        //     $booksWithRequests = BookRequsestAuthor::where('book_id', $userBookId);
        // }
        $allRequests = BookRequsestAuthor::whereIn('book_id', $userBookIds)
        ->with('book') 
        ->get();

        return response()->json([
            'status' => true,
            'BookRequsestAuthor' => $allRequests
        ], 200);
    }


    public function addRequest(Request $request)
    {
        $user_id = Auth::user()->id;

        $inputs = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
        ]);

        $existingRequest = BookRequsestAuthor::where('book_id', $inputs['book_id'])
            ->where('user_id', $user_id)
            ->exists();

        if ($existingRequest) {
            return response()->json([
                'message' => 'Request already sent',
            ], 400);
        }

        $book = Book::find($inputs['book_id']);
        if ($book->user()->where('users.id', $user_id)->exists()) {
            return response()->json([
                'message' => 'You are already an author of this book',
            ], 400);
        }

        $inputs['user_id'] = $user_id;
        $bookRequsestAuthor = BookRequsestAuthor::create($inputs);
        return response()->json([
            'message' => 'the request added',
            'BookRequsestAuthor' => $bookRequsestAuthor
        ], 201);
    }

    public function accseptRequestes(Request $request)
    {
        $inputs = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'user_id' => ['required', 'exists:users,id']
        ]);

        $user = Auth::user();
        $book = Book::findOrFail($inputs['book_id']);

        if ($user->id != $book->owner_id) {
            return response()->json([
                'message' => 'The book is not yours'
            ], 403);
        }

        $partnerUser = User::findOrFail($inputs['user_id']);

        if (!$book->user->contains($partnerUser->id)) {
            $book->user()->attach($partnerUser->id);
        }

        BookRequsestAuthor::where('book_id', $book->id)
            ->where('user_id', $partnerUser->id)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Request accepted and user added as partner'
        ], 200);
    }

    public function rejectRequest(Request $request)
    {
        $inputs = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'user_id' => ['required', 'exists:users,id']
        ]);

        $user = Auth::user();
        $book = Book::findOrFail($inputs['book_id']);

        if ($user->id != $book->owner_id) {
            return response()->json([
                'message' => 'The book is not yours'
            ], 403);
        }

        BookRequsestAuthor::where('book_id', $book->id)
            ->where('user_id', $inputs['user_id'])
            ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Request rejected'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $inputs = $request->validate([
            'title' => ['required', 'max:255'],
            'publish_year' => ['required', 'min:4', 'max:4'],
            'price' => ['required', 'decimal:1,50'],
            'isbn' => ['required'],
            'category_id' => ['required', 'exists:categories,id'],
            'owner_id' => ['nullable'],
            'qty' => ['nullable'],
        ]);

        $inputs['owner_id'] = $user->id;

        $book = Book::create($inputs);

        $user->books()->attach($book->id);

        return response()->json($book);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function updateStock(Request $request, $book_id)
    {
        $user = Auth::user();
        $userBookIds = $user->books()->pluck('id');
        $isBookForSingedInAuthor = false;
        foreach ($userBookIds as $userBookId) {
            if ($book_id == $userBookId) {
                $isBookForSingedInAuthor = true;
                break;
            }
        }
        if ($isBookForSingedInAuthor) {
            $book = Book::findOrFail($book_id);
            $book->qty = $request->qty;
            $book->save();
            return $book;
        }

        return response()->json([
            'message' => 'The book are not yours to update qty'
        ], 401);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $userBookIds = $user->books()->pluck('id');
        $isBookForSingedInAuthor = false;
        foreach ($userBookIds as $userBookId) {
            if ($id == $userBookId) {
                $isBookForSingedInAuthor = true;
                break;
            }
        }

        if (!$isBookForSingedInAuthor) {
            return response()->json([
                'message' => 'The book are not yours to update it'
            ], 401);
        }

        $book = Book::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'isbn' => 'sometimes|string|max:250|unique:books,isbn,' . $book->id,
            'publish_year' => 'sometimes|max:4|min:4',
            'price' => 'sometimes|decimal:1,50',
            'category_id' => 'sometimes|exists:categories,id',

        ]);

        if (isset($validated['title'])) {
            $book->title = $validated['title'];
        }

        if (isset($validated['isbn'])) {
            $book->isbn = $validated['isbn'];
        }

        if (isset($validated['publish_year'])) {
            $book->publish_year = $validated['publish_year'];
        }

        if (isset($validated['price'])) {
            $book->price = $validated['price'];
        }

        if (isset($validated['category_id'])) {
            $book->category_id = $validated['category_id'];
        }

        $book->save();

        return response()->json([
            'message' => 'Book updated successfully',
            'book' => [
                'title' => $book->title,
                'isbn' => $book->isbn,
                'publish_year' => $book->publish_year,
                'price' => $book->price,
                'category_id' => $book->category_id,
            ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
