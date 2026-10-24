<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Design\DesignTokens;
use Illuminate\Contracts\View\View;

/**
 * Serves the Phase 2 design previews: foundations, the component library and page designs.
 * Content is limited to owner-confirmed facts; everything else is a labelled placeholder.
 */
class DesignPreviewController extends Controller
{
    public function index(): View
    {
        return view('pages.design.index', $this->content());
    }

    public function foundations(DesignTokens $designTokens): View
    {
        return view('pages.design.foundations', [
            ...$this->content(),
            'colors' => $designTokens->colors(),
            'pairings' => $designTokens->pairings(),
        ]);
    }

    public function components(): View
    {
        return view('pages.design.components', $this->content());
    }

    public function home(): View
    {
        return view('pages.design.home', $this->content());
    }

    /**
     * Confirmed facts only: categories (OD-07), Noida properties (OD-08, OD-13) and customer
     * segments (OD-04). Summaries are draft copy for layout; final copy is written in Phase 6.
     * Database records replace this list once properties and categories exist (Phases 4–5).
     *
     * @return array{
     *     categories: list<array{name: string, icon: string, summary: string}>,
     *     properties: list<array{name: string, area: string, city: string}>,
     *     segments: list<string>
     * }
     */
    private function content(): array
    {
        return [
            'categories' => [
                ['name' => 'Private cabins', 'icon' => 'building-office', 'summary' => 'Lockable offices for teams that want their own room.'],
                ['name' => 'Dedicated desks', 'icon' => 'computer-desktop', 'summary' => 'Your own desk in a shared floor, kept for you.'],
                ['name' => 'Hot desks', 'icon' => 'squares-2x2', 'summary' => 'Flexible seating: any open desk, when you need it.'],
                ['name' => 'Meeting rooms', 'icon' => 'user-group', 'summary' => 'Rooms for client meetings and team discussions.'],
                ['name' => 'Conference and training rooms', 'icon' => 'presentation-chart-bar', 'summary' => 'Larger rooms for workshops, training and events.'],
                ['name' => 'Virtual office', 'icon' => 'envelope', 'summary' => 'A business address for your company, without a desk.'],
                ['name' => 'Managed offices', 'icon' => 'building-office-2', 'summary' => 'Dedicated office suites set up and run for enterprises.'],
            ],
            'properties' => [
                ['name' => 'A-70', 'area' => 'Sector 2', 'city' => 'Noida'],
                ['name' => 'A-71', 'area' => 'Sector 2', 'city' => 'Noida'],
                ['name' => 'A-23', 'area' => 'Sector 3', 'city' => 'Noida'],
                ['name' => 'H-105', 'area' => 'Sector 62', 'city' => 'Noida'],
            ],
            'segments' => [
                'Startups and small businesses',
                'Freelancers and independent professionals',
                'SMEs and growing companies',
                'Large enterprises and corporates',
                'Remote and hybrid employees',
                'Consultants and business travellers',
                'Companies seeking managed offices',
                'Businesses needing a virtual office or registered address',
            ],
        ];
    }
}
