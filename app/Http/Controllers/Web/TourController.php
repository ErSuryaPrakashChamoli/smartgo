<?php

namespace App\Http\Controllers\Web;

use App\Actions\Booking\ChangeTour;
use App\Actions\Booking\ClaimTourSlot;
use App\Actions\Booking\RequestTour;
use App\Enums\Booking\TourStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\TourRequest;
use App\Services\Booking\TourCalendar;
use App\Services\Booking\TourSlots;
use App\Services\Content\SiteNavigation;
use App\Services\Website\EnquiryForm;
use App\Services\Website\TourForm;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Tour requests on the website (FR-BK-2, IA /book-a-tour, the approved tour journey): pick a location
 * and a free time, then give contact details. A request holds its place until SmartGro confirms it; it
 * is not a booking and nothing is charged. Open only once the privacy notice is published and for
 * locations with tour hours (TourForm). The visitor's private link (a signed address, no personal data
 * shown) lets them see the status, move or cancel the tour, and add a confirmed tour to their calendar.
 */
class TourController extends Controller
{
    public function __construct(private TourForm $form, private TourSlots $slots, private EnquiryForm $enquiryForm, private SiteNavigation $siteNavigation) {}

    public function create(Request $request): View
    {
        $navigation = $this->siteNavigation->present();
        $properties = $this->form->properties();
        $property = $properties->firstWhere('id', (int) $request->query('property')) ?? ($properties->count() === 1 ? $properties->first() : null);

        return view('pages.tours.create', [
            'isOpen' => $this->form->isOpen(),
            'properties' => $properties,
            'property' => $property,
            ...($property !== null ? $this->picker($property, (string) $request->query('date')) : ['days' => [], 'day' => null, 'times' => []]),
            'notice' => $this->enquiryForm->privacyNotice(),
            'started' => $this->enquiryForm->startedToken(),
            'utm' => collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
                ->mapWithKeys(fn (string $key): array => [$key => is_string($request->query($key)) ? mb_substr($request->query($key), 0, 120) : null])
                ->filter()->all(),
            'from' => $this->sameSitePath((string) $request->headers->get('referer')),
            'maxVisitors' => (int) config('tours.max_visitors'),
            'contact' => $navigation['contact'],
            'navigation' => $navigation,
            'canonical' => rtrim((string) config('app.url'), '/').'/book-a-tour',
        ]);
    }

    /**
     * @throws ValidationException back to the form, with the next free times when the chosen one was taken
     */
    public function store(Request $request, RequestTour $requestTour): RedirectResponse
    {
        try {
            $tour = $requestTour->handle($request->except(['_token']), $request->ip());
        } catch (ValidationException $exception) {
            if (($exception->errors()['starts_at'][0] ?? null) === ClaimTourSlot::SLOT_TAKEN) {
                $request->session()->flash('tour_slot_taken', true);
            }

            throw $exception->redirectTo(route('tours.create', array_filter(['property' => $request->input('property'), 'date' => $request->input('date')])));
        }

        return redirect()->route('tours.requested')->with(['tour_requested' => true, 'tour_reference' => $tour?->reference]);
    }

    public function requested(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('tour_requested', false)) {
            return redirect()->route('tours.create');
        }

        $tour = TourRequest::query()->where('reference', (string) $request->session()->get('tour_reference'))->with('property.city')->first();

