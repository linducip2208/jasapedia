<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use App\Models\SeoMetadata;
use App\Models\BlogPost;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CmsController extends Controller
{
    use ApiResponse;

    public function homepageBlocks(): JsonResponse
    {
        $blocks = CmsBlock::where("is_active", true)->orderBy("sort")->get();

        return $this->ok(["blocks" => $blocks]);
    }

    public function page(string $slug): JsonResponse
    {
        $page = CmsPage::where("slug", $slug)->where("status", "published")->first();

        if (! $page) {
            return response()->json(["error" => ["code" => "NOT_FOUND", "message" => "Page not found", "details" => [], "reference_id" => (string) str()->uuid()]], 404);
        }

        return $this->ok(["page" => $page]);
    }

    public function blog(): JsonResponse
    {
        $posts = BlogPost::where("status", "published")
            ->whereNotNull("published_at")
            ->latest("published_at")->paginate(12);

        return $this->paginated($posts);
    }

    public function blogPost(string $slug): JsonResponse
    {
        $post = BlogPost::where("slug", $slug)->where("status", "published")->first();

        if (! $post) {
            return response()->json(["error" => ["code" => "NOT_FOUND", "message" => "Post not found", "details" => [], "reference_id" => (string) str()->uuid()]], 404);
        }

        return $this->ok(["post" => $post]);
    }

    /** SEO landing: /jasa/{category}/{city} metadata + copy (doc 86). */
    public function seoLanding(string $category, ?string $city = null): JsonResponse
    {
        $categoryRow = \App\Models\Category::where("slug", $category)->first();

        $seo = null;
        if ($categoryRow) {
            $seo = SeoMetadata::where("page_type", $city ? "category_city" : "category")
                ->where("category_id", $categoryRow->id)
                ->where(function ($q) use ($city) {
                    $q->whereNull("city");
                    if ($city) {
                        $q->orWhere("city", $city);
                    }
                })
                ->orderByRaw("CASE WHEN city IS NULL THEN 1 ELSE 0 END") // prefer exact city
                ->first();
        }

        return $this->ok([
            "seo" => $seo,
            "category" => $categoryRow?->only(["id", "name", "slug"]),
            "city" => $city,
        ]);
    }
}
