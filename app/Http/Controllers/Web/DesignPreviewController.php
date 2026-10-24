<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Design\DesignPreviewContent;
use App\Services\Design\DesignTokens;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Serves the Phase 2 design previews: foundations, the component library and page designs.
 * Content is limited to owner-confirmed facts; everything else is a labelled placeholder.
 */
class DesignPreviewController extends Controller
{
    public function __construct(private DesignPreviewContent $content) {}

    public function index(): View
    {
        return view('pages.design.index', $this->shared());
    }

    public function foundations(DesignTokens $designTokens): View
    {
        return view('pages.design.foundations', [
            ...$this->shared(),
            'colors' => $designTokens->colors(),
            'pairings' => $designTokens->pairings(),
        ]);
    }

    public function components(): View
    {
        return view('pages.design.components', $this->shared());
    }

    public function home(): View
    {
        return view('pages.design.home', $this->shared());
    }

    public function locations(): View
    {
        return view('pages.design.locations.index', [...$this->shared(), 'cities' => $this->content->cities()]);
    }

    /**
     * Old city names redirect permanently to the canonical page (OD-16); hidden cities do not exist publicly (OD-18).
     */
    public function city(string $city): View|RedirectResponse
    {
        if ($canonical = $this->content->canonicalCitySlug($city)) {
            return redirect()->route('design.city', $canonical, 301);
        }

        $record = $this->content->city($city);

        abort_if($record === null || $record['preview'] === 'hidden', 404);

        $cityProperties = collect($this->content->properties())->where('citySlug', $city);
        $requestedArea = request()->query('area');
        $selectedArea = is_string($requestedArea) && $cityProperties->contains('area', $requestedArea) ? $requestedArea : null;

        return view($record['preview'] === 'operating' ? 'pages.design.locations.city' : 'pages.design.locations.coming-soon', [
            ...$this->shared(),
            'city' => $record,
            'areas' => $cityProperties->countBy('area')->all(),
            'selectedArea' => $selectedArea,
            'cityProperties' => ($selectedArea === null ? $cityProperties : $cityProperties->where('area', $selectedArea))->values()->all(),
        ]);
    }

    public function property(string $city, string $property): View
    {
        $record = $this->content->property($city, $property);

        abort_if($record === null, 404);

        return view('pages.design.locations.property', [
            ...$this->shared(),
            'property' => $record,
            'nearby' => collect($this->content->properties())->where('citySlug', $city)->reject(fn (array $other): bool => $other['slug'] === $property)->values()->all(),
        ]);
    }

    /**
     * A fictional property that shows the property template fully populated. Every value on it is
     * illustrative, so no real property is ever shown with invented data.
     */
    public function sampleProperty(): View
    {
        return view('pages.design.locations.sample-property', $this->shared());
    }

    public function workspaces(): View
    {
        return view('pages.design.workspaces.index', $this->shared());
    }

    public function workspace(string $category): View
    {
        $record = $this->content->category($category);

        abort_if($record === null, 404);

        return view('pages.design.workspaces.category', [...$this->shared(), 'category' => $record]);
    }

    public function enterprise(): View
    {
        return view('pages.design.enterprise', [...$this->shared(), 'cities' => $this->content->cities()]);
    }

    public function flow(string $flow): View
    {
        abort_unless(in_array($flow, ['enquiry', 'tour', 'request-to-book', 'online-booking'], true), 404);

        return view("pages.design.flows.{$flow}", $this->shared());
    }

    /**
     * @return array{categories: list<array<string, mixed>>, properties: list<array<string, string>>, segments: list<string>}
     */
    private function shared(): array
    {
        return [
            'categories' => $this->content->categories(),
            'properties' => $this->content->properties(),
            'segments' => $this->content->segments(),
        ];
    }
}
