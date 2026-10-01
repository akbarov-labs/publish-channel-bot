<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\PublicPostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Feeds for the Next.js reader site. */
class PublicPostController extends Controller
{
    /** Latest posts across every author, for the root domain. */
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->visible()
            ->with(['media', 'user'])
            ->latest('published_at')
            ->paginate($this->perPage($request));

        return $this->paginated($posts);
    }

    /** Every author with at least one post, most recently active first. */
    public function authors(): JsonResponse
    {
        $authors = $this->authorsWithStats()
            ->whereHas('posts', fn (Builder $query) => $query->visible())
            ->orderByDesc('latest_post_at')
            ->get();

        return response()->json(['data' => AuthorResource::collection($authors)]);
    }

    public function author(string $username): JsonResponse
    {
        $author = $this->authorsWithStats()->where('username', $username)->firstOrFail();

        return response()->json(new AuthorResource($author));
    }

    public function authorPosts(Request $request, string $username): JsonResponse
    {
        $author = User::where('username', $username)->firstOrFail();

        $posts = $author->posts()
            ->visible()
            ->with('media')
            ->latest('published_at')
            ->paginate($this->perPage($request));

        return $this->paginated($posts);
    }

    /** One post, with the author and the neighbouring posts for "read next". */
    public function show(string $username, string $slug): JsonResponse
    {
        $author = $this->authorsWithStats()->where('username', $username)->firstOrFail();

        $post = $author->posts()
            ->visible()
            ->where('slug', $slug)
            ->with('media')
            ->firstOrFail()
            ->setRelation('user', $author);

        $neighbour = fn (string $operator, string $direction) => $author->posts()
            ->visible()
            ->where('published_at', $operator, $post->published_at)
            ->orderBy('published_at', $direction)
            ->with('media')
            ->first();

        $more = $author->posts()
            ->visible()
            ->whereKeyNot($post->id)
            ->latest('published_at')
            ->with('media')
            ->limit(3)
            ->get();

        $older = $neighbour('<', 'desc');
        $newer = $neighbour('>', 'asc');

        return response()->json([
            ...(new PublicPostResource($post, withBody: true))->resolve(),
            'older' => $older ? (new PublicPostResource($older))->resolve() : null,
            'newer' => $newer ? (new PublicPostResource($newer))->resolve() : null,
            'more' => PublicPostResource::collection($more)->resolve(),
        ]);
    }

    private function authorsWithStats(): Builder
    {
        return User::query()
            ->whereNotNull('username')
            ->withCount(['posts' => fn (Builder $query) => $query->visible()])
            ->withMax(['posts as latest_post_at' => fn (Builder $query) => $query->visible()], 'published_at');
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->integer('per_page', 12), 50));
    }

    private function paginated(LengthAwarePaginator $posts): JsonResponse
    {
        return response()->json([
            'data' => PublicPostResource::collection($posts->getCollection())->resolve(),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
        ]);
    }
}