        return view('pages.tours.requested', [
            'tour' => $tour,
            'manageUrl' => $tour === null ? null : URL::signedRoute('tours.show', ['reference' => $tour->reference]),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    public function show(Request $request, string $reference): View
    {
        $tour = $this->tour($reference);
        $canChange = $tour->status->isUpcoming() && $tour->scheduled_start_at->isFuture();
        $picker = $canChange ? $this->picker($tour->property, (string) $request->query('date'), $tour) : ['days' => [], 'day' => null, 'times' => []];
        $picker['days'] = array_map(fn (array $day): array => [...$day, 'href' => URL::signedRoute('tours.show', ['reference' => $tour->reference, 'date' => $day['date']])], $picker['days']);

        return view('pages.tours.show', [
            'tour' => $tour,
            'canChange' => $canChange,
            ...$picker,
            'links' => [
                'self' => URL::signedRoute('tours.show', ['reference' => $tour->reference]),
                'cancel' => URL::signedRoute('tours.cancel', ['reference' => $tour->reference]),
                'move' => URL::signedRoute('tours.move', ['reference' => $tour->reference]),
                'calendar' => URL::signedRoute('tours.calendar', ['reference' => $tour->reference]),
            ],
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    public function cancel(Request $request, string $reference, ChangeTour $changeTour): RedirectResponse
    {
        $tour = $this->tour($reference);
        $changeTour->handle(null, $tour, 'cancel', $tour->status, ['reason' => 'Cancelled by the visitor']);

        return redirect()->to(URL::signedRoute('tours.show', ['reference' => $tour->reference]))->with('tour_changed', 'Your tour is cancelled.');
    }

    public function move(Request $request, string $reference, ChangeTour $changeTour): RedirectResponse
    {
        $tour = $this->tour($reference);

        try {
            $changeTour->handle(null, $tour, 'reschedule', $tour->status, ['starts_at' => (string) $request->input('starts_at')]);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(URL::signedRoute('tours.show', ['reference' => $tour->reference]));
        }

        return redirect()->to(URL::signedRoute('tours.show', ['reference' => $tour->reference]))
            ->with('tour_changed', config('tours.confirm_on_request') ? 'Your tour has moved.' : 'Your new time is requested. SmartGro confirms it with you.');
    }

    public function calendar(string $reference, TourCalendar $calendar): Response
    {
        $tour = $this->tour($reference);

        abort_unless($tour->status === TourStatus::Confirmed && $tour->scheduled_start_at->isFuture(), 404);

        return response($calendar->for($tour), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="smartgro-tour-'.strtolower($tour->reference).'.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The days with tour times and the times of the chosen day (the first day with a free time unless
     * the visitor chose another), in India time.
     *
     * @return array{days: list<array{date: string, label: string, free: int}>, day: string|null, times: list<array{value: string, label: string, disabled: bool}>}
     */
    private function picker(Property $property, string $date, ?TourRequest $ignoring = null): array
    {
        $timezone = (string) config('backoffice.timezone');
        $byDay = $this->slots->bookable($property, $ignoring)->groupBy(fn (array $slot): string => $slot['starts_at']->timezone($timezone)->toDateString());
        $days = $byDay->map(fn ($slots, string $day): array => [
            'date' => $day,
            'label' => CarbonImmutable::parse($day)->format('D j M'),
            'free' => $slots->where('free', true)->count(),
        ])->values()->all();
        $chosen = $byDay->has($date) ? $date : collect($days)->firstWhere('free', '>', 0)['date'] ?? null;

        return [
            'days' => $days,
            'day' => $chosen,
            'times' => $chosen === null ? [] : $byDay->get($chosen)->map(fn (array $slot): array => [
                'value' => $slot['starts_at']->toAtomString(),
                'label' => $slot['starts_at']->timezone($timezone)->format('H:i'),
                'disabled' => ! $slot['free'],
            ])->values()->all(),
        ];
    }

    private function tour(string $reference): TourRequest
    {
        abort_unless(TourRequest::isAvailable(), 404);

        return TourRequest::query()->where('reference', $reference)->with('property.city', 'property.primaryBuilding')->firstOrFail();
    }

    /**
     * The path of the page the visitor came from on this site, for the enquiry's attribution.
     */
    private function sameSitePath(string $referer): ?string
    {
        $host = parse_url($referer, PHP_URL_HOST);
        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($host) && $host === parse_url((string) config('app.url'), PHP_URL_HOST) && is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? mb_substr($path, 0, 255)
            : null;
    }
}
