<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\PublicPostResource;
use App\Models\Post;
use App\Models\Tag;
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
        $topic = $request->string('topic')->trim()->value();
        $terms = $request->string('q')->trim()->limit(100, '')->value();

        if ($topic !== '' && ! $author->tags()->where('slug', Tag::slugFor($topic))->exists()) {
            abort(404);
        }

        $posts = $author->posts()
            ->visible()
            ->with(['media', 'tags'])
            ->when($topic !== '', fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->where('slug', Tag::slugFor($topic))))
            ->when($terms !== '', fn (Builder $query) => $query->search($terms))
            ->latest('published_at')
            ->paginate($this->perPage($request));

        // The RSS feed asks for bodies so readers can read whole posts in their app.
        $response = $this->paginated($posts, withBody: $request->boolean('with_body'));

        // The front page shows the pinned post above the rest.
        if ($posts->currentPage() === 1 && $topic === '' && $terms === '') {
            $pinned = $author->posts()->visible()->whereNotNull('pinned_at')->with('media')->first();
            $response->setData([
                ...$response->getData(true),
                'pinned' => $pinned ? (new PublicPostResource($pinned))->resolve() : null,
            ]);
        }

        return $response;
    }

    /** An author's topics that have posts, the busiest first. */
    public function topics(string $username): JsonResponse
    {
        $author = User::where('username', $username)->firstOrFail();

        $topics = $author->tags()
            ->whereHas('posts', fn (Builder $query) => $query->visible())
            ->withCount(['posts' => fn (Builder $query) => $query->visible()])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $topics->map(fn (Tag $tag) => [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'posts_count' => $tag->posts_count,
            ])->values(),
        ]);
    }

    /** Every visible post's address and last change, for the author's sitemap. */
    public function sitemap(string $username): JsonResponse
    {
        $author = User::where('username', $username)->firstOrFail();

        $posts = $author->posts()
            ->visible()
            ->latest('published_at')
            ->get(['slug', 'published_at', 'updated_at']);

        return response()->json([
            'data' => $posts->map(fn (Post $post) => [
                'slug' => $post->slug,
                'updated_at' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
            ])->values(),
        ]);
    }

    /** One post, with the author and the neighbouring posts for "read next". */
    public function show(string $username, string $slug): JsonResponse
    {
        $author = $this->authorsWithStats()->where('username', $username)->firstOrFail();

        $post = $author->posts()
            ->visible()
            ->where('slug', $slug)
            ->with(['media', 'tags'])
            ->firstOrFail()
            ->setRelation('user', $author);

        $neighbour = fn (string $operator, string $direction) => $author->posts()
            ->visible()
            ->where('published_at', $operator, $post->published_at)
            ->orderBy('published_at', $direction)
            ->with(['media', 'tags'])
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

    private function paginated(LengthAwarePaginator $posts, bool $withBody = false): JsonResponse
    {
        return response()->json([
            'data' => $posts->getCollection()
                ->map(fn (Post $post) => (new PublicPostResource($post, $withBody))->resolve())
                ->values(),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
        ]);
    }
}
